# Plan overview — accordion detail refinement

**Date:** 2026-07-24
**Surface:** `view-plan.php` → the expanded detail of each accordion item, built client-side by `accordion.js`
**Kit:** `docs/learner-kit/screens/ovw-detail-{tabs,progress,courses,evidence}.html` + `docs/design-kit/tooltip.html`

## Why

The expanded detail grew one region at a time and now reads as five competing
areas: four tabs plus a linked-course scroller that is not a tab and stays on
screen whatever tab is active. The learner lands on their grade rather than on
what to do about it, and answering "how am I doing on this competency?" means
visiting two tabs and holding the first in mind while reading the second. Three
smaller defects ride along: the assessment block never got the card the kit
specified, the evidence rows never got the aligned-column treatment, and the
course cards say nothing about a course the learner cannot open.

## Scope

Eight changes, all inside the accordion detail:

1. Linked courses become a tab, absent when there is nothing to show.
2. Course cards gain locked / self-enrol / single-activity states, adapted from
   the tracker's locked card rather than copied from it, and the pane gains a
   footer link to the tracker when three or more courses are shown.
3. "About this scale" gains an admin setting.
4. Assessment and Evidence merge into one **Progress** tab.
5. The assessment block matches the kit: card, label above, scale button right.
6. The evidence journey row matches the kit: four-column grid, abbreviated date,
   truncated grade chip with a tooltip.
7. Tooltips enter the Design System.
8. Tab order: Related content · Description · Progress · Rules.

Every existing plan-overview setting keeps working; one new setting is added.

## Decisions

| Decision | Chosen | Why |
|---|---|---|
| Course-card states | State strip replacing the progress row, artwork dimmed | The tracker's frosted overlay + 80px icon ring + dashed border would bury the image, name, outcome badge and activities drawer on a 255px card in a scroller. The progress row is the one row that carries no meaning without access, so it is the one that is replaced. |
| Lock rule | Not actively enrolled **and** unable to self-enrol | The tracker's `is_locked()` also demands the student role, so it reports every card locked to anyone reviewing a plan without one — which is exactly why `get_competency_courses` documents not applying it today. The plan card answers "can this viewer open it", the same question its own link is about to answer. |
| Date format | `strftimedatefullshort` → `12/01/26` | A Moodle string every language pack localises, the most compact of the options, and the one that aligns best under `tabular-nums`. |
| Tooltip home | `docs/design-kit/tooltip.html`, referenced by the learner kit | That folder is already the single source of the Design System's foundations. One definition, no drift. |

## Design

### Tab strip

`buildSummaryTabs` pushes in this order; `tabs[0]` renders active, so "no
courses → Description leads" needs no new mechanism.

| Tab | Label | Gate |
|---|---|---|
| Related content | `related_content` + count badge | `visibleCourses.length > 0` |
| Description | `description_label` | `hasDesc \|\| hasTaxonomyCard \|\| hasPath \|\| hasRelated` |
| Progress | `progress_tab` *(new)* | `hasStatus \|\| hasEvidence` |
| Rules | `rules_tab` | unchanged |

The count badge moves off the course-section heading (which disappears — the tab
label replaces it) and onto the tab. Roving tabindex, arrow keys and the focus
ring are untouched.

**Consequence to accept:** a competency with a user-competency row currently
always opens on its grade; it will now open on its linked courses. This is the
point of the reorder, not a side effect.

**Tighten while here:** `hasPath` is `showpath && comp` and never checks that a
path exists, while the footnote renderer returns an empty string when both
halves are empty — so a root competency with `showpath` on can produce an empty
Description tab. Require actual path parts.

### Progress pane

`renderStatusPane` + `renderEvidencePane` become one `renderProgressPane`. The
section renderers below them are reused, not rewritten. Order:

1. Assessment card — `hasStatus`
2. Decisive result strip (rule completion) — unchanged logic
3. Stale-rating note + review request — unchanged logic
4. "On the journey" label + count — `hasEvidence` and rows exist
5. Journey rows — `hasEvidence`
6. Submit evidence — `enableevidencesubmitbutton`

