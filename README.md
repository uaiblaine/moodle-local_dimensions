moodle-local_dimensions
=======================

See your learning path in a new dimension.

A Moodle local plugin that extends the core competency system in both directions: for learners, two visual learning plan views — **Competency tracker** and **Full plan overview** — with real-time course progress and a visual identity per plan; for administrators, the **Competency hub**, a single screen that manages frameworks, competencies and learning plan templates end to end. All of it on top of Moodle's own data model — the plugin creates no database tables.


Requirements
------------

- Moodle 4.5 or later (tested up to Moodle 5.2)
- Core competencies enabled (`core_competency`)


Motivation for this plugin
--------------------------

Moodle's competency system is one of the most sophisticated learning-outcome management mechanisms available on a learning platform. But for all that potential to actually come through, it takes presentation layers and complementary resources that make its rules, relationships, and graphical progress representations clear.

This plugin was built to explore that gap, without changing Moodle's underlying data model. It started on the student side: whoever navigates a learning plan gained custom fields, a visual identity of its own, and clear graphical progress representations. Over time, the focus extended into management: the Competency Hub brings together dozens of screens and paths currently scattered across the admin area into a single place, built for fast, intuitive management. From there, naturally, the educator who runs all of this day to day became central to the design too, with a light management touch of its own, since native reports were extended, though that's a detail next to the rest.

Dimension, here, isn't a new angle: it's more room to see. This plugin creates no tables: it uses what Moodle already stores and already knows how to do. It avoids depending on non-core plugins whenever a native path exists, and prefers exploring that path in ways nobody has tried yet. The goal is to simplify the journey: clear steps, obvious actions, a flow that doesn't need a manual. Less plugin, more Moodle. It's not about adding a new piece. It's about revealing what was already there, just in a dimension no one had opened yet.

Installation
------------

Install the plugin like any other plugin to folder `/local/dimensions`.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.


Learner views
-------------

Two ways to visualize a learning plan, selectable per template via a custom field.

### Competency tracker

The linked courses of one competency as a responsive grid of course cards: real-time section progress (circular indicators, recursive subsection support), lock status with configurable icons and messages, availability dates for future enrolments, and tag-driven chip filters. An optional redirect skips the grid entirely when the learner has a single active enrolment.

### Full plan overview

All competencies of the plan as an expandable accordion, loaded lazily. Each panel can show the competency description, its taxonomy card and hierarchy path, related competencies, evidence cards with detail modals, linked course cards with progress, and a **Rules** tab that turns Moodle's competency completion rules — All/Points, outcome, earned progress, required children, proficiency status — into something learners actually understand at a glance.

A draggable **"Return to plan"** floating button brings learners back from course pages to the plan they came from.


Competency hub
--------------

The hub (`central.php`, under **Site administration → Competencies**) gathers the dozens of screens Moodle spreads across the admin area into a single surface — three tabs, everything in modals backed by AJAX web services, no page reloads:

- **Structures**: a lazy competency tree with search-and-reveal, drag-and-drop reorder and reparent, a native completion-rule editor, per-competency usage counters, and course/activity linking with rule outcomes.
- **Learning plans**: master–detail template management — create and edit in modals, full duplication (custom fields, embedded files and images included), a competency picker with framework browser, and the **Manage participants** modal: cohorts with background plan generation, individual users, cohort-role rules, and **bulk enrolment methods** — apply or remove cohort sync / cohort-restricted self enrolment across all courses linked to the plan's competencies, processed as background tasks with live per-course status.
- **Frameworks**: native create/edit with scale configuration, duplication, visibility toggle, reason-gated delete, and CSV import/export.

The hub remembers where you were — tab, context, selected framework or template and display toggles persist per user across sessions and devices. And it logs the administrative decisions core never does (cohort attach/detach, links, duplication, bulk enrolment actions…) as regular Moodle events in the standard log.

Two companion admin pages host core's custom-field definition UI for the competency and template areas.


Customisation
-------------

Competencies and templates gain auto-provisioned custom fields: card and hero background images (built-in file areas by default, or the external `customfield_picture` plugin), background and text colours, classification tags, a type label, the per-template display mode and — when enabled — per-template/per-competency **custom SCSS**, validated and compiled server-side and cached.

