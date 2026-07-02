# Changelog

All notable changes to the **local_dimensions** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- **Plans tab to-be (design screen `pln-plans`)** — same master-detail, cleaner:
  - Search plans by name **or idnumber** (`local_dimensions_template_idnumber`), client-side over the rendered rows, with a no-results notice.
  - **Multi-competency filter** as removable tags (intersection semantics: a template must contain *every* tagged competency), plus "clear filter". The picker autocomplete stays hidden until "Add to filter" is clicked.
  - **Show disabled plans** toggle (managers only — non-managers never receive hidden templates). Choice persists per session; disabled rows stay in the DOM and are revealed at reduced opacity. This also fixes the previous behaviour where hidden templates were never listed at all (`api::list_templates` was called with `$onlyvisible = true`, making the mustache "hidden" badge dead code).
  - **Resizable panes** via a shared draggable divider starting at 50/50 (`central/pane_resizer` — extracted from the Structure tab implementation, now used by both tabs). Both panes keep a fixed working height (min 320px, max 60vh capped at 680px) and scroll internally.
  - Detail pane surfaces **enabled state, due date, learner-plan count and cohort count** (single grouped SQL each) next to the competency count.
  - Per-competency row actions (move up/down, remove) collapsed into a **kebab menu**; template actions become "Edit details", "Add competency", "Participants" and an overflow menu with **Duplicate** (new — `core_competency_duplicate_template`, the copy is auto-selected) and Delete (the shared explicit-consequence modal).
  - Auto-selection prefers the first *visible* template so the detail matches the default list view.
  - Both competency pickers (filter + add-to-plan) render suggestions in the Structure-search pattern: name, idnumber in monospace, and a muted breadcrumb line (framework tag / ancestor path) — `local_dimensions_search_competencies` now returns the `path` via `helper::competency_breadcrumbs()`.
  - Panes always match vertically: the grid body owns one fixed height (`clamp(320px, 60vh, 680px)`); in the detail card only the competency list scrolls, with the header (plan name + counts) and the action bar pinned as a fixed footer. The divider can shrink the plan list down to ~200px (the competency side usually needs the room).
  - Per-competency kebab gains **Edit competency**, opening the standard competency modal (`competency_dynamic_form`; each row carries its framework id so the form resolves the right context).
  - **Display options** on the plan detail, mirroring the Structure tab: a gear next to the plan title expands a (collapsed by default) panel with "show taxonomy", "show paths" and "show identifiers" switches; choices persist per session and apply as `show-*` classes on the competency list. Taxonomy comes from `helper::get_taxonomy_at_level()` at each competency's level, paths from `helper::competency_breadcrumbs()`.
  - **Drag-and-drop reordering** of the plan's competencies: a grip handle fades in on row hover (file-tree style); the row live-repositions while dragging and a single `core_competency_reorder_template_competency` call persists the drop.
  - **Move to position modal** for long lists (core-like): a plain click on the grip (or the kebab item) opens a `core/modal_save_cancel` with a numbered select of every position, annotated with the competency currently there; saving issues one reorder call. This is also the keyboard-accessible path (the grip is a real focusable button revealed on focus).
  - **Reorders are applied in place** (kebab up/down, drag-drop and the position modal): no pane reload, the moved row flashes briefly and the first/last menu states are recomputed — long lists keep their scroll position. Flows that still reload the pane (add/remove/edit competency, plan selection) now restore the scroll of both lists afterwards.

### Added
- **Structure tab parity with the Plans tab**:
  - Drag-and-drop reordering of competencies among their same-parent siblings, from the hover-revealed grip; the node travels with its subtree, live-repositions at the pointer's midpoint, and the drop persists as one batched request of single-step `core_competency_move_up/down_competency` calls (core has no reorder-to-position service for framework competencies). Reparenting stays out of scope (Edit's parent select covers it); a plain grip click opens the shared "move to position" modal — the keyboard path.
  - Same container treatment: tree and detail panes share one fixed working height (60vh, 320–680px), the tree body and detail body scroll internally (toolbar, display options and search stay pinned), and the divider gains the Plans reach (tree can shrink to ~200px).
  - Detail pane gains a **Linked learning plans** counter (batched `competency_templatecomp` count per node) and all three counters (courses / activities / plans) are now clickable, opening a read-only usage modal — new `local_dimensions_competency_usage` web service listing linked courses, activities (each labelled with its course) and the learning plan templates bundling the competency.

