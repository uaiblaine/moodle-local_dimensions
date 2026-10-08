# Claude instructions for `local_dimensions`

This file is auto-loaded whenever Claude works in this plugin's tree. **Fleet-wide standards
live in `~/dev/CLAUDE.md`** and are not repeated here. This file keeps the rules that are true
for this plugin, each with its one-line reason; the measurements and incidents behind them are
in `docs/CLAUDE-long-form.md` (the previous version of this file, kept whole, not auto-loaded).
Read its named section when a task touches that area, and record a new lesson there with a
one-line rule here.

Plugin context: a Moodle **local** plugin ("Competency Dimensions") that extends core
competencies and learning plan templates with custom fields and renders two learner views, the
**Competency tracker** (course-card grid) and the **Full plan overview** (accordion), plus a
draggable "Return to Plan" FAB and the admin **Competency hub** (`central.php`). **No own
tables**: everything lives in core competency tables and `customfield_data`. Supports Moodle
**4.5 through 5.2** (`$plugin->supported = [405, 502]`); CI is the MAH reusable workflow, one
job per supported branch in `.github/workflows/ci.yml` (update them when `supported` changes).
Development on m501/m502; mounted on m405, m501, m502 at `local/dimensions`.

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

```sh
mdl ci moodle-local_dimensions --only phpcs,phpdoc,mustache,grunt   # static pass, one leg
mdl ci moodle-local_dimensions --matrix --behat                     # every leg GitHub runs: the pre-merge gate
mdl phpunit m501 local_dimensions                                   # or a path under local/dimensions/tests/
mdl behat m501 /var/www/html/public/local/dimensions/tests/behat/<x>.feature   # absolute container path
mdl grunt m501 local/dimensions                                     # after every amd/src edit; cachejs off still serves amd/build
mdl mutate moodle-local_dimensions mutations/<spec>.conf --fast     # both --db families; run BEFORE the commit that adds gates
```

- `amd/build/**` is tracked: rebuild and commit with the source plus a `version.php` bump.
- A `version.php` bump stales the test sites: `mdl phpunit-init` / `mdl behat-init` first.
- The 5.02 legs (GitHub and `mdl ci`) install `local_unlistedcourses`, so the real-plugin halves
  of `enrolment_provider_unlisted_test` and `enrolment_provider_test` run there and skip on 5.01
  and 4.05; `test_ci_checks_out_unlistedcourses_on_the_502_leg` fails if the job drops it.
- Cloud session (no `mdl`): `mpci` from this directory, `mpci --branch MOODLE_405_STABLE`,
  `mpci --reuse --only phpunit --filter <test>`; the owner runs the matrix before merging.
- Test zip: `git archive --format=zip --prefix=dimensions/ HEAD -o
  ~/Downloads/moodle-local_dimensions-<version>-<shortSHA>.zip` (the prefix is the install
  directory shared by all three dimensions plugins; the SHA is what tells builds apart).
- Every CI job checks out `block_dimensions` under `plugin-dependencies` and
  `colour_tokens_test::test_ci_checks_out_the_family_sibling` fails the build if one drops it
  (the token-parity test would otherwise skip silently). It is not a runtime dependency.

## Code layout

```
settings.php, lib.php, styles.css, version.php
view-plan.php / view-competency.php   learner views (plan overview; competency tracker)
central.php                           the Competency hub: admin tree entry and a course category's
                                      More-menu entry (locked to the category; design in
                                      docs/superpowers/specs/2026-09-02-central-category-context-design.md)
classes/
  hook_callbacks.php   before_footer_html_generation -> Return FAB
  helper.php           custom-field provisioning, return context, queries
  observer.php         competency event observers (cache invalidation, customfield cleanup)
  calculator.php       course/section progress and enrolment predicates
  constants.php        CFIELD_* shortnames and shared constants (never string literals)
  *_cache.php          MUC wrappers (template/competency metadata, template_course, plan_trail)
  scss_manager.php, picture_manager.php, chip_filters.php
  customfield/         competency_handler + lp_handler (singleton create(), two areas)
  event/, external/ (one WS class per file), form/ (dynamic_form), task/, reportbuilder/
  local/               plan_access, plan_status, bootstrap (BS4 body class), colour_mode
                       (constants only), enrolment_provider*, view_events, category_lifecycle
  output/              renderables: learner pages and hub tabs
  privacy/             preference-only provider (core_user owns preference deletion)
db/                    access, caches, events, hooks, services, install, upgrade, uninstall; NO install.xml
templates/, amd/src (plain AMD, no React), amd/build, lang/{en,pt_br}
docs/design-kit, docs/learner-kit   design kits; tokens.html names core's --mds-* in DOCS only
tests/                 PHPUnit incl. the file-scanner suites under tests/local/; behat/
mutations/             one spec per area (plan_access, detail_access, sec_*, followups_*, enrolment_provider*)
```

