# Changelog

All notable changes to the **local_dimensions** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed

Decided by the owner after the comment audit's findings were re-checked against the current code:

- The status icons on the competency tracker's course cards (completed, locked, not started, no
  completion tracking) and the grade badge icons in the plan overview now take their colours from
  the theme and follow dark mode and Windows high-contrast mode; in light mode the completed check
  now uses the theme's success colour.
- The learning plan template settings now show 'Locked card display mode' and 'Show availability
  date' in 'Full plan overview' mode as well, because the plan overview uses them.
- Plan accordion: when staff open a learner's plan, the course cards now show the learner's courses,
  progress, activity completion, activity restrictions and card shape rather than the reviewer's
  own; what clicking a card does (open, enrol, pending, locked and its date) is still the
  reviewer's.
- Competency tracker: when staff open it from a learner's plan, the course list, the course cards'
  progress, completion, section restrictions and card shape, and the completed-courses count now
  describe the learner rather than the reviewer; whether a card opens (and its lock, date, enrol and
  pending state) is still the reviewer's, and a reviewer enrolled in a course without a student role
  is no longer shown it as locked. A reviewer who may read a draft plan but not the learner's
  competencies is refused the tracker, as on Moodle's own competency page for a plan.
- Learning plan CSV import: a competency structure named by ID number is now resolved only among the
  structures offered in the target category (its parent and child contexts), so a structure in a
  sibling category is reported as missing instead of being linked.

### Fixed

The findings the 2026-09-23 comment audit reported, re-checked on 2026-09-25 against the code other sessions had
changed since, and fixed where they still held.

#### Learner pages

- The competency detail's result strip now shows the most recent rule completion instead of the
  oldest one.
- Prior-learning evidence is no longer labelled a file attachment because the word 'file' appears in
  the name the learner gave it.
- Favourite competencies are trimmed to what a Moodle 4.5 user preference can hold (other plans'
  oldest favourites go first), instead of every later star silently failing to save.
- Course cards on the learner pages unlock for learners enrolled under any student-archetype role,
  not only a role whose shortname is 'student'.
- The Rules tab's points unit comes from the language pack instead of a hardcoded English 'pts'.
- The progress bar of a rule that counts completed items now announces items, not points, to screen
  readers.
- The competency tracker's course-card load error, retry button and loading text are never shown in
  English on a non-English site.
- The locked course card's opening date follows the language pack's short date format instead of a
  fixed day/month/year order.
- The plan overview now shows core's error when the plan's competencies cannot be read, instead of
  an empty plan.
- Course chip filters on the competency tracker now show values from select-type course custom
  fields (their labels were empty, and every course field raised a PHP warning), and all courses'
  values are read in one query.
- Scale ratings, tag sublines and chip filter values on the learner pages are filtered to the
  learner's language (multilang) and stripped of markup.

#### Competency hub

- Competency hub, Structure tab: closing 'Courses & activities' now updates the competency's
  linked-course count with the true total, not only the courses loaded so far.
- Competency hub, 'Browse frameworks' and 'Related competencies' pickers: a search or framework page
  that answers after the filter or framework changed no longer adds old competencies, or a stray
  empty-state message, to the list.
- Competency hub, Enrolment methods tab: changing the method, cohort, category, hidden-course toggle
  or search while a request is in flight no longer shows one method's status under the other or
  lists competency groups twice.
- Competency hub: script errors and broken server responses now open the error dialogue instead of a
  misleading 'Connection lost' notice.
- Competency hub, Structure and Learning plans tabs: a pane-divider drag that the browser interrupts
  (for example a touch turned into a scroll) now ends cleanly instead of resizing on later hover.
- Competency hub, competency rule dialogue: negative or fractional points are refused with the
  inline message instead of failing when saved.
- Competency hub: the highlight on a changed row now uses the theme's warning tint, so it follows
  the site palette and dark mode.
- The icon picker now caches the Font Awesome icon map it builds from core when Boost Union is not
  installed. Each search no longer rebuilds it. The cache is listed as 'Font Awesome icon map for
  the icon picker'.
- The framework export dialog now announces 'Exporting...' to screen readers on every export, not
  only the first one in the dialog.
- The points-rule alert now names every condition that makes a points configuration invalid: points
  must be whole numbers of zero or more, the required points must be at least 1, and the total
  available points must be at least the required points.
- The cache administration page no longer calls the plan trail cache a session cache.

#### Web services and import

- Rules tab: on a completed learning plan, each rule child the plan held now shows the rating
  archived when the plan was completed, instead of a live rating given afterwards.
