# Course-card shape — separating the single-section and single-activity courses

**Date:** 2026-07-26
**Surfaces:** the course card in both learner views — `view-competency.php` (Competency tracker) and `view-plan.php` (Full plan overview, Related content tab)
**Kit:** `docs/learner-kit/screens/trk-locked.html`, `docs/learner-kit/screens/ovw-detail-courses.html`

## Why

The tracker's card draws a timeline of sections. Two kinds of course have nothing
useful to draw there, and the kit collapsed them into one:

- A course whose format is Moodle's native **`singleactivity`** — one activity, by
  definition, and no sequence at all.
- A course with a **single section holding several activities** — a sequence, but a
  timeline of one row that names a section usually called "General".

`trk-locked.html`'s `TRK-CARD-SINGLE` illustrated both with the same anatomy, and its
first card gives the conflation away: a **60% ring** beside an activity name. One
activity can only ever be 0% or 100%; 60% is only possible across several. The card
was drawn for the single-section case and labelled for the single-activity one.

The code never distinguished them either, and in two ways:

1. **The plugin never reads `$course->format`.** The "single activity" card is
   *inferred* from `count($trackedcms) === 1` in `calculator::get_course_section_progress()`.
   That misfires both ways: a `topics` course with ten sections but one tracked
   activity gets the activity card, and a real `singleactivity` course whose activity
   has completion switched off gets nothing useful.
2. **The two flags are independent and computed in different places.** `activity` comes
   from the server; `issinglesection` is derived **client-side** in
   `competency_view.js:197` as `sections.length === 1`. In `progress_card_body.mustache`
   the `{{#activity}}` section short-circuits the timeline entirely, so when both hold,
   the single-section treatment can never render.

## Scope

1. Replace the two independent flags with one server-resolved **`cardmode`**.
2. Detect `singleactivity` properly, by course format rather than by inference.
3. Give the single-section course its own compact card body.
4. Align the plan's card with the tracker's for both compact bodies, **without the
   course cover image**, keeping the outcome badges where they already sit.
5. Correct both kit screens and both field maps.

## Decisions

| Decision | Chosen | Why |
|---|---|---|
| What triggers the activity body | Format is `singleactivity` **or** exactly one tracked activity | The format check covers the `singleactivity` course whose activity is not completion-tracked, which today shows nothing useful. Keeping the count keeps the card helpful for a `topics` course that really does boil down to one thing — but now by a declared rule rather than by accident. |
| The single-section body | Ring + section name **only when the section has an authored name** | A single-section course almost always calls its section "General" or "Topic 1"; repeating that under the course name informs nobody. `$section->name` is `NULL` when Moodle generates the label, so this is a boolean the calculator already holds, not a heuristic. |
| Badge placement on the imageless plan card | Unchanged order: name → badge → compact body → drawer | The same order every other card state uses, so a card with a cover and one without still read alike. |
| The section body's call to action | **"Access content"** — a neutral label | Naming the destination was the original intent (using the competency's `local_dimensions_type` label), but the stored options are **plural and capitalised** (`Activities … Units` / `Atividades … Unidades`), a single destination needs the singular, Portuguese needs a gendered article, and no rule derives either — `Níveis` → `Nívei`, `Activities` → `Activitie`. A neutral label sidesteps all of it and, crucially, **removes the need to carry the label to the client at all**. |

The last decision collapses a large part of the original request: there is no label
lookup, no fallback when the label is unset, no lowercasing, and one new string
instead of sixteen.

## Design

### One explicit card mode

`calculator` gains a single resolver that both web services call:

```
calculator::resolve_card_shape(int $courseid, int $userid): array
    → ['mode' => 'activity'|'section'|'timeline',
       'activity' => ['cmid','name','url','completed','tracked'] | null,
       'section'  => ['name','hasownname','url'] | null]
```

Resolution order, first match wins:

1. `$course->format === 'singleactivity'` and modinfo yields a user-visible module →
   **activity**. `tracked` reports whether completion is on for it; when it is off the
   card shows the name and the link with no state marker, instead of today's empty card.

   **Not** through `core_courseformat\main_activity_interface::get_main_activity()`,
   which would answer this directly: that interface arrived in Moodle 5.1 (MDL-85433,
   present only on `MOODLE_501_STABLE`, `MOODLE_502_STABLE` and `main`), and this plugin
   supports 4.5 upward with CI running all four branches. An `instanceof` against a
   missing interface returns false rather than failing, so the branch would simply never
   fire on 4.5 and 5.0 — a silent version-dependent difference. The format string is on
   the course record on every branch, and the format guarantees a single activity, so
   modinfo answers the same question with one code path.
2. Exactly one trackable, user-visible module → **activity**, `tracked` true.
3. Exactly one visible section → **section**.
4. Otherwise → **timeline**.

`resolve_single_activity()` is **absorbed** into this resolver and removed; it is two
days old and this is its only caller. Its test file follows.

