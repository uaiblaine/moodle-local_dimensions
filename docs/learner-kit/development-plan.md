# Learner kit — development plan (code backlog)

The to-be screens in `screens/` propose UI that needs **runtime code** to become real. This file
is the backlog of those code slices, gathered from the redesign brainstorms. It is **not** a
styling list (that is [`token-migration.md`](token-migration.md)) — it is the behaviour/logic work.
Nothing here is built yet; each becomes its own slice, after annotation, with a `version.php` bump
where a web service / cache / setting changes.

## High priority

- **Enrolment-filter the child-competency list (Rules tab).** Apply the `enrollmentfilter` rule to
  the list of child competencies shown in `ovw-detail-rules`. A child competency with **no linked
  courses**, or whose linked courses the user **cannot access**, is a dead click (its own page only
  shows the description). What is displayed must be an **administrative rule** driven by
  `enrollmentfilter` (reuse `calculator::user_enrolled_or_self_enrolable` / the summary enrolment
  filter already used elsewhere). Not a screen-visual change — a data/gating rule. *(Registered on
  request; the Rules-tab visual stays as brainstormed.)*

## Per screen

### Evidence (`ovw-detail-evidence`)
- Add a `getEvidenceTypeInfo` case for `evidence_competencyrule` (today it falls into "Other").
- Compute per-evidence proficiency via the existing `isGradeProficient(grade, scaleconfig)` and the
  precedence: rule-completion (`evidence_competencyrule` + `ACTION_COMPLETE`) leads over a proficient
  rating; surface the "Completed by competency rule" card linking the Rules tab.
- New `showevidencecounts` admin setting (counts by outcome: "N decisive · N on the journey").
- Replace the slider with the outcome-first list (result strip + journey rows), keep the modal.

### Plan overview (`ovw-overview`)
- Per-plan **favourite** stored as a `user_preference` JSON (the plugin owns no tables), toggled by a
  small write WS modelled on `block_dimensions_toggle_favourite`; `isfavourite` on each server row.
- Sort / completion-filter / favourites-filter / list↔grid all **client-side** over the already-
  loaded rows (no re-fetch); persist the view state as a `user_preference` (not localStorage).
- Grid mode = a `view` param branch; detail stays **lazy** per competency (heavy — not pre-loaded).
- Ghost card ("N more to explore" — the not-yet-favourited count), like `block_dimensions`.
- Detail modal with prev/next pagination across competencies + full-screen expand (mobile).

### Tracker (`trk-tracker`)
- Filter `[Not completed | All]` by **course completion**; sort (Completed first / Name / Recent);
  a Filter button opening the existing `chip_filters` (align with view-plan). All client-side.
- Completed-course card: a "Completed" seal + the timeline collapsed to a summary (expandable).
- "Continue" shortcut on an in-progress card → the current section (first `is_started && !is_completed`).

### Locked card (`trk-locked`)
- Surface the **self-enrol** state: when `calculator::current_user_can_self_enrol()` is true, swap the
  padlock for a `fa-right-to-bracket` icon + an "Enrol to start" action (precedes blocked/learn-more).
- Render the (already-in-payload) sections **blurred** behind a lighter overlay veil.
- Reframe the availability line as **"Opens {date}"** (anticipatory); drop a **past** date in
  learn-more mode.
- **`min-height: 320px`** on tracker cards so short courses don't clip the icon/marker.
- **Single-activity courses:** a calculator branch for courses with no trackable sections — surface the
  **activity name + its completion status** (ring % / check / circle) instead of a timeline, with a
  direct "Go to activity".

### Rules (`ovw-detail-rules`)
- (Brainstorm in progress.) Soften the "X / Y" progress bar; promote the "how it's completed" text.
- Plus the enrolment-filter item above.

## Styling
- The Material/Google → Moodle DS token migration lands as its own `styles.css` slice, driven by
  [`token-migration.md`](token-migration.md), with a `version.php` bump for the cache revision.
