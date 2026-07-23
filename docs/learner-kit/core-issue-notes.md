# Core finding — a competency RULE has no override control of its own

Verified in a Moodle 5.x dev checkout (`public/`), post-MDL-83424 restructure. Raised by the user
from live testing; traced and adversarially re-verified here. **No core code was changed.**
These notes exist so the tracker issue written at the end of this work is accurate.

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

- `add_evidence()` declares `$overridegrade = false` as its last parameter (`api.php:4285-4287`).
- When a child completes, it forwards that flag into the parent's rule evaluation
  (`api.php:4505-4507`).
- `apply_competency_rules_from_usercompetency()` passes it straight into the parent's own
  `add_evidence()` call (`api.php:4638`).

So whether a rule overrides is decided by **the activity link that happened to trigger the child**,
configured by an admin who was thinking about that activity — not about the parent.

Consequences: the same parent behaves differently depending on which child completed last; and the
checkbox's label says nothing about affecting a parent competency.

## Corrections to the initial framing — both matter for the issue

1. **`competency_coursecomp` has NO `overridegrade` column.** The setting exists **only** on
   activity (module) links: `competency_modulecomp.overridegrade`
   (`lib/db/install.xml:3944`), declared at `competency/classes/course_module_competency.php:74`,
   and added to the **activity** form only (`admin/tool/lp/lib.php:165-169`). A sweep of all 17
   `competency*` tables returns exactly one hit.
2. **Course completion can therefore never override.** `observe_course_completed()` calls
   `add_evidence()` with only 9 arguments, never reaching the 13th
   (`api.php:4759-4769`), so the flag is always `false` there. Contrast the module observer, which
   reads it from the link (`api.php:4673`, passed at `:4705`).

## Is the current behaviour intended?

**Partly — and that is the honest framing.**

- The "do not overwrite an existing grade" guard is **deliberate and old**: the explaining comment
  block at `api.php:4322-4331` is by Damyon Wiese (2016, `fdd85edef9e`), and the behaviour is
  pinned by a test — `test_add_evidence_complete`, `competency/tests/api_test.php:2123-2134`,
  which asserts an `ACTION_COMPLETE` against a graded user competency leaves the grade alone.
  The rationale is sound: an automated system should not silently clobber a human rating.
- The `|| $overridegrade` escape hatch was **grafted on in 2022** by MDL-56567 ("Course module
  competency option to override grade", `cfb643293c55`, Matthew Hilton) — and only for activity
  links.

So the gap is not that someone forgot the guard; it is that the 2022 escape hatch was given to one
trigger path and not to the rule, while the rule silently inherits it anyway.

**File it as an improvement with a consistency argument, not as a plain bug.** A bug report that
claims "rules are broken" would be refuted by the 2016 test and lose the reviewer.

## Supporting detail worth citing

- Neither `add_evidence()` (`api.php:4266-4284`) nor
  `apply_competency_rules_from_usercompetency()` (`api.php:4561-4563`) documents `$overridegrade`
  with an `@param` at all — the propagation is undocumented in both hops.
- The `competency` persistent carries `ruleoutcome`, `ruletype`, `ruleconfig` and nothing
  override-related (`competency/classes/competency.php:91-105`).
- The label is `$string['overridegrade']` in `admin/tool/lp/lang/en/tool_lp.php:168`, with **no**
  `_help` string.