### Fixed
- **Dropdowns dead on Moodle 4.5.** The Plans-tab kebab and overflow menus were wired with `data-bs-toggle` only — Bootstrap 4 (Moodle 4.5) listens on `data-toggle`, so the menus never opened there. The toggles now carry both attributes and the menus both alignment classes (`dropdown-menu-right dropdown-menu-end`). Also silenced two intentional `promise/no-nesting` ESLint warnings (recovery reload inside failure handlers) that only surface under CI's `--max-lint-warnings 0`.
- **CI red on the Plans-tab redesign.** Moodle's stylelint (stricter than the repo config): `clamp()` in `height` rejected by `csstree/validator` (now `height` + `min/max-height`) and three `!important` (now the plan rows own their `display` in a plugin class instead of Bootstrap's `.d-flex`, so the visibility toggles need no `!important`). Behat: four scenarios still clicked controls the redesigned UI moved behind collapsed containers — they now open the ⋯ menu, the row kebab, the "Add competency" panel and the "Add to filter" picker first.
- **Custom field data leaked on competency/template deletion (Moodle 5.1+).** Core destroys the instance context before firing the `*_deleted` event, so the observer's `delete_instance()` cleanup silently found nothing. Added a context-independent sweep that removes `customfield_data` by instance id and area.
- Removed a static MUC loader handle (`self::$cache`) from the four cache helper classes; it survived PHPUnit's `resetAfterTest()` and broke cache-invalidation tests (the core cache factory already memoises one loader per definition per request).
- Migrated CI to the moodle-an-hochschulen reusable workflow (PHPUnit + Behat now run on every PHP × DB leg) and cleared the pre-existing phpcs/phpdoc/ESLint debt the previous workflow never actually ran.

### Added
- **Manage competencies** revamp:
  - Context segmented switch (System / Course category) and category autocomplete with badges showing the number of competency frameworks per category.
  - Tree / Table view toggle, server-side search, "show hidden frameworks" filter, "show identifiers" toggle.
  - Right-side details aside with metadata, a sticky resizer, and inline action buttons (edit, add child, move, related competencies, rules, linked courses, **delete** — new) all using FontAwesome icons.
  - Tree row icon-actions migrated to FontAwesome.
  - Empty-state hint when the user lacks `competencyframeworkmanage` in the active context.
- **Manage learning plan templates** revamp matching the competencies UX:
  - Same context switch + category dropdown with a per-category template-count badge.
  - Cards (default) / Table view toggle, search, show-hidden, show-identifiers.
  - Card view leverages template customfield visuals (card image, background colour, type/tag chips).
  - Right-side details aside with template metadata, plan / cohort counters, action buttons including duplicate and delete.
  - Delete modal with explicit consequence (design screen `mod-delplans`, to-be): a `core/modal_delete_cancel` that names the template ("Template:" label in the body), shows the real learner-plan count and states the consequence of each radio choice — unlink (default; the plans keep existing without a template) or delete the plans (irreversible). Shared by both delete flows: the manage templates page and the Competency hub Plans tab (which previously used a plain unlink/delete radio dialogue — `templates/central/delete_template_plans.mustache`, now removed in favour of the shared `templates/delete_template_modal.mustache`).
  - Cohort linking and plan creation link out to the native `tool/lp` pages.
- **Edit competency / edit template** parity:
  - Mustache shell with back link, hero (title / context / metadata chips), sticky section navigation, and submit button in the action bar.
  - `aria-current="page"` on the active section link, `aria-labelledby` on each form section.
  - Live colour swatch preview next to bgcolor / textcolor custom fields.
