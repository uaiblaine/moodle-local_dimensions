# Learner return navigation — two return buttons, honest labels

**Date:** 2026-07-28
**Surfaces:** `view-competency.php` (Competency tracker), `classes/hook_callbacks.php` (the course FAB), `amd/src/accordion.js` (the related-competency pill)
**Line references pinned to:** `dce41b3` — the branch moved while this was being written, so every reference below was re-verified against that tree.

## Why

A learner opens the plan overview, follows a competency into the tracker, opens a
course from there, and presses the floating **"Return to plan"** button. It returns
them to the **tracker**. The label promises the plan and delivers the tracker.

The mechanism is a single overwrite. `view-plan.php:69` stamps the plan URL onto
the session cache key `course_{id}` for every course of the plan's template. The
tracker then overwrites the same keys with **its own** URL, for every course of the
competency being viewed:

```php
// view-competency.php:127-132
} else {
    \local_dimensions\helper::set_return_context(
        new moodle_url($PAGE->url, ['noredirect' => 1]),
        array_keys($courses)
    );
}
```

The cache holds one URL per course and nothing else — no plan id, no origin, no
timestamp. Every write is a bare `$cache->set()`, so it is pure last-write-wins and
the reader (`hook_callbacks.php:91-105`) cannot tell which page wrote the value.

Two findings make this more than a mislabelled button.

**The tracker is a hard dead end.** Neither learner view calls `set_pagelayout`, so
both keep core's default `base` (`lib/pagelib.php:202`), while the footer hook only
renders on `['course', 'incourse']` (`hook_callbacks.php:79-81`). The FAB therefore
*cannot* appear on the tracker. `templates/view_competency.mustache` has no anchor of
its own, no navbar node is set, and the renderable is never even given the plan id
(`view-competency.php:145` passes `$competency, $courses, $USER->id`). Returning the
learner "to where they came from" returns them to a page with no way onward.

**The same course already behaves two different ways, invisibly.** The plan overview
links straight into content, bypassing the tracker entirely — the course card's own
URL (`accordion.js:2258`), the "go" link a compact card renders for its single
activity or single section (`accordion.js:2143-2152`), and every activity row of an
expanded card (`accordion.js:1998`). On those edges the FAB is correct, because
nothing overwrote the plan URL. So one course returns to the plan or to the tracker
depending on a path the learner cannot see.

## Scope

1. Derive the course FAB's label from the destination that is already cached.
2. Give the tracker its own return button, rendered by the page itself.
3. Mark the related-competency pill so its new tab does not get a redundant button.
4. Move both decisions into pure helpers and give the subsystem its first tests.
5. Correct the two wrong comments in `db/caches.php`.

## Decisions

