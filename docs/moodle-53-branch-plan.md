# Moodle 5.3 and one branch per Moodle version: local_dimensions + block_dimensions

Status: IN PROGRESS. The owner approved D1 to D5 as recommended (2026-10-15). D3 follows from the
core's PHP floors: PHP 8.3 on 5.3.

Baseline (S0.1, from the last green GitHub runs; 38475995002 for local, 38461284117 for block):
- local: 693 tests on every leg (27 skipped on 4.5), and 31 Behat scenarios (1 skipped on 4.5).
- block: 154 tests, and 18 scenarios (1 skipped on 4.5).

S0.3 found two repairs that are unsafe to run on every upgrade:
- The enrolment-filter sync compared the option by its label in the upgrader's language.
- The shortname rename would also rename a field an administrator created later under an old
  name.

So `local\legacy_repair` runs those two **once per site**, recorded in the plugin setting
`legacyrepairsapplied`. `db/install.php` records them on a new site. The sync now counts lines
instead of reading the label. The security read also flagged the dropped
`template_metadata_cache::purge_all()` calls; core refutes that: `upgrade_noncore()` runs
`cache_helper::purge_all()` and `purge_all_caches()` after plugin upgrades, on 4.5 and 5.3.

The matrices run from a clone, `~/dev/moodle-dev-dimensions`, branch
`dimensions-branch-per-version`. It holds the per-branch `plugins.conf` lines and
`extra_deps_for`. Editing the live `plugins.conf` would reach the shared stacks on any session's
next `mdl up`, because `mdl up` regenerates the mounts. Until the merge, the 5.3 lines point at
the `-503` worktrees.

## 1. Where we start (measured 2026-10-14)

