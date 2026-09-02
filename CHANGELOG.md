# Changelog

All notable changes to the **local_dimensions** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- **The Competency hub from a course category's "More" menu.** A manager who holds
  `moodle/competency:competencymanage` or `templatemanage` in a course category (and nowhere
  else) now finds the hub beside core's *Competency frameworks* and *Learning plan templates*
  entries, opened with `pagecontextid` the way tool_lp's pages are, following the same page setup
  (category heading, category navigation, no admin tree). The page is locked to that category:
  the context switch and the picker give way to the category's name, the System switch is
  offered only to viewers who may read something at the site, and nothing from a locked visit is
  written into the remembered context — the next visit through Site administration reopens
  where it was. The menu entry is gated on managing, not reading, because reading is an
  authenticated-user default at every category.
- **Deleting a course category now accounts for its frameworks and learning plan templates.**
  Core's deletion moves cohorts and deletes grade categories, content bank items and calendar
  events, then drops the context and leaves competency data pointing at it — invisible,
  unreachable and undeletable; neither core nor tool_lp registers a callback. The deletion
  form now lists the category's frameworks and templates and how many are in use; "delete
  all" is refused while a competency is linked to a course, activity, template or plan or a
  template still has plans (mirroring core's refusal to delete a competency in use) and
  otherwise deletes them through the competency API; "move contents" re-homes them to the
  destination category, and is offered only to someone who may manage them there.
- **A Behat generator for competency objects in a course category.** Core's generator hardcodes
  the system context and its Behat generator cannot name one, so a scenario about category
  scoping could only create site-wide objects. `the following "local_dimensions > frameworks"
  exist` and `"local_dimensions > templates"` take the category's idnumber; the new
  `central_category.feature` walks a category manager from the category page into the locked hub.
- **The category entry lists the category's descendants too.** Frameworks, structure and
  learning plans on the locked entry use core's `children` scope, as tool_lp's category pages
  do, and the bar's headline count covers the same subtree. The site entry keeps listing one
  context at a time, so the System view never shows other contexts' objects.
- **Competency and template pictures check who may see the object.** `local_dimensions_pluginfile`
  served every picture to any logged-in user by id, which was harmless while only site
  administrators created these objects and becomes a cross-category leak once categories are
  delegated. A picture of a visible object is still served to any logged-in user, as course
  images are; a hidden template's picture only to those who may read it in its context or hold
  a plan based on it, and a competency's only to those who may read its framework.

### Changed

- **The hub's course category picker searches on demand instead of listing every category.**
  The context bar used to enumerate every category the viewer could see on every render —
  a context instantiation and up to four capability checks per category, a nested name built
  per category, and thousands of `<option>` elements for the autocomplete to chew through —
  which does not survive a site with thousands of categories. The picker is now a search
  (`local_dimensions_search_categories`, 25 hits per query, name match accent-insensitive)
  that returns the plain nested name and both counts per hit; the server renders only the
  selected category. A viewer who may read competencies at the site skips the per-category
  capability checks altogether (a category can only narrow what the site grants, and the
  page re-checks the chosen category anyway); a category-scoped viewer is checked per hit.
- **The Competency hub decides tab availability in the context the pane names, not at the site.**
  All three tabs asked `can_read_context()` about the system context whatever category the page
  was showing, so a manager holding the competency capabilities in one course category only was
  refused the Learning plans pane (measured on 5.2: `nopermissiontoaccesspage` over AJAX) and, for
  the other two, admitted only through the authenticated-user default for `competencyview`. The
  tab strip now honours that answer the way core's own dynamic-tabs export does — an unavailable
  tab renders disabled — and the active tab falls back to the first available one instead of a
  saved preference throwing on the whole page.
- **The hub's Site administration entry no longer requires `moodle/site:config`.** The plugin
  wrapped its whole admin subtree in `$hassiteconfig`, which core does not impose on local plugins
  and tool_lp does not apply to its own pages, so a system-level competency manager was locked out
  of the hub while core's pages admitted them. The hub is gated by
  `moodle/competency:competencymanage` alone; the settings page and the two custom-field
  definition pages keep the site-configuration guard, because field definitions are site-wide.

### Fixed

- **A manager scoped to one course category saw no custom fields on a competency and saved
  none.** `competency_handler::can_edit()` resolved `competencymanage` at the system context
  whatever the competency, and core filters both the rendered and the saved fields through it,
  so the modal opened (the form checks the framework's context) with every plugin field missing
  and each save wrote nothing, without an error. The handler now resolves the competency's own
  framework context, latches the instance while a new one is saved, and takes a context hint
  from the form for the create path — the same shape `lp_handler` already had, which also gains
  the hint so a category manager sees the fields when creating a template.
- **The competency usage popover failed for a category manager.** Its templates section went
  through `api::list_templates_using_competency()`, which requires template read access at the
  system context and throws otherwise. Templates are now read through the persistent and
  filtered on their own context.
- **The participants picker offered the whole site directory.** `search_assignable_users`
  required only `templatemanage` on the template and then listed every active user, with email
  and ID number, while the follow-up `add_template_user_plan` then failed on `planmanage`. The
  search is now filtered to users the caller may create a plan for, the way core's tool_lp user
  search is; the grid also learns per row whether unlink and delete are allowed and renders
  the two actions only there.
- **Eight read services no longer depend on the authenticated-user default for `competencyview`
  at the site.** Structure browsing and search, competency search, course links, linkable
  courses and usage validated the system context and required `competencyview` there before
  their real per-framework gate, so a hardened site (or a category manager without that
  default) lost the Structure tab. Each now validates the framework's own context; the
  cross-framework search keeps only the login gate and its per-framework filter.
- **Course category names with an ampersand rendered as `&amp;` in the hub's context bar.**
  `make_categories_list()` hands back names already run through `format_string()`, and the
  picker's double stashes escape once more. The bar now rebuilds the nested name unescaped.
- **The "open cohorts page" shortcut is judged in the hub's context and opens the category's
  cohort page.** It was evaluated at the system context and hardcoded the site cohort list,
  although `cohort:manage` is a course-category capability and core's page is context-aware.
- **A course whose enrolment places had been freed by expiry still showed as closed.** The check
  for "this course is full" was re-implemented here rather than asked of `enrol_apply`, and that
  copy counted enrolments whose period had already run out. Since the plugin changed its own
  answer, the two disagreed: `enrol_apply` offered the button and accepted the application while
  this surface went on treating the course as full. The question is now put to the plugin, so the
  two cannot drift again.

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
- **A course you can only get into by applying is no longer drawn as a padlock.** The predicate
  behind every locked card walked the course's enrolment instances looking for `enrol_self` and
  nothing else, so a course whose only way in is `enrol_apply` was classified as locked — cover
  image dimmed, lock overlay on top, no path forward — for learners who were perfectly eligible
  to apply. Widening it is not a matter of asking a more general question, because there is no
  general question to ask: `enrol_plugin::can_self_enrol()` is an unconditional `return false;`
  in the base class and `enrol_self` is the only plugin in the whole of 5.2 that overrides it,
  so a loop written against it reports every other enrolment method as "cannot". The predicate
  now dispatches per plugin — `can_self_enrol()` for self, `allow_apply()` for apply, with the
  already-applied check and the `customint3` places cap mirrored beside it because they live
  outside `allow_apply()` where self keeps its equivalents inside. `enrol_apply` stays optional:
  an `is_callable()` guard means a site without it, or with a different build of it, simply
  never matches. The method is now `calculator::current_user_can_enrol()`;
  `current_user_can_self_enrol()` remains as a deprecated alias.
- **An application awaiting a decision is now its own card state.** A pending `enrol_apply`
  application writes a *suspended* enrolment row, which answers no to both questions a card
  asks: the learner is not actively enrolled, and the plugin will not accept a second
  application. They were therefore handed the padlock — the same card as somebody who was never
  eligible — and the one thing it could not say was the one thing they needed to know. Both
  learner views now report a third state between open and locked: an hourglass with "Application
  pending" instead of a lock with a date, and no button, because there is nothing left to do.
  The state is scoped to `apply` instances on purpose — a suspended row on a manual or self
  instance is an administrative suspension, not an application — and it excludes a row whose
  enrolment period has run out, which is the clause that separates a real application from an
  approval that `process_expirations()` re-suspended under a "suspend" expiry action. Without
  it a learner whose enrolment merely lapsed would be told, permanently, to wait for a decision
  nobody was going to take. The `enrolledorself` display filter needs no new branch: an
  application is a real enrolment row, so its existing `onlyactive=false` test already counts
  it.
- The **card's shape** is now decided over the same set of activities as its percentages. It was
  not: the shape resolver asked "is there exactly one activity here" using *openable now*, so a
  course holding one open activity beside one released-later activity looked like a one-activity
  course, took the single-activity card and drew a completed tick over a course that was half
  undone — beside a bar reading 50%. Whether an activity may be **offered as a link** stays a
  separate question, and the single-activity card still asks it: a course whose only work has not
  opened yet is real work, and is counted, but it falls through to the section or timeline card
  rather than rendering a button that goes nowhere.
- **One rule now decides what a learner's progress is measured against**, for the course bar and
  the section rings alike: an activity counts when completion is tracked on it, when the learner
  can see it, and when it is theirs to do — now or later. The third condition is the one that had
  never been stated. Core already draws that line for us: `is_applied_to_user_lists()` marks the
  restrictions that are **permanent** for a person (group, grouping, profile) and leaves the ones
  that have merely not come round yet (date, grade, completion of something else). So an activity
  released next week stays in the denominator, because the learner will have to do it; an activity
  restricted to a group they are not in leaves it, because no amount of studying will ever unlock
  it — and counting it would put 100% permanently out of their reach. Previously the rings counted
  only what was **openable right now**, so a course whose remaining work was date-released read a
  finished-looking 100% and then walked backwards on the release date.
- The course card's **progress bar** reading wrong on Moodle 4.5. It no longer calls
  `core_completion\progress::get_course_progress_percentage()`, whose numerator is not a subset
  of its denominator on that branch (MDL-60912, fixed in 5.0.7 / 5.1.4, never backported): the
  denominator drops a module flagged for deletion while the numerator keeps its completion row,
  so deleting an activity the learner had already completed made the bar jump — measured 67%
  where 33% was the truth, and `clamp_percentage()` cannot catch it because the value never
  passes 100. 4.5's denominator also applied no visibility filter at all, so a hidden activity
  still counted and a learner could never reach 100% in a course holding one, which showed as a
  50% bar above a 100% section ring on the same card. Neither 5.1+ helper that fixes these
  upstream exists on 4.5 to call, so `calculator::course_completion_percentage()` reproduces
  what 5.1 and 5.2 core compute — activities visible **on the course page**, minus those a group
  or grouping restriction excludes the learner from. All three branches now answer alike, and
  the bar keeps counting work that is merely date-released rather than reporting a finished
  100% and then walking backwards. One further change worth knowing: the bar is now rounded by
  the same rule as the section rings, so 199 of 200 activities reads 99 rather than rounding up
  to a 100 that claimed the course was finished.
- Course cards counting activities the learner could no longer reach, after a **subsection** was
  deleted. Deleting a subsection flags only the subsection module itself — every activity inside
  its delegated section keeps `deletioninprogress = 0` and stays user-visible until the adhoc
  task runs — so `calculator::get_course_section_progress()` went on cascading those activities
  into the parent section's ring while the course page had already withdrawn the whole
  subsection. Reproduced on 5.1 and 5.2 as 25% where 50% was the honest answer; the window
  closed at the next cron run, or never, on a site whose delete task keeps failing. Plain
  activity deletion was never affected (core forces `uservisible` to false for a flagged
  module, which the counter already tested).
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
- **The hub loading two tabs at once on every visit.** `core/dynamic_tabs` opens the first tab in
  the DOM and ignores the server's active flag, and its `loadTab` re-fetches over the web service
  unconditionally — even the tab the server had just rendered. Since `context.js` then clicked the
  saved tab to restore it, any visit whose saved tab was not Structures produced three renders and
  discarded two: a PHP render core replaced, and a full `getContent` for a pane left invisible.
  Measured on 4.5: two calls of 845 ms and 1023 ms starting in the same millisecond, leaving 3250
  bytes of content in a hidden pane. The saved tab now reaches core through the URL fragment, which
  the new `central/tab_hash` template writes synchronously before core initialises — the same
  technique, and the same reason, as core's own template ("We must not use the JS helper otherwise
  this gets executed too late"). The server pre-renders that tab instead of Structures, so it
  paints immediately. Now one `getContent`, one populated pane, whichever tab was saved.

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
