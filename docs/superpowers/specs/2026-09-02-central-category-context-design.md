# Competency hub in course-category context

Design record for opening `central.php` to course-category managers, dated 2026-09-02.
The full gap analysis (50 findings, 22 adversarially confirmed, one empirical measurement on
5.2) lives outside the repository; this file keeps what the code needs to stay coherent.

## The problem

A manager holding `moodle/competency:competencymanage` or `templatemanage` in ONE course
category could not open the hub at all. Measured on 5.2 with such a manager: `central.php`
returned HTTP 500 "Access denied" from `admin_externalpage_setup()`, even with
`?contexttype=coursecat&categoryid=X`, while core's `tool_lp` category pages for the same
category answered 200. Two independent layers caused it: the plugin registered its whole admin
subtree inside `if ($hassiteconfig …)`, and `admin_externalpage_setup()` checks the page's
capability at the system context. Below the entry, the three dynamic tabs also gated
`is_available()` on the system context, and the hand-built tab strip ignored that answer
(`enabled => true`, no fallback), so a saved Plans tab would have thrown on the whole page.

Everything else was already contextual: `helper::resolve_central_context()`, the tab exports,
the dynamic forms, and 31 of the 45 web services derive their context from the object.

## Decisions (Anderson Blaine, 2026-09-02)

1. **Who sees the category menu entry:** managers only — `competency_framework::can_manage_context()`
   OR `template::can_manage_context()` at the category. Reading (`competencyview`) is an
   authenticated-user default at every category, so a read gate would put a management surface
   in every student's "More" menu. The PAGE still admits readers (like tool_lp), rendering
   read-only.
2. **Menu label:** the existing `central` string.
3. **Persisted context:** an explicit entry wins for the visit and is never remembered. A locked
   visit writes back the context that was stored before it, so the next Site-administration
   visit reopens where it was.
4. **Category deletion:** ideally BLOCKED when the category holds competencies that cannot be
   removed, mirroring core's refusal to delete a competency in use. Parked as a future
   improvement; needs its own investigation and tests (`pre_course_category_delete` callback).
5. **`local_dimensions_pluginfile`:** must gain an owning-object read check. Storage stays at the
   system context (moving files buys nothing: the callback decides access, not the storage
   context). Rule to implement: a visible framework/template picture is served to any logged-in
   user, as core serves course images; a hidden one only to managers of its context and to
   users with a plan on it, so learner views keep working.
6. **Listing scope:** `children` (the category plus its descendants, as tool_lp's category
   pages list) strictly on the locked category entry (`pagecontextid`). The site entry keeps
   `self` everywhere — in System mode and for a category picked in the bar — so the scope is a
   property of the entry, not of the resolved context, and the System view never lists other
   contexts' objects. The locked bar's headline count follows the same rule.
7. **Custom fields:** defining fields stays site-wide (the two definition pages keep
   `$hassiteconfig`); FILLING them on competencies and templates must work in category context,
   which means `competency_handler::can_edit()` resolves at the framework's context (the
   `lp_handler` fix, ported).

## The entry contract

- `central.php` (no parameters): the Site-administration entry. `admin_externalpage_setup()`,
  capability `moodle/competency:competencymanage` at the system context, remembered context,
  free context switch.
- `central.php?pagecontextid=<course category context id>`: the category entry, linked from
  the category's "More" menu by `local_dimensions_extend_navigation_category_settings()`.
  Follows tool_lp's sequence — `require_login(null, false)`, `api::require_enabled()`,
  `helper::can_read_competency_context()` or `required_capability_exception`, `set_context`,
  layout `admin`, `core_course_category::page_setup()`, own node made active — and never
  touches `admin_externalpage_setup()`. The page is LOCKED to that category: the context bar
  shows the category name instead of the switch and the picker, the System button appears only
  for viewers who may read something at the site, and the hidden-categories toggle is dropped.
- `pagecontextid` naming the system context behaves like the bare entry; any other context
  level is `invalidcontext`.
- Links that must land a category manager back on the hub go through
  `helper::hub_page_url($context, $params)`, which carries `pagecontextid` for a category.

## Availability

Each tab's `is_available()` resolves the pane's own `contexttype`/`categoryid` through
`helper::resolve_central_context()` and asks `can_read_context()` there. The resolver downgrades
an unreadable category to the system context, so the answer for a category-scoped viewer is a
refusal, never a system listing. `central.php` feeds the strip's `enabled` flag from those
answers and opens the requested tab only when it is available, else the first available one
(`helper::pick_available_tab()`); with none available it throws.

## Stages

1. Done: settings.php registration, tab availability, tab strip + fallback, tests.
2. Done: category navigation node, dual entry, locked context bar, pinned preference,
   `hub_page_url()` for the forms, AMD rebuild.
3. Done: `competency_handler::can_edit()` at the framework context (with the saving latch and
   the form's context hint, added to `lp_handler` too); `competency_usage` without core's
   system-context template check; participants search filtered by `planmanage` on the user
   context and per-row gating of plan actions; the cohorts escape link at the resolved context;
   the eight read web services validating the framework's or competency's context rather than
   system + `competencyview`.
4. Done: `children` listing scope on the locked entry only, carried as a `locked` pane
   argument, with the bar's headline count covering the subtree
   (`helper::count_frameworks_in_subtree()`); category names rebuilt unescaped for the picker
   and the locked label; `picture_manager::can_view()` applied by `local_dimensions_pluginfile`
   (decision 5 as stated above); README, CHANGELOG and CLAUDE.md.
5. Done: `tests/generator/lib.php` + `behat_local_dimensions_generator` create frameworks and
   templates in a course category by idnumber (core's generator hardcodes the system context);
   `tests/behat/central_category.feature` opens the hub from the category's "More" menu as a
   category manager and checks the lock, the listing and the Learning plans tab. The refusal of
   the bare URL stays in PHPUnit: Behat fails any step that lands on an exception page. Gate:
   `mdl ci moodle-local_dimensions --matrix --behat` (the 4.05 leg is where the Bootstrap 4
   More menu is exercised).

Decision 4, done the same day: `classes/local/category_lifecycle.php` behind four lib.php
callbacks. `get_course_category_contents` lists the category's frameworks and templates and
how many are in use on core's deletion form; `pre_course_category_delete` refuses "delete all"
while a competency is linked to a course, activity, template or plan or a template has plans
(mirroring core's refusal to delete a competency in use) and otherwise deletes them through the
competency API; `can_course_category_delete_move` offers "move contents" only to someone who
may manage the objects at the destination; `pre_course_category_delete_move` re-homes them
there with one UPDATE per table, as core's `cohort_delete_category()` moves cohorts (the
persistents refuse a context change by validation; this is the one moment it is the point). Only the category's own context is handled per call: core recurses
into children for "delete all" and moves them whole for "move contents".

## The category picker at scale

The bar used to enumerate every category the viewer could see on every render: a context
instantiation and up to four capability checks per category, a nested name built per
category, and one `<option>` per category for the autocomplete to enhance. The owner runs
sites with thousands of categories, so the picker became a search: `helper::central_category_search()`
behind `local_dimensions_search_categories` (one SQL name match, 25 hits, accent-insensitive
where the site has it, visibility per hit through core, competency readability per hit only
for a viewer who cannot already read at the site), and the server renders only the selected
category (`helper::central_category_option()`). Measured on m501 with 2,003 categories
(2026-09-02): first page 77 ms cold and 2 ms warm, a name search 3 to 10 ms, the selected
option 0 ms, the whole bar export 96 ms cold — against 51 ms for core's own
`make_categories_list()` alone, which the old path called and then walked.