| | local_dimensions | block_dimensions |
|---|---|---|
| `main` head | `aedee74` (#61, `[skip ci]`) | `cd5e083` (#22, `[skip ci]`) |
| version / release / maturity | `2026101301` / v1.0 / BETA | `2026101300` / v2.0 / STABLE |
| requires / supported | `2024100700` / [405, 502] | `2024100702` / [405, 502] |
| dependencies | none | `tool_lp`, `local_dimensions => 2026092200` |
| CI jobs | 502 (full), 501, 405 | 502 (full), 501, **500**, 405 |
| sibling checkouts in CI | block `main` (all), enrol_apply `master` (501, 502), unlistedcourses `main` (502) | local `main` (all) |
| upgrade steps | `2026031101` ... `2026073100` + catch-all field provisioning | none (`db/` has no upgrade.php) |
| mounts (`plugins.conf`) | `auto` | `auto` |
| MDL Shield `moodle.versions` | `["5.2"]` | `["5.2"]` |
| unmerged remote branches | none | none |

Facts that shape the plan:

- **Core PHP floors** (`public/admin/environment.xml`): 4.5 needs PHP 8.1, 5.1 needs 8.2, 5.2 and
  **5.3 need 8.3**. The 5.3 branch can raise the floor from 8.1 to 8.3, and no higher: a 5.3 site
  may run 8.3, and the 5.3 CI legs run 8.3. PHP 8.4 syntax (property hooks, asymmetric visibility,
  `new Foo()->bar()` without parentheses, `array_find`) stays out.
- Moodle 5.3 is released (`5.3 (Build: 20261005)`, `MATURITY_STABLE` on m503).
- **Every per-version number is lower than what the stacks have installed** (`2026101301` /
  `2026101300`), 5.3's `20261005XX` included. The switch is a downgrade of the version row, done
  with the feedback_tracker 1.2.0 recipe (section 5).
- Every upgrade step (`2026031101` to `2026073100`) lies above the 4.5, 5.1 and 5.2 namespaces.
  On those branches they would re-run on a site whose version row is set below them. The plugin
  has **no own tables** (no `install.xml`). Apart from cache purges and lazy field provisioning,
  which the catch-all already repeats, the steps leave five durable effects, and each can be
  written so that it runs any number of times with the same result:
  - the `2026031101` shortname rename (`customcard` to `local_dimensions_customcard`, and six
    more);
  - `sync_enrollmentfilter_option()` on both areas;
  - `unset_config('summaryenrollmentfilter')`;
  - `unset_config('viewcompetency_filter_fields_competency')`;
  - `ensure_unaccent()`.
- **`aiplacement_dimensions` breaks with this split unless it changes too**: it declares
  `local_dimensions => 2026072700` (higher than the 5.1 and 5.2 r1 numbers), and its CI checks
  out `moodle-local_dimensions,main` on both its 5.1 and 5.2 jobs. It also declares
  [501, 503] with no 5.3 job.
- block_dimensions ships a `package.json`: `mdl mutate --fast` dies on it ("checkout changed").
  Use the slow `mdl mutate` for the block.
- The Bootstrap 4 polyfill (`classes/local/bootstrap.php` in both, ~200 CSS lines in local) and
  the `$CFG->branch` gates (`bootstrap::FIRST_BS5_BRANCH`,
  `enrolment_provider::UNLISTED_MIN_BRANCH`) exist only because one branch serves 4.5.
- Tests: 121 + 14 `*_test.php` files carry 311 `@covers` / `@dataProvider` docblock lines, kept
  only for the 4.5 moodle-cs leg.

## 2. Decisions for the owner

- **D1. Which branch is `main`.** The fleet rule says "`main` identical to the newest stable".
  5.3 is released, and the dimensions family takes its new features on 5.3. Every other fleet
  repository still has `main` = 5.2 with a 5.3 alpha branch.
  - **Recommended: `main` = `MOODLE_503_STABLE`.** New features and fixes land on `main`. Fixes are
    then backported to 502, 501 and 405. MDL Shield (which reads only the default branch) moves
    to `["5.3"]`. The institution's site deploys from `MOODLE_502_STABLE` until its own 5.3
    upgrade (theme_dimensions O1).
  - Alternative: `main` = 5.2 as in the rest of the fleet. The 5.3 branch then takes every
    feature, but MDL Shield keeps reviewing it as 5.2 code.
- **D2. A code line for 4.5, 5.1 and 5.2.** Recommended: those three branches keep the same
  code and differ only in `version.php`, `ci.yml`, the README requirements line and the
  legacy repair block, which is the same on all four branches. A fix then cherry-picks cleanly to all three. Only 5.3 sheds the 4.5
  compatibility code. The alternative trims the polyfill on 501 and 502 as well. The polyfill is
  dead code there but harmless, and every trim adds a conflict to each backport.
- **D3. PHP floor on 5.3**: 8.3 (section 6). Requiring 8.4 would exclude 5.3 sites that are
  correctly configured on 8.3, and it would fail the 8.3 CI leg.
- **D4. Moodle 5.0.** It gets no branch: the block's `ci-500` job is dropped and 5.0 sites lose
  support. This follows feedback_tracker and groupdist.
- **D5. Maturity and release names per branch.** Recommended: local `MATURITY_STABLE` on all
  four branches; the BETA comes from before the audit and does not describe the code now. Release
  names `v4.5-r1`, `v5.1-r1`, `v5.2-r1`, `v5.3-r1`, as in groupdist and repeatable.

## 3. Branch and number table (assuming D1 = 5.3)

| Branch | Checkout | Stacks | local version / requires | block version / requires / dep on local | supported |
|---|---|---|---|---|---|
| `MOODLE_405_STABLE` | `moodle-<repo>-405` | m405 | `2024100700` / `2024100700` | `2024100700` / `2024100702` / `2024100700` | [405, 405] |
| `MOODLE_501_STABLE` | `moodle-<repo>-501` | m501 | `2025100600` / `2025100600` | `2025100600` / `2025100600` / `2025100600` | [501, 501] |
| `MOODLE_502_STABLE` | `moodle-<repo>-502` | m502, m502b | `2026042000` / `2026042000` | `2026042000` / `2026042000` / `2026042000` | [502, 502] |
| `MOODLE_503_STABLE` = `main` | `moodle-<repo>` | m503, m503b | `2026100500` / `2026100500` | `2026100500` / `2026100500` / `2026100500` | [503, 503] |

Each branch increments its own counter by one for each change on that branch. A cherry-pick keeps
the target branch's `version.php` and then bumps that branch's counter. When resolving a
`version.php` conflict, re-read all `$plugin->` lines afterwards: a regex resolution once dropped
maturity, release and supported on groupdist's 503 branch.

CI per branch: a single `moodle-plugin-ci` job naming that branch's core (full PHP x DB matrix),
with sibling checkouts **on the matching branch**:

| Branch | local_dimensions ci.yml installs | block_dimensions ci.yml installs |
|---|---|---|
| 405 | block `MOODLE_405_STABLE` | local `MOODLE_405_STABLE` |
| 501 | block `MOODLE_501_STABLE`, enrol_apply `master` | local `MOODLE_501_STABLE` |
| 502 | block `MOODLE_502_STABLE`, enrol_apply `master`, unlistedcourses `main` | local `MOODLE_502_STABLE` |
| 503 | block `main`, enrol_apply `master`, unlistedcourses `MOODLE_503_STABLE` | local `main` |

`test_ci_checks_out_the_family_sibling` and `test_ci_checks_out_unlistedcourses_on_the_502_leg`
read `ci.yml`, so each branch updates them in the same commit. On 503 the second one becomes
"on the 503 leg, from `MOODLE_503_STABLE`".

## 4. Stages

Each stage is one pull request per repository unless stated otherwise. Local runs before block
because block depends on it.

### S0. Preflight (no code)

1. Baseline counts for both repos from the last local matrix in the bodies of PRs #61 and #22.
   If a PR body has none, run `mdl ci <repo> --matrix --behat` once on `main`, in the
   background. The split must reproduce these test and scenario counts on every branch, minus
   the tests that skip by branch.
2. `ListAgents`: ask the other sessions before any remount or upgrade on a shared stack. An open
   session ("Plano de implementação theme_dimensions") works on m503, and local_dimensions is
   already mounted there.
3. Prove each of the five durable upgrade effects idempotent by reading the code: running it
   twice must leave the same rows. `sync_enrollmentfilter_option()` must not append the option
   a second time, and the rename must touch only the old shortnames. Where one is not
   idempotent, make it so in S1 with a test.

### S1. The branch commit, four times per repository (version, CI, upgrade repair; no feature change)

1. Create the four stable branches in both repos **at the current `main` commit** first. Both
   heads carry `[skip ci]`, so pushing them starts no job, and the cross-checkouts in the
   per-branch PRs then find a branch to clone. Pushing directly to `MOODLE_*_STABLE` is blocked
   by the auto-mode classifier, so the owner runs the exact commands this session hands over.
2. On each branch, one commit "Branch for Moodle X.Y":
   - `version.php`: version, requires, supported, maturity and release from the table. The
     block's `local_dimensions` dependency becomes the local r1 number of the same branch.
   - `ci.yml`: one job, plus the sibling checkouts from the table; the block drops `ci-500`.
   - `db/upgrade.php` (local): remove the numbered pre-release steps `2026031101` to
     `2026073100`. Call `local\legacy_repair::run()` before the catch-all
     `ensure_custom_fields_exist()` calls, which stay. On every upgrade it unsets the retired
     settings and ensures `unaccent`. The rename and the sync run once per site
     (`legacyrepairsapplied`). There is no savepoint. The 7 mutation gates are in
     `mutations/legacy_repair.conf`. A test runs it twice on
     a site seeded with the old shortnames and the two old settings. This needs no version guard
     and no "reach v1.0 first" rule: a site upgrading from any date-numbered version gets the
     same repairs. That works because the plugin has no schema to bring up step by step. Use
     the same block on all four branches, so a later fix in it cherry-picks cleanly.
   - block: no `db/upgrade.php` and no tables. Only the version and the dependency change.
   - `CHANGELOG.md`: a `## [v<X.Y>-r1]` entry that spells out the switch for sites (section 5).
     README: the requirements line for that branch.
   - Repository `CLAUDE.md`: replace "One branch serves every supported Moodle version until the
     5.3 work starts" with the branch table. Update the MDL Shield line.
3. `main` (= 503): `.mdlshield/config.yml` `versions: ["5.3"]` in both repos.
4. `security-diff-read` skill over the legacy repair block: it touches upgrade steps. One
   `!mdlshield review full` on the 503 PR of local only, when the owner says so. Never a review
   on the twins: their code is the same.

### S2. aiplacement_dimensions in step (one PR on its `main`)

- `$plugin->dependencies = ['local_dimensions' => 2025100600]`: the lowest r1 number. All four
  r1 releases contain everything it needs from `2026072700`. Say so in a comment.
- `ci.yml`: the 501 job checks out local `MOODLE_501_STABLE`, the 502 job checks out
  `MOODLE_502_STABLE`, and a new 503 job (the highest, with the full matrix) checks out `main`.
  Today it claims [501, 503] with no 503 job, so the 5.3 leg has never run.
- Merge it together with S1, never before: until S1 lands, the old local `main` still says
  `2026101301`.

### S3. Environment (moodle-dev PR, then the stacks)

1. `plugins.conf`: replace the two `auto` lines with explicit per-branch lines, using the
   feedback_tracker and groupdist pattern. Checkouts are worktrees
   (`git -C ~/dev/moodle-local_dimensions worktree add ../moodle-local_dimensions-405
   MOODLE_405_STABLE`, and so on). `aiplacement_dimensions` stays `auto`.
2. `mdl-ci` `extra_deps_for`: local's optional siblings per branch (enrol_apply on 501+,
   unlistedcourses on 502 and 503 from their per-branch checkouts), as in moodle-dev #72 and
   #79. A worktree whose directory name `extra_deps_for` does not return gets the plugin
   installed twice ("The sorted list of plugins does not match the size of original list").