The merge removes a duplication the kit had proposed: the result strip's own
outcome badge and its "1 decisive · 4 on the journey" header are dropped,
because the assessment card sits directly above and already states the level and
proficiency. The journey count moves to the group label. **The proposed
`showevidencecounts` setting is dropped with it.**

### Assessment card

The rating headline and proficiency pill already exist; the structure does not.
Wrap in a bordered card, add a header row (`space-between`) carrying the
`rating_label` eyebrow on the left and "About this scale" on the right. The
rating level takes the truncation + tooltip pair — scale names are author-written
and unbounded.

`showscaledescription` (new checkbox, default **on**, Full Plan Overview block)
becomes a second gate beside "the scale actually has a description". Gate it
server-side too: with the setting off, `get_scale_description()` need not run at
all, saving a scale read and a `format_text` per expansion.

### Journey row

Grid `28px · minmax(0,1fr) · auto · 84px` — type icon, description over a small
type label, grade chip, date.

- **Date** — `strftimedatefullshort`, right-aligned, `tabular-nums`. Two gaps in
  the client formatter must be closed: it has **no `%y` case at all**, and its
  `%d` is unpadded. strftime specifies `%d` as zero-padded, so padding it is a
  fix rather than a regression, and it is what keeps the column rectangular.
- **Grade chip** — neutral grey by default, green when the grade is proficient
  (today it is green/amber), hard `max-width` plus ellipsis, full value in the
  tooltip.
- The decisive row stays lifted out of the list, as today.

### Course cards

Per card, precedence **enrol → locked → single activity → normal**:

- **enrol** — accent CTA `enrol_to_start` + muted `selfenrolment_open`, no date
  (it is open now). The CTA is a `<span>`, not a nested anchor: the card is
  already the link.
- **locked** — muted `locked_content` + the availability chip (`available_at` or
  `enrolment_starts`), artwork dimmed. The date is dropped when it lies in the
  past. Honours `showlockeddate`. Honours `lockedcardmode`: learn-more keeps the
  card a link to the course where core explains the restriction, blocked drops
  the link.
- **single activity** — the activity's state marker, name and completion, plus a
  "Go to activity" link on its own line; suppresses the activities drawer when
  that drawer would list the same module.

`animatelockedborder`, `cardicon` and `learnmorebuttoncolor` do **not** carry
over — they style an overlay, a dashed border and an icon ring this card does not
have.

**Pane footer — "View detailed progress".** A right-aligned link under the track,
rendered only when `visibleCourses.length >= 3`, going to
`view-competency.php?id={planid}&competencyid={competencyid}` — the same URL shape
the related-competency pills and the rules children already write, built from the
`viewcompetencyurl` already in the accordion's display settings. A card can only
summarise a course as one percentage; the tracker shows per-section progress,
locked sections and availability dates, and today there is no route from the
accordion to it.

Three details that are decisions, not defaults:

- **Threshold is a plain count of what is shown**, not the condition that reveals
  the scroll arrows. That condition also fires with two cards on a narrow screen,
  so the link would appear and vanish on resize.
- **No `noredirect=1`.** The single-course redirect requires exactly one course to
  survive the filter, so it cannot fire from three up; adding the flag would
  diverge from the two existing links that write this URL.
- **Outlined, not solid** — the cards own the primary actions in this pane, and it
  is a link, not a button: it navigates.

The destination resolves the same enrolment-filter cascade, so it reveals no
course the pane was hiding.

The enrolment filter and the states are complementary: the filter decides whether
a card exists, the state decides what the learner can do with it. Under the
`all` default all three states occur; under `enrolledorself` the enrol state is
precisely what the filter admits beyond "enrolled".

### Tooltip

CSS-only, no JavaScript, no ARIA. A positioned wrapper carries the text in a data
attribute; the balloon is `::after` and the arrow `::before`. The rule that makes
this sound: **the full text stays in the DOM** — the clip is `text-overflow`
only — so nothing is hidden from assistive technology and the balloon is
decoration. That removes the need for `role="tooltip"`, `aria-describedby`, an
Escape handler and a `tabindex` on non-interactive text, and it sidesteps the
Bootstrap 4 vs 5 `data-toggle` / `data-bs-toggle` split entirely.

