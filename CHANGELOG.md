# Changelog

All notable changes to the **local_dimensions** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- **Rules tab** in the Full plan overview accordion, showing competency completion rules with:
  - Progress bar with earned/required points display.
  - Child competency cards with proficiency status icons (proficient, in-progress, to-do).
  - Rule type and outcome description info box (points-based and all-based rules).
  - "Submit prior learning evidence" button (configurable via admin setting `enableevidencesubmitbutton`).
  - Lazy loading via AJAX on first tab activation.
- New web service `local_dimensions_get_competency_rule_data` for retrieving competency rule data (children, points, required status).
- Admin setting to enable/disable the "Submit Evidence" button in the Rules tab.
- 22 language strings for the Rules tab (progress, outcomes, assessments, screen reader labels).

### Changed
- **Taxonomy-aware labels**: competency labels now adapt dynamically to the framework's taxonomy configuration (e.g. "domain", "skill", "indicator") instead of hardcoded "competencies".
  - New `resolveTaxonomy()` function reads the framework's taxonomy levels and maps them to the correct depth.
  - Language strings for rule outcomes, related competencies, and required warnings now use `{$a}` placeholders for taxonomy names.
  - 22 new taxonomy language strings (11 types × singular/plural) in English and Brazilian Portuguese.
- Tab and pane IDs are now unique per competency (`dims-tab-{type}-{id}`, `dims-tabpane-{type}-{id}`), fixing conflicts when multiple accordions are open simultaneously.
- Improved keyboard navigation: tabs now support arrow-key cycling, `Home`/`End` keys, and proper `tabindex` management following WAI-ARIA Tabs pattern.
- Tab panes now include `aria-labelledby` attributes linking back to their corresponding tab buttons.

## [1.0] - 2026-03-08

### Added
- Two display modes for learning plans: **Competency tracker** (course card grid) and **Full plan overview** (expandable accordion).
- Custom fields for competencies and learning plan templates: card image, background image, background colour, text colour, tags, display mode, and custom SCSS.
- Auto-provisioning of custom fields on first admin access when core competencies are enabled.
- Real-time course section progress calculation with recursive subsection support (Flexsections, `mod_subsection`).
- Competency completion rules display (Rules tab) in Full plan overview, showing rule type, rule outcome, sub-competency progress, proficiency status, and required flags.
- Evidence cards with detail modals in accordion panels.
- Comments section with reply functionality in accordion panels.
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
- Seven AJAX web services: course progress, competency courses, user competency summary, comments (read/write), FontAwesome icons, and competency rule data.
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