3. Stacks, one at a time, after asking the peer sessions: `mdl mounts`, then `mdl up <stack>`.
   Then **after** the new files are in place,
   `cfg.php --component=local_dimensions --name=version --set=<r1 - 1>` and the same for
   block_dimensions. Then `mdl upgrade`, `mdl phpunit-init`, `mdl behat-init`. Setting the row
   before swapping the files would re-run the old steps.
4. `mdl grunt <stack> local/dimensions` and `blocks/dimensions` from each stack that mounts that
   checkout. Since moodle-dev #73 it builds the mounted checkout. Judge the build by the map's
   `sourcesContent`, not by the exit status.

### S4. Verification of the split

- `mdl ci <checkout> --matrix --behat` per branch and repository: eight matrices. Run them in the
  background from the main session, never inside a subagent, and never two at once on the same
  checkout (`mdl ci` mounts the live working tree). Read every leg log for `: FAILED`; the
  summary once printed PASS over failed scenarios.
- Expected per branch: the baseline counts, minus what skips by branch. On 405 the enrol_apply
  and unlistedcourses cases skip. On 503 the polyfill tests still run, because S5 removes them.
- GitHub credit is at zero (owner, 2026-10-14, relayed by the theme_dimensions session): every
  matrix runs locally, 503 included, and nothing goes to GitHub. Every pushed commit and every
  merge subject carries `[skip ci]`, because a push to an open PR without it starts a run. No
  workflow dispatch. Each PR body names its local legs, tests and scenarios. If credit returns,
  the 503 PR is the one to run on GitHub: it is the newest leg and the one this machine has run
  least.
