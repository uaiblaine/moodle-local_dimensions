moodle-local_dimensions
=======================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-local_dimensions/actions/workflows/moodle-plugin-ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-local_dimensions/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Amain)

See your learning path in a new dimension.

A Moodle local plugin that extends the core competency system with custom fields support, course section progress tracking, and a visual learning plan interface. This plugin enables users to view their learning plan competencies with detailed progress information for each linked course.


Requirements
------------

This plugin requires Moodle 4.5+


Motivation for this plugin
--------------------------

Moodle's core competency system provides excellent competency management but lacks detailed progress visualization at the course section level. This plugin was created to:

1. Extend competencies with custom fields (e.g., custom card images for visual representation)
2. Provide real-time course section progress tracking for competency-linked courses
3. Offer a modern, accessible learning plan visualization page with hero header and progress indicators
4. Support subsection progress calculation for courses using Format Flexsections or similar


Installation
------------

Install the plugin like any other plugin to folder
/local/dimensions

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins


Usage & Settings
----------------

After installing the plugin, configure it through the Site Administration:

**Site administration → Plugins → Local plugins → Custom Fields**
- Configure custom fields for competencies (e.g., card image)

**Site administration → Plugins → Local plugins → Manage Competencies**
- Navigate and edit competencies with custom field values
- Browse competency frameworks and edit individual competencies

**Accessing the Learning Plan View:**
- Navigate to `/local/dimensions/view-plan.php?id={planid}&competencyid={competencyid}`
- This page displays all courses linked to a competency with real-time section progress

There are 2 admin pages available:

### Custom Fields Configuration
Path: Site administration → Plugins → Local plugins → Dimensions → Custom Fields

Allows you to create and manage custom fields for competencies, such as:
- Card images for visual representation in blocks
- Additional metadata for competencies

### Manage Competencies
Path: Site administration → Plugins → Local plugins → Dimensions → Manage Competencies

Provides a tree view of all competencies within a framework, with the ability to edit each competency including its custom field values.


Web Services
------------

This plugin provides the following web services:

### local_dimensions_get_course_progress
Calculates the progress of course sections including subsections.

**Parameters:**
- `courseid` (int): The course ID

**Returns:**
An array of section progress data including:
- Section name and URL
- Completion percentage
- Activity count
- Lock status
- Error messages (if any)


Capabilities
------------

This plugin introduces these additional capabilities:

### local/dimensions:view
Allows viewing the Dimensions learning plan pages.
By default, this capability is granted to managers, teachers, and authenticated users.


Scheduled Tasks
---------------

This plugin does not add any additional scheduled tasks.


How this plugin works
---------------------

### Custom Fields for Competencies
The plugin implements the Moodle custom fields API to extend competencies with additional metadata. This is achieved through a custom handler (`competency_handler`) that integrates with the customfield system.

### Course Progress Calculation
The `calculator` class provides real-time progress calculation:
1. Fetches all course sections and their completion-enabled activities
2. Recursively calculates progress for subsections (supporting flexsections format)
3. Checks enrollment and access status for locked content detection
4. Returns structured data for frontend display

### Learning Plan View
The `view-plan.php` page:
1. Receives a plan ID and competency ID as parameters
2. Fetches the competency data and linked courses
3. Renders a hero header with the competency name and description
4. Displays course cards in a responsive grid
5. Loads section progress via AJAX using the AMD module

### Frontend Integration
The plugin includes:
- Mustache templates for responsive layouts (hero header, course cards, progress indicators)
- AMD JavaScript module (`ui.js`) for dynamic progress loading and hero repositioning
- CSS styles for accessibility-compliant progress circles and visual hierarchy


Accessibility
-------------

This plugin follows WCAG guidelines with:
- Semantic HTML structure
- ARIA labels for screen readers
- Sufficient color contrast for progress indicators
- Keyboard navigation support


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is not published in the Moodle plugins repository.

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

This plugin uses the Moodle custom fields API which may store additional data for competencies. The custom field data is associated with the competency system, not with individual users.

The course progress calculation is performed in real-time and no personal data is stored beyond what is already handled by Moodle's core completion system.


Maintainers
-----------

The plugin is maintained by\
Anderson Blaine


Copyright
---------

The copyright of this plugin is held by\
Anderson Blaine

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
