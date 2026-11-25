## What

The server half of stage S5 of the competency hub v2 plan: a read-only web service, `local_dimensions_get_framework_insights`, that answers for one framework what the workspace's lenses and structure checker draw. No client uses it yet (that is the next pull request).

- **Usage** per competency: courses, activities, learning plan templates, learning plans, user evidence, at one of three levels. `exact` (counts) only for a viewer who may list every use at the site and whom no role refuses any of those capabilities in any context (the administrator excepted); `presence` (which kinds of use apply) for a manager of the framework's competencies; `none` for any other reader.
- **Rules**: each competency's completion rule, what it asks, and whether a learner could ever meet it.
- **Relations**: every pair of the framework once, and a count per competency.
- **Checks**: a rule that cannot fire, a rule over a single child or with no children, an empty description or ID number, a competency nothing uses, custom colours under a 4.5:1 contrast (`classes/local/contrast_ratio.php`).

A fixed number of statements whatever the framework's size (a test compares 5 competencies against 60), no per-competency query, declared `readonlysession`. Version `2026100537`.

## Security

Written with `secure-by-design` and read with `security-diff-read` before the push.

- The context comes from the framework row; the gate is `competency_framework::can_read_context`, as core's `read_framework`; a framework in an unlisted category is refused as if it were missing (stricter than the sibling services); guest and not-logged-in refused; nothing is written.
- **One finding, fixed in this pull request.** The `exact` level read its capabilities once at the system context, so a viewer refused in one course, category or user context by an override (or by a prohibiting role assigned lower down) still received counts that include rows core would not list to them. `exact` now also requires that no row of `role_capabilities` carries a negative permission for any of those capabilities, for any role in any context (a role definition's prohibit applies wherever the role is assigned, so the row's own context does not bound its reach); such a viewer falls to `presence`. Six tests hold it (a course prohibit, a category prevent, a prohibiting role assigned in a course only, the administrator keeps `exact`, an unrelated capability does not demote, and the control) and four mutation gates.
- Informational, by design: `presence` tells a manager of the framework which kinds of use apply to a competency, as `local_dimensions_competency_delete_check` already does one competency at a time.

Not requested here: an MDL Shield review (the owner posts that command).

## Gate

Tier 3 (a web service, SQL). On the head `7bea7a5`; the pushed head differs from it only in `version.php` and the CHANGELOG's version line (538 to 537, to follow main).

`mdl ci wt-local_dimensions-hubv2-s5api --matrix --behat --remote --keep-logs` (Mac mini): **4 of 4 legs passed**.

| Leg | PHPUnit | Behat |
|---|---|---|
| 5.03 · PHP 8.4 · pgsql (statics, build, Jest 199 of 199) | 1,079 tests, 25,968 assertions, 8 skipped | 41 scenarios, 701 steps, all passed |
| 5.03 · PHP 8.3 · pgsql | 1,079 tests, 25,968 assertions, 8 skipped | 41 scenarios, 701 steps |
| 5.03 · PHP 8.3 · mariadb | 1,079 tests, 25,957 assertions, 11 skipped | 41 scenarios, 701 steps |
| 5.03 · PHP 8.4 · mariadb | 1,079 tests, 25,957 assertions, 11 skipped | 41 scenarios, 701 steps |

Mutation sweep, `mdl mutate wt-local_dimensions-hubv2-s5api mutations/framework_insights.conf --fast --branch MOODLE_503_STABLE --php 8.4 --db <family> --jobs 4`: **103 of 103 mutations reddened a test on mariadb and on pgsql**.

An earlier baseline on MariaDB failed one test (an ID number of spaces compares equal to the empty string there); the fixture now keeps the blank case in a framework of its own.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
