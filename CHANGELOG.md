# Changelog

All notable changes to the **local_dimensions** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

Macro view of everything since v1.0 — per-change detail lives in the commit history.

### Added
- **Competency hub** (`central.php`): a single admin surface for the whole competency domain —
  three dynamic tabs with modal-based CRUD, a system/category context switch and no full-page
  reloads.
  - *Structures*: lazy competency tree with search-and-reveal, drag-and-drop reorder/reparent,
    move-to-position modal, native rule editor, display toggles and per-competency usage
    counters (courses / activities / plans).
  - *Learning plans*: master-detail template management — search and multi-competency filter,
    create/edit/delete in modals, **full duplication** (custom fields, embedded files and card
    images included), competency picker + framework browser, drag-and-drop ordering, resizable
    panes.
  - *Frameworks*: native create/edit with scale configuration, duplicate, visibility toggle,
    reason-gated delete and **CSV import/export**.
  - *Learning plan CSV transfer*: export the templates the Plans tab lists — competency links in
    order, the plugin's fourteen template custom fields, and a companion download for each
    referenced structure — and import them back with a **dry-run preview**. The preview projects
    every row against the target site (create / update / in sync / skip / conflict / blocked /
    orphan link) with its field diff, its resolved competency links, its effect on existing
    learner plans, and per-row ways out of a conflict; **nothing is written until you apply**, and
    then only the rows you ticked, each in its own transaction and re-validated at write time so a
    site that moved under you is refused rather than half-written.
  - Modals: *Participants* (cohorts with background plan sync, individual users, cohort-role
    assignment), *Courses & activities* (linking with rule outcomes, activity search,
    completion-rule badges), *Related competencies* (shared tree browser).
  - ~30 AJAX web services back the hub; the front-end is ESM, zero-YUI, Bootstrap 4+5
    compatible.
- **Learning plan template CSV transfer** — *groundwork only, not yet reachable from the UI*:
  the two-row-type CSV format (`template` / `link` rows, every column read by header name)
  carrying all fourteen template custom fields, cross-framework competency links and their
  order, plus the `local_dimensions_export_templates` web service and a one-directional
  ingest shim for `admin/tool/lptmanager` files. The hub toolbar, the dry-run import preview
  and the partial-apply importer are specified in
  `docs/superpowers/specs/2026-07-27-learning-plan-template-csv-transfer-design.md` and
  planned in `docs/superpowers/plans/2026-07-27-template-csv.md`; tasks 2-8 remain.
- **Per-user persistent hub state**: last tab/context/framework/template, display toggles and
  gear panels survive sessions and devices (two JSON user preferences + privacy provider).
- **Bulk enrolment methods** (participants modal, 4th tab): apply/remove cohort sync
  (`enrol_cohort`) or cohort-restricted self enrolment (`enrol_self`, `customint5`) on the
  courses linked to a template's competencies — single role per operation (gradebook roles),
  category/hidden filters, lazy per-competency accordion, per-course status against the
  selected cohort, and one background adhoc task per (course, method, cohort) combination
  with queue dedup, Lock API serialisation, idempotent execution, audit events and a queue
  poll driving per-row Processing states.
- **15 administrative audit events** for decisions core never logs: cohort attach/detach,
  cohort-role rules, custom-field value changes (effective-value diff, SCSS redacted),
  course/activity links with rule outcomes, template duplication, and enrolment methods
  applied/removed in bulk.
- **Concurrency safeguards**: custom-field provisioning under a core Lock API lock,
  deduplicated cohort-sync task queueing, and a retry on concurrent first-saves of custom
  fields.
- **Learner views**: taxonomy card, Rules-tab filters and warnings with backend-provided
  texts, status/taxonomy icon assets, plan-trail session cache, and a draggable Return-to-Plan
  button. Both views were then rebuilt end to end (learner kit, Phases 0–6):
  - *Related content*: each competency panel carries its completion-rule **outcome badge** and
    the activities linked to it, grouped by course, drawn with core's own activity icons and
    purpose colours. A restricted activity is shown locked and leads to the course page; a
    hidden one is not shown at all.
  - *Locked course card*: **self-enrolment** where the course offers it, and an anticipatory
    "Opens" / "Enrolment opens" date instead of a bare lock.
  - *Single-activity and single-section courses* resolve to the activity or the section itself,
    rather than to a card leading to a course page with one link on it.
  - **Sort and completion filter**, persisted per user and resolved server-side so the first
    paint is already ordered: plan order, name, completed first or favourites first, over
    "not completed" or "all".
  - **Per-plan favourites**: a star on each competency, a "My favourites" / "Show all" pill
    pair, and a ghost card counting what the filter hides so it cannot be left on unnoticed.
    Own-plan only — staff reviewing someone else's plan get no star — and gated by the new
    `enablefavourites` admin setting (default on, mirroring `block_dimensions`).
  - **Grid layout** beside the list, with a competency **detail modal** carrying a pager across
    competencies and a full-screen expand; both choices persist.
  - *Competency tracker*: completion tabs (Not completed / All), a **"Continue"** shortcut to
    the first started-but-unfinished section, and a seal on a completed course's card.
  - **Collapsible hero**: a handle on the header's bottom edge folds it to a slim row — the
    title with the plan's due date beside it, no description — and back. The choice is stored
    per plan and per competency and applied server-side, so a learner who returns lands on the
    header they left.
  - The toolbar is realigned with `block_dimensions`: sticky at every width, filters folding
    behind an adjustments button on narrow screens, and a "Clear filters" button with an icon.
