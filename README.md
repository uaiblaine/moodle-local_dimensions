moodle-local_dimensions
=======================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-local_dimensions/actions/workflows/moodle-plugin-ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-local_dimensions/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Amain)

See your learning path in a new dimension.

A Moodle local plugin that extends the core competency system with custom fields, course section progress tracking, and a visual learning plan interface. It provides two display modes — **Competency tracker** and **Full plan overview** — with rich customisation options for colours, images, icons, tags, and SCSS.


Requirements
------------

- Moodle 4.5 or later (tested up to Moodle 5.2)
- Core competencies enabled (`core_competency`)


Motivation for this plugin
--------------------------

Moodle's core competency system provides excellent competency management but lacks detailed progress visualisation at the course section level. This plugin was created to:

1. Extend competencies and learning plan templates with custom fields (card images, background colours, tags, icons, SCSS styling)
2. Provide real-time course section progress tracking, including recursive subsection support (Flexsections)
3. Offer a modern, accessible learning plan visualisation with two distinct modes
4. Support enrolment-aware filtering and locked content detection


Installation
------------

Install the plugin like any other plugin to folder `/local/dimensions`.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.


Display modes
-------------

The plugin provides two ways to visualise a learning plan, selectable per template via a custom field:

### Competency tracker

Shows linked courses for a single competency as a responsive grid of course cards. Each card displays:
- Real-time section progress via circular SVG indicators
- Lock status with configurable icons and "Learn More" buttons
- Availability dates for future enrolments

### Full plan overview

Shows all competencies in the plan as an expandable accordion. Each accordion panel loads via AJAX and can display:
- Competency description
- Hierarchy path (framework → parent → competency)
- Related competencies (optionally clickable)
- Evidence cards with detail modals
- Comments section with reply functionality
- Linked course cards with section progress


Admin settings
--------------

All settings are under **Site administration → Competencies → Competency Dimensions settings**.

### General settings

| Setting | Description | Default |
|---|---|---|
| Enable "Return to plan" button | Floating FAB button to navigate back to the plan from course pages | Enabled |
| Button colour | Colour of the return button | `#667eea` |
| Image handling method | Built-in or external `customfield_picture` plugin | Built-in |
| Enable custom SCSS | Allow per-template and per-competency SCSS injection | Disabled |

### Competency tracker mode

| Setting | Description | Default |
|---|---|---|
| Percentage display mode | Fixed, on hover, or hidden | Hover |
| Locked card display mode | "Locked content" message or "Learn More" button | Locked content |
| "Learn More" button colour | Colour for the learn more button on locked cards | `#667eea` |
| Show availability date | Show the availability date on locked cards | Enabled |
| Locked card icon | Custom FontAwesome icon for locked cards (searchable picker) | Default lock |
| Enrolment filter | Show all courses, enrolled only, or active enrolments only | All |
| Single course redirect | Skip the competency tracker if the user has only one active enrolment | Disabled |

### Full plan overview mode

| Setting | Description | Default |
|---|---|---|
| Enrolment filter | Filter courses in accordion by enrolment status | All |
| Show competency description | Display the full description text | Enabled |
| Show competency path | Display the hierarchy path (framework → parent) | Disabled |
| Show related competencies | Display linked related competencies | Disabled |
| Link related competencies | Make related competency names clickable | Disabled |
| Show evidence | Display evidence cards with icons | Enabled |
| Show comments | Display comments section with reply functionality | Disabled |


Admin pages
-----------

The plugin provides four admin pages under **Site administration → Competencies → Competency Dimensions settings**:

| Page | Path | Capability required |
|---|---|---|
| Plugin settings | `settings.php` | `moodle/site:config` |
| Competency custom fields | `customfield.php` | `moodle/competency:competencymanage` |
| Manage competencies | `manage_competencies.php` | `moodle/competency:competencymanage` |
| Learning plan template custom fields | `customfield_template.php` | `moodle/competency:templatemanage` |
| Manage learning plan templates | `manage_templates.php` | `moodle/competency:templatemanage` |


Custom fields
-------------

The plugin automatically creates the following custom fields for both competencies and learning plan templates:

| Shortname | Type | Description |
|---|---|---|
| `customcard` | picture | Card image for visual representation |
| `custombgimage` | picture | Background image for the hero header |
| `custombgcolor` | text | Background colour in hex format |
| `customtextcolor` | text | Text colour in hex format |
| `tag1` | select | Year/period classification |
| `tag2` | select | Category/type classification |
| `customscss` | textarea | Custom SCSS code (when enabled) |
| `local_dimensions_displaymode` | select | Display mode (templates only) |