- Linking a competency to a course or an activity that is already linked no longer logs a second
  'link added' event.
- The rating scale's 'About this scale' description is now formatted once, in the competency's
  context, so text filters no longer run twice and their output is no longer stripped.
- Enrolment methods tab: a negative offset or a page size below 1 now returns the first page at the
  default size, instead of the list's tail or nothing.
- Competency browser service: a page size below 1 now returns the declared default of 25 (it
  returned 50), and the query parameter states its 2-character search threshold.
- Participants grid: a learner holding only 'Manage own learning plans' can now unlink and delete
  their own template-based plan, as core and the grid's own manage flag already allowed.
- Detaching a cohort from a learning plan template now goes through core and is refused while
  competencies are disabled on the site.
- Template cohort roles listing now reads its cohorts, role holders and sync counts in a fixed
  number of queries, however many rows there are.
- A competency's activity links are now read for the requested course only, not for every course on
  the site.
- Learning plan import preview no longer fails outright when a missing structure's ID number or name
  in the file contains markup.
- Course progress: an error while reading one course now becomes that course's error card, instead
  of failing the tracker response for every course.
- Learning plan import preview: a competency matched by ID number inside a structure found only by
  its name is now badged as a fallback match ('Matched by structure name'), not as an exact match.

#### Custom fields and templates

- Opening a learning plan no longer creates missing template custom fields outside the provisioning
  lock. A missing field now falls back to the site-wide setting until provisioning restores it.
- Custom-field provisioning no longer fails on Moodle 5.1+ when every plugin field category has been
  deleted and a core shared category is enabled for the area. The fields now go into a new plugin
  category.
- In the Competency hub structure tree, type and tag labels no longer shift or disappear when a
  select field's option list contains a blank line.
- The audit events of a hub modal save now report 'isnew' correctly: editing a template or a
  competency logs false and creating one logs true, in both the values event and the image event.
- Copying plugin data onto a duplicated template now deletes the files embedded in the custom-field
  rows it replaces, where they used to be left orphaned.
- A blank line in a tag or type field's option list no longer shifts or blanks the label shown on
  templates and competencies, and a template setting whose stored option no longer exists now reads
  as inherit, which is how the settings form shows it.

#### Caches, events and tasks

- Plan cards: a completed plan lists the competencies it was completed with, as core's plan page
  does, instead of the template's current list.
- Plan cards: a learner's competency trail updates as soon as a teacher rates them or evidence
  arrives. The plan_trail cache is now shared by all users, so invalidating it reaches the learner's
  entry. Before, the old trail could stay for up to 5 minutes.
- Competency and template observers: a POST without a sesskey (for example a web service call) is
  now ignored quietly. It used to throw inside the observer and skip the cache invalidation.
- Template cohort sync: the background plan generation now finishes and logs the reason when the
  template is hidden, competencies are disabled, the task user can no longer view the template, or
  the cohort is hidden from that user or deleted. Before, it failed and was retried.
- Course category deletion: "Delete all" is not offered while a framework or template in the
  category or any subcategory is in use. Before, an in-use subcategory let the parent's frameworks
  and templates be deleted before the deletion stopped half way.
- Framework modal: an ID number already used by another framework anywhere on the site is reported
  on the field, not as an error on save.
- Course log restore now maps the competency, course, course module, role and enrolment instance ids
  of the hub's course link, module link and enrolment method events, and no longer prints a
  developer warning.
- Developer: removed the unused lib.php wrappers local_dimensions_set_return_context(),
  local_dimensions_set_return_context_for_course() and
  local_dimensions_get_return_context_for_course(); call \local_dimensions\helper directly.

#### Accessibility and styles

- the custom SCSS field in the Competency hub's competency and template dialogues now hides its
  redundant text-format selector and shows its intended dark code-editor styling. Both rules
  targeted an outdated field name and never applied.
- screen readers now announce the wait while a structure (competency framework) is exported from the
  Competency hub.
- the per-competency points and required inputs and the total-required input in the competency rule
  dialogue now have accessible names.
- on touch devices, dragging the pane divider on the Competency hub's Structures and Learning plans
  tabs no longer turns into a page scroll.
- the course card header in the competency tracker uses the Bootstrap 5 bold utility (polyfilled on
  Moodle 4.5), not a Bootstrap 4 name that Moodle 5.x deprecates.

### Security