`get_course_section_progress()` calls the same resolver rather than deciding again, so
one rule has one implementation. The extra `get_fast_modinfo()` is request-cached, so
the cost is negligible.

**`issinglesection` moves off the client.** `competency_view.js:197` stops deriving it
from `sections.length`; the mustache switches on `cardmode` instead.

### The two compact bodies

| Mode | Content | Call to action | Target |
|---|---|---|---|
| `activity` | state marker + **activity** name + state text | `go_to_activity` (existing) | the activity's URL |
| `section` | progress ring + section name *when authored* + state text | **`access_content`** (new) | `/course/section.php?id={sectionid}` |

The section URL is built the way the timeline rows already build theirs. The ring
reuses the timeline's existing progress-ring markup, so this is a re-arrangement rather
than a new component.

`cardmode` is mutually exclusive, so `progress_card_body.mustache` replaces its nested
`{{#activity}}` / `{{^activity}}` structure with one switch. The locked overlay keeps
precedence over all four modes, unchanged.

### Aligning the plan's card

The plan's card gains the same two compact bodies, **dropping the cover image** for
those two modes only. Every other mode keeps the image.

This settles an open question from the previous slice by a better route. The plan's
card is currently a single anchor wrapping its whole body, which is why "Go to
activity" had to be a `<span>` and ended up opening the course — the fix then pointed
the whole card at the activity. Aligning with the tracker means adopting the tracker's
**two targets**: the course name links to the course, the call to action links to the
activity or the section. The activities drawer already sits outside the card's anchor,
so the pattern exists in the same function.

Card order for the two compact modes: course name → outcome badge → compact body →
activities drawer.

### Data

`get_course_progress` (tracker) and `get_competency_courses` (plan) both gain
`cardmode`, plus `activity.tracked` and the `section` object. Both `execute_returns()`
structures must declare every new key — the structure is an allowlist and an
undeclared key is stripped silently.

## Code changes

- **`classes/calculator.php`** — add `resolve_card_shape()`; remove
  `resolve_single_activity()`; have `get_course_section_progress()` return `cardmode`
  from the shared resolver; carry `hasownname` on section rows.
- **`classes/external/get_course_progress.php`** — declare `cardmode`,
  `activity.tracked`, `section`.
- **`classes/external/get_competency_courses.php`** — same three, replacing the
  `resolve_single_activity()` call.
- **`templates/progress_card_body.mustache`** — switch on `cardmode`; add the section
  body; keep the locked overlay ahead of it.
- **`amd/src/competency_view.js`** — drop the client-side `issinglesection` derivation.
- **`amd/src/accordion.js`** — the plan card renders the two compact bodies, drops the
  image for them, and splits its single anchor into two targets.
- **`styles.css`** — the section body; the imageless plan card.
- **`lang/en` + `lang/pt_br`** — one new key, `access_content` ("Access content" /
  "Acessar conteúdo"), in its alphabetical slot in both files.
- **`version.php`** — bump; two web-service return structures change.

## Kit and map changes

- **`trk-locked.html`** — split `TRK-CARD-SINGLE` into `TRK-CARD-SINGLE` (the
  `singleactivity` course, green check or grey circle, "Go to activity") and a new
  `TRK-CARD-SECTION` (the single-section course, ring with a real percentage, "Access
  content"). The 60% ring moves to the section card, where it is possible.
- **`ovw-detail-courses.html`** — `OVW-CRS-SINGLE` becomes the same two states, drawn
  imageless and with two link targets.
- **`maps/viewcompetency.md`** and **`maps/viewplan.md`** — record `cardmode` and the
  new IDs; the map is an as-is inventory, so it is updated when the code lands.

## Tests

- `resolve_card_shape()` per branch: a `singleactivity` course with completion on and
  with it off; a `topics` course with exactly one tracked activity; a one-section
  course with several; a many-section course.
- The completed branch of the activity body, which decides whether the card reads
  "Completed" or "Not completed".
- Both web services' payloads asserted **through** `clean_returnvalue`, so a missing
  allowlist entry fails loudly.

PHPUnit does not run in this checkout; these run in CI.

## Out of scope

- Naming the destination after the competency's `local_dimensions_type` label, and
  everything it implied — the singular list, the gendered article, carrying the label
  to the client. Superseded by the neutral call to action.
- The tracker's timeline itself, which is unchanged for courses with several sections.
- The FAB return-context question raised separately; it has its own brainstorm.

## Risks

- **A `singleactivity` course can have no activity configured yet**, and one whose
  format was switched away could hold several. Both fall through to the count check and
  then to the timeline, which is the honest outcome, but neither may be assumed away.
- **A `topics` course with one tracked activity keeps the activity card.** This is now
  deliberate, but it does mean the card can name an activity while the course holds
  other, untracked content. The card's job is progress, and untracked content does not
  count toward it.
- **Two views must stay in step.** The whole point is one resolver; a future change
  that re-derives the mode in either view reintroduces the drift this spec removes.
