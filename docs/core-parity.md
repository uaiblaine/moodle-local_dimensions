# Core parity of the progress rule

On this branch (Moodle 4.5) the tracker and the plan overview count a learner's workload by the rule
Moodle 5.1 introduced (`completion_info::get_user_activities_with_completion()`), which core 4.5 does not
have: 4.5's own `core_completion\progress` counts every activity with completion. One call is replaced: the list filter of a restricted activity is judged for the one learner
(`local\learner_restrictions`) instead of listing every member of every group. This file is how the
copy stays equal to core: what holds it, when to audit, and the log of every audit.

## What holds it

| Guard | Where | Reddens when |
|---|---|---|
| The fingerprint | `tests/local/core_fingerprint_test.php`, `tests/fixtures/core_parity.json` | Core changed the code of a mirrored function (comments and whitespace aside), removed one, or changed which condition types filter user lists. |
| The restrictions' own oracle | `tests/local/learner_restrictions_test.php` | `learner_restrictions` and `info_module::filter_user_list()` disagree for one learner, read by four viewers. |
| The weekly look upstream | the fleet's scheduled check (`core-parity-weekly-upstream`) | Core's `MOODLE_503_STABLE` moved a fingerprinted function (main's manifest). Report only. |

There is no oracle on this branch: core 4.5 has no rule to compare with. The 5.1, 5.2 and 5.3 branches
hold the same rule to core's with `tests/local/core_parity_test.php`.

The fingerprint reddens on purpose. The fleet runs the GitHub pipeline only on the commit a release
tag goes on, so a core point release that moved this code stops the release until it is audited; a
development stack moved to a new core stops the same way.

The same rule has a twin in `block_compass` (`local\progress\learner_restrictions` and
`local\progress\single_user_capability_checker`, copied whole, and the same manifest; its ADR-027).
An audit reads both, and a change to one is made to the other in the same round.

## When to audit

- The fingerprint reddens (a stack, `mdl ci`, the release pipeline).
- The weekly look upstream reports a moved function.
- Before every release of this plugin, even with everything green: step 3 below is the part no test
  can do.

## The audit

1. **Run the restrictions' oracle on the new core.** `mdl phpunit m405 local/dimensions/tests/local/learner_restrictions_test.php`.
2. **Read core's diff for every symbol the fingerprint names.**
   `git -C ~/dev/moodle-405 diff <audited tag> <new tag> -- <file>`, the tag in the manifest's
   `audited`. `php local/dimensions/cli/core_parity.php` on the stack lists what moved; with
   `--root=<path>` it reads another checkout.
3. **Search the tracker** for issues fixed since the audited release in `core_completion` (progress,
   `get_user_activities_with_completion`, `count_modules_completed`), `cm_info` visibility
   (`update_user_visible`, `is_visible_on_course_page`) and `core_availability` (`filter_user_list`,
   `is_applied_to_user_lists`, `capability_checker`, the group, grouping and profile conditions), and
   for new condition types in core.
4. **Decide, change by change.** Either the rule follows it (code in `classes/calculator.php` or
   `classes/local/learner_restrictions.php`, a test that reddens without the code, the same change on
   the other branches), or it changes nothing the rule depends on (a line in the log
   below saying why).
5. **Record it.** `php local/dimensions/cli/core_parity.php --rebuild=<new tag> > tests/fixtures/core_parity.json`
   on a stack at the new core, then a log line below, a `CHANGELOG.md` line and the version bump in the
   same commit. The manifest's symbols are a list to extend: a new function the rule comes to lean on
   gets an entry, with `"hash": null`, before the rebuild.

This branch keeps `$cm->visible` beside `is_visible_on_course_page()`: core 4.5 lacks MDL-66780, so it
shows a teacher-hidden activity that carries a restriction on the course page. `cm_info` lives in
`lib/modinfolib.php` here, and the manifest names it there.

## Log

| Date | Core audited | Result |
|---|---|---|
| 2026-11-17 | v4.5.15 | First manifest on this branch: the 22 functions of main's that core 4.5 has (`get_user_activities_with_completion()` is 5.1's); list-filtering condition types group, grouping, profile. The workload rule took 5.1's in this round: a stealth activity no longer counts (version 2024100716). |