## Architecture rules

- **Custom fields.** Two handler areas (`competency_handler`, `lp_handler`); fields provisioned
  lazily under a core lock with `reset_configuration_cache()` after acquiring it; both areas
  reuse shortnames, so never `get_record('customfield_field')` by bare shortname; data rows
  carry `itemid = 0` and embedded files key on the data row id. `can_edit()` resolves in the
  instance's own context, never the site; a create-path caller must call
  `set_edit_context_hint()` or category managers see no fields. Core destroys the context before
  `competency_deleted`, so deletion cleanup sweeps `customfield_data` by instance id, never by
  context. Long form: "Custom-field auto-provisioning".
- **Return FAB.** Renders only for a logged-in non-guest, a course in context, a pagelayout
  allowlist (`course`/`incourse`) minus an admin pagetype blocklist, and a stored return
  context; every stored `view-competency.php` URL carries `noredirect=1` (anti-loop); contexts
  are written only for the plan's own user; `init()` steps the button out of a hidden
  `#region-main` (format_mtube) one ancestor at a time. The tracker's own return button is
  separate and gated on `plan_overview_is_routed()`, which must agree with
  `block_dimensions`' `resolve_plan_display_context()`; never give a learner view a
  course-content pagelayout with a course in context, and never add `related` to the tracker's
  `set_url`. Long form: "Return-to-Plan FAB".
- **Category deletion**: `category_lifecycle` answers all five core callbacks; the in-use check
  reads the whole subtree because the parent's callback deletes before any child can refuse;
  adding a callback needs a `version.php` bump.
- **A completed plan reads `{competency_usercompplan}`** (the archive) scoped to the plan, under
  a separate cache key (`_c`); `invalidate_plan()` and `invalidate_user()` delete both.