Fields are automatically provisioned on first admin login when competencies are enabled.


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

**Returns:** JSON-encoded competency summary data.

### local_dimensions_get_comments

Get comments for a user competency.

**Parameters:**
- `component` (string), `area` (string), `itemid` (int), `contextid` (int), `page` (int)

**Returns:** Paginated comment list with user names and profile images.

### local_dimensions_add_comment

Add a comment to a user competency.

**Parameters:**
- `component` (string), `area` (string), `itemid` (int), `contextid` (int), `content` (string)

**Returns:** The new comment data or an error message.

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

The plugin uses three application-level MUC caches to minimise database queries:

| Cache | Key | Purpose | TTL |
|---|---|---|---|
| `template_courses` | `template_id` | Stores valid course IDs per template | 1 hour |
| `template_scss` | `css_{templateid}` | Compiled CSS from template SCSS | Manual invalidation |
| `competency_scss` | `css_{competencyid}` | Compiled CSS from competency SCSS | Manual invalidation |

Compiled SCSS caches are automatically invalidated when the SCSS field is saved. The template courses cache uses static acceleration with a pool size of 50 entries.

Additionally:
- Custom field provisioning runs only once per admin session (session flag)
- Course section progress is calculated on demand via AJAX, not stored
- Accordion panels in full plan overview load lazily via AJAX (one request per panel)
- The icon picker service caps results at 3000 and uses efficient string matching


Event observers
---------------

| Event | Handler | Purpose |
|---|---|---|
| `competency_created` | `observer::competency_created` | Save custom field data when competencies are created via core forms |
| `competency_updated` | `observer::competency_updated` | Save custom field data when competencies are updated via core forms |


Hook callbacks
--------------

| Hook | Handler | Purpose |
|---|---|---|
| `core_form\hook\after_definition` | `form_extension::callback` | Injects custom fields into the core `tool_lp` competency form |
| `before_footer_html_generation` | `hook_callbacks::before_footer_html_generation` | Renders the "Return to plan" floating button and ensures custom fields exist |


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
2. Builds a parent-child section map for recursive subsection support (Flexsections, mod_subsection)
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
- **13 Mustache templates** for responsive layouts (hero header, course cards, accordion panels, evidence modals, etc.)
- **4 AMD JavaScript modules**: `accordion.js` (lazy-loaded accordion panels), `ui.js` (progress loading and hero positioning), `fontawesome_icon_selector.js` (AJAX icon picker), `scss_validation.js` (client-side SCSS validation)
- **CSS styles** with properly namespaced selectors (`.local-dimensions-*`, `.dims-*`, `#dimensions-*`)


Accessibility
-------------

This plugin follows WCAG guidelines with:
- Semantic HTML structure
- ARIA labels and roles for screen readers
- Sufficient colour contrast for progress indicators
- Keyboard navigation support
- SVG-based progress circles with `aria-label` for completion percentages


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme. It should also work with Boost child themes, including Moodle Core's Classic theme. FontAwesome icon support is enhanced when using Boost Union. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is not yet published in the Moodle plugins repository.

The latest development version can be found on Github:
https://github.com/uaiblaine/moodle-local_dimensions


Bug and problem reports / Support requests
------------------------------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/uaiblaine/moodle-local_dimensions/issues

We will do our best to solve your problems, but please note that due to limited resources we can't always provide per-case support.


Feature proposals
-----------------

Please issue feature proposals on Github:
https://github.com/uaiblaine/moodle-local_dimensions/issues

Please create pull requests on Github:
https://github.com/uaiblaine/moodle-local_dimensions/pulls


Moodle release support
----------------------

Due to limited resources, this plugin is only maintained for the most recent major release of Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to the LTS release. However, new features and improvements are not necessarily backported to the LTS release.

Apart from these maintained releases, previous versions of this plugin which work in legacy major releases of Moodle are still available as-is without any further updates in the Moodle Plugins repository.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.


Translating this plugin
-----------------------

This Moodle plugin is shipped with English and Brazilian Portuguese language packs. All translations into other languages must be managed through AMOS (https://lang.moodle.org) by what they will become part of Moodle's official language pack.


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


Privacy
-------

This plugin does not store any personal user data. Custom field data is associated with competencies and learning plan templates, not with individual users. Course progress calculation is performed in real-time and no personal data is stored beyond what is already handled by Moodle's core completion and comment systems.

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