- **The competency tracker, the Rules tab data and the accordion's course cards answer only for a
  competency the plan reaches.** `view-competency.php` rendered any competency by id to anyone who
  could read a plan of their own; `local_dimensions_get_competency_rule_data` returned the plan
  owner's ratings to a reader core would refuse them (a draft reader holding only
  `planviewdraft`); and `local_dimensions_get_competency_courses` answered for any competency and
  hid a failed plan read. All three now read the plan through `plan_access` and ask
  `plan_access::competency_scope()`, which admits the plan's own competencies (the archive for a
  completed plan), a related competency only when `showrelated` and `showrelatedlink` resolve on for
  the template and the viewer holds `moodle/competency:competencyview` in its context (the links the
  accordion renders), and a direct child of a rule-bearing plan competency (the Rules tab's links).
  Anything else gets the same not-found answer as a missing id. The rule data service also asks
  core's `user_competency::can_read_user()`, as `api::get_plan_competency()` does.
- **The hub no longer shows hidden templates, or another template's courses, to those who may not
  see them.** `local_dimensions_competency_usage` listed hidden templates to users holding only
  `templateview`; `local_dimensions_get_enrol_queue_status` answered for any course id and any cohort.
- **The participant search follows `showuseridentity`.** It searched and returned email, ID number
  and username whenever the caller held `moodle/site:viewuseridentity`, so a site listing only email
  (the default) exposed the other two and let the search confirm a username. It now uses core's
  `\core_user\fields::get_identity_fields()`.
- **An exception message can no longer void a whole web-service response.** The template import and
  the course progress service returned raw exception text in `PARAM_TEXT` fields; under developer
  debugging a DML error's SQL made `clean_returnvalue()` reject every row of the response.

### Changed

- **Code comments are written for a Moodle developer, following Moodle's comment guidance.**
  197 files lost the history, the measurements and the references to the local development
  environment that had accumulated in them, and kept the reasons, contracts and edge cases a
  maintainer needs at each line. 285 comments that no longer matched the code they describe were
  corrected. No code changed: the comment-free token stream of every changed PHP and JS file is
  identical before and after, and the AMD build was regenerated only because its module docblocks
  and source maps carry the comment text.

### Fixed
- **A template's metadata has the same shape whether it comes from the cache or was just built.**
  `template_metadata_cache::get_template_metadata()` and `get_metadata_for_many()` returned a
  freshly built payload without resolving it, so a cold read lacked the `enrollmentfilter` and
  `singlecourseredirect` keys, and ordered its keys differently from a warm read of the same template.
  Both readers now resolve the payload on a miss as they did on a hit. The cache still stores
  the unresolved `inherit` keys, so a change to the site settings applies without purging it.
- **The template metadata cache reads a template's own enrolment filter and single-course redirect.**
  It decoded both select fields by parsing each option as `key|label`, but the fields have been
  provisioned with plain labels since May 2026. So every template read as `inherit`
  and got the site setting, whatever it stored. It now decodes by the option's position, as
  `helper::get_template_enrollmentfilter()` does, which also works for fields that still spell
  `key|label`. No page read these two keys from the cache, so neither defect was visible;
  `block_dimensions` now batches its template reads through `get_metadata_for_many()`.
  `template_metadata_cache_test` compares cold and warm reads of both readers, a mixed batch, and
  the two option spellings; `mutations/template_metadata_cache.conf` breaks each guard.
- **The raised-contrast styles never applied.** All four blocks asked for `prefers-contrast: high`,
  an early draft value no browser shipped; they now ask for `more`, the value Media Queries Level 5
  defines. They reach users for the first time: a stronger progress-ring groove and readout, a
  solid hero title and description, thicker evidence dividers and a ringed Return to plan button.
  The hero rule had kept a fixed white ink over any text colour the admin set, which would have
  put white on a light hero the admin paired with dark text; it now strengthens only the
  translucent default and keeps the admin's colour. `preference_queries_test` pins that every
  preference query uses a defined value, that no raised-contrast override loses on specificity, and
  that none drops an admin colour its base rule reads.
- **Reduced motion now stops the movement it had missed.** With `prefers-reduced-motion: reduce`,
  the Return to plan button no longer lifts on hover, focus or press, rises into view or springs
  onto the screen edge after a drag. The enrol and Learn more buttons no longer lift. Progress
  bars and the progress ring are drawn at their value instead of growing to it. The filter tab
  indicator jumps instead of sliding, and the tab panes and enrolment groups appear without
  sliding in. Chevrons and switch knobs still show their state but no longer animate the change.
  Finding a competency in the hub's structure tree jumps to it instead of scrolling smoothly.
  The hub's loading spinner keeps turning, slowed, since its motion is what says the page is
  working. `preference_queries_test` now fails the build for a lift on interaction, a transition
  of position, size or a transform, a moving keyframe animation or a smooth scroll that the
  preference does not switch off.
- **Names are escaped exactly once, on the hub and on the learner pages.** Course, competency,
  framework, cohort, role and activity names were escaped by the server and again by the page, so an
  ampersand showed as `&amp;`. Web services and template data now carry the plain spelling and each
  sink escapes once; the hub's HTML sinks share the new `local_dimensions/central/escape` module, and
  values core's exporters already escaped are decoded rather than escaped again. The icon picker
  setting had the same defect. `hub_plain_names_test` and `learner_plain_names_test` pin both
  directions with a bare `&` and a `<`.
- **A completed plan's trail now reads the ratings core froze when the plan was completed.**
  `api::complete_plan()` archives every rating into `competency_usercompplan`, keyed by the plan,
  and `api::list_plan_competencies()` reads that archive for a complete plan and the live
  `competency_usercomp` for every other status. `plan_trail_cache` always read the live table, so a
  completed plan's trail would have shown the learner's CURRENT state and disagreed with core's own
  plan page about a plan that closed months ago. Nothing showed it yet, because `block_dimensions`
  renders active plans only; the defect would have arrived with its status filter, which is why it
  is fixed first. `get_trail_data()` takes the plan's completeness as a fourth argument (defaulting
  to the old behaviour, so the current caller is unaffected), the archive join is scoped to the plan
  being read, and the two readings are cached under different keys - a plan completed mid-session
  would otherwise keep serving the live trail it cached minutes earlier. `plan_trail_cache_test`
  covers the manual plan, the template plan (a separate query), the plan scoping and the cache;
  each of the three mutations - always read live, drop the plan scope, share one cache key -
  reddens it.

