# Core parity of the progress rule

The tracker and the plan overview count a learner's workload by core's rule
(`completion_info::get_user_activities_with_completion()`, which `core_completion\progress` counts by),
with one call replaced: the list filter of a restricted activity is judged for the one learner
(`local\learner_restrictions`) instead of listing every member of every group. This file is how the
copy stays equal to core: what holds it, when to audit, and the log of every audit.

## What holds it

| Guard | Where | Reddens when |
|---|---|---|
| The oracle | `tests/local/core_parity_test.php` | `calculator::counts_towards_progress()` and core count a different set of activities, or a different percentage, for any learner of the matrix course, in any of its five scenarios. Runs on every CI leg. |
| The fingerprint | `tests/local/core_fingerprint_test.php`, `tests/fixtures/core_parity.json` | Core changed the code of a mirrored function (comments and whitespace aside), removed one, or changed which condition types filter user lists. |
| The restrictions' own oracle | `tests/local/learner_restrictions_test.php` | `learner_restrictions` and `info_module::filter_user_list()` disagree for one learner, read by four viewers. |
| The weekly look upstream | the fleet's scheduled check (`core-parity-weekly-upstream`) | Core's `MOODLE_503_STABLE` moved a fingerprinted function. Report only. |

The fingerprint reddens on purpose. The fleet runs the GitHub pipeline only on the commit a release
tag goes on, so a core point release that moved this code stops the release until it is audited; a
development stack moved to a new core stops the same way.

The same rule has a twin in `block_compass` (`local\progress\learner_restrictions` and
`local\progress\single_user_capability_checker`, copied whole, and the same manifest; its ADR-027).
An audit reads both, and a change to one is made to the other in the same round.

## When to audit

- The fingerprint or the oracle reddens (a stack, `mdl ci`, the release pipeline).
- The weekly look upstream reports a moved function.
- Before every release of this plugin, even with everything green: step 3 below is the part no test
  can do.

## The audit

1. **Run the oracle on the new core.** `mdl phpunit m501 local/dimensions/tests/local/core_parity_test.php`
   on a stack at that core (or `mdl ci moodle-local_dimensions --only phpunit`). A red oracle is a
   behaviour difference: the rule must follow core before anything else.
2. **Read core's diff for every symbol the fingerprint names.**
   `git -C ~/dev/moodle-501 diff <audited tag> <new tag> -- public/<file>`, the tag in the manifest's
   `audited`. `php local/dimensions/cli/core_parity.php` on the stack lists what moved; with
   `--root=<path>` it reads another checkout.
3. **Search the tracker** for issues fixed since the audited release in `core_completion` (progress,
   `get_user_activities_with_completion`, `count_modules_completed`), `cm_info` visibility
   (`update_user_visible`, `is_visible_on_course_page`) and `core_availability` (`filter_user_list`,
   `is_applied_to_user_lists`, `capability_checker`, the group, grouping and profile conditions), and
   for new condition types in core.
4. **Decide, change by change.** Either the rule follows it (code in `classes/calculator.php` or
   `classes/local/learner_restrictions.php`, a new case in the oracle's matrix that reddens without the
   code, the twin in `block_compass`), or it changes nothing the rule depends on (a line in the log
   below saying why).
5. **Record it.** `php local/dimensions/cli/core_parity.php --rebuild=<new tag> > tests/fixtures/core_parity.json`
   on a stack at the new core, then a log line below, a `CHANGELOG.md` line and the version bump in the
   same commit. The manifest's symbols are a list to extend: a new function the rule comes to lean on
   gets an entry, with `"hash": null`, before the rebuild.

This branch (Moodle 5.1) follows core's rule as main does, `is_visible_on_course_page()` alone, so
a site on a point release before MDL-66780 (5.1.5, 5.2.1) counts what its own core counts. The 4.5 branch
keeps `$cm->visible` beside it, because core 4.5 has no rule to follow. The manifest is audited against
this branch's core, and the weekly look upstream reads main's.

## Log

| Date | Core audited | Result |
|---|---|---|
| 2026-11-17 | v5.1.8 | First manifest on this branch, main's 23 functions read from this core; list-filtering condition types group, grouping, profile. The workload rule followed core's (version 2025100616): a stealth activity no longer counts, and a hidden one counts for a learner who may see it. The oracle agrees in all five scenarios. |