- Merge order: local 503 and block 503 together, then 502, 501, 405 in pairs, then S2.

### S5. Raising the bar on 5.3 (`main` only; separate PRs, mechanical, gated by unchanged counts)

Each item is its own PR, so a red result points at one kind of change:

1. **Drop the 4.5 line**: `classes/local/bootstrap.php` in both, the polyfill block at the tail of
   local's `styles.css`, the `local-dimensions-bs4` body class and its hook, the
   `bootstrap_compat` scanning tests, and the doubled `data-toggle` attributes (keep
   `data-bs-toggle`). Remove `UNLISTED_MIN_BRANCH`: on 5.3 the branch test is always true, so
   only the optional-plugin check stays.
2. **Test metadata to attributes**: `#[CoversClass]`, `#[DataProvider]`, `#[CoversNothing]` in
   place of the 311 docblock lines. 5.3's moodle-cs reads attributes.
3. **PHP 8.2/8.3 where it says something the code cannot say today**:
   - `#[\Override]` on every method that overrides core: renderers, `external_api` subclasses,
     `dynamic_form`, the block class, privacy provider methods. Signature drift in core then
     becomes a compile error instead of a silent new method.
   - Typed class constants (`const int MAX_... = 200;`).
   - `readonly` classes for value objects already built in their constructors.
   - DNF types where a parameter accepts `A&B|null`.
   - `json_validate()` before the custom SCSS/JSON decodes.
   - Skip rewrites made only for style. Each change needs a reason a reviewer can see.
