moodle-local_dimensions
=======================

[![ci](https://github.com/uaiblaine/moodle-local_dimensions/actions/workflows/ci.yml/badge.svg?branch=MOODLE_405_STABLE)](https://github.com/uaiblaine/moodle-local_dimensions/actions/workflows/ci.yml?query=branch%3AMOODLE_405_STABLE)
[![ci](https://github.com/uaiblaine/moodle-local_dimensions/actions/workflows/ci.yml/badge.svg?branch=MOODLE_500_STABLE)](https://github.com/uaiblaine/moodle-local_dimensions/actions/workflows/ci.yml?query=branch%3AMOODLE_500_STABLE)
[![ci](https://github.com/uaiblaine/moodle-local_dimensions/actions/workflows/ci.yml/badge.svg?branch=MOODLE_501_STABLE)](https://github.com/uaiblaine/moodle-local_dimensions/actions/workflows/ci.yml?query=branch%3AMOODLE_501_STABLE)
[![ci](https://github.com/uaiblaine/moodle-local_dimensions/actions/workflows/ci.yml/badge.svg?branch=MOODLE_502_STABLE)](https://github.com/uaiblaine/moodle-local_dimensions/actions/workflows/ci.yml?query=branch%3AMOODLE_502_STABLE)

See your learning path in a new dimension.

A Moodle local plugin that extends the core competency system with custom fields, course section progress tracking, and a visual learning plan interface. It provides two display modes — **Competency tracker** and **Full plan overview** — with rich customization options for colors, images, icons, tags, and SCSS.


Requirements
------------

- Moodle 4.5 or later (tested up to Moodle 5.2)
- Core competencies enabled (`core_competency`)


Motivation for this plugin
--------------------------

Moodle’s competency framework is one of the most sophisticated learning-outcome management systems available in a learning platform. However, for the system to reach its full potential, additional presentation layers and complementary resources are needed to clearly express its rules, relationships, and graphical representations of progress.

This plugin was designed to explore that perspective: enhancing the way competencies and learning plans are visualized and interpreted without changing Moodle’s underlying model. By enriching the presentation layer and providing clearer structural feedback, the goal is to make competency-based learning easier to understand and apply in practice.

Specifically, the plugin aims to:
	1.	Extend competencies and learning plan templates with custom fields (card images, background colors, tags, icons, and SCSS styling)
	2.	Provide real-time course section progress tracking, including recursive subsection support
	3.	Offer a modern and accessible learning plan visualization with two distinct display modes
	4.	Support enrolment-aware filtering and locked content detection

While the competency system has been part of Moodle for many years, its conceptual depth often makes it underutilized in practice. By improving the visual and structural representation of competency relationships and progress, this project seeks to highlight the expressive power already present in the framework and make it more approachable for everyday instructional design.

Installation
------------

Install the plugin like any other plugin to folder `/local/dimensions`.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.


Display modes
-------------

The plugin provides two ways to visualize a learning plan, selectable per template via a custom field:

### Competency tracker

Shows linked courses for a single competency as a responsive grid of course cards. Each card displays:
- Real-time section progress via circular SVG indicators
- Lock status with configurable icons and "Learn More" buttons
- Availability dates for future enrolments

### Full plan overview

Shows all competencies in the plan as an expandable accordion. Each accordion panel loads via AJAX and can display:
- Competency description
- Main taxonomy card for the current competency (optional)
- Hierarchy path (framework → parent → competency)
- Related dimensions (optionally clickable)
- Competency completion rules (rule type, outcome text, sub-competency progress, required-item alerts, and proficiency status)
- Evidence cards with detail modals
- Linked course cards with section progress

Taxonomy metadata is resolved on the backend and included in the AJAX payload, so the frontend renders the current competency taxonomy and rule outcome text without inferring framework labels client-side.

#### Competency completion rules

When a competency has a completion rule configured in Moodle's core competency framework, a **Rules** tab is displayed in the accordion panel. It shows:

- **Rule type**: "All" (all linked sub-competencies must be rated as proficient) or "Points" (sum of points from sub-competencies must reach a threshold)
- **Rule outcome**: What happens when the rule is met — evidence is attached automatically, the competency is marked as complete, or the competency is recommended for review. Outcome descriptions are returned already localized by the backend
- **Progress indicator**: A progress bar showing earned points (or completed count) versus the total required
- **Required-item alert**: Highlights when the minimum score was reached but mandatory child items are still pending
- **Sub-competency list**: Each child competency with its current rating, proficiency status (proficient, graded but not proficient, or not yet evaluated), and whether it is marked as required
- **Local filters**: Quick toggle between all child items and only required ones when the rule includes mandatory children
- **Submit evidence**: Optional button (configurable in admin settings) linking to the prior learning evidence page


Admin settings
--------------

All settings are under **Site administration → Competencies → Competency Dimensions settings**.

### General settings

| Setting                        | Description                                                        | Default   |
|--------------------------------|--------------------------------------------------------------------|-----------|
| Enable "Return to plan" button | Floating FAB button to navigate back to the plan from course pages | Enabled   |
| Button colour                  | Colour of the return button                                        | `#667eea` |
| Image handling method          | Built-in or external `customfield_picture` plugin                  | Built-in  |
| Enable custom SCSS             | Allow per-template and per-competency SCSS injection               | Disabled  |

### Competency tracker mode

| Setting                    | Description                                                           | Default        |
|----------------------------|-----------------------------------------------------------------------|----------------|
| Percentage display mode    | Fixed, on hover, or hidden                                            | Hover          |
| Locked card display mode   | "Locked content" message or "Learn More" button                       | Locked content |
| "Learn More" button colour | Colour for the learn more button on locked cards                      | `#667eea`      |
| Show availability date     | Show the availability date on locked cards                            | Enabled        |
| Animate locked border      | Enable marching-ants animation on the locked-card dashed border       | Disabled       |
| Locked card icon           | Custom FontAwesome icon for locked cards (searchable picker)          | Default lock   |
| Enrolment filter           | Show all courses, enrolled only, or active enrolments only            | All            |
| Single course redirect     | Skip the competency tracker if the user has only one active enrolment | Disabled       |

### Full plan overview mode

| Setting                         | Description                                                                        | Default  |
|---------------------------------|------------------------------------------------------------------------------------|----------|
| Enrolment filter                | Filter courses in accordion by enrolment status                                    | All      |
| Show competency description     | Display the full description text                                                  | Enabled  |
| Show main taxonomy card         | Display the current competency taxonomy as a dedicated card in the Description tab | Disabled |
| Show competency path            | Display the hierarchy path (framework → parent)                                    | Disabled |
| Show related competencies       | Display linked related competencies                                                | Disabled |
| Link related competencies       | Make related competency names clickable                                            | Disabled |
| Show evidence                   | Display evidence cards with icons                                                  | Enabled  |
| Enable "Submit Evidence" button | Show a button in the Rules tab linking to the prior learning evidence page         | Disabled |


Admin pages
-----------

The plugin provides five admin pages under **Site administration → Competencies → Competency Dimensions settings**:

| Page                                 | Path                       | Capability required                  |
|--------------------------------------|----------------------------|--------------------------------------|
| Plugin settings                      | `settings.php`             | `moodle/site:config`                 |
| Competency custom fields             | `customfield.php`          | `moodle/competency:competencymanage` |
| Manage competencies                  | `manage_competencies.php`  | `moodle/competency:competencymanage` |
| Learning plan template custom fields | `customfield_template.php` | `moodle/competency:templatemanage`   |
| Manage learning plan templates       | `manage_templates.php`     | `moodle/competency:templatemanage`   |


Custom fields
-------------

The plugin auto-provisions custom fields on first admin access. Non-image fields are created for both competencies and learning plan templates; image fields are created only when the external `customfield_picture` handler is active.

| Shortname                          | Type     | Description                                                                   |
|------------------------------------|----------|-------------------------------------------------------------------------------|
| `local_dimensions_customcard`      | picture  | Card image for visual representation (external image handler mode only)       |
| `local_dimensions_custombgimage`   | picture  | Background image for the hero header (external image handler mode only)       |
| `local_dimensions_custombgcolor`   | text     | Background colour in hex format                                               |
| `local_dimensions_customtextcolor` | text     | Text colour in hex format                                                     |
| `local_dimensions_tag1`            | select   | Year/period classification (example only; values can be adjusted as needed)   |
| `local_dimensions_tag2`            | select   | Category/type classification (example only; values can be adjusted as needed) |
| `local_dimensions_type`            | select   | Competency/category type label                                                |
| `local_dimensions_customscss`      | textarea | Custom SCSS code (when enabled)                                               |
| `local_dimensions_displaymode`     | select   | Display mode (templates only)                                                 |

In built-in image mode, card/background images are stored in plugin file areas (`competency_card`, `template_card`, `competency_bg`, `template_bg`) instead of custom field records.


Web services
------------

This plugin provides the following AJAX web services:

### local_dimensions_get_course_progress

Calculates the progress of course sections including recursive subsections.

**Parameters:**
- `courseids` (int[]): List of course IDs

**Returns:** Array of section progress data (name, percentage, URL, lock status, completion state).

### local_dimensions_get_competency_courses

Get courses linked to a competency with the enrolment filter applied.

**Parameters:**
- `competencyid` (int): The competency ID

**Returns:** Array of courses with image, name, progress percentage, and visibility.

### local_dimensions_get_user_competency_summary_in_plan

Wrapper for `tool_lp_data_for_user_competency_summary_in_plan` that avoids context issues with theme string loading during AJAX calls.

**Parameters:**
- `competencyid` (int): The competency ID
- `planid` (int): The plan ID

**Returns:** JSON-encoded competency summary data enriched with taxonomy metadata for the current competency (`taxonomy.current`, `taxonomy.children`, `taxonomy.bylevel`) and a ready-to-render `taxonomyterm`.

### local_dimensions_get_competency_rule_data

Get competency completion rule data including children, points, required status, and proficiency for the Rules tab.

**Parameters:**
- `competencyid` (int): The parent competency ID
- `planid` (int): The learning plan ID

**Returns:** JSON object with rule type, localized outcome text, total required, earned points, mandatory counts, missing-mandatory alert state, child competencies (with grade name, proficiency, points, and required status), taxonomy metadata, and whether the evidence submission button is enabled.

### local_dimensions_get_fontawesome_icons

Get FontAwesome icons matching a search query for the admin icon picker. Supports Boost Union's extended icon map if available, with a fallback to core icons and SCSS parsing.

**Parameters:**
- `query` (string): Search query

**Returns:** Matching icons with CSS classes and sources, capped at 3000 results.


Capabilities
------------

### local/dimensions:view

Allows viewing the Dimensions learning plan pages and accessing AJAX web services.

**Default archetypes:** Manager, Teacher, Authenticated User.


Performance and caching
-----------------------

The plugin uses application and session-level MUC caches to minimize database queries and avoid repeated computation:

| Cache                 | Mode        | Key                  | Purpose                                                                           | TTL                 |
|-----------------------|-------------|----------------------|-----------------------------------------------------------------------------------|---------------------|
| `template_courses`    | application | `template_id`        | Stores valid course IDs per template                                              | 1 hour              |
| `template_metadata`   | application | `templateid`         | Caches template metadata for card rendering (type, tags, colours, card image URL) | 30 minutes          |
| `competency_metadata` | application | `competencyid`       | Caches competency metadata for card rendering (tags, colours, card image URL)     | 30 minutes          |
| `template_scss`       | application | `css_{templateid}`   | Compiled CSS from template SCSS                                                   | Manual invalidation |
| `competency_scss`     | application | `css_{competencyid}` | Compiled CSS from competency SCSS                                                 | Manual invalidation |
| `returncontext`       | session     | `returncontext`      | Stores return URL and allowed course IDs for the floating "Return to plan" button | Session lifetime    |
| `plan_trail`          | session     | `planid_userid`      | Stores lightweight plan competency trail data for summary/status rendering        | 5 minutes           |

Compiled SCSS caches are automatically invalidated when the SCSS field is saved. Metadata caches are invalidated when related metadata/images change. The template courses cache uses static acceleration with a pool size of 50 entries.

Additionally:
- Custom field provisioning runs only once per admin session (session flag)
- Course section progress is calculated on demand via AJAX, not stored
- Accordion panels in full plan overview load lazily via AJAX (one request per panel)
- The icon picker service caps results at 3000 and uses efficient string matching


Event observers
---------------

| Event                                                 | Handler                                   | Purpose                                                                       |
|-------------------------------------------------------|-------------------------------------------|-------------------------------------------------------------------------------|
| `core\event\competency_created`                       | `observer::competency_created`            | Save custom field data when competencies are created via core forms           |
| `core\event\competency_updated`                       | `observer::competency_updated`            | Save custom field data when competencies are updated via core forms           |
| `core\event\competency_user_competency_rated`         | `observer::user_competency_rated`         | Invalidate the affected user's plan trail session cache                       |
| `core\event\competency_user_competency_rated_in_plan` | `observer::user_competency_rated_in_plan` | Invalidate the specific plan trail cache entry for the affected user          |
| `core\event\competency_evidence_created`              | `observer::evidence_created`              | Invalidate the affected user's plan trail cache after new evidence is created |


Hook callbacks
--------------

| Hook                              | Handler                                         | Purpose                                                                      |
|-----------------------------------|-------------------------------------------------|------------------------------------------------------------------------------|

| `before_footer_html_generation`   | `hook_callbacks::before_footer_html_generation` | Renders the "Return to plan" floating button and ensures custom fields exist |


How this plugin works
---------------------

### Custom fields for competencies and templates

The plugin implements the Moodle custom fields API through two handlers (`competency_handler` and `lp_handler`) that integrate with the `core_customfield` system. Custom fields are auto-provisioned via the `helper` class and can be managed through dedicated admin pages.

### Image handling

Two modes are available:
- **Built-in** (default): Manages images via `picture_manager` using Moodle's file storage API with dedicated file areas (`competency_card`, `template_card`, `competency_bg`, `template_bg`)
- **External plugin**: Delegates to the `customfield_picture` plugin if installed

### Course progress calculation

The `calculator` class provides real-time progress calculation:
1. Fetches all course sections and their completion-enabled activities
2. Builds a parent-child section map for recursive subsection support (Flex sections, mod_subsection)
3. Checks enrolment, access restrictions, and section visibility
4. Detects locked content and future enrolment start dates
5. Returns structured data for frontend display

### Custom SCSS injection

When enabled, per-template and per-competency SCSS code is:
1. Validated client-side (brace/parenthesis matching, punctuation checks)
2. Compiled server-side using Moodle's SCSS compiler
3. Cached in MUC with manual invalidation on save
4. Injected as inline `<style>` tags on the plan view pages

### Frontend architecture

The plugin includes:
- **14 Mustache templates** for responsive layouts (hero header, course cards, accordion panels, evidence modals, settings widgets, etc.)
- **7 AMD JavaScript modules**: `accordion.js`, `ui.js`, `manage_competencies.js`, `return_button.js`, `fontawesome_icon_selector.js`, `setting_iconpicker.js`, and `scss_validation.js`
- **Plugin icon assets** under `pix/status` and `pix/taxonomy` for hero badges, locked cards, rule states, and taxonomy cards
- **CSS styles** with properly namespaced selectors (`.local-dimensions-*`, `.dims-*`, `#dimensions-*`)


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


Moodle release support
----------------------

This plugin uses a **branch-per-version** strategy. Each Moodle major version has its own stable branch that receives bug fixes and security patches independently. New features are committed to `main` first and backported selectively.

| Branch              | Moodle version     | PHP versions | Status                                      |
|---------------------|--------------------|--------------|---------------------------------------------|
| `MOODLE_405_STABLE` | 4.5 (LTS)          | 8.1 – 8.3    | Maintained – bug fixes and security patches |
| `MOODLE_500_STABLE` | 5.0                | 8.2 – 8.3    | Maintained – bug fixes                      |
| `MOODLE_501_STABLE` | 5.1                | 8.2 – 8.4    | Maintained – active                         |
| `MOODLE_502_STABLE` | 5.2                | 8.2 – 8.4    | Maintained – active (latest)                |
| `main`              | Development (next) | 8.3 – 8.4    | Development branch – not for production use |

**Tag format:** `v{MAJOR}.{MINOR}-r{RELEASE}` per stable branch, with independent release counters (e.g. `v4.5-r1` on `MOODLE_405_STABLE`, `v5.1-r3` on `MOODLE_501_STABLE`).

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on GitHub.


Translating this plugin
-----------------------

This Moodle plugin is shipped with English and Brazilian Portuguese language packs. All translations into other languages must be managed through AMOS (https://lang.moodle.org) by what they will become part of Moodle's official language pack.


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with an RTL language, and it doesn't work as-is, you are free to send us a pull request on GitHub with modifications.


Privacy
-------

This plugin does not store persistent personal profile data. Custom field data is associated with competencies and learning plan templates, not with individual users. Course progress calculation is performed in real-time, and temporary session cache entries (`returncontext`, `plan_trail`) are used only to support navigation and rendering during active sessions.

The plugin implements the Moodle Privacy API (`null_provider`).


Scheduled tasks
---------------

This plugin does not add any scheduled tasks.


Contributors
------------

- Anderson Blaine
- William Mano


Copyright
---------

The copyright of this plugin is held by\
Anderson Blaine

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