- **CI**: moodle-an-hochschulen reusable workflow — static checks plus PHPUnit and Behat
  across the supported PHP × DB matrix (Moodle 4.05–5.02).

### Changed
- The **Return-to-Plan button** was hardened end to end: redirect loops are structurally
  impossible, it renders only on course-content layouts (never in secure quiz windows or on
  administrative pages), only for the plan's own user, and stale contexts expire (4h TTL).
- Editing UX unified around core `dynamic_form` modals with in-modal toasts and row flashes;
  pagination standardised at 25 across grids and pickers.

### Security
- **Per-course authorisation on the two tracker card services.** `get_course_progress` and
  `get_courses_completion_status` took a raw course-id list and were gated only by the
  site-wide `local/dimensions:view`, which every authenticated user holds — so a direct AJAX
  call could read the visible section names and start date of any course, including deliberately
  hidden ones. Both now resolve their ids through `helper::readable_competency_courses()`, which
  keeps only courses that exist, that core would let the viewer see listed
  (`core_course_category::can_view_course_info()`) and that carry a competency link — the only
  courses either view ever lists. Everything else gets the same locked, empty row, so a probing
  caller cannot tell a hidden course from a missing one.
- **CSV exports no longer carry live spreadsheet formulas.** A competency or template whose name
  began with `=`, `+`, `-` or `@` was written verbatim into the framework and template exports
  and evaluated when the file was opened. Both serializers now neutralise those cells
  (`local\csv_formula`, matching core's `\core\dataformat::escape_spreadsheet_formula()`), and
  both importers strip the guard again — including from files written by core's own
  `csv_export_writer`, which has no counterpart on the way in.
- **No more DDL from a request path.** `helper::sql_like_ai()` attempted
  `CREATE EXTENSION unaccent` on every search that reached it when the extension was missing,
  which a least-privilege database account can only ever fail. The catalogue check is now
  `helper::has_unaccent()`; provisioning stays in `helper::ensure_unaccent()` and runs from
  `db/install.php` and `db/upgrade.php` only.

### Removed
- The entire **legacy admin surface**: `manage_competencies.php`, `manage_templates.php`, the
  `edit_*` pages, their forms, templates and AMD modules, ~2.3k lines of CSS and 125 orphaned
  language strings — the hub covers every action they offered.
- The **comments** feature (accordion reply threads, its services, JS, CSS and strings).
- Client-side SCSS validation (the server-side validator is the single gate) and the unused
  customfield-aware CRUD web services.

### Fixed
- Custom-field data leaked on competency/template deletion (Moodle 5.1+ context teardown).
- Bootstrap 4 dropdowns dead on Moodle 4.5 (missing `data-toggle` bridges).
- Web-service return structures silently stripping undeclared fields from lazily-fetched rows.
- TinyMCE not initialising on template edit; assorted modal heading/labelling issues.
- Four learner-view defects that predated the rebuild: the filter-tab click handler reaching
  outside its own toolbar, the course-progress payload tripping on a completion-disabled
  course, the last hard-coded colours left by the palette migration, and `isGradeProficient`
  misreading core's scale configuration.
- A partial seed silently erasing a whole-value preference: `local_dimensions_learner_view`
  holds five keys in one JSON value, so any control that saved reset every key the page had
  not seeded — choosing a sort discarded the grid layout, and the favourites filter and the
  modal size were lost the same way, unseen. The whole resolved state is now handed to the
  client as a single object.
- **Bootstrap 5 class names that resolve to nothing on Moodle 4.5.** The bridging between the
  branches is asymmetric — 4.5's forward bridge is 116 lines (`g-0`, `btn-close`, the
  `ms/me/ps/pe` spacers, `float/text/border/rounded-start/end`) while 5.x's backward bridge runs
  past a thousand — so BS4 names resolve on both branches and BS5 names do not. Measured on a
  running 4.5 site: `visually-hidden` did not hide (24 sites, so every table caption printed as a
  heading and "opens in new window" showed after each external link), `form-select` left 21
  selects with no border, radius, chevron or padding, `gap-*` collapsed 15 toolbars into
  run-together controls, and `fw-*`, `font-monospace`, `form-switch` and `form-label` were inert.
  Fixed with a gated Bootstrap 4 utility polyfill at the tail of `styles.css` rather than by
  writing BS4 names, which Moodle 6.0 removes (MDL-84465). The gate is a body class added only
  when `$CFG->branch < 500`, so the block cannot reach 5.x.
