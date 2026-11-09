# Proposal — competency-based assessment (2026-10-23)

A future direction, not a plan of record. It came out of matching the plugin against a call for a
competency-based assessment solution (rubrics standardised per criterion, grades that vary with the
submission date, feedback by criteria, teacher and institutional dashboards, comparison with
external exams). `local_dimensions` and `block_dimensions` already cover the management and the
learner side of that call; the assessment engine and the analytics are the gap. This folder records
what Moodle 5.3 already does, what the plugins directory offers, and how the gap could be closed
without breaking the plugin's rule: **no tables of its own, core data wherever core has it**.

Nothing here is built. Every decision below is open until the owner answers it.

## What Moodle 5.3 already does (verified in `~/dev/moodle-503`)

| Need | Core in 5.3 | Where |
|---|---|---|
| Late-submission penalty | `gradepenalty_duedate`: rules per context, inherited from category to course to activity | `grade/penalty/duedate`, `core_grades\penalty_manager` |
| Which activities take a penalty | Only modules declaring `FEATURE_GRADE_HAS_PENALTY`: **`mod_assign` alone** | `mod/assign/lib.php` |
| Bonus for an early submission | **Impossible through the penalty API**: `penalty_container::aggregate_penalty()` throws `errornegativepenalty` on a negative value | `grade/classes/penalty_container.php` |
| Gradebook outcomes, renamed **Learning outcomes** in 5.3 (MDL-89106) | One scale grade item per outcome ticked on an activity, rated by hand; nine modules support them, quiz and assign included. New in 5.3: outcomes **without a scale** (an alignment, not a rating: `grade_outcomes_modules` links them to course modules), a course **Learning outcomes** page (`course/learningoutcomes.php`), backup, restore, import and export | `FEATURE_GRADE_OUTCOMES`, `lib/grade/grade_outcome.php` |
| Rubrics as shared templates | Advanced grading forms can be published as templates and picked in any activity | `grade/grading/pick.php`, `moodle/grade:sharegradingforms` |
| Rubric criterion linked to a competency | **None** | — |
| Competency rated from a grade | **None**: course-module competency rules fire on completion only (evidence, review, complete) | `core_competency\course_module_competency` |
| Writing a rating with a trail | `api::add_evidence(..., ACTION_OVERRIDE, ..., $grade, $actionuserid, $note)` in any context, with a URL; `api::grade_competency_in_course()` for a teacher's rating | `competency/classes/api.php` |
| Linking competencies across frameworks | **None**: related competencies must share a framework | `api::add_related_competency()` |

## Prior art in this fleet

`mod_interactivevideo` already turns a score into a scale level for gradebook outcomes
(`classes/local/outcome_mapping.php`, shared with `mod_flexbook`):

- a link per interaction and outcome, in mode `score` (lower bounds in percent, one per scale level,
  the first always 0) or `fixed` (completing awards a level);
- several interactions feeding one outcome average their levels weighted by XP, rounded half up;
- ratings go straight to the outcome item's final grade (`grade_item::update_final_grade()`, since
  outcome items refuse raw grades), recomputed on every progress save and by an ad hoc task
  (`rate_outcomes`) when a mapping changes;
- it overwrites a teacher's manual rating. That is the one choice not to copy blindly (D3).

The band model, the weighted mean and the backfill task carry over; the target changes from an
outcome grade item to a competency rating written as evidence.

## Plugins directory survey

Read on 2026-10-23 from the directory's pages (marketplace.moodle.com); install counts and dates
as listed there. Take only what a piece needs; none of these is a dependency of this plugin.