- **Caches**: a new query over cached metadata gets its invalidation in `observer.php`, not a
  TTL. Keys never contain `:`; `plan_trail` is application mode on purpose (the staling change
  happens in another user's request); a new definition needs a `cachedef_<name>` string whose
  words match its mode and a `version.php` bump.
- **Audit events** need no registration; `objecttable` over a core table requires `objectid`
  (fetch rows before deleting); core APIs returning `false` on the idempotent path must not reach
  a trigger. The `*_customfields_updated` events fire from `instance_form_save()` and diff
  effective values.
- **View events are core's**, logged before `$OUTPUT->header()`; core has two
  competency-in-plan events (`user_competency_plan_viewed` for a completed plan,
  `user_competency_viewed_in_plan` otherwise) and the wrong one fails with
  `dml_missing_record_exception`, not `coding_exception`. The accordion logs from JS once per
  (plan, competency) per page load, chained after the summary renders, separately from the data
  cache. Behat log counts poll.
- **Competency detail data** in `accordion.js`: `detailPanes` (DOM), `competencyData` (fetched
  once per page; `request_review` forgets the entry) and `ruleData` are three maps that never
  absorb each other; a layout switch is checked twice because the string fetch is a second round
  trip. Long form: "Competency detail data".
- **Detail access (`candetail`)**: core's `can_read_user()` ignores the draft capabilities, so
  a draft-only reader sees the list and no detail; the template drops the region, the toggle
  returns on a missing `aria-controls`, `openDetailModal()` returns before `Modal.create()`.
  Spec `mutations/detail_access.conf`.
- **Reading a plan (`plan_access`)**: never wrap `api::read_plan()` in a catch that reports
  `invalidplan`; only `dml_missing_record_exception` means no plan. `competency_scope()` decides
  which competencies a plan reaches (own, related when both `showrelated*` resolve and the
  viewer holds `competencyview`, rule children); `require_owner_readable()` before describing
  the owner. The tracker describes the plan OWNER (filter, progress, completion) while the lock,
  enrol and pending state are the VIEWER's. Specs `sec_scope`, `sec_courses`, `followups_T1/T2`.
- **Enrolment state of the cards** (`local\enrolment_provider`, stage 5c; design record
  `moodle-dev/docs/enrolment-status-matrix/`): the rule lives in `local_unlistedcourses`, never
  here. `enrolment_provider_core` is FROZEN; `enrolment_provider_unlisted` only translates the
  plugin's API behind `unlisted_available()` (branch ≥ 502, every method present) and maps its
  constants from LITERALS, because the plugin is absent on 4.5/5.1 and a constant of a missing
  class throws. Neither provider decides the lock (core's `is_enrolled()` does, D7); a provider is
  asked only about a locked card, for `$USER`, with the viewer handed in; providers return
  facts and `enrolment_state::export()` is the one presenter; a route line goes only to a
  relationship; state colours are aliases of the family tokens. Specs
  `enrolment_provider.conf` (`--fast`) and `enrolment_provider_stack.conf` (stack, with the
  plugin).

## Colour tokens and dark mode

- `styles.css` declares **34 colour tokens on `body`** (byte-identical suffixes to
  `block_dimensions`), 30 of them `var(--bs-NEW, var(--BS4-OLD, #literal))`; **a colour
  literal outside the block fails the build**, and none of those 30 gets a dark rule (core's
  dark palette already flips them). Only `shadow`, `scrim`, `favourite` carry a plugin dark
  value, in the one activation rule `body[data-bs-theme="dark"], [data-bs-theme="dark"] body`
  (`body` as subject keeps a navbar-scoped attribute out; `.theme-dark` is never accepted).
  The host writes `data-bs-theme`; the plugin never does (`colour_mode.php` is constants only).
  The `prefers-color-scheme` block is written and inert behind `[data-dimensions-media-optin]`.
- `--dimension-custombgcolor` / `--dimension-customtextcolor` are admin instance data written
  by a template onto the painted element, never tokens; a branded island reads no mode token.
- `surface-inset` is a platter, not a ground for coloured ink; `ink-faint` only for inactive
  text; `brand-fill` only under `on-brand-fill`. Focus is one `outline` chained on
  `--bs-emphasis-color`, never brand-coloured, never a `box-shadow` alone.
- `tests/local/colour_tokens_test.php` (21 mutation-checked arms) holds all of it; the hero's
  inline `background-color` stays inside `{{^hasbgimage}}` and every template-to-stylesheet
  custom-property transport needs its own test (nothing in the pipeline reads that chain).
  `docs/design-kit/tokens.html` names `--mds-*` in documentation only; never in CSS.
  Long form: "Colour tokens and dark mode".

## Conventions specific to this plugin

- Lang keys: `<key>` + `<key>_desc` for settings, `cachedef_<name>`, `dimensions:<capname>`;
  `tests/lang_string_references_test.php` checks every literal key exists in both files, so
  grep before deleting one.
- Web services: `execute_returns()` is an allowlist; when `helper::structure_nodes()` gains a
  field, update every WS that channels it.
- Forms: `require_once($CFG->libdir . '/formslib.php')`; a submit label differs from any
  section header label; editors populated through `set_data()`.
- PHPUnit: five file-scanner suites on `\basic_testcase` under `tests/local/`
  (`colour_tokens_test`, `bootstrap_compat_test`, `stylesheet_markup_contract_test`,
  `status_icons_test`, `preference_queries_test`) plus `customscss_field_styles_test`,
  `hub_javascript_guards_test` and `export_loader_script_test`; every arm was mutation-checked
  and a new arm must be (delete the production line, confirm the red). `@covers` stays a
  docblock while 405 is supported.
- Behat: step definitions are site-global, and a step worded like `block_dimensions`' kills
  both suites; the two plugins' colour-mode steps diverge on purpose (table in the long form).
  `colour_mode.feature` sets `themedesignermode` (Behat restores the compiled theme CSS around
  runs) and asserts relative invariants, with the dark palette detected at runtime. Locator
  rules in the fleet file; a hover-revealed helper whose `aria-label` embeds the row name steals
  name-based clicks, so it sits after the main control in the DOM. Long form: "Behat (JS)".
- Hub front end: ESM, zero YUI; autocomplete enhanced on `ModalEvents.shown`; `form-select`
  (polyfilled on 4.5), never `custom-select`; BS5 utilities only through the polyfill block at
  the tail of `styles.css`, gated on `body.local-dimensions-bs4`; both `data-toggle` spellings;
  toasts from a modal go to a region in the modal body; `flashRow()` for in-place changes;
  `pane.dataset` seeded from the server-rendered value; `array_flip([5])` makes
  `!empty($map[5])` false, test membership with `isset`.
- One branch serves every supported Moodle version until the 5.3 work starts; the polyfill
  already runs ~200 lines, so the branch split is due with that work.

## MDL Shield reviews

Manual only (`!mdlshield review` / `review full`, by the owner), 100 reviews a month shared by
every repo, 3,000 effective lines soft limit (`mdl mdlshield size`). Config and context read
from `main` only; `moodle.versions: ["5.2"]` although the plugin runs on 4.5 and 5.1;
`fail_on.severity: high`. Ask for a review early on any change touching plan access, the two
card services, the hub's web services or the custom SCSS field, after the `security-diff-read`
skill. Rules and reasons: fleet file, "MDL Shield reviews".

## When in doubt

Follow the patterns in existing files; the codebase is internally consistent. If a new file
matches no existing shape, re-examine the approach.