Admin settings (**Site administration → Competencies → Competency Dimensions settings**) cover the "Return to plan" button, image handling and SCSS, plus per-view display options: percentage and locked-card behaviour, enrolment filters, the single-course redirect, and which sections each accordion panel shows (description, taxonomy, path, related competencies, evidence, rules).


Under the hood
--------------

- **No own tables**: everything lives in core competency tables, `customfield_data`, file areas, user preferences and MUC caches.
- **Performance**: metadata and progress caches with defensive TTLs and event-driven invalidation; lazy AJAX loading throughout; session caches for navigation state.
- **Background work**: heavy actions — plan generation from cohorts, cohort-role sync, bulk enrolment methods — are queued as ad-hoc tasks with deduplication and Lock API serialisation, processed by Moodle's standard cron.
- **Frontend**: ESM AMD modules, zero YUI, Bootstrap 4 + 5 compatible, Mustache server-side rendering, namespaced CSS.


Accent-insensitive search
-------------------------

Competency, plan and course searches in the Competency hub are accent-insensitive ("lingua" also matches "língua"). On MySQL/MariaDB this works out of the box via the database collation. On **PostgreSQL** it uses the `unaccent` extension, which the plugin creates automatically (`CREATE EXTENSION IF NOT EXISTS unaccent`) during upgrade or on first search where the database user has permission — on supported PostgreSQL (13+) `unaccent` is a *trusted* extension the database owner may create without superuser rights.

If your instance is locked down and the plugin cannot create it, run this once as a database superuser and searches become accent-insensitive on PostgreSQL too (until then they degrade gracefully to accent-sensitive):

    CREATE EXTENSION unaccent;

Accessibility
-------------

This plugin follows WCAG guidelines with:
- Semantic HTML structure
- ARIA labels and roles for screen readers (including `aria-labelledby` on tab panels)
- Sufficient color contrast for progress indicators
- Full keyboard navigation for tabs following the WAI-ARIA Tabs pattern (arrow keys, Home/End, roving `tabindex`)
- SVG-based progress circles with `aria-label` for completion percentages
- Screen reader labels for competency proficiency status icons in the Rules tab


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme. It should also work with Boost child themes, including Moodle Core's Classic theme. FontAwesome icon support is enhanced when using Boost Union. However, we can't support any other theme than Boost.


Companion plugin
-----------------

**Dimensions Block** (`block_dimensions`) is a companion block plugin that displays competency and learning plan cards directly on the Moodle dashboard or any page where blocks are allowed. It provides quick-access cards for plans and competencies styled with the same custom fields managed by this plugin.

Repository: https://github.com/uaiblaine/moodle-block_dimensions


Plugin repositories
-------------------

This plugin is not yet published in the Moodle plugins repository.

The latest development version can be found on GitHub:
https://github.com/uaiblaine/moodle-local_dimensions


Bug and problem reports / Support requests
------------------------------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on GitHub:
https://github.com/uaiblaine/moodle-local_dimensions/issues

We will do our best to solve your problems, but please note that due to limited resources we can't always provide per-case support.


Feature proposals
-----------------

Please issue feature proposals on GitHub:
https://github.com/uaiblaine/moodle-local_dimensions/issues

Please create pull requests on GitHub:
https://github.com/uaiblaine/moodle-local_dimensions/pulls


Translating this plugin
-----------------------

This Moodle plugin is shipped with English and Brazilian Portuguese language packs. All translations into other languages must be managed through AMOS (https://lang.moodle.org) by what they will become part of Moodle's official language pack.


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with an RTL language, and it doesn't work as-is, you are free to send us a pull request on GitHub with modifications.


Privacy
-------

The plugin stores no personal data beyond two per-user preferences that remember the Competency hub's last-visited view and display choices. These are exported by the Privacy API on a data-subject request and removed on plugin uninstall. Custom field data is associated with competencies and learning plan templates, not with individual users. Course progress calculation is performed in real-time, and temporary session cache entries are used only to support navigation and rendering during active sessions.

The plugin implements the Moodle Privacy API as a preference-only provider (`core_privacy\local\request\user_preference_provider`).


Scheduled tasks
---------------

This plugin adds no scheduled (cron-registered) tasks. It queues **ad-hoc background tasks** on demand — plan generation from template cohorts, cohort-role synchronisation and bulk enrolment methods — which Moodle's standard cron processes.


Contributors
------------

- Anderson Blaine
- William Mano


Copyright
---------

The copyright of this plugin is held by\
Anderson Blaine

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