| Decision | Chosen | Why |
|---|---|---|
| Where the course FAB returns to | **The origin, not the journey root** — plan → course → plan, competency → course → competency | Returning always to the plan was the smaller change (deleting the tracker's write), but it only reads well *because* the tracker is a dead end. Fixing the dead end is the better repair, and then "back one step" is the honest model: every step has a way onward. |
| Who renders the tracker's button | **The page**, not the footer hook | The hook cannot reach layout `base`, and dragging it onto the tracker would mean giving that page a course-content layout *and* a course in context — the two conditions of N1 below. Both buttons use the fixed DOM id `local-dimensions-return-fab` (`templates/return_button.mustache:42`), so two on one page is invalid markup as well as a self-referential destination. Rendering in-page also needs **no cache at all**: the plan id is already `required_param` (`view-competency.php:34`). |
| How the label is chosen | **Classify the cached URL by script name** | The alternative — adding a `kind` key to the payload — changes no cache *definition* either, but it does need every writer updated and a fallback for sessions holding the old payload. Classifying the URL needs one reader-side function, no writer changes, and no migration. The four writers already store the right URL; only the reader was blind to it. |
| How the tracker knows to stay quiet | **An explicit `related=1` on the pill's URL** | The real reason is that the pill opens a **new tab** (`accordion.js:2372`), and that is not observable server-side. `helper::competency_in_plan()` is already computed at `view-competency.php:65` and looks free, but it encodes "new tab" as "outside the plan", which leaks both ways: a related competency that *is* in the plan would get a button in a new tab, and a bookmark to a competency outside the plan would lose its button in the same tab. |
| Whether the marker reaches the cache | **It does not, and costs nothing** | `$PAGE->set_url` declares only `id` and `competencyid` (`view-competency.php:43-46`), so `$PAGE->url` never carries `related` and the write at `:129` already excludes it. A learner who descends from a pill-opened tracker into a course and presses the FAB lands back on the tracker **with** its return button — by then they are navigating inside that tab, and a way out to the plan has become useful. |
| Whether staff see the tracker's button | **Yes** | The ownership gate (`view-plan.php:63-66`, `view-competency.php:114-117`) exists to stop a manager reviewing someone else's plan from polluting their own session cache. An in-page link writes nothing, and `api::read_plan()` has already authorised the destination. |
| `version.php` | **No bump** | The version is frozen: no schema change, no new web service. A cache revision is not a reason to bump — test installs purge caches as routine. `amd/build` ships rebuilt in the same commit. |
| Behat | **Out** | The learner views have no scenarios today and there is no local Behat runner, so a first scenario would cost a CI round. PHPUnit now runs locally, and both decisions are pure functions — the logic belongs there. |

## Design

### Two systems, one vocabulary

**System A — the course FAB (exists).** The footer hook stays the only renderer on
course-content pages, reading `returncontext` exactly as it does now. Same writers,
same guards, same allowlist. One change: the label.

**System B — the tracker's return button (new).** `view-competency.php` renders the
same `templates/return_button.mustache` itself, pointing at
`view-plan.php?id={planid}` and always labelled `returntoplan` — its destination never
varies, so it needs no classification. It reads no cache and writes no cache.

Both share `amd/src/return_button.js`, so drag position
(`sessionStorage['local_dimensions_fab_pos']`), the double-click reset and the iframe
guard behave identically — one button identity across course and tracker.

### Labels

| Cached destination | String | Status |
|---|---|---|
| `view-plan.php` | `returntoplan` — "Return to plan" | exists (`lang/en/local_dimensions.php:653`) |
| `view-competency.php` | `returntocompetency` — "Return to competency" | **one** new string, `en` + `pt_br`, in the alphabetical slot |

The template already binds the label to `title` **and** `aria-label`
(`templates/return_button.mustache:43-44`). The mobile rule hides only the visible
`<span class="fab-label">` (`styles.css`, `@media (width <= 576px)`), so the
destination stays available in the tooltip and to screen readers with no extra work.

Because `local_moodlecheck` cannot verify a constructed string id, the hook maps the
kind to a **literal** key:

```php
$label = match (helper::return_destination_kind($context['url'])) {
    'competency' => get_string('returntocompetency', 'local_dimensions'),
    default => get_string('returntoplan', 'local_dimensions'),
};
```

### The tracker's return button

Rendered **outside** the `if ($competency)` block that spans
`view-competency.php:78-139`. The empty state — a competency that was deleted or
never existed — is precisely where the learner is most stuck, and today it renders a
page with no exit at all.

```php
$returnbutton = \local_dimensions\helper::tracker_return_context($planid, $related);
if ($returnbutton !== null) {
    echo $OUTPUT->render_from_template('local_dimensions/return_button', $returnbutton);
    $PAGE->requires->js_call_amd('local_dimensions/return_button', 'init');
}
```

`$related` is read as `optional_param('related', 0, PARAM_BOOL)` and is deliberately
**not** added to `$PAGE->set_url`.

### The related-competency pill

`accordion.js:2473` appends `&related=1` to the URL it builds. Nothing else about the
pill changes — it keeps `target="_blank" rel="noopener"` and its `showrelatedlink`
gate.

### The navigation matrix

| Journey | Cached for the course | Course FAB | Tracker button |
|---|---|---|---|
| Block → plan overview → **course** (`accordion.js:2258`) | plan | "Return to plan" | — |
| Plan overview → **activity or section** (`accordion.js:2143-2152`, `:1998`) | plan | "Return to plan" *(when the layout passes the allowlist)* | — |
| Plan overview → tracker (rule child `:1001` / footer `:2329`) → course | tracker | "Return to competency" | → plan |
| Plan overview → tracker **via the pill** (`:2473`, new tab) | tracker, without `related` | "Return to competency" | **suppressed** |
| …then → course → FAB → tracker | tracker | "Return to competency" | → plan |
| **Block competency card** → tracker → course | tracker | "Return to competency" | → plan |
| Tracker with one course + `singlecourseredirect` | **plan** | "Return to plan" | *(the page redirects past itself)* |
| Direct link / bookmark on the tracker | tracker | — | → plan |
| Staff viewing someone else's plan | nothing written | none | → plan |

The block's **competency card** is the product's default entry into the tracker
(`template_metadata_cache::get_displaymode_value()` defaults to competency cards), and
it seeds no plan URL at all. System B fixes that path without touching the block.

### Invariants preserved

| | What | Where |
|---|---|---|
| I1 | Anti-loop: the tracker's cached URL still carries `noredirect=1`, honoured in `$willredirect` | `view-competency.php:108`, `:129` |
| I2 | Pagelayout allowlist plus pagetype blocklist, failing closed | `hook_callbacks.php:79-86` |
| I3 | Context is written only for the learner's own plan | `view-plan.php:63-66`, `view-competency.php:114-117` |
| I5 | The tracker always has a plan id — what makes System B stateless | `view-competency.php:34` |
| I7 | `view-plan.php` takes no parameter but `id` | `view-plan.php:34` |

### Two new invariants

Both belong in the CLAUDE.md "Return-to-Plan FAB" section.

- **N1 — no learner view may combine `set_pagelayout('course'|'incourse')` with a
  course in `$PAGE->context`.** The footer hook's `get_current_course_id()` runs, and
  returns, before the pagelayout check, so today it is the tracker having no course in
  context — not the layout — that keeps the hook off it. Layout is one of two
  conditions: both together would render a second button with the same DOM id, and the
  tracker would become a destination for itself.
- **N2 — `related` must never enter the tracker's `$PAGE->set_url`.** It would leak
  into the URL cached at `:129` and suppress the tracker's own button on the way back
  from a course.

## Code changes

| File | Change |
|---|---|
| `classes/helper.php` | Add `return_destination_kind(string $url): string` returning `'plan'` or `'competency'`, defaulting to `'plan'`. Add `tracker_return_context(int $planid, bool $related): ?array` returning the template context (`returnurl`, `label`, `buttoncolor`) or `null` when `related` is true or `enablereturnbutton` is off. |
| `classes/hook_callbacks.php` | Replace the fixed `get_string('returntoplan', …)` at `:103` with the literal `match` above. |
| `view-competency.php` | Read `related`; render System B after the main template, outside the `if ($competency)` block. |
| `amd/src/accordion.js` | Append `&related=1` to the pill URL at `:2473`. |
| `amd/build/accordion.min.js` + `.map` | Rebuilt with `npx grunt amd --root=public/local/dimensions`, committed together. |
| `lang/en/local_dimensions.php` | `returntocompetency` — "Return to competency", between `returnbuttoncolor_desc` (`:652`) and `returntoplan` (`:653`). |
| `lang/pt_br/local_dimensions.php` | The same key in the same slot, after `returnbuttoncolor_desc` at `:652` — "Voltar à competência". The neighbouring `returntoplan` capitalises "Plano"; that inconsistency is pre-existing and left alone. |
| `db/caches.php` | Fix `:91` (`Key: 'returncontext'` → `course_{courseid}`) and `:92` (the value is `['url' => string]`, never a course-id list). |
| `CLAUDE.md` | Record N1 and N2. |

No `version.php` bump and no `db/upgrade.php` step: no schema change, no cache
definition change, no new web service.

## Tests

`tests/helper_return_navigation_test.php` — class
`local_dimensions\helper_return_navigation_test extends \advanced_testcase`, with a
`@covers` annotation and `resetAfterTest()` in the DB cases. This is the subsystem's
first coverage: `returncontext`, the hook and `noredirect` have **no** tests today in
either plugin.

| Test | Asserts |
|---|---|
| `test_return_destination_kind_classifies_plan_url` | a `view-plan.php` URL yields `'plan'` |
| `test_return_destination_kind_classifies_tracker_url` | a `view-competency.php` URL yields `'competency'`, with and without `noredirect=1` |
| `test_return_destination_kind_defaults_to_plan` | an unrecognised URL yields `'plan'` — pins the default branch |
| `test_tracker_return_context_points_at_the_plan` | the returned `returnurl` is `view-plan.php?id={planid}` and carries the configured colour |
| `test_tracker_return_context_suppressed_when_related` | `null` when `$related` is true |
| `test_tracker_return_context_suppressed_when_feature_disabled` | `null` when `enablereturnbutton` is off |
| `test_set_return_context_writes_one_entry_per_course` | one cache entry per id, each holding the same URL |
| `test_set_return_context_with_no_courses_writes_nothing` | pins the silent no-op — the empty `foreach` at `helper.php:2098` |
| `test_get_return_context_for_course_returns_null_when_absent` | the reader's miss path |

PHPUnit runs locally in this checkout since 2026-07-28 (Postgres in Docker, the root
`config.php`, PHP 8.3 at `/opt/homebrew/opt/php@8.3/bin/php`), so this suite is green
before the push rather than after a CI round.

Two things stay unverifiable locally and are checked by inspection: `phpcs`, which has
no local runner, and the `noredirect=1` invariant, which lives in a procedural file
PHPUnit cannot include — it stays guarded by N1/N2 in CLAUDE.md and by review.

## Out of scope

- **An admin-configurable label per view.** Splitting one string into two is what makes
  it possible later; the settings themselves are a separate slice.
- **A return affordance on the plan overview.** The plan is the root of the journey.
- **The block_dimensions web service.** It already writes the plan URL, which now
  labels itself correctly with no change.
- **Behat coverage of the learner views**, as decided above.

## Risks

| | Risk | Mitigation |
|---|---|---|
| R1 | A third cached URL kind added later would classify silently as `'plan'` | The default branch is pinned by a test and documented at the function |
| R2 | A stale `amd/build` would ship the pill without `related=1`, putting a redundant button in the new tab | Rebuild and commit together, as CLAUDE.md already requires |
| R3 | `related` reaching `$PAGE->set_url` breaks the return path | N2, plus the current `set_url` listing its parameters explicitly |
| R4 | Someone sets a course pagelayout on a learner view and gets two buttons | N1 |
| R5 | The cache still has no delete path, so a stale entry survives plan deletion for up to 4h | Pre-existing and untouched; the TTL added by the earlier audit bounds it |

### Known limits this design does not remove

An activity whose layout falls outside the allowlist — a `secure` quiz attempt, a popup,
a print view — still shows no button, by the earlier audit's deliberate decision. Staff
hitting `singlecourseredirect` are still dropped into the course with nothing, because
the redirect is evaluated against the viewer while the write is gated on the owner.
`view-plan.php`'s course set ignores `c.visible` and enrolment while the tracker's
applies both, so a hidden course in the template gets a plan button and never a
competency one. `template_course_cache` still carries a one-hour TTL with
delete-only invalidation. The pill's cache write is not suppressed by `related`,
only its own button is: opening the new tab still stamps the tracker's URL onto
every course of the related competency, gated only on the feature switch and plan
ownership. `returncontext` is a session cache, not a tab-scoped one, so a tracker
visit in any tab retargets the FAB in every tab for the courses the two share — a
course belonging to both the plan's template and the related competency has its FAB
flip to "Return to competency" back in the original tab, for a competency that tab
never opened. Skipping the write was rejected: it would leave courses that belong to
the related competency but not the plan's template with no button at all. The branch
still improves this case over the prior behaviour, where the same button always said
"Return to plan" and landed on a tracker with no way out. Drag and
double-click-to-reset remain pointer-only.
