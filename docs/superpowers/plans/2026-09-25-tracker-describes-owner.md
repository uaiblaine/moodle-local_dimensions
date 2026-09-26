# Handoff: the competency tracker describes the plan owner

**Status: implemented 2026-09-26 (version 2026092600).** Beyond this plan, the owner decided three
things: the viewer's access to a card follows the accordion's rule (D1), the completed-courses count
gets the same plan id (D2), and both card services also take the competency id and answer only for
courses linked to it (D3, parity with the accordion). Review added one more gate: `view-competency.php`
refuses a viewer who reads the plan but not the owner's user competencies
(`plan_access::require_owner_readable()`). The list below is still open.

Written 2026-09-25 at the end of the follow-up round of the comment audit (PR merged into `main` as
"Fix the audit findings that still held, and the owner's four decisions", version 2026092500). This
is the next task, decided by the owner and not started: no file was edited for it.

## The decision

When someone views ANOTHER person's learning plan, the course information describes the **plan
owner**, and only what the viewer can click stays the viewer's. The plan accordion already works
this way: `classes/external/get_competency_courses.php` (tests:
`tests/external/competency_courses_owner_test.php`). The owner decided on 2026-09-25 that the
**competency tracker** reached from the same plan (`view-competency.php`) must follow the same rule.

## What to change

- `view-competency.php`: the course-list filter
  (`calculator::filter_courses_by_enrollment(..., $USER->id, ...)`) and anything else that describes
  the learner use the plan owner (`$plan->get('userid')`). The single-course redirect, the return
  button context and the view logging are the viewer's actions: keep them on `$USER`, own-plan only,
  exactly as today.
- The tracker's cards get their progress from `amd/src/competency_view.js`, which calls
  `local_dimensions_get_course_progress` (`classes/external/get_course_progress.php`). That service
  takes course ids only and computes for `$USER` through `classes/calculator.php`
  (`get_course_section_progress()`, `is_locked()`, `current_user_can_enrol()` ...). Give it an
  optional plan id (`VALUE_DEFAULT 0`, so every existing caller keeps working). With a plan id: read
  the plan through `plan_access::read_plan()`, require `user_competency::can_read_user($owner)` the
  way `get_competency_rule_data` does, and compute for the owner. The course ids must still pass
  `helper::readable_competency_courses()` for the viewer. No capability may get looser.
- Thread a user id through `calculator` where it reads `$USER`, keeping the current-user entry points
  working for their other callers (`rg` every caller first).
- `competency_view.js` sends the plan id on the tracker page only. `amd/build` must be rebuilt in
  the same commit, with a `version.php` bump and a `CHANGELOG.md` line under "Changed".

## Tests

As a teacher or manager viewing a learner's plan where the learner and the viewer differ in enrolment
and completion: the tracker's course list and `get_course_progress` with the plan id describe the
learner; without a plan id the service still describes the caller; a caller who cannot read the
owner's user competencies is refused. A control beside every refusal. For the JS, a source-reading
PHPUnit test in the style of `tests/local/hub_javascript_guards_test.php` that pins the plan id being
sent. Write each guard into a new `mutations/followups_T1.conf` (format of `mutations/sec_scope.conf`).

## Left open on purpose

- M19: on Moodle 4.5 a single-activity course whose activity sits outside section 0 gets no activity
  card until someone opens the course page. 4.5 only, heals itself; low priority.
- The file icon for prior-learning evidence: S09 stopped guessing "file" from the evidence name. A
  real icon needs a server-computed `hasfiles` flag in
  `get_user_competency_summary_in_plan` (a product decision).
- `db/services.php` still lists only `moodle/competency:planmanage` for the unlink and delete plan
  services, while the code now asks `plan::can_manage()` (informational metadata).
- `helper`'s template select getters return `$allowed[index - 1]` for a stored index past the last
  option; the metadata cache now reads that case as inherit.
- `pix/status/rules-*.svg` and `calendar-light.svg` still carry fixed colours (the status icons
  became masks; these did not).
- Outside this plugin: `aiplacement_dimensions`' framework picker double-escapes names, and core's
  `tool_lp` rule editor writes an escaped competency name back on every save (an MDL candidate).

## Where things are

- Findings history: the audit's code findings (S01..S44, M01..M53) and the follow-up triage were
  recorded in the owner's session; the fixed ones are named in `CHANGELOG.md` and in the commit
  messages of the two audit PRs.
- Mutation specs: `mutations/sec_*.conf`, `mutations/esc_*.conf`, `mutations/followups_*.conf`.
- Plugin rules: `CLAUDE.md` ("Reading a plan (plan_access)", "MUC caches", "Colour tokens").
