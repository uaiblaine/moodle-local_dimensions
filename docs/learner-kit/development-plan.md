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
- **Retire the always-visible inline chip bar** (`chip_filters` rendered under the hero). Filtering moves
  into the toolbar's **Filter** control (`OVW-TOOLBAR`); the `chip_filters` component is reused inside the
  Filter panel it opens, not rendered inline. Same retirement applies to the Tracker (below). The
  `chips` kit screen is kept only as the historical record of the retired component.

### Tracker (`trk-tracker`)
- Filter `[Not completed | All]` by **course completion**; sort (Completed first / Name / Recent);
  a Filter button opening the existing `chip_filters` (align with view-plan). All client-side. The
  inline chip bar is **retired** here too — the chips open from this Filter button, not inline.
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

### Status / Assessment (`ovw-detail-status`)
- Rebuild the status pane so the **rating leads** (the scale level `gradename` as the primary fact,
  neutral strong text) and **proficiency is a qualifier pill** beside it (green when proficient, amber
  "Not yet proficient", dropped when there's no grade → hero reads "Not yet rated"). Replaces the
  two-cell grid in `renderStatusSection` (accordion.js ~2028). One new lang string ("Not yet rated";
  "Not yet proficient" can reuse the existing no-string or be its own key).
- **"About this scale" → modal.** Because the rating scale is per-competency, add a button that opens
  the scale's own `description` in a `core/modal`, rendered **only when that description is non-empty**.
  Needs a new field on the learner WS carrying `mdl_scale.description` (today only the scale *name* is
  fetched, admin-side, at `helper.php:2406`); a `version.php` bump since a WS return changes. Optional
  richer modal: the scale items (`mdl_scale.scale`) + `scaleconfiguration` to render a structured level
  list with the proficient levels marked, instead of just the raw description blob.

### Linked courses / activities (`ovw-detail-courses`) — kanban-hybrid under Assessment
- **Keep the course-card scroller; add a decisive outcome badge.** The image-card scroller stays. Each
  card gains a badge from the link's `ruleoutcome` (`course_competency` / `course_module_competency`):
  strong accent for the two that conclude/advance the competency — **Completes the competency**
  (`OUTCOME_COMPLETE`) and **Sends for review** (`OUTCOME_RECOMMEND`) — a subtle grey **Attach evidence**
  (`OUTCOME_EVIDENCE`), and **no badge** for **Do nothing** (`OUTCOME_NONE`, the default, so most cards
  stay clean). Accent, not green: green = achieved (progress ring), badge = what finishing it *does*.
  Mirrors the Evidence tab's decisive/journey line. Add `ruleoutcome` to the courses WS return.
- **Activities expand from the card.** A course whose modules are linked to *this* competency shows a
  "N activities" disclosure; expanded, it lists compact rows (icon + name + own decisive badge +
  done/to-do), each linking straight to the activity. The card links to the course. Extend the WS to
  also return the competency's **module links** (`competency_modulecomp`, grouped by courseid) with
  `ruleoutcome` + completion — the admin side already joins this (`helper.php:2373`). Handle activities
  whose course isn't itself linked (show the course as a neutral container, no course badge).
- **Label-driven title.** "Linked courses" becomes "Linked **{label}** & activities", where {label} is
  the competency's `local_dimensions_type` select ("Competency label"; options already plural:
  Activities / Lessons / Cycles / Stages / Modules / Levels / Periods / Units), read server-side as
  `cf_type` (`helper.php:1319`). Falls back to "Linked courses" when unset; degrades to "Linked
  activities" when the label itself is "Activities".
- **Keep the nav pill (`OVW-CRS-NAV`).** Move the prev/next scroll pill into the section header (stable
  when a card expands), hide the native horizontal scrollbar (`scrollbar-width:none`) so the pill is the
  clean nav affordance; keep the existing gates (shown only when itemCount > 2 / mobile, Prev disabled
  at the left edge).
- All of the above is one WS-return extension → a `version.php` bump.

## Styling — DONE (nothing left here)
- **The Material/Google → Moodle DS token migration has landed** (commit `345ffb4`, CI green): 50
  value-only literal swaps in `styles.css` plus the two colour-picker defaults in `settings.php`
  (`returnbuttoncolor`, `learnmorebuttoncolor`). Driven by
  [`token-migration.md`](token-migration.md), which records the six "review" decisions taken
  (taxonomy-label and focus-ring → primary, "rated" amber kept, modal-note trio → BS warning-light)
  and the ~10 loose greys deliberately left for optional future normalisation.
- `version.php` was **not** bumped — the version is frozen at `2026071801` until 2.0, and this was a
  visual-parity change (no schema, no WS). The CSS cache busts at the next real bump, or purge caches
  on the test server.