| Plugin | What it does | Moodle | Bearing on this proposal |
|---|---|---|---|
| `local_latepenalty` ([3791](https://marketplace.moodle.com/plugins/3791), Jean Lúcio, 2026-10-14) | Progressive late deductions on any graded activity (assign, quiz, SCORM, H5P, forum, lesson, workshop) from `user_graded`; on 5.1+ it writes core's penalty field; quiz lateness follows the attempt and the grading method; leaves scale grades, teacher edits, locked grades and core-penalised assignments alone | 4.5–5.3 | Closes the quiz penalty gap. No bonus |
| `qbank_comp_ext` ([3939](https://marketplace.moodle.com/plugins/3939), 2026-10-11) | Links question bank questions to core competencies (a bank column, auto-link by tag), kept across backup and restore | 4.4–5.3 | The mapping only: rates nothing |
| `qbank_yetkinlik` + `local_yetkinlik` (Hakan Çiğci, 2026-09-03) | Question success rates turned into competency evidence in the background, matched to the scale by percentage, combined with other activities by weights; PDF export | 4.4–5.2 | Closest to P2 for quizzes. Whether it writes core ratings was not confirmed |
| `gradingform_rubric_ranges` (Catalyst, 2024-11-22) | Rubric levels as point ranges | 3.9–4.5 | No 5.x release |
| `gradingform_rubrix` (Catalyst, GitHub) | Rubric with more criterion types and penalty methods | not listed | Not in the directory |
| `tool_cmcompetency` + `report_cmcompetency` | Rate competencies per course module, bulk rating on cron; a report of those ratings | up to 5.1 | Manual rating, not from a grade |
| `report_lpmonitoring` (2026-10-07) | Learning plan statistics per plan and per competency, with filters | 3.1–5.1 | Monitoring for P4; no 5.2/5.3 release |

**No plugin links a rubric criterion to a competency or to an outcome**, as a grading method or
otherwise; the only trace is a 2016 forum thread about custom code on the competency web services.
P1 is unclaimed ground.

## Proposed pieces

Each piece is its own plugin, so a site takes only what it needs and `local_dimensions` keeps no
tables. `local_dimensions` integrates each one behind a runtime guard, as it already does with
`enrol_apply` and `local_unlistedcourses`: a chip in the hub, a column in its report sources.

### P1 — Rubric criteria linked to competencies

A criterion of a core rubric gains a competency and a level-to-scale map. When the grade is
released, each mapped criterion rates its competency as evidence in the activity's context, with
the criterion, the level and the teacher's remark as the note and the grader as the URL.

- **Shape.** A `local_*` plugin over the core rubric, not a new grading method: existing rubrics and
  shared templates gain the mapping without migration, and teachers keep the method they know. The
  mapping is edited on a page of its own, reached from the activity's *Advanced grading* node;
  nothing is injected into the rubric editor (a moodleform with no extension point).
- **Table.** `criterionid`, `competencyid`, the band map (JSON), `weight`. Copied with the rubric
  when a template is picked; backup and restore remap both ids.
- **Trigger.** `\core\event\user_graded` on the activity's grade item, read through the grading
  area's active instance and its fillings; under a marking workflow, only when the grade is
  released.
- **Several criteria or activities on one competency.** Weighted mean of levels (the IV rule),
  rounded half up, recomputed from the stored fillings, never accumulated.
- **Preconditions.** The competency must be linked to the course; the grader needs
  `moodle/competency:competencygrade`. A mapping that cannot fire says why on the mapping page.

### P2 — Grade bands to scale

The same band model applied to an activity's whole grade: an activity grade (assign, quiz, any
module with a grade item) turned into a level of a competency scale, or of a gradebook outcome's
scale, by lower bounds in percent. This is what core lacks between "the learner scored 78" and
"proficient".

- Bands are set per course-module competency link, inside the hub's *Courses & activities* panel,
  next to the completion outcome it already edits.
- Optional feedback text per band: written into the evidence note, and sent to the learner through
  the message API when the rating changes (the "automated feedback by criteria" of the call).
- Outcome targets reuse the IV writer (`update_final_grade()`), but never over a rating a teacher
  set by hand (D3).
- 5.3's scale-less learning outcomes are alignment only: they say which activities address an
  outcome and carry no level, so P2 reads them as a declaration (the hub can show them beside the
  competency links) and rates only scaled outcomes and competencies.
- For quizzes, a question-level source (`qbank_comp_ext`'s mapping) can feed the same bands per
  competency instead of the whole quiz grade; reading its table is a runtime-guarded option, not a
  dependency.

### P3 — Due-date modifiers beyond core

- **Quiz and the other modules.** Core penalises only the assignment in 5.3; `local_latepenalty`
  covers the rest and writes core's own penalty field. Recommend it rather than build it. Its one
  limit that matters here: scale grades are not discounted, so a penalty must act on the points
  grade before P2 turns it into a level, never on the level.
- **Early bonus.** Not a `gradepenalty_*` plugin (negative penalties throw). A bonus written to a
  manual grade item counted as extra credit under natural aggregation is visible in the gradebook,
  reversible, and leaves the activity grade untouched.

### P4 — Dashboards and monitoring bands ("réguas")

On top of the seven report builder sources the plugin already ships:

- a **Performance** tab in the hub: competencies × learners of a course or cohort as a heat map,
  the distribution of scale levels per competency, the proficient share per course and per
  category;
- **monitoring bands** per framework (for example below 50 % proficient, 50–79 %, 80 % and up),
  named and coloured by the administrator, used by the tab, the learner views and the report
  sources as a condition. Frameworks have no custom field area in core: the bands live in plugin
  config keyed by framework id, or in a framework area the plugin registers (D5).

### P5 — Comparison with external assessments

- A **cross-framework map** (internal competency ↔ the competency of an external matrix, such as
  an exam's reference matrix), which core cannot express (related competencies share a framework).
  One small table; the only piece that would add one to `local_dimensions` itself, so it belongs in
  its own plugin too.
- **Imported results** at the level the source publishes them: aggregate per course, area or year
  for de-identified public microdata, per learner only where the institution holds the learner's
  own result. Per-learner imports are personal data: a privacy provider from day one.
- A report source joining the institution's proficiency per mapped competency with the imported
  result of the same area, per course and per year.

## Open decisions

| # | Decision | Default proposed |
|---|---|---|
| D1 | P1 as a `local_*` over the core rubric, or a `gradingform_*` method of its own? | `local_*`: no migration, shared templates keep working |
| D2 | Where do the band maps of P1 and P2 live? | In the engine plugins, never in `local_dimensions` |
| D3 | May an automatic rating replace a teacher's? | No: evidence always, a rating only while the last rating was automatic |
| D4 | Early bonus: extra-credit item, or out of scope? | Extra-credit item, opt-in per activity |
| D5 | Monitoring bands: plugin config or a framework custom field area? | Plugin config first |
| D6 | External results: aggregate only, or per learner too? | Aggregate first |
| D7 | Does `aiplacement_dimensions` grow a feedback draft for the grader (core AI, teacher edits before release)? | Later, after P2 |

## Order

P2 first (the smallest, the IV model is proven, and it gives P1 its writer), then P1, then P4 over
the evidence both produce. P3 and P5 wait for a site that needs them.
