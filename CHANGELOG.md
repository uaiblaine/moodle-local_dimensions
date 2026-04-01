# Changelog

All notable changes to the **local_dimensions** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Admin setting `showtaxonomycard` to display the main taxonomy card in the Description tab.
- Admin setting `animatelockedborder` to control the locked-card dashed-border animation.
- Backend taxonomy helpers to expose current, child, and per-level taxonomy metadata for a competency.
- Taxonomy card UI with dedicated taxonomy icon assets in `pix/taxonomy`.
- New status icon assets in `pix/status` for due dates, locked cards, progress cards, summary badges, and Rules tab states.
- Additional MUC cache definitions for template metadata, competency metadata, return context, and plan trail data.
- Event observers for competency rating/evidence events to invalidate stale plan trail session cache entries.
- Rules tab enhancements:
  - Required-only filter pills for child items.
  - Warning state when the minimum score is reached but mandatory children are still pending.
  - Localised outcome and required-warning text returned by the backend.
  - Optional "Submit prior learning evidence" button in the Rules tab.

### Changed
- Taxonomy rendering in the accordion now uses backend-provided payload data instead of client-side taxonomy inference.
- The Description tab can now show the current competency taxonomy beside the descriptive content.
- Related competency wording was simplified to "Related dimensions" in both language packs.
- Rules tab rendering now uses backend-provided outcome text, required-warning text, and mandatory counters.
- Locked cards, due-date badges, summary status badges, and Rules icons now use plugin image assets instead of inline SVG markup.
- English and Brazilian Portuguese strings were streamlined to match the new taxonomy card and Rules tab behaviour.
- Documentation audit: `README.md` now matches current implementation for custom field shortnames/provisioning, admin settings, cache map, event observers, and frontend asset counts.

### Removed
- Comments section with reply functionality in accordion panels (`showcomments` setting, external services, JS, CSS, and language strings).

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
