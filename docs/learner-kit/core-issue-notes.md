# Core finding — a competency RULE has no override control of its own

A limitation in **Moodle core**, not in this plugin. Raised by the user from live testing during the
learner-view work, then traced and adversarially re-verified. **No core code was changed**, and none
is proposed here: the plugin ships a mitigation instead (see the last section).

Re-verified against the `moodle` weekly `dd5063e5268` (5.3dev, `$version = 2026072200.00`), which
carries the MDL-83424 `public/` restructure — line numbers below are that revision's, and the paths
omit the `public/` prefix.

## The observed behaviour

If a competency already has a **manual rating**, and it is later completed by a **competency rule**
(all children complete / enough points), the grade and proficiency are **not updated**.

## Why

`competency/classes/api.php:4381-4388`, the `ACTION_COMPLETE` branch:

```php
// When completing the competency we fetch the default grade from the competency. But we only mark
// the user competency when a grade has not been set yet or if override option is enabled.
// Complete is an action to use with automated systems.
if ($usercompetency->get('grade') === null || $overridegrade) {
    $setucgrade = true;
```

A **parent** competency always reaches this branch rather than the course/module one, because
`competency::get_context()` returns the *framework's* context — system or course-category, never
`CONTEXT_COURSE`/`CONTEXT_MODULE` (`competency/classes/competency.php:739-741`).

## The part that makes it a design problem, not just a missing feature

The rule does not merely lack an override option — it **inherits one that belongs to a different
entity**:

- `add_evidence()` declares `$overridegrade = false` as its 13th and last parameter
  (`api.php:4285-4287`).
- When a child completes, it forwards that flag into the parent's rule evaluation
  (`api.php:4505-4507`).
- `apply_competency_rules_from_usercompetency()` passes it straight into the parent's own
  `add_evidence()` call (`api.php:4638`).

So whether a rule overrides is decided by **the activity link that happened to trigger the child**,
configured by an admin who was thinking about that activity — not about the parent.

Consequences: the same parent behaves differently depending on which child completed last; and the
checkbox's label — "Override existing competency grade when completed." — says nothing about
affecting a parent competency.

## Corrections to the initial framing — both still hold

1. **`competency_coursecomp` has NO `overridegrade` column.** The setting exists **only** on
   activity (module) links: `competency_modulecomp.overridegrade`
   (`lib/db/install.xml:3947`), declared at `competency/classes/course_module_competency.php:74`,
   and added to the **activity** form only (`admin/tool/lp/lib.php:165-169`). A sweep of all 17
   `competency*` tables in `install.xml` returns exactly one hit.
2. **Course completion can therefore never override.** `observe_course_completed()` calls
   `add_evidence()` with only 9 arguments, never reaching the 13th
   (`api.php:4759-4769`), so the flag is always `false` there. Contrast the module observer, which
   reads it from the link (`api.php:4673`, passed at `:4705`).

## Is the current behaviour intended?

**Partly — and that is the honest framing.**

- The "do not overwrite an existing grade" guard is **deliberate and old**: the explaining comment
  block at `api.php:4322-4331` is by Damyon Wiese (`fdd85edef9e`, 2016-03-21, MDL-53452), and the
  behaviour is pinned by a test — `test_add_evidence_complete`
  (`competency/tests/api_test.php:2065`), whose block at `:2123-2134` asserts an `ACTION_COMPLETE`
  against a graded user competency leaves the grade alone. The rationale is sound: an automated
  system should not silently clobber a human rating.
- The `|| $overridegrade` escape hatch was **grafted on in 2022** by MDL-56567 ("Course module
  competency option to override grade", `cfb643293c5`, Matthew Hilton, 2022-09-20) — and only for
  activity links.

So the gap is not that someone forgot the guard; it is that the 2022 escape hatch was given to one
trigger path and not to the rule, while the rule silently inherits it anyway.

That is why the finding reads as an **improvement with a consistency argument, not a plain bug**: a
report claiming "rules are broken" would be refuted by the 2016 test.

## Supporting detail

- Neither `add_evidence()` (`api.php:4266-4284`) nor
  `apply_competency_rules_from_usercompetency()` (`api.php:4561-4563`) documents `$overridegrade`
  with an `@param` at all — the propagation is undocumented in both hops.
- The `competency` persistent carries `ruleoutcome`, `ruletype`, `ruleconfig` and nothing
  override-related (`competency/classes/competency.php:93-107`).
- The label is `$string['overridegrade']` in `admin/tool/lp/lang/en/tool_lp.php:167`, with **no**
  `_help` string.

## What the plugin does about it

Rather than touch core, the learner view **names the situation and offers core's own way out**. When
the decisive evidence is a rule completion but the user competency is not proficient,
`amd/src/accordion.js:1290-1301` emits a `.local-dimensions-ev-stale` note carrying
`evidence_rule_stale` — *"This rule was met, but an earlier rating is still in force, so your status
has not changed. You can ask for it to be reviewed."* — and, when
`uc.isrequestreviewallowed`, a **Send for review** button that calls core's
`core_competency_user_competency_request_review` (`accordion.js:1810-1829`). If the request is
already in flight, `isstatuswaitingforreview` swaps in "Sent for review" instead.

Strings: `evidence_rule_stale`, `evidence_rule_sendreview`, `evidence_rule_reviewsent`
(`lang/en/local_dimensions.php:401-403`, mirrored in `pt_br`). Styles: `styles.css:3431-3434`
(`.local-dimensions-ev-stale`, `-text`, `-action`, `-sent`).