### Added

- **The learner pages log competency views the way core's pages do.** Neither the plan overview
  nor the competency tracker logged anything, so a learner who only used them left no trace of
  what they looked at. The plan overview now logs core's plan viewed event, as
  `admin/tool/lp/plan.php` does. Opening a competency in the accordion or the grid logs its
  view once per page load, through core's own `core_competency_user_competency_viewed_in_plan`
  web service, or `core_competency_user_competency_plan_viewed` on a completed plan, the same
  pair `tool_lp`'s user competency popup calls. The count stays one whether the learner
  collapses and reopens, switches layout or pages through the grid modal. A failed log call
  never reaches the learner. The competency tracker logs the same view before rendering, but
  not when it redirects straight to a single course, and not for a related competency outside
  the plan. Views of someone else's plan are logged too, with the viewer and the owner, as core
  logs them. `classes/local/view_events.php` holds the server side, and
  `tests/behat/view_plan_logging.feature` is the first Behat coverage of the plan overview.
- **The whole colour contract is a build gate now, not a paragraph.**
  `tests/local/colour_tokens_test.php` grew from four accessibility arms to seventeen, covering
  every rule the design states: no colour literal outside the token block or a named exemption
  (checked in both directions, so an exemption that stops matching anything fails too), the
  `:root` block declaring exactly the 34 contract tokens with their exact chains, the 34 suffixes
  matching the array `block_dimensions` carries byte-identically, the two plugins' blocks being
  the same block under two prefixes, the dark block assigning only the three plugin-owned
  decorative tokens, every colour-mode selector anchored at `:root`, the OS-preference fallback
  written and unreachable, three coloured inks never normal-size text on the inset surface
  (resolved through selector ancestry, with a ratchet on what the scanner cannot resolve), every
  declared pair clearing its WCAG floor in all three resolutions with the arithmetic done in the
  test and the values read out of the stylesheet, the admin's colours never owned by the mode
  layer, the hero transport intact and its `{{^hasbgimage}}` guard present, branded islands free
  of mode tokens, the plugin never writing the host's colour-mode attribute, `ink-faint` only ever
  inactive text, and every `var()` naming a token something declares. Every arm was
  mutation-checked before it landed: the mutation applied, the suite run, the redness confirmed
  and the mutation reverted.
- **`classes/local/colour_mode.php`** - the four attribute names in the family's activation
  contract, and deliberately no `is_dark()`: whether the page is dark is not server-knowable, and
  a wrong guess is the exact defect the design exists to prevent.
