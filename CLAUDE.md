# Claude instructions for `local_dimensions`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. It captures the **Moodle development standards** this plugin
follows so future edits stay in the same style and pass CI on the first try.
The conventions are shared with the sibling plugins `block_feedback_tracker`
and `local_profilefield_repeatable`; this file keeps only what is true here.

Plugin context: a Moodle **local** plugin ("Competency Dimensions") that
extends core competencies and learning plan templates with custom fields and
renders two learner-facing views — **Competency tracker** (course-card grid)
and **Full plan overview** (expandable accordion) — plus a draggable "Return
to Plan" FAB. It defines **no database tables of its own**: all data lives in
core competency tables and `customfield_data`. Supports Moodle **4.5 through
5.2** (`$plugin->requires = 2024100700`, `$plugin->supported = [405, 502]`).
CI is the **moodle-an-hochschulen/moodle-workflows** reusable workflow, called
once per supported branch in `.github/workflows/ci.yml` (5.02 full PHP × DB
matrix; 5.01/5.00/4.05 one-DB-only) — **update those calls when `supported`
changes**. Development happens on Moodle 5.1.

## Commands

The plugin repo is `~/dev/moodle-local_dimensions` and is **bind-mounted** into every
stack its `$plugin->supported` range admits (m405, m501, m502) per the fleet manifest
`~/dev/moodle-dev/plugins.conf`: one edit is live in every Moodle version at once, no
clone inside a checkout, no rsync. Everything runs through the fleet's `mdl` CLI
(`~/dev/moodle-dev/bin`, on PATH in interactive shells; use the absolute path from an
agent's Bash), and git runs from this directory (or `git -C`). `git fetch && git pull`
before starting so you don't build on a stale base.

### Building JavaScript assets (required before committing JS)

`mdl grunt m501 local/dimensions` rebuilds `amd/build/*.min.js` + `.map` in a node
container pinned to the version Moodle expects; never hand-edit minified output.
`amd/build/**` is **tracked in git**: every `amd/src` edit ships its rebuilt output in the
same commit, plus a `version.php` bump so the cache revision changes. The stacks run with
`cachejs = false`, so during development just edit `amd/src` and reload.

### Gates (run locally before pushing)

```sh
mdl ci moodle-local_dimensions --only phpcs,phpdoc,mustache,grunt   # static pass, one leg
mdl ci moodle-local_dimensions --matrix --behat                     # every leg GitHub runs
mdl phpunit m501 local_dimensions                                   # or a path: local/dimensions/tests/...
mdl behat m501 /var/www/html/public/local/dimensions/tests/behat/<x>.feature   # absolute container path
```

CI runs `phpcs --max-warnings 0`, `phpdoc --max-warnings 0` and `grunt --max-lint-warnings 0`
(eslint plus stylelint on Moodle's own config), so **every warning fails the build**. The
stylelint rules that bite: `declaration-no-important` (never `!important`; own the property
in a plugin class instead of fighting a Bootstrap utility), `csstree/validator` (rejects
`clamp()`/`min()`/`max()` in every length-valued property; use plain `height` +
`min-height`/`max-height` pairs, `calc()` and grid `minmax()` are fine) and
`time-min-milliseconds: 100` (a transition under 100ms is an error). A `version.php` bump
stales the stacks' test sites: `mdl phpunit-init <stack>` / `mdl behat-init <stack>` first.
Behind the corporate proxy, containers created off-network need
`mdl down <stack> && mdl up <stack>` before Behat works (see `moodle-dev/CLAUDE.md`). The
5.02 CI legs cannot install locally there (esm.sh through undici); GitHub runs them.

### Test deploy / dev loop

Deployment is a **manual zip install** on a test server, produced by `git archive`
from the plugin clone. `git archive` packages a **commit** (a tree), never the
working tree — so commit first; uncommitted edits never enter the zip.

To test local work **before it is pushed**, archive `HEAD` (the current branch
tip, pushed or not); name the zip `moodle-<component>-<version>-<shortSHA>.zip`
— here `moodle-local_dimensions-<version>-<shortSHA>.zip`.

Two parts of that command name different things and must not be conflated:

- The **filename** carries the frankenstyle component with a `moodle-` prefix,
  matching the GitHub repo name. This matters because `local_dimensions`,
  `block_dimensions` and `aiplacement_dimensions` all install into a folder
  called `dimensions` — under the older `dimensions-<version>-<sha>.zip` naming
  their zips collided in `~/Downloads` and none of them said which plugin it was.
- The **`--prefix`** is the *install directory*, which is the component with its
  type stripped (`${comp#*_}`). All three dimensions plugins share `dimensions/`.
  Moodle validates this one; getting it wrong makes it refuse the zip.

The short SHA is **required**, not optional: the `version.php` version is frozen
(many slices share one version number), so the version alone can't tell two
builds apart — the commit SHA is what does.

The snippet is plugin-agnostic; run it from any plugin clone:

```sh
comp=$(grep -oE "\$plugin->component[[:space:]]*=[[:space:]]*'[^']+'" version.php \
  | grep -oE "'[^']+'" | tr -d "'")
ver=$(grep -oE '\$plugin->version[[:space:]]*=[[:space:]]*[0-9]+' version.php | grep -oE '[0-9]+')
sha=$(git rev-parse --short HEAD)
git archive --format=zip --prefix="${comp#*_}/" HEAD -o ~/Downloads/moodle-$comp-$ver-$sha.zip
```

To package the **published** state instead, `git fetch origin` first and archive
`origin/main` in place of `HEAD` (and read the SHA from `origin/main`) — the fetch
is only needed there, to refresh the remote ref `origin/main` resolves to. For the JS dev loop, set *Site admin →
Development → Debug = DEVELOPER* and *cachejs = off* so Moodle serves `amd/src`
directly without a rebuild.

## CI gating

`moodle-plugin-ci install` runs per job. The **static leg** gates on: `phplint`,
`phpmd` (informational), `phpcs --max-warnings 0` (**warnings fail**),
`phpdoc --max-warnings 0`, a development-leftover checker that fails on stray
to-do markers / merge-conflict markers in **any** file (docs included — never
write those tokens literally), `validate`, `savepoints`, `mustache`, and
`grunt --max-lint-warnings 0` (incl. stylelint). **Runtime legs** run PHPUnit
(`--fail-on-warning`) and Behat on every PHP × DB combination.
`.moodle-plugin-ci.yml` filters `node_modules`/`vendor` from the scan.

Every gate runs locally through the fleet's `mdl ci moodle-local_dimensions`
(`--only phpcs,phpdoc,mustache,grunt` for the static pass, `--matrix --behat`
before a push) and `mdl phpunit <stack> local_dimensions`; the rules below are
what to pre-empt at write time so the first run is green.

**Every job checks out `block_dimensions` under `plugin-dependencies`, and a test enforces that
it keeps doing so.** The two plugins share one colour-token contract under two frankenstyle
prefixes, and `colour_tokens_test::test_token_block_is_identical_to_the_sibling` compares the
blocks declaration by declaration — without the checkout it **skips**, and a skip nobody notices
is how a cross-repo lock quietly stops running, so `test_ci_checks_out_the_family_sibling` reads
`ci.yml` and fails the build for any job that drops the line. It is not a runtime dependency:
neither plugin declares the other in `$plugin->dependencies` and each installs standalone. Note
the asymmetry with the other entry there — `enrol_apply` declares `$plugin->supported = [501, 502]`
so it must **not** appear on the 4.05 job, while `block_dimensions` declares `[405, 502]` and
appears on all three.

## Code layout

```
settings.php                 Admin tree — added under the 'competencies' admin
                             category (not 'localplugins'), gated on
                             get_config('core_competency', 'enabled'); only the
                             settings page and the two custom-field definition
                             pages sit behind $hassiteconfig — the hub page is
                             gated by its own capability at the system context
lib.php                      Procedural hooks + SCSS injection
styles.css                   Two :root blocks (5 motion/loading + 34 colour
                             tokens), ONE activation rule
                             :root[data-bs-theme="dark"], one inert
                             prefers-color-scheme block, then the components,
                             then the Bootstrap 4 polyfill at the tail
version.php                  component / version / requires / supported
view-plan.php                Learner views (plan overview / competency tracker)
view-competency.php          Single-competency detail view
central.php                  The Competency hub (Structure / Learning plans /
                             Frameworks tabs — the whole admin surface). Two
                             entries: the admin tree (admin_externalpage_setup,
                             remembered context, 'self' listing) and a course
                             category's More menu via pagecontextid (tool_lp's
                             page setup, locked to the category, 'children'
                             listing, never written to the preference) — see
                             docs/superpowers/specs/2026-09-02-central-category-context-design.md
customfield*.php             Custom field config landing pages (core field defs)
classes/
  hook_callbacks.php         before_footer_html_generation → Return FAB
  helper.php                 Custom-field provisioning + return-context + queries
  observer.php               core_competency event observers (cache + cleanup)
  calculator.php             Real-time course/section progress
  constants.php              CFIELD_* shortnames + shared constants
  *_cache.php                MUC loader wrappers (template/competency metadata,
                             template_course, plan_trail)
  scss_manager.php           Per-template/competency SCSS compile + cache
  picture_manager.php        Card image handling (builtin vs customfield_picture)
  chip_filters.php           Custom-field-driven chip filter model
  admin/                     setting_iconpicker (AJAX FontAwesome picker)
  customfield/               competency_handler + lp_handler (two CF areas)
  event/                     Audit events (cohort links/roles, customfields,
                             course/module links, duplication)
  external/                  Web-service functions (one class each)
  form/                      dynamic_form subclasses (competency/template/framework)
  local/                     plan_status, bootstrap, colour_mode (dark-mode
                             attribute names — constants only) and other value
                             helpers
  output/                    Renderables: learner (view_*_page) + hub
                             (central/, dynamictabs/ tab classes)
  task/                      Adhoc tasks (cohort role + template cohort sync)
  privacy/                   Preference-only provider (metadata\provider +
                             user_preference_provider) — no tables, no delete
                             or context providers: core_user owns preference
                             deletion (user/classes/privacy/provider.php)
  reportbuilder/             Datasources + entities (competencies, plans)
db/                          access, caches, events, hooks, services, install,
                             upgrade, uninstall  (NO install.xml — no own tables)
templates/                   Mustache (server-rendered UI)
amd/src/                     Plain AMD modules (define([], …)) — NOT Preact/React
amd/build/                   Committed minified output (grunt) — keep in sync
lang/{en,pt_br}/             English + Brazilian Portuguese, both kept in sync
docs/design-kit/             Hub design kit. tokens.html documents core's
                             palette under core's --mds-* NAMES — legitimate in
                             documentation, NEVER shipped in CSS (see below)
docs/learner-kit/            Learner-view design kit + token-migration.md, the
                             historical record of two colour migrations
tests/                       PHPUnit (observer, helper_*, local/colour_tokens_test
                             and local/bootstrap_compat_test et al) + behat/
                             (hub smoke-test .features incl. colour_mode.feature,
                             plus step definitions)
```

## Architecture gotchas

### Custom-field auto-provisioning
The plugin owns two `customfield` areas via `classes/customfield/`:
`competency_handler` and `lp_handler` (learning plan templates). Fields are
provisioned lazily: `helper::ensure_all_fields()` runs once per session from the
footer hook (guarded by `get_config('core_competency', 'enabled')`), and
`helper::ensure_custom_fields_on_setting_change()` runs from setting
`set_updatedcallback`s. Field shortnames are constants in
`classes/constants.php` (`CFIELD_*`) — reference those, never string literals.
Storage facts that bite: the both-areas fields (colors, tags, filters) reuse the
**same shortname** in the lp and competency areas — never `get_record` on
`customfield_field` by bare shortname (dml_multiple_records); scope through the
category (component+area) or the handler. Data rows carry `instanceid = <id>`
and **`itemid = 0`**; files embedded in field data are keyed by the **data row
id** (`customfield_textarea`/`value`, `customfield_picture`/`file`), not the
instance. Provisioning is serialised under a core Lock API lock and calls
`reset_configuration_cache()` after acquiring it (the plugin handlers override
`create()` as **singletons**, unlike core's per-call `create()`) — keep both
when touching `ensure_custom_fields_exist()`; neither `customfield_field` nor
`customfield_category` has a DB unique index to catch duplicates.
Both handlers resolve `can_edit()` in the **instance's own context** (the
template's, or the competency's framework's), never at the site: core filters
the rendered AND the saved fields through it, so a site-context check makes a
manager scoped to one course category see no fields and save nothing, silently.
Two mechanisms keep the create path honest, where core asks about instance 0:
`instance_form_save()` latches the id being saved, and the form calls
`set_edit_context_hint()` before rendering. Any new caller of a handler on a
create path must set the hint or the fields vanish for category managers.

### Custom-field data cleanup on delete (Moodle 5.1+)
Core destroys the instance context **before** firing `competency_deleted` /
`competency_template_deleted`, so a context-scoped `delete_instance()` cleanup
finds nothing. `observer.php` therefore sweeps `customfield_data` by instance id
+ area directly. Preserve that context-independent path when touching deletion.

### Return-to-Plan FAB (`hook_callbacks::before_footer_html_generation`)
Renders only when: feature enabled, logged-in non-guest, a course is in context,
the page is **course content** — a pagelayout **allowlist** (`course`/`incourse`,
fails closed so `secure` quiz windows, popups, `mypublic` profiles and
layout-less scripts never get the button) plus a pagetype blocklist for the
administrative core pages that ship layout `incourse` (participants, tool_lp,
`grade-*`, quiz editing…) — and a stored return context exists for that course.
Anti-loop invariant: every `view-competency.php` URL the button stores carries
`noredirect=1` (honoured in its `$willredirect` check) — keep this when touching
the redirect path. When it redirects, the destination course's stored URL
follows the same display-mode routing as the tracker's own button
(`helper::redirect_return_url()`, gated on `helper::plan_overview_is_routed()`):
the plan overview when the plan's learners are routed there, the tracker's own
URL — carrying `noredirect=1` — otherwise. Both views write contexts **only for
the plan's own user** (staff reviewing someone else's plan must not pollute
their session), and the `returncontext` session cache has a 4h defensive TTL.
The FAB is draggable; its position persists in `sessionStorage` (per-tab,
current session) — see `amd/src/return_button.js`.
The tracker renders a **second, separate** return button of its own
(`helper::tracker_return_context`, echoed by `view-competency.php`), because the
hook cannot reach it: both learner views leave `$PAGE->pagelayout` at core's
default `base`, which the allowlist excludes. Two consequences are invariants.
**Never combine a course-content pagelayout with a course in `$PAGE->context` in a
learner view** — `get_current_course_id()` runs, and returns, before the pagelayout
check, so on the tracker (no course in context) the hook exits there regardless of
layout; it is that check, not the layout alone, that keeps it off the tracker today.
Both conditions together would render a second FAB sharing the fixed DOM id
`local-dimensions-return-fab`, and the tracker would become a destination for
itself. And **never add `related` to the tracker's `$PAGE->set_url`** — the
related-competency pill sets it to suppress the tracker's button in the new tab it
opens, and leaking it into the cached URL would suppress the button on the way back
from a course too. The course FAB's label is derived from the cached URL
(`helper::return_destination_kind`), so a context pointing at the tracker reads
"Return to competency"; keep the mapping a literal `match`, since the string
checker cannot verify a constructed identifier.
The tracker's button is further gated on `helper::plan_overview_is_routed()`: it
only appears when the plan overview is a page this learner is actually routed to.
`block_dimensions` routes learners by the plan's display mode — `DISPLAYMODE_PLAN`
yields a plan card leading to the overview, anything else (including a template
that never set the field) yields competency cards leading straight to the
tracker — and in competency-card mode the tracker *is* the learner's root, so
offering the overview from there would be offering a page they are never routed
to. This plugin owns the *value* (the `local_dimensions_displaymode` template
custom field) but `block_dimensions` owns the *routing*, so
`plan_overview_is_routed()` must keep agreeing with that plugin's
`dataset_provider::resolve_plan_display_context()`: no template means plan mode, a
template without the field means competency mode. If the two plugins' defaults
ever drift apart, the button lies again.

### Course category deletion (`classes/local/category_lifecycle.php`)
Core's `delete_full()` / `delete_move()` know nothing about competency data and would leave
frameworks and templates pointing at a deleted context. `lib.php` answers all four callbacks
core offers (`get_course_category_contents`, `pre_course_category_delete`,
`can_course_category_delete_move`, `pre_course_category_delete_move`) through that class:
refuse "delete all" while anything is in use (`competency::can_all_be_deleted()`, linked
plans), delete the rest through the competency API, re-home on "move contents" with one
UPDATE per table the way core moves cohorts (the persistents refuse a context change by
validation, so neither the API nor `update()` can do it). The callbacks
are discovered by `get_plugins_with_function()`, cached per `allversionshash`: adding one
needs a `version.php` bump or it never runs. Only the category's own context per call.

### Caches and invalidation
`observer.php` invalidates the metadata/trail caches on the relevant
`core\event\competency_*` events. When you add a query that reads cached
metadata, add the matching invalidation to the observer rather than relying on
the defensive TTL alone.

### Audit events (`classes/event/`)
The hub logs decisions core never does (cohort attach/detach, cohort-role
rules, customfield value changes, course/module links, duplication). Events
need **no registration** (`db/events.php` is observers-only); `objecttable`
over a core table is legal (mod_quiz precedent) but then `objectid` is
required — fetch link rows **before** deleting them. Core APIs that return
`false` on the idempotent duplicate path (`create_cohort_role_assignment`,
`add_competency_to_template`) must not reach a trigger's `->get()`. The two
`*_customfields_updated` events fire from the `instance_form_save()` override
in both handlers (covers modals, WSes, observer repost, CSV import — new
handler writes are auto-logged) and diff **effective** values via
`get_value()`, redacting textarea bodies to `'(updated)'`. In PHPUnit, core
refuses a module link unless the competency is on the course first.

## Colour tokens and dark mode

**The plugin does not own a palette.** `styles.css` declares **34 colour tokens** on bare
`:root` — `--local-dimensions-*`, byte-identical suffixes to `block_dimensions`'
`--block-dimensions-*` — and every component rule reads one. **A colour literal outside that
block fails the build.** Do not reintroduce one; do not add a per-site literal fallback at a
consumption site.

**How the chains work.** 30 of the 34 are `var(--bs-NEW, var(--BS4-OLD, #literal))`. Measured on
the running stacks: Moodle 4.5 declares **none** of these `--bs-*` names and Moodle 5.1/5.2
declare **none** of the Bootstrap 4 legacy names, so exactly one rung resolves per branch and the
literal is a safety net rather than a value the plugin expects to use. The literals are core's own
compiled 5.2 light values, so every rung of a chain means the same thing.

**Why there is almost no dark rule — read this before adding one.** Moodle 5.1/5.2 already compile
a complete `[data-bs-theme="dark"]` token block; nothing writes the attribute yet, so those values
are dormant, not absent. A colour written as `var(--bs-secondary-bg, #e9ecef)` is therefore
**already dark-correct with no rule of its own**: the plugin's card face *is* `--bs-body-bg`, the
same value `body`'s background uses, and the two cannot disagree because they are one value.
**Never add a dark rule for any of those 30 tokens.** Only `shadow`, `scrim` and `favourite` carry
a plugin-authored dark value — core has no equivalent for any of them — and they are the entire
contents of the one activation rule. That bound is the second, independent guarantee that this
plugin can never paint a dark surface on a light page: even if the rule fired on a branch with no
dark palette at all, the worst outcome is a deeper shadow, a darker veil and a brighter star.

**The activation rule is `:root[data-bs-theme="dark"]`, and the `:root` anchor is load-bearing.**
A bare `[data-bs-theme="dark"]` matches through **any** ancestor at any depth, and CSS descendant
combinators have no nearest-ancestor-wins rule. That is not hypothetical: `theme_boost_union_fundaseg`
sets `data-bs-theme="dark"` on the **navbar element itself**, and `theme_boost_union` then re-pins
`data-bs-theme="light"` by hand on five nested templates (`core/moremenu`, `core/user_menu`,
`theme_boost/language_menu` and the two smartmenu children) to stop the dark scope leaking into its
own submenus. A bare selector would ignore those re-pins. `:root` restricts the match to the `html`
element and forecloses the whole class at zero cost. **`.theme-dark` and `body.dark` are not
accepted** — nothing in Moodle 4.5/5.0/5.1/5.2/5.3-dev, `theme_boost_union` or
`theme_boost_union_fundaseg` has ever emitted either. If a theme ever scopes the attribute to
`<body>`, add a **separate** rule `:root:has(> body[data-bs-theme="dark"])` with the same three
declarations; never append it to the existing selector, because an unsupported `:has()` inside a
comma-separated list invalidates the whole list and takes the working selector down with it.

`data-bs-theme` is written by the **host**, never by this plugin: core writes it from
`theme_boost`'s `before_html_attributes` listener plus a head script resolving `auto` with
`matchMedia`, gated behind `theme_boost/enablecolourmodes` (**default off**, and absent entirely
before 5.3). Whether a page is dark is not server-knowable, so `classes/local/colour_mode.php` is
**constants only** — `HOST_ATTRIBUTE`, `DARK`, `LIGHT`, `MEDIA_OPTIN_ATTRIBUTE` — and deliberately
exposes no `is_dark()`-style helper. Only Behat may name the attribute, because a scenario exercises
the host's side of the contract; a test fails the build if any shipped file writes it.

**The `@media (prefers-color-scheme: dark)` block is written and deliberately inert.** Every selector
in it is gated on `[data-dimensions-media-optin]`, which nothing in either plugin ever writes.
Switch-on is **one edit**: delete `[data-dimensions-media-optin]` from that selector, nothing else.
It is inert because the OS preference is the wrong signal on its own — core treats it as an *input*
to `data-bs-theme`, never as an independent trigger, so firing on it directly would override an
explicit user choice and would let the plugin be dark inside a light page. **That exact defect used
to ship here**: the WCAG contrast panel carried its own live `prefers-color-scheme` rules and flipped
from the OS preference while the rest of the Moodle page stayed light. Preferred retirement, once
`$plugin->supported`'s **minimum** reaches 503: delete the block outright, since core resolves `auto`
itself from 5.3 on.

**Two names are transport, not tokens.** `--dimension-custombgcolor` and
`--dimension-customtextcolor` carry *admin instance data* — a colour a site chose. They are
**written by a template** (`block_dimensions`' competency card, and this plugin's own
`hero_header.mustache`) onto the element being painted, and read from there by `styles.css`. They
are never declared in the token block and never re-pointed in the dark rule, so a site's chosen
colour stays its colour in both modes and only the neutrals around the island adapt. Inside a
**branded island** nothing may read a mode token: "adapt" there means relative to the admin's
colour, not to the page — which is also why the hero's photo overlays and the glass chips
composited over an admin colour stay literals, under a named exemption.

**Usage rules the tokens cannot enforce by themselves** (all measured, all with a test):
`surface-inset` is a platter a control sits **in**, not a ground for coloured ink — in dark,
`accent` and `brand-ink` are 4.50:1 on it and `danger-ink` 4.20:1. `ink-faint` is for
inactive-control text and placeholders only (2.70:1 on `surface-inset` in **light**, riding WCAG
1.4.3's incidental exception); never a border, fill or icon. `brand-fill` is a solid fill under
`on-brand-fill` and nothing else — `--bs-primary` does not flip. Meaning rides `-ink` / `-tint` /
`-edge`, which is what core's own `.alert-*` is built from.

**Focus is one converged indicator: `outline: 2px solid var(--local-dimensions-focus-ring)` with
`outline-offset: 2px`.** The ring chains `--bs-emphasis-color`, **not** `--bs-focus-ring-color`:
core's own focus colour does not flip (measured, the same rgba in both modes, 1.02:1 on the dark
page), and a 3:1 obligation cannot be delegated to a colour the site owner picks either
(`--bs-link-color` is 2.33:1 on the fleet theme's dark card). Never draw a focus ring in the brand
colour or in a literal, and never let a `box-shadow` be a focus indicator's only signal — Windows
High Contrast Mode renders no `box-shadow` and does not restore an author's `outline: none`.

### The enforcement suite (`tests/local/colour_tokens_test.php`)

Twenty arms, every one mutation-checked. Prose has failed at this repeatedly in this fleet; these
are what actually hold the design. What each pins:

- `test_no_colour_literal_outside_the_token_block` — the literal ban, **checked both ways**: an
  unexempted literal fails, and so does an exemption matching nothing, so the allow-list cannot rot
  into a blanket as rules are deleted around it.
- `test_token_block_declares_exactly_the_contract` — equality on the declaration *string*, not a
  shape regex; the terminating literal is the whole of the plugin's Moodle 4.5 behaviour.
- `test_token_suffixes_match_the_family_contract` — the parity floor, needing no sibling checkout.
- `test_token_block_is_identical_to_the_sibling` — declarations compared under a prefix sentinel,
  with a residue check so an unprefixed name cannot pass by looking the same on both sides.
- `test_ci_checks_out_the_family_sibling` — the anti-vacuity control for the one above.
- `test_dark_activation_block_assigns_only_plugin_owned_tokens` — the three-token bound.
- `test_activation_selectors_are_root_anchored` — the `:root` anchor and the absence of
  `.theme-dark`.
- `test_media_fallback_is_written_and_unreachable` — three independent assertions: the block
  **exists**, every selector carries the gate, and no runtime file writes the gate attribute.
- `test_no_low_contrast_ink_on_the_inset_surface` — resolves the **effective** background through a
  selector-ancestry map, so the ancestor-background / descendant-colour split cannot slip past; a
  rule with no resolvable font-size counts as normal text, never large.
- `test_declared_values_clear_their_floor` — values read **out of the stylesheet** and followed one
  hop against core's measured `--bs-*` map, in all three resolutions (5.x light, 5.x dark, 4.5).
- `test_focus_indicators_survive_forced_colors` and `test_focus_ring_is_never_brand_coloured`.
- `test_admin_colours_are_never_declared_by_the_mode_layer`, `test_admin_colour_transport_is_intact`
  and `test_branded_islands_use_no_mode_token` — the branded-island contract.
- `test_plugin_never_writes_the_host_signal`.
- `test_ink_faint_is_only_inactive_text`.
- `test_every_token_read_is_declared` — the typo net. **An unresolved `var()` does not fall back to
  anything**: the whole declaration is invalid at computed-value time, so a mistyped token name is
  not a wrong colour, it is *no* colour, and every gate in the pipeline is blind to it.
- `test_favourite_state_is_not_carried_by_colour_alone` — the star swaps `fa-star` / `fa-star-o`.
- `test_icon_only_controls_are_named`.

### Two traps no gate can see

**1. The hero's admin text colour had never worked.** `styles.css` read
`--dimension-customtextcolor` five times and **nothing wrote it**, so an admin's chosen hero text
colour was inert for the life of the feature. `templates/hero_header.mustache` now emits both
custom properties on the element it paints, and `test_admin_colour_transport_is_intact` pins both
halves of the transport. Nothing else in the pipeline can see that chain: phpcs does not read
Mustache, the mustache lint validates markup rather than cross-file cascade, and stylelint never
opens a template. **Any new template-to-stylesheet custom-property transport needs a test of its own
for the same reason.**

**2. `hero_header.mustache`'s inline `background-color` must stay inside its `{{^hasbgimage}}`
guard.** Both flags are a real, designed-for simultaneous state, and the wrapper already handles it
with an overlay. Drop the guard and an opaque inline `background-color` paints over the admin's
photograph — **and it cannot be mitigated from CSS**: a class selector never outranks an inline
`style` attribute and `!important` is banned fleet-wide. A comment claiming otherwise
(`background-color: transparent; /* Override inline style background */`) was in the file and was
simply wrong. The guard's presence is asserted by the same test.

### The design kits document core's `--mds-*` names — never ship them

`docs/design-kit/tokens.html` names core's palette under `--mds-*`. That is **core's** design-system
namespace: Moodle 5.2 ships `theme/boost/scss/design-system/` defining `$mds-*`, 5.1 has no such
directory, and 5.3 LTS brings MDS React. Naming them **in documentation** is legitimate and
deliberate — it keeps the kit legible against core's own vocabulary. **Declaring them in shipped CSS
is not**, and on `:root` it squats a namespace core is actively expanding, globally. The plugin's own
motion tokens were renamed away from `--mds-*` for exactly this reason on 2026-08-06. Do not
"finish the job" by moving that block into `styles.css`. `docs/learner-kit/token-migration.md` is a
**historical** record of two migrations, not a description of the current stylesheet: its line
numbers and its "where the value lives today" column are superseded, and its header says so.

## Coding style

### File header
Every PHP file starts with the GPL block, then a file docblock with
`@package local_dimensions`, `@copyright`, `@license` (no `@author`).
Namespaced class files add `namespace local_dimensions\<sub>;`. Use
`defined('MOODLE_INTERNAL') || die();` in every file **with side-effects**
(procedural files, `db/*.php`, files with `require_once`/globals). **Omit** it in
pure namespaced single-class files (constants/enums/handlers with no
side-effects) — the sniff `moodle.Files.MoodleInternal.MoodleInternalNotNeeded`
fails the build otherwise. (This plugin's classes do not use
`declare(strict_types=1)`; match the surrounding files.)

### PHPDoc (`phpdoc --max-warnings 0`)
- Every class, method, property, constant has a `/** */` docblock; `@param`,
  `@return`, `@throws` declared explicitly even when implied by the signature.
- **`@param` array types must be plain `array`** — `local_moodlecheck` can't pair
  `$var` to its parameter when the type is a generic (`array<int,string>`) or a
  shape (`array{...}`), and reports "incomplete parameters list (error)". Put the
  shape in the description prose. `@return array{...}`/`array<…>` is fine (no var
  to pair).
- Property docblocks need `@var` even with typed properties
  (`moodle.Commenting.VariableComment.MissingVar`).

### Naming
- Classes/methods: `lower_snake_case` (Moodle, not PSR-4 PascalCase).
- Constants: `UPPER_SNAKE_CASE`. Properties: single lowercase word where possible.
- Frankenstyle prefix on globals/functions: `local_dimensions_*`.

### CodeSniffer rules that routinely bite (pre-empt at write time)
1. **Variables are lower-case only** — no camelCase/snake_case
   (`...ValidVariableName.VariableNameLowerCase`). `$courseid`, not `$courseId`.
2. **PSR-2 multi-line calls** — `(` last on its line, one arg per line, `)` on its
   own line at call indent.
3. **Inline `//` comments**: one space, capital first letter, terminal
   punctuation. Lowercase-start / version-tagged / multi-line commentary belongs
   in a `/* … */` block (`moodle.Commenting.InlineComment.*`). The same applies to
   the leftover checker — never type to-do or merge-conflict tokens literally.
4. **Operator spacing**: exactly one space around `===`/`!==`/`?`/`:` — column
   alignment with extra spaces fails (`Squiz.WhiteSpace.OperatorSpacing`).
5. **Multi-line `if`**: first expression on the line after `(`, `)` on its own
   line (`PSR12.ControlStructures.ControlStructureSpacing.*`).
6. **Line length**: hard max **180** (error), soft max **132** (warning, and the
   warning count fails `phpdoc --max-warnings 0`). Wrap long `@return` shapes.
7. **No "commented-out code"** false positives: drop trivial trailing `//`
   comments containing `=` or PHP-looking text (`Squiz.PHP.CommentedOutCode`).

phpcs has no local runner here, so rules 3 and 6 are the ones that slip through
eyeballing and fail CI. **Before pushing, grep the changed PHP for both** — every
hit is a CI failure (`phpcs --max-warnings 0`):
```sh
# soft-max 132 line length (rule 6)
awk 'length($0)>132{print FILENAME":"FNR" ("length($0)")"}' <files>
# inline // comment starting lowercase — first line of a block only; continuation
# lines whose first line is capitalised are fine (rule 3)
grep -nE '^\s*// [a-z]' <files>   # ignore the GPL header lines 5-15
```

### Dynamic string references
The string checker can't verify constructed IDs. **Don't**
`get_string('foo_' . $x, …)` — use a literal `switch`/`match` returning each
fixed key.

## Lang strings
`lang/en/local_dimensions.php` and `lang/pt_br/local_dimensions.php` are kept in
**sync** and **alphabetically sorted** (the `validate` step enforces ordering).
Conventions here: a setting uses plain keys `<key>` + `<key>_desc` (e.g.
`enablereturnbutton` / `enablereturnbutton_desc`); each cache definition has a
`cachedef_<name>`; each capability a `dimensions:<capname>` (the `local/` prefix
is dropped in the lang key). When adding a string, insert it in the correct
alphabetic slot in **both** language files.

## Web services
- Function classes under `classes/external/`, one per file, extend
  `\core_external\external_api`; `execute_parameters()` →
  `external_function_parameters`, `execute_returns()` → an external structure.
- Every read function does `validate_context()` + `require_capability()`; writes
  add an event. Register in `db/services.php` (`type` read/write, `ajax => true`)
  — **services install only on upgrade, so a new function needs a `version.php`
  bump.**
- A WS that emits localised strings must include `current_language()` in any
  cache key.
- **`execute_returns()` is an allowlist**: `clean_returnvalue` silently strips
  keys the structure doesn't declare. When a shared builder (e.g.
  `helper::structure_nodes()`) gains a field, update the returns of **every**
  WS that channels it (`browse_structure` etc.). Symptom: server-rendered rows
  carry the new data, WS-rendered ones (lazily-fetched children) don't — e.g.
  drag grips appearing only on root nodes.

## MUC caches (`db/caches.php`)
Cache **keys must avoid `:`** (unsafe in file-store paths). This plugin's keys:
`returncontext` → `course_{id}`, `*_scss` → `css_{id}`, `plan_trail` →
`{planid}_{userid}`, metadata caches → bare id. Application caches use defensive
TTLs + `staticacceleration`; session caches hold per-user transient state. Each
definition needs a `cachedef_<name>` lang string.

## Mustache templates
Every `templates/*.mustache` needs an `Example context (json):` block in its
docblock — the Mustache lint renders against it and validates the HTML (supply
non-empty loop data so the preview produces valid markup). **Never write a `{{…}}`
tag inside the `{{! … }}` docblock** (e.g. describing the template with
"strings via `{{#str}}`"): Mustache comments don't nest and close at the *first*
`}}`, so the `}}` of the embedded tag ends the comment early and the rest of the
docblock (incl. `Example context (json): { }`) renders as visible text. Describe
tags in prose without the braces. Use triple-stash
`{{{html}}}` only for trusted server-rendered HTML (e.g. `moodleform::render()`).
Server-side rendering uses `renderable` + `templatable` + `render_from_template`
— **zero `html_writer`** in plugin code (moodleform's own markup excepted).

**An inline `style` attribute is the one thing CSS cannot take back.** A class selector never
outranks it and `!important` is banned fleet-wide, so a conditional inline declaration is a
cross-file contract with the stylesheet that no gate reads. `hero_header.mustache` holds two of
them and both are pinned by `colour_tokens_test::test_admin_colour_transport_is_intact`: the
`{{^hasbgimage}}` guard on `background-color` (without it an opaque colour paints over the admin's
hero photograph), and the two `--dimension-custom*color` custom properties the stylesheet reads —
one of which nothing wrote for the life of the feature. See "Colour tokens and dark mode" above.

## Forms (moodleform)
Form classes under `classes/form/` start with
`require_once($CFG->libdir . '/formslib.php')` (moodleform isn't autoloaded). The
submit-button label must differ from any collapsible section-header label (a11y +
Behat target the header toggle otherwise). Populate rich-text/editor fields via
`set_data()` (form-level), not `setDefault()`, so TinyMCE initialises with text +
format.

## Upgrade savepoints
Each `db/upgrade.php` step ends with
`upgrade_plugin_savepoint(true, <version>, 'local', 'dimensions');` — match
`<version>` to the `version.php` bump.

## PHPUnit tests
- `tests/<area>/<thing>_test.php`; class
  `local_dimensions\<ns>\<thing>_test extends \advanced_testcase`; `@covers`
  annotation on the class docblock; `$this->resetAfterTest()` in any DB test.
- `$DB->get_records()` / `getDataGenerator()->create_*()` return **string** ids
  under both drivers — cast to `(int)` for typed-int signatures and normalise
  haystacks before strict `assertContains`.
- Two suites are **file scanners** on `\basic_testcase`, not DB tests, and they exist because the
  pipeline is structurally blind to what they check: `local/colour_tokens_test.php` (the colour
  token contract, 20 arms) and `local/bootstrap_compat_test.php` (Bootstrap 4/5 class vocabulary
  and badge text colours). Nothing in phpcs, the mustache lint or stylelint reads a class name out
  of a Mustache or JS file, or a custom property across a template/stylesheet boundary. **Every
  assertion in them was mutation-checked**, and that is not ceremony: earlier drafts of
  `bootstrap_compat_test` passed while blind to the very defect they were written for. When you
  add an arm, delete the production line it guards and confirm it actually reddens.

## Behat (JS) — locator gotchas
Run locally with `mdl behat m501 /var/www/html/public/local/dimensions/tests/behat/<x>.feature`
(the ABSOLUTE container path — the relative forms match nothing) after `mdl behat-init m501`;
keep scenarios as thin smoke tests and put the logic in PHPUnit. A scenario that lands on a
Moodle exception page fails at that step whatever it asserts (Behat's after-step hook throws
on exception pages), so refusals belong in PHPUnit. `tests/generator/` provides
`"local_dimensions > frameworks"` / `"templates"` rows with a `category` idnumber column, the
only way to create competency objects in a course category from a feature.

**Step definitions are SITE-GLOBAL, and two plugins declaring an identically-worded step is a hard
failure that takes out BOTH plugins' entire suites.** Behat loads every installed plugin's context
file into one suite, so a duplicated `@Given` regex is an ambiguity error, not a shadowing — and it
kills the run, not the scenario. **Measured on m502, not theorised.** `local_dimensions` and
`block_dimensions` both needed colour-mode steps, so their wordings diverge on purpose and must
stay diverged:

| here (`local_dimensions`) | sibling (`block_dimensions`) |
|---|---|
| `the page colour mode is "<mode>"` | `the host colour mode is "<mode>"` |
| `I remember the page background colour` | `I remember the host page background colour` |
| `the "<sel>" element background should still match the page` | `… should still match the host page` |
| `the "<token>" colour token should resolve to "<value>"` | `… should resolve to "<value>" on the host` |
| `the site has a host colour mode` | `the host ships a colour mode` |

Before adding **any** step here, grep the sibling's `tests/behat/behat_block_dimensions.php` for the
wording. The same applies in reverse, and the cost of getting it wrong is paid by whichever plugin
did not change.

`tests/behat/colour_mode.feature` is the plugin's dark-mode coverage. Two things about it are
deliberate and must survive edits. It sets **`themedesignermode`**, because Behat saves the compiled
theme CSS at init and restores it around every run
(`lib/behat/classes/util.php::restore_saved_themes`) — measured: with the theme cache in play,
deleting the **whole** dark activation block left all four scenarios green, a suite certifying
nothing. And its first three scenarios assert a **relative** invariant (the plugin surface equals
the page surface), so they are correct on every supported branch with no skip and no branch tag,
including 4.5 which ships no dark palette at all; only the fourth needs a real dark palette and it
**detects one at runtime** rather than guessing from `$CFG->branch`. Do not "simplify" either into
an absolute colour assertion or a version gate.

Hard-won:
- **Autocomplete:** pick a value with **only** `I set the field "<label>" to
  "<text>"` — it types, clicks the auto-activated suggestion and presses ESC
  (`behat_form_autocomplete::add_value`). A following `I click on "…" item in the
  autocomplete list` hits a now-hidden `<li>` → `ElementNotInteractableException`.
- **Confirm dialog:** `… "button" in the "<X>" "dialogue"` matches the modal by its
  **title** (the first arg of `Notification.saveCancelPromise`/`deleteCancelPromise`),
  not the word "Confirmation".
- **Checkbox:** the `"checkbox"` named selector needs a real `<label>` (for/wrapping),
  **not** `aria-label`.
- **Progressive-disclosure UI:** controls inside dropdown menus or collapsed panels
  exist in the DOM but are **not interactable** — the step dies with
  `ElementNotInteractableException` (no retry). Open the container first: the ⋯
  overflow menu ("More actions"), a row kebab ("Actions" scoped to the
  `"list_item"`), the "Add competency" panel, the "Add to filter" picker. After
  any pane reload (add/remove/edit) collapsed panels **re-collapse** — re-open
  before the next interaction, and put a `Then I should see` barrier after the
  reload so the click doesn't race the re-render.
- **Icon-only buttons:** give them an `aria-label`; the `"button"` named selector
  matches it (Moodle 3.11+). Disambiguate per-row toggles by scoping to the row's
  `"list_item"`.
- **aria-labels that embed the row name hijack name-based clicks:** the button
  selector matches `aria-label` by `contains()` and takes the **first
  document-order hit** — a hover-revealed helper (opacity: 0 is still
  WebDriver-interactable!) whose label is "Move to position…: {name}" placed
  before the real row control steals `I click on "{name}" "button"`. Put such
  helpers **after** the main control in the DOM and pull them left visually
  with flex `order: -1` (see the drag grips).
- **Reworking a tab's UI breaks its `.feature` steps** — grep `tests/behat/` for
  every label/button you move, rename or collapse, and fix the scenarios in the
  same commit (this cost a full CI round on the Plans-tab redesign).
- Don't Behat tree expand / infinite scroll / shift-select / drag-drop (headless-fragile)
  — cover them in PHPUnit at the data layer.

## Hub front-end (AMD / modals)
New `amd/src` is **ESM**; the hub is **zero-YUI** — reused legacy YUI components
(`tool_lp/competencypicker`, `competencyruleconfig`) break embedded here, so wrap core
web services in a native `core/modal*` instead.
- **Autocomplete in a modal:** enhance on the `ModalEvents.shown` event —
  `core/form-autocomplete` `enhance()` resolves the element via `document.querySelector`,
  which finds nothing before `modal.show()` attaches the modal. A single-select autocomplete
  has no clear API → re-render the body to reset it.
- **Exclude list:** read `data-exclude` via `element.dataset` (fresh per search) in your own
  datasource; `core/form-cohort-selector` caches it via jQuery `.data()`.
- **Raw `<select>` chevron:** `form-select`, which the plugin polyfills for 4.5 (see
  the Bootstrap vocabulary rule below); never `custom-select`.
- **Bootstrap 4 vs 5 — BS5 *class names* are NOT bridged either.** The bridging is
  asymmetric, and this is the single fact to remember: **BS4 names resolve on both
  branches, BS5 names resolve only on 5.x.** Moodle 4.5's forward bridge
  (`theme/boost/scss/moodle/bs5-bridge.scss`) is **116 lines** and covers only
  `g-0`, `btn-close`, the `ms/me/ps/pe` spacers and `float/text/border/rounded-start/end`.
  Moodle 5.x's backward bridge (`theme/boost/scss/moodle/bs4-compat.scss`) is **1009
  lines** and covers ~38 BS4 names. Measured on the running 4.5 stack, these resolve to
  **nothing**: `visually-hidden`, `form-select`, `form-select-sm`, `gap-*`, `fw-*`,
  `font-monospace`, `form-switch`, `form-label`.
  The fix is **not** to write BS4 names — 5.x already wraps every one of them in
  `@include deprecated-styles(...)` and Moodle 6.0 deletes `bs4-compat.scss`
  (MDL-84465). The fix is the **Bootstrap 4 utility polyfill** block at the tail of
  `styles.css`, which defines those families for 4.5, scoped to the plugin's own
  surfaces. Add a family there before using it; `tests/local/bootstrap_compat_test.php`
  fails the build on a BS5 utility the polyfill does not cover.
- **Bootstrap 4 vs 5 — JS data attributes are NOT bridged:** Moodle 4.5 runs
  Bootstrap 4, whose data-API listens on `data-toggle`; 5.x listens on
  `data-bs-toggle`. Components wired via markup (dropdowns etc.) need **both**
  attributes side by side, and both alignment classes
  (`dropdown-menu-right dropdown-menu-end`). Symptom of forgetting: the toggle
  clicks fine but the menu never opens on 4.5 — CI's 4.05 Behat leg catches it
  as `ElementNotInteractableException` on the menu item.
- **`[hidden]` vs `.d-block`:** `.d-block { display:block !important }` overrides `[hidden]`;
  to toggle via `el.hidden` use a plain block (`<div>`). `.form-check` adds `margin-left:-1.5em`
  to its input (overlaps a preceding chevron) — use a plain `d-flex` row for custom rows.
  That rule stands, but it has a 4.5 consequence: an unwrapped `.form-check-input` is
  `position:absolute; margin-left:-20px` under Bootstrap 4 (BS5 leaves it in flow), so it
  escapes the row and overlaps its neighbour. Do **not** fix that by wrapping — the
  polyfill block restores in-flow layout for any `.form-check-input` whose parent is not
  a `.form-check`, which serves both branches with one rule and no markup change.
- **Feedback in modals (house pattern):** for success/error/info messages fired from inside a
  `core/modal`, **host a toast region in the modal body** so `core/toast` renders *above* the
  dialog. The page-level `.toast-wrapper` is `z-index:1051` (below the modal's `1055`), so a toast
  fired from a modal lands behind it. On `ModalEvents.shown` call
  `addToastRegion(modal.getBody()[0]).catch(Notification.exception)` (from `core/toast`); core's
  `core/modal` auto-removes it on close (`removeToastRegion(this.getBody())`), so no leak and **no
  global z-index override**. The **host** modal owns the region — modules that only `mount()` into
  it (`cohort_manager`, `participants_users`) must not add their own. For an *in-place* change (a row
  added/edited without a full list reload) also briefly **flash** the affected element
  (`el.animate([{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}], {duration: 1500})`)
  so the confirmation is visible where the user is looking. JS-built `<select>`/inputs need an `id`
  or `name` or the browser logs an autofill warning. (Wired in `competency_links` +
  `participants_manager`.)
- **dataset-as-truth panes:** seed `pane.dataset.<arg>` from the server-rendered selected value
  in `init`, or a WS receives 0 → `context::instance_by_id()` "Invalid context id".
- **PHP:** `array_flip([5])` → `[5 => 0]`, so `!empty($map[5])` is **false** — test membership
  with `isset`, or build an explicit `[$id => true]` map.

## Cross-DB SQL
CI runs PostgreSQL and MariaDB. Avoid `SELECT :literal FROM t` (PG infers text);
avoid `ORDER BY … NULLS FIRST` (use `COALESCE(col, 0)`); cast numeric columns
read from the DB to `(int)`/`(float)` when typing matters.

## Git / version.php
The plugin repo (`main`) is separate from the Moodle checkout it's built inside —
run git from the plugin dir (or `git -C`), since `cd` doesn't persist between Bash
calls. When rebasing/cherry-picking conflicts on the `version.php` `$plugin->version`
line, keep the **higher** number so the upgrade still triggers.

## When in doubt
Follow the patterns in existing files. The codebase is internally consistent —
if a new file feels like it matches no existing shape, re-examine the approach.