4. Constraint: `validate` fatals on a member with two modifiers in the files it parses. Verify
   whether that still holds on the 5.3 leg (`mdl ci <repo> --branch MOODLE_503_STABLE --only
   validate` on a probe commit) before using `public readonly` or `final readonly class`
   outside `classes/`. For local those files are `db/upgrade.php` and `lang/en/*.php`; the
   block adds `block_dimensions.php`. Until that is verified, keep two-modifier members out of
   those files.
5. Mutation sweep on 503 after items 1 and 3, both database families: `--fast` for local, slow
   `mdl mutate` for the block (because of `package.json`). Run each sweep **before** the commit
   that adds new gates, and check the `#   ->` lines against the sweep log.

### S5b. Shared token layer with theme_dimensions (`main` only, after S5.1; local and block in one step)

The contract was proposed by the theme_dimensions session on 2026-10-14. It will be written as
`docs/tokens.md` in `uaiblaine/moodle-theme_dimensions`, and the theme side ships first.
- **What the theme does.** It declares `--dimensions-<suffix>` on `:root`, on `body` and on the
  `core/modal` root, with light and dark values.
- **What the plugins do.** Each plugin token whose suffix is in the contract becomes
  `--<plugin>-<suffix>: var(--dimensions-<suffix>, <its chain after S5.1>)`. Without the theme,
  nothing changes.

The contract is measured against `styles.css` on 2026-10-14:
- Shared by local and block (33 suffixes; the block declares only these and `favourite`):
  - surface, surface-alt, surface-inset, line;
  - ink, ink-strong, ink-muted, ink-faint;
  - accent, accent-hover, brand-fill, brand-ink, brand-tint, brand-edge, on-brand-fill,
    focus-ring;
  - success, warning, danger, info and neutral, each as -tint, -edge and -ink;
  - shadow, scrim.
- **local only** (21 suffixes):
  - motion-fast, motion-base, motion-ease;
  - state-{enrolled,guest,neutral,open,pending,scheduled}-{bg,edge,ink}.

  They live outside the block that `test_token_block_is_identical_to_the_sibling` compares, so
  the parity test does not cover them. They need their own arm in local's `colour_tokens_test`.
- Out of the contract: favourite, icon-glyph, loading-min-height, motion-flash and every tabs-*.
- **Dark values: correction to the proposal.** `shadow` and `scrim` are redeclared with
  literals in the `body[data-bs-theme="dark"]` rule and in the inert
  `[data-dimensions-media-optin]` block. If only the light declaration is wrapped, those dark
  rules still win when the theme is present in dark mode, and the theme's dark value is
  ignored. Wrap the two dark redeclarations the same way: `var(--dimensions-shadow, <dark
  literal>)`. `favourite` stays a plain literal.
- Tests: one arm checks every contract token reads `var(--dimensions-<same suffix>, ...)` first,
  including in the dark rules. Mutation-check it by unwrapping one token, which must turn it
  red. The literal-outside-the-block rule and the no-`--mds-*` rule stay as they are.

### S6. Release (owner's call at each step)

`git archive --prefix=dimensions/` zips named `moodle-<component>-<version>-<shortSHA>.zip`. Tags
are `v4.5-r1` ... `v5.3-r1` on each repo, with GitHub releases; the 5.3 release is the Latest.
Uploads to moodle.org are manual, by the owner. Sites pick the branch that matches their core.