- **Behat cover for the colour contract** (`tests/behat/colour_mode.feature`, five new steps).
  Three scenarios assert a relative invariant - the plugin surface equals the page surface - so
  they hold on every supported branch with no skip: one with the host saying nothing, one with the
  host in dark mode, and one inside a dialogue, which is the `local_awareness` defect class where
  an unresolved `var()` leaves an element with no background at all. The fourth asserts the three
  plugin-owned tokens flip, and gates itself by measuring at runtime whether this Moodle ships a
  dark palette rather than guessing from a version number. The feature switches `themedesignermode`
  on, and that is load-bearing rather than incidental: Behat saves the compiled theme CSS when the
  site is initialised and restores it around every run, so with the cache in play all four
  scenarios stayed green with the whole dark activation block deleted. Designer mode compiles the
  same CSS per request, which is what makes the assertions about the stylesheet in the tree.
- **A sixth arm on `bootstrap_compat_test`: zero deprecated Bootstrap 4 class names.** The
  asymmetry runs both ways, and this is the direction that is easy to miss - `sr-only`, `ml-*` and
  their siblings do resolve on 5.x, but only through `bs4-compat.scss`, which marks each one
  deprecated and which Moodle 6.0 deletes.

- **Enforcement for the four accessibility rules no other gate can see.**
  `tests/local/colour_tokens_test.php` fails the build when a focus indicator is drawn with a
  `box-shadow` instead of a real outline (nothing paints a shadow in Windows High Contrast mode),
  when a focus ring is anchored to the brand colour or a literal instead of the focus-ring token,
  when the favourite star stops swapping its glyph, and when a control that renders only an
  aria-hidden icon carries no `aria-label`. Each of the five arms was mutation-checked before it
  landed: the mutation was applied, the suite run, the redness confirmed and the mutation
  reverted. phpcs reads PHP, the mustache lint reads markup structure and stylelint reads CSS
  syntax; none of them can tell any of these four things apart from their correct form.
- **Windows High Contrast support, which the plugin had none of.** `prefers-contrast: high` and
  `forced-colors: active` are unrelated media features, and the four blocks this plugin had were
  addressing only the first. Under forced colours every background collapses to the system canvas,
  so the three selected states carried by a fill alone — the toolbar pill groups, the chip filters
  and the list/grid toggle — now also draw a dashed outline, which is a property the browser does
  paint (and remaps) in that mode.
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
- **The plan overview fetches each competency's detail once per page.** The cache that stopped a
  second fetch recorded a rendered pane, not the data. The grid modal therefore dropped the
  competency and called both detail web services again on every card open and every pager step,
  and a switch between list and grid dropped every competency at once. The fetched summary and
  course cards are now kept per page, apart from the record of which pane shows them, so paging
  back, reopening a card or switching layout renders from what is already there. Measured on m502
  over a session that opens three cards, pages back and forth and switches layout twice, the two
  services went from 8 calls each to 3. A review request sent from the detail changes that data,
  so it drops the competency and the next pane fetches it again. A rating made elsewhere shows on
  the next page load, as it always did in an expanded list pane. Logging a competency view stays
  once per page and is not tied to this cache. `tests/behat/view_plan_detail_cache.feature` asserts
  the counts in the browser.