- **Badge contrast on both branches.** Bootstrap 4's `.badge` sets no text colour and Bootstrap
  5's defaults it to white, so a badge that did not state its own colour failed AA on one branch
  or the other: measured 3.07:1 for `bg-success` on 4.5, and 1.49:1 for `bg-secondary` on 5.2 —
  a live defect on the current stable target, not only on the old one. Every badge now declares
  its text colour.
- Unwrapped `.form-check-input` controls escaping their row on 4.5, where Bootstrap 4 makes them
  `position: absolute; margin-left: -20px` and expects a `.form-check` parent to compensate.
- The participants filter panel closing mid-interaction on 4.5: `data-bs-auto-close` has no
  Bootstrap 4 equivalent, and BS4 exempts only `input` and `textarea` targets, so the cohort
  select and the switch label both dismissed the panel.
- Core 4.5's modal close button showing two glyphs, its own `&times;` span plus the plugin's
  Font Awesome `::before`.

### Changed
- The plugin's motion and loading custom properties moved from `--mds-*` to
  `--local-dimensions-*`. `--mds-` is core's namespace: Moodle 5.2 ships
  `theme/boost/scss/design-system/` with `$mds-*` tokens and 5.3 LTS brings MDS React, so
  declaring those names in `:root` was squatting a namespace core is actively expanding.
- CI no longer runs a Moodle 5.0 job. 5.0 leaves security support on 2026-10-05; the remaining
  jobs are 5.02 (full PHP × DB matrix), 5.01 and 4.05.
- `tests/local/bootstrap_compat_test.php` now enforces the Bootstrap contract that prose had
  failed to hold three times: every BS5 utility used must be polyfilled, the polyfill must carry
  nothing unused, every badge must state its text colour, data-API attributes must be paired,
  the stylesheet must not declare `--mds-*`, and every entry point setting a plugin body class
  must mark the Bootstrap version.

## [1.0] - 2026-03-16

### Added
- Two display modes for learning plans: **Competency tracker** (course card grid) and **Full plan overview** (expandable accordion).
- Custom fields for competencies and learning plan templates: card image, background image, background colour, text colour, tags, display mode, and custom SCSS.
- Auto-provisioning of custom fields on first admin access when core competencies are enabled.
- Real-time course section progress calculation with recursive subsection support (Flexsections, `mod_subsection`).
- Competency completion rules display (Rules tab) in Full plan overview, showing rule type, rule outcome, sub-competency progress, proficiency status, and required flags.
- Evidence cards with detail modals in accordion panels.
- Related competencies display (optionally clickable) in accordion panels.
- Competency hierarchy path display (framework → parent → competency).
- Floating "Return to plan" button with configurable colour.
- FontAwesome icon picker with AJAX search for locked card icons (supports Boost Union extended icon map).
- Custom SCSS injection per template and per competency with client-side validation and server-side compilation.
- Enrolment-aware filtering (all, enrolled, active) for both display modes.
- Single course redirect option when user has only one active enrolment.
- Lock status detection with configurable icons and "Learn More" buttons.
- Availability date display on locked cards.
- Optional "Submit prior learning evidence" button in the Rules tab.
- Moodle Privacy API implementation (`null_provider`).
- Application-level MUC caches for template courses, template SCSS, and competency SCSS.
- Five AJAX web services: course progress, competency courses, user competency summary, FontAwesome icons, and competency rule data.
- Custom capability `local/dimensions:view` for controlling access.
- Event observers for `competency_created` and `competency_updated`.
- Hook callbacks for injecting custom fields into core competency forms and rendering the return button.
- Clean uninstall routine removing all custom fields, file areas, and caches.
- 13 Mustache templates for responsive layouts.
- 4 AMD JavaScript modules: accordion, UI, FontAwesome icon selector, SCSS validation.
- WCAG-compliant accessibility: ARIA labels, keyboard navigation, semantic HTML, screen reader support.
- English and Brazilian Portuguese language packs.

[Unreleased]: https://github.com/uaiblaine/moodle-local_dimensions/compare/v1.0...HEAD
[1.0]: https://github.com/uaiblaine/moodle-local_dimensions/releases/tag/v1.0