- New custom field `local_dimensions_template_idnumber` (text, lp area only) — fills the role of an idnumber for templates which have no native column.
- New helpers in `local_dimensions\helper`: `count_frameworks_by_category`, `count_templates_by_category`, `count_plans_by_template`, `count_cohorts_by_template` (single grouped SQL each).
- New batch method `template_metadata_cache::get_metadata_for_many` — single grouped customfield_data SELECT for a list of templates, populating MUC for cache misses.
- **Return to Plan** FAB is now draggable: the user can move it when it overlaps other UI. The chosen position is kept in `sessionStorage` (per-tab, current session only) and restored on reload; double-click resets it to the default corner. Dragging never triggers navigation (movement threshold + click suppression).

### Changed
- **Return to Plan** FAB no longer appears on administrative / report pages (course settings, gradebook and other reports, site admin, etc.). `hook_callbacks::before_footer_html_generation` now skips pages whose `$PAGE->pagelayout` is `admin`, `report`, `maintenance`, `login`, or `redirect`, so the button shows only on course-content and activity pages.
- `manage_competencies.php` and `manage_templates.php` now route through `admin_externalpage_setup()` when accessed in system context, so the capability registered in `settings.php` becomes the real gate (not just a menu filter). Coursecat access keeps the lighter per-context check.
- `manage_competencies.php` and `manage_templates.php` push the `visible=0` check to SQL: a single `record_exists_select` for the toggle flag, plus `onlyvisible=true` passed to `api::list_frameworks`/`api::list_templates` when "show hidden" is off.
- `template_form.php` populates the description field via `set_data()` (form-level) instead of `setDefault()`, so the rich-text editor (TinyMCE) initialises correctly with both text and format.
- Mustache `{{#str}}hidden{{/str}}` qualified to `tool_lp` everywhere (the string lives in `tool_lp`, not in core/moodle).

### Fixed
- TinyMCE editor not loading on `edit_template.php`.
- Manage templates page raising "Unexpected property 'idnumber' requested" — `competency_template` has no native idnumber column.
- Pre-existing `unknownscale` lang key out of alphabetical order.

### Build
- Plugin lives at `<moodle>/local/dimensions` on Moodle 4.5 and at `<moodle>/public/local/dimensions` on Moodle 5.0+. The grunt mirror build path differs per version — see the README "Building JavaScript assets" section.

### Previously Unreleased

The following entries were already in `[Unreleased]` before the manage_competencies / manage_templates revamp landed; they remain unreleased.

- Admin setting `showtaxonomycard` to display the main taxonomy card in the Description tab.
- Admin setting `animatelockedborder` to control the locked-card dashed-border animation.
- Backend taxonomy helpers to expose current, child, and per-level taxonomy metadata for a competency.
- Taxonomy card UI with dedicated taxonomy icon assets in `pix/taxonomy`.
- New status icon assets in `pix/status` for due dates, locked cards, progress cards, summary badges, and Rules tab states.
- Additional MUC cache definitions for template metadata, competency metadata, return context, and plan trail data.
- Event observers for competency rating/evidence events to invalidate stale plan trail session cache entries.
- Rules tab enhancements: required-only filter pills, warning state when minimum score is reached but mandatory children are still pending, localised outcome and required-warning text from the backend, optional "Submit prior learning evidence" button.
- Taxonomy rendering in the accordion now uses backend-provided payload data instead of client-side taxonomy inference.
- The Description tab can now show the current competency taxonomy beside the descriptive content.
- Related competency wording simplified to "Related dimensions" in both language packs.
- Rules tab rendering now uses backend-provided outcome text, required-warning text, and mandatory counters.
- Locked cards, due-date badges, summary status badges, and Rules icons now use plugin image assets instead of inline SVG markup.
- English and Brazilian Portuguese strings streamlined to match the new taxonomy card and Rules tab behaviour.
- Documentation audit: `README.md` aligned with custom field shortnames/provisioning, admin settings, cache map, event observers, and frontend asset counts.
- Removed: comments section with reply functionality in accordion panels (`showcomments` setting, external services, JS, CSS, and language strings).

## [1.0] - 2026-03-08

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