- **The colour token layer moved from `:root` to `body`, and the dark rule grew a second arm - so
  the plugin follows a colour-mode scope wherever the host writes it.**
  Thirty-one of the 34 tokens are DERIVED: each resolves a `var(--bs-*)` chain (the other three,
  `shadow`, `scrim` and `favourite`, are plugin-owned literals), and Bootstrap redefines the
  `--bs-*` set on whatever element carries `data-bs-theme`. Its own `color-mode` mixin emits an
  UNANCHORED `[data-bs-theme="..."]` (`bootstrap/mixins/_color-mode.scss:16`) precisely so the
  attribute can scope any subtree, and Bootstrap's components read `--bs-*` at the component for
  the same reason. A derived layer pinned at `:root` snapshots the root's values and is then blind
  to every scope, including core's own. Counted in the compiled 5.2 sheet: of 32
  `[data-bs-theme="dark"]` rules, 28 are unanchored - Bootstrap's and core's - and the only 4
  anchored at `:root` belonged to this fleet's plugins. The anchoring was ours, not the ecosystem's.

  What it cost, measured on m502 at 1440x900 through `theme_moove`'s own dark switch (moove writes
  the attribute on `document.body`, `amd/src/darkmode.js:35`): the page went to `#1d2125` while this
  plugin's `surface` stayed `#f2f3f7`, and text inheriting the page's dark-mode colour landed at
  **1.17:1** on the plugin's own card, against the 4.5:1 AA floor. After the move the same
  measurement reads **12.44:1**, and `surface`, `line`, `ink`, `shadow` and `favourite` all flip.

  `body` rather than the plugin's own surfaces, deliberately: it is the one ancestor every surface
  has. The Return-to-Plan FAB renders on course pages outside every plugin wrapper, and
  `core/modal` appends its dialogue to `document.body` as a SIBLING of the page container - the
  case that once left a dialogue with no background at all when a token block was scoped to a page
  class.

  The activation rule is now `body[data-bs-theme="dark"], [data-bs-theme="dark"] body`. Naming body
  as the SUBJECT is what still forecloses the leak the `:root` anchor existed to prevent - there is
  exactly one body and its only ancestor is html, so the second arm can only ever mean
  `html[data-bs-theme="dark"] body`, and no deeper scope (the navbar one `theme_boost_union`
  re-pins on five templates) can reach it. Both arms verified in the browser: the first through
  moove's switch, the second with the attribute on `<html>` as core writes it. The note that used
  to prescribe a separate `:root:has(> body[...])` rule is superseded - it would have flipped only
  the three plugin-owned tokens and left the other 31 reading the html element's light values,
  which was the whole of the defect.

  `colour_tokens_test` moved with the contract: `token_block()` reads the `body` rule,
  `activation_block()` and `contract_block_selectors()` share a new `DARK_ACTIVATION_SELECTOR`
  constant, and `test_activation_selectors_are_root_anchored` was re-founded as
  `test_activation_selectors_have_body_as_subject`. Mutation-checked, three ways: a bare attribute
  selector reddens it, a `.theme-dark` rule reddens it, and putting the token block back on `:root`
  reddens five arms. `block_dimensions` moved in the same change - the two blocks are compared
  byte-for-byte under a prefix sentinel, so they cannot travel apart.

- **Eleven Bootstrap 4 class names migrated to their Bootstrap 5 spellings** - seven `sr-only`
  spans in `accordion.js`, `competency_view.js` and `central/competency_links.js` become
  `visually-hidden`, and the four `ml-2`/`mr-2` utilities become `ms-2`/`me-2`. The BS5 spelling
  alone is correct on both branches: 4.5's forward bridge covers the spacers, and the plugin's own
  polyfill covers `visually-hidden`.
- **CI checks out `block_dimensions` beside the plugin on every job.** The two share one
  colour-token contract under two frankenstyle prefixes, and the test that compares the two blocks
  can only run where both are installed; a skip nobody notices is how a cross-repo lock quietly
  stops running, so a further test reads the workflow file and fails the build for any job that
  drops the line. It is not a runtime dependency: neither plugin declares the other.

- **Focus indicators converge on one ring across both dimensions plugins.**
  `outline: 2px solid var(--local-dimensions-focus-ring)` with a 2px offset, everywhere: the two
  3px rings and the stray 1px and 3px offsets are gone, and the two duplicate
  `.local-dimensions-filter-tab` rule blocks now declare a byte-identical focus rule so source
  order no longer decides how a filter pill draws focus. Ten controls that had no ring at all
  gained one — the course-card title link and its `-single-go` twin, the activity row, the hub's
  outline button, the structure related chip, the activity-search rows, the plans resizer, the
  drag grip, the plugin-skinned modal close chip and the referenced-competency modal's close.
  On the two branded islands the ring is `currentcolor`, because their ground is the admin's own
  colour in both modes and the emphasis extreme would be wrong there.
- **`prefers-contrast: high` no longer assumes a light page.** The progress-ring block hardcoded
  `#157347` for the arc and `#000` for the readout, both chosen against white; `#157347` measures
  2.63:1 on a dark page, which is *worse* than the value it was replacing (3.40:1) and below the
  3:1 floor. All three declarations now read tokens that flip with the host. The hero and the FAB
  blocks keep a fixed ink and say why: their ground is a branded island, which does not flip.
- **The competency tracker's course card gains the hover and focus feedback it never had**, and
  its face, border, radius and shadow are the companion block's, so a learner moving between the
  two never meets two card shapes. The locked card takes the same 0.5rem radius.
- **The favourite star swaps its glyph as well as its colour** (`fa-star` / `fa-star-o`), in the
  template and when the star is toggled without a reload, matching what `block_dimensions` has
  always done. State carried by colour alone is a WCAG 1.4.1 failure.
- **Two screen-reader-only spans move from `sr-only` to `visually-hidden`**, the spelling the
  other 25 sites in this plugin already use and the one its Bootstrap 4 polyfill defines. `sr-only`
  resolves on 5.x only through the compatibility layer Moodle 6.0 deletes.
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