Known limit, documented in the component: any ancestor whose `overflow` is not
`visible` clips the balloon, and `overflow-x: auto` forces the Y axis to `auto`
too. The evidence journey is a static list, so it can use a tooltip; the course
scroller cannot, which is why its state is written on the card.

## Code changes

**`classes/external/get_competency_courses.php`** — add per course: `access`
(`open` / `enrol` / `locked`), `lockdate`, `isenrolstart`, and `activity`
(`{name, url, completed}` or null). `execute_returns()` is an allowlist, so every
new key must be declared or it is silently stripped.

**`classes/calculator.php`** — reuse `current_user_can_self_enrol()`,
`get_availability_date()` and `get_enrolment_start_date()`. Add a lean helper
that counts trackable modules and returns the single one; running the full
`get_course_section_progress()` would compute subsection hierarchy and per-section
percentages for a binary answer.

**`amd/src/accordion.js`** — the bulk: `buildSummaryTabs` order and gates,
`renderProgressPane` replacing two panes, the courses call moving inside the tab
wrapper, the assessment card structure, the journey row grid, the date format and
the two `formatTimestamp` fixes, the truncation wrappers. This file is already
~150 KB; the new renderers should not deepen it — prefer extracting the progress
pane's pieces as siblings of the existing section renderers.

**`styles.css`** — card, state strip, journey grid, `.dim-tip` primitive.
Constraints that bite here: `!important` is a hard error, `clamp()`/`min()`/
`max()` are rejected by the CSS validator in **every** length-valued property, and
a transition under 100 ms fails. The tooltip's 150 ms and the max-widths in `px`
already respect all three.

**`settings.php` + both lang files** — `showscaledescription` and its `_desc`.

**New strings** (English and Brazilian Portuguese, inserted in alphabetical
order in both files): `progress_tab`, `evidence_journey`, `showscaledescription`,
`showscaledescription_desc`, `view_detailed_progress` ("View detailed progress" /
"Ver progresso detalhado", slotting between `view_courses` and `view_grid`).
Note `view_courses` is **not** reusable for the footer link — it is already the
`aria-label` of the tracker's own course-grid region. Everything else the course
states need already exists —
`enrol_to_start`, `selfenrolment_open`, `locked_content`, `available_at`,
`enrolment_starts`, `go_to_activity`, `course_completed`, `filter_not_completed`,
`rating_label`, `related_content`.

**`version.php`** — bump: the web-service return structure changes, and the
rebuilt `amd/build` needs a new cache revision. Rebuild with grunt from the
Moodle root and commit the `.min.js` + `.map` in the same commit.

## Tests

PHPUnit is where the logic goes, since Behat here is CI-only:

- the access resolution per course — enrolled, self-enrolable, neither, and the
  staff case that motivated the rule change;
- the single-activity helper — zero, one and many trackable modules;
- the new web-service keys surviving `clean_returnvalue` (the allowlist trap);
- `showscaledescription` off suppressing the scale description server-side.

The tab order and gating are client-side; cover them by keeping the gate
expressions small and readable rather than by adding a fragile Behat scenario.

## Out of scope / dropped

- **`OVW-CRS-TITLE`** — the label-driven section title. The heading no longer
  exists, and deriving a tab label per competency would make the strip change
  wording between accordion items.
- **`OVW-CRS-EMPTY`** — the empty-state line. No courses now means no tab.
- **`showevidencecounts`** — a row count needs no administrative control.
- **Aligning the tracker's `is_locked()`** with the plan cards' access rule. The
  two will disagree until a later slice takes it on deliberately.
- The `styles.css` token migration described in `token-migration.md` stays a
  separate slice.

## Risks

- **Landing tab changes.** Learners used to opening on their grade will open on
  linked courses. Intended, but it is the change most likely to draw comment.
- **Lock semantics diverge from the tracker** until they are aligned. Documented
  in the kit and in the web service.
- **Per-course cost.** Resolving access adds an enrolment check per card, and
  self-enrolment adds a walk through the course's enrol instances — asked only
  when the learner is not actively enrolled. Bounded by courses per competency,
  and the same work the tracker already does per card.
- **`ovw-detail-evidence.html`'s left panel is stale** — it depicts the card
  slider, removed in an earlier slice that never re-synced the kit. Labelled as
  superseded rather than redrawn; redrawing it is a separate cleanup.
