# Changelog

All notable changes to the **local_dimensions** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
  - Native `tool_lp/actionselector` modal on delete: choose between "Delete the learning plans" and "Unlink the learning plans from their template".
  - Cohort linking and plan creation link out to the native `tool/lp` pages.
- **Edit competency / edit template** parity:
  - Mustache shell with back link, hero (title / context / metadata chips), sticky section navigation, and submit button in the action bar.
  - `aria-current="page"` on the active section link, `aria-labelledby` on each form section.
  - Live colour swatch preview next to bgcolor / textcolor custom fields.
- New custom field `local_dimensions_template_idnumber` (text, lp area only) — fills the role of an idnumber for templates which have no native column.
- New helpers in `local_dimensions\helper`: `count_frameworks_by_category`, `count_templates_by_category`, `count_plans_by_template`, `count_cohorts_by_template` (single grouped SQL each).
- New batch method `template_metadata_cache::get_metadata_for_many` — single grouped customfield_data SELECT for a list of templates, populating MUC for cache misses.

### Changed
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