- **The Rules tab of a competency shown a second time no longer spins forever.** The tab fetched
  its data once per page and marked the whole page as loaded, but every detail render builds a new
  Rules pane. After a card was reopened, the modal stepped back to a competency, or the list pane
  was rebuilt by a layout switch, the new pane found the page already marked and never filled in.
  The mark now lives on the pane and the fetched data is kept per page, so a rebuilt pane renders
  at once without fetching again.
- **A plan the viewer may not read is no longer reported as an invalid plan.** `view-plan.php` and
  `view-competency.php` turned every exception from `api::read_plan()` into "Invalid learning
  plan". Two refusals were misreported that way:
  - Core's permission error. No default archetype holds `moodle/competency:planviewowndraft`, so
    every learner on an unmodified site is refused their own draft, waiting-for-review or in-review
    plan, and was told that plan did not exist.
  - The "competencies are not enabled" error.

  Both pages now read the plan through `classes/local/plan_access.php`. It reports only a missing
  plan as invalid (a `dml_missing_record_exception`, for an unknown id or one below 1) and lets
  every other error surface as core's own, as `admin/tool/lp/plan.php` does. The learner-facing
  `block_dimensions` was checked and needs no change: its plan and competency cards come only from
  active plans, so it never links a learner to a draft or in-review plan.
- **A viewer allowed to read draft plans no longer gets an error from every competency.** Core
  lets a role holding only `moodle/competency:planviewdraft` read a draft, waiting-for-review or
  in-review plan. But each competency's detail goes through `api::get_plan_competency()`, which
  checks `user_competency::can_read_user()`, and that check never consults the draft
  capabilities. Such a reviewer could open the plan overview, while every competency they expanded
  failed with an error in the row and an exception dialog. The page now works out server-side
  whether the viewer can read the plan owner's user competencies. When they cannot, it renders the
  list with plain headers, no detail region and no chevron, and says once, in a localised notice,
  that the details are not available. Default roles are unaffected: a manager holds every
  capability involved, and the owner reads an active plan normally.