### After this plan: 5.3-only features

These go on `main` only, each with its own design and stages. Features shared with
theme_dimensions (5.3 only, phase 5 "competency UX line") are designed together with it. The
colour-token contract shared between local and block, which `colour_tokens_test` compares, must
stay in step with the theme's `--dimensions-*` family (theme O6, stage S5b).

## 5. Switching a site (CHANGELOG text, every branch)

1. (No prerequisite release: the legacy repair block covers every date-numbered version.)
2. Replace the files with the branch that matches the site's Moodle.
3. `php admin/cli/cfg.php --component=local_dimensions --name=version --set=<r1 - 1>`, and the
   same for block_dimensions, for example `2026042000 - 1 = 2026041999` on 5.2.
4. `php admin/cli/upgrade.php`, then purge caches.

Step 3 must come after step 2. With the old files still in place, it re-runs every old
numbered step. Without step 3, Moodle refuses the files as a downgrade.

## 6. Lessons from earlier sessions applied here

| Lesson | Source (memory) | Where it shows up |
|---|---|---|
| Per-version numbering; the old steps above the range are removed; `cfg.php` after the files (feedback_tracker needed a schema guard; local_dimensions has no schema, so idempotent repairs replace it) | feedback-tracker-branch-per-version | S1, section 5 |
| A `version.php` conflict resolved by regex dropped three `$plugin->` lines | groupdist-branch-per-version | section 3 |
| `mdl grunt` builds the checkout the stack mounts (moodle-dev #73); judge by `sourcesContent` | groupdist-branch-per-version | S3.4 |
| A worktree name unknown to `extra_deps_for` installs the plugin twice; a bare `mdl ci` on main runs the 501 leg | repeatable-dictionaries-plan | S3.2, S4 |
| Deleting a stacked PR's base branch closes the stacked PR | repeatable-dictionaries-plan | merge order in S4 |
| A direct push to a stable branch is blocked: hand the owner the command | feedback-tracker-bulk-removal-queue | S1.1 |
| `package.json` in the plugin breaks `mdl mutate --fast` | repeatable-dictionaries-plan | S5.5 |
| A two-modifier member in a validated file is fatal on the 5.2 leg | mpci-validate-modifier-fatal-52 | S5.4 |
| A `version.php` bump on any mounted plugin stales the PHPUnit site; re-init just before | phpunit-site-staled-by-other-sessions | S3.3, S4 |
| Ask the peer sessions before `mdl up` or `upgrade` on a shared stack | shared-stack-ask-peers-first | S0.2, S3.3 |
| `mdl ci` mounts the live tree: no edits during a matrix | mdl-ci-mounts-live-working-tree | S4 |
| "Turned green" is not proof: `gh pr checks` alone, merge only with nothing pending | check-pending-before-merge | S4 |
| One MDL Shield review per change on the branch where development happens, never on twins | mdlshield-pr-reviews, unlistedcourses | S1.4 |
| A plugin's `.stylelintrc` disables the CSS gate (none here; do not add one with the split) | fleet-stylelintrc-template-disables-css-gate | S1 |
| 5.3 grunt: `esm.js` replaces `react.js`, and `bundle:false` deletes `js/esm/build` | block-compass-handoff | not used yet (neither plugin has `js/esm`); read it before the first React feature on 5.3 |

## 7. Agent roles (fleet rule 6)

- S1 version, CI and CHANGELOG edits (eight commits from a written recipe): `fleet-fixer`, one
  per repository.
- S0.3 idempotence read and S5 candidate inventory (`#[\Override]` targets, value objects, typed
  constants): `fleet-reader`.
- S5.1 and S5.2 (mechanical, with counts that must not change): `fleet-fixer`. S5.3 (judgement
  per site): `fleet-implementer`.
- Lang lockstep, `#   ->` lines against the sweep log, counts in PR bodies: `fleet-checker`.
- Matrices, mutation sweeps and stack upgrades: the main session, in the background.