- **The Return to plan button never appeared on a MooTube (`format_mtube`) course page.** Every
  gate passed and `init()` set `display: flex`, but core places the footer hook's HTML inside
  `#region-main`, and the format hides every child of `#page` except its own app with
  `display: none !important` - so the button rendered 0x0, with nothing in any log. Measured on
  m502 as a student with a stored return context. `init()` now steps the button out one ancestor
  at a time while it has no client rects and stops at the first spot where it renders (right after
  `#page` there); on a page that hides nothing it does not move, so it keeps its place in the main
  landmark and in tab order everywhere else. On that page, outside editing (where the format hides
  nothing and core's footer button is back in the corner), it also takes the format's corner: aligned
  with the format's tools button (bottom 2rem, right 2rem), one gap to its left when a teacher has
  it (its menu opens upward in its own column), 5rem up below 768px to clear the format's bottom
  bar, and hidden with the format's own chrome in its HTML frame fullscreen.
- **Three more places a theme's own decisions reached into the plugin, all measured on m502 at
  1440x900 and fixed in the sheet that owns the cause.**

  - *The sticky filter toolbar anchored 10px too high on moove and 4px on trema.* It already
    read `var(--navbar-height, 60px)`, which is the right channel - theme_almondb declares
    `--navbar-height: 70px` for its 70px navbar and the toolbar lands exactly right there with
    no help. moove (70px navbar) and trema (64px) leave core's 60px in place, so rows slid
    under the navbar while scrolling. `styles_moove.css` and `styles_trema.css` now state the
    measured height in the one place the plugin already reads, rather than overriding the
    toolbar's `top` somewhere else and leaving two numbers to drift.

  - *The Return-to-Plan FAB covered the theme's own footer button.* core Boost parks
    `.btn-footer-popover` at `bottom: 2rem` with a 2rem box, i.e. the band 32-64px up from the
    bottom edge; the FAB claimed 24-75px at `z-index: 9999` against the button's 1000, so the
    theme's control was present, focusable and invisible. `styles_boost.css` lifts the FAB to
    `calc(2rem + 2rem + 1rem)` - core's own arithmetic, so it follows if core moves the button -
    which also clears moove's lower placement of the same button (16-48px) and trema's
    `#goto-top-link` (0-38px). A second arm mirrors core's `.hasstickyfooter` offset. Verified
    with the FAB actually rendered: measuring it while it was absent reported "no collision"
    for the wrong reason, twice.

  - *Competency cards lost their border on moove and trema.* `styles.css` styles them with
    `border-color` only, riding on Bootstrap's `.card` carrying `border: 1px solid`; both themes
    remove it, and the cascade resolves longhands independently, so the colour survived and
    painted nothing. The two themes need different repairs and the difference is specificity,
    not taste: trema writes `.card:not(.fp-navbar) { border: none }` at (0,2,0), which ties with
    the plugin's own selector and wins on document order - taking `border-color` with it, which
    is why its border measured `#373a3c` (`--bs-body-color`) rather than the token - so trema
    restates the colour as a token read; moove writes `.card { border: none }` at (0,1,0), loses
    `border-color` to the plugin, and needs only the two structural longhands.

- **The hero sits against the navbar on every theme, and stops guessing at how far to move.**
  `styles.css` pulled the hero up by a flat `margin-top: -1rem`, commented "pull the hero up to
  overlap any remaining spacing" - one constant standing in for a number that is different in
  every theme, so it was right in none of them. Measured on m502 at 1280px, logged in, on
  `view-plan.php`, the white band between the navbar and the hero was 32px on boost, boost_union
  and boost_union_fundaseg, 33px on almondb, 25px on trema and 9px on moove; on Moodle 4.5 it was
  32px on boost and 121px on theme_academi, whose own navigation bar is static and 71px tall. The spacing does not belong to the hero at all - it is spent by the
  containers above it, and the fix is to zero those, which lands the hero at `#page`'s own top
  edge, a value every Boost child already sets to its navbar's height. The plugin therefore needs
  to know nothing about any theme's navbar. New `styles_boost.css` carries it, because core loads
  `styles_<candidate>.css` for every candidate in [base parent ... theme] and so reaches every
  Boost child from one file; `styles_academi.css` carries the two deltas that are academi's own.
  All eight theme/branch combinations now measure 0 or -1px, the -1 being the hero tucked a pixel
  under an opaque fixed navbar.

  Three things this cost, worth keeping:

  - **Specificity, not `!important`.** The first attempt used `body.<class> #topofscroll` and lost
    to Boost Union's `#page.drawers .main-inner`, which has one id and two classes: the class count
    is compared before the element count. Anchoring on two ids settles it on the id count, which is
    compared first, and needs no `!important` - still banned.
  - **trema spends part of its spacing on `#page-content`**, the only theme measured that does, so
    a fix aimed only at `#topofscroll` looked right on five themes and left 16px on the sixth. The
    rule names the whole chain rather than the container that happened to be guilty first.
  - **The last pixel is core's skip-link anchor.** Boost gives the empty `<span id="maincontent">`
    a 1px height, and it sits immediately before the hero; on boost and boost_union_fundaseg it
    lands in the 1px their `#page` margin is already short of the navbar and vanishes, while on
    moove and trema it became a visible hairline. It is collapsed with `height: 0` - the same
    thing core does for embedded media previews - and never with `display: none`, which would take
    "Skip to main content" out of the accessibility tree with it.

- **The admin's chosen hero text colour was inert, and had been for as long as the rule existed.**
  `styles.css` reads `--dimension-customtextcolor` five times - the hero title, description,
  due-date label and value, and the collapse button - and nothing had ever written it: the colour
  reached the DOM as a bare inline `color:` on the ancestor, which all five descendants then
  overrode with their own white fallback. The hero now emits both
  `--dimension-custombgcolor` and `--dimension-customtextcolor` beside the plain properties, which
  is the transport `block_dimensions` has always used, so the two plugins now carry an admin colour
  the same way. The inline background keeps its `{{^hasbgimage}}` guard, which is load-bearing: a
  class selector never outranks an inline style and `!important` is banned fleet-wide, so without
  the guard an opaque colour paints over the hero photograph and no stylesheet can undo it.

- **The icon picker's two icon-only buttons reach a screen reader with a name.** The chevron and
  the clear button had a `title` and nothing else; `title` alone is exposed inconsistently across
  browser and screen-reader pairings and never appears on touch. Both now carry an `aria-label`
  built from the string they already had, so no new lang key was needed.
- **The custom-SCSS editor's focus indicator survives forced colours.** Its only ring was a
  `box-shadow` glow, which that mode does not paint at all; it is a real outline now.
- **The enrolment methods pane explains itself when the plan has no linked courses.** The
  methods act on the courses linked to the plan's competencies; a plan with no competencies,
  or none linked to a course the viewer may configure, showed an empty grid that read as a
  permission problem — a category manager holding every required capability hit exactly that.
  The pane now says so and points at the two steps that fill it.
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
