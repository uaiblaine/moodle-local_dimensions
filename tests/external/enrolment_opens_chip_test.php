<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dimensions\external;

use core_external\external_api;
use local_dimensions\constants;
use local_dimensions\helper;
use local_dimensions\local\enrolment_provider;

/**
 * The "Enrolment opens on" chip on both learner cards, against the real local_unlistedcourses.
 *
 * A locked card whose only refusal is an enrolment window that has not opened yet names the day it
 * opens, whatever showlockeddate says, and the course start date stands down on that card. The date
 * is local_unlistedcourses' (access::get_next_action()'s opens, batched for the plan accordion), so
 * the chip exists only with the unlisted provider on. Skipped where that plugin is not installed,
 * which includes every Moodle 4.5 and 5.1 site; the translation itself is tested everywhere in
 * enrolment_provider_unlisted_test against a stand-in.
 *
 * The class-level covers tags stay in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_course_progress
 * @covers     \local_dimensions\external\get_competency_courses
 * @covers     \local_dimensions\local\enrolment_state
 */
final class enrolment_opens_chip_test extends \advanced_testcase {
    /** @var int The course start date, a week after the window opens. */
    private int $startdate = 0;

    /**
     * Skip without local_unlistedcourses, then switch its provider and the course start chip on.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        if (!enrolment_provider::unlisted_available()) {
            $this->markTestSkipped('local_unlistedcourses is not installed on this site.');
        }
        $this->resetAfterTest();
        set_config(enrolment_provider::SETTING, '1', 'local_dimensions');
        set_config('showlockeddate', '1', 'local_dimensions');
        $this->startdate = time() + WEEKSECS;
    }

    /**
     * A window opening tomorrow gives the tracker's card the chip, and withdraws the course start.
     *
     * @return void
     */
    public function test_the_tracker_names_the_day_enrolment_opens(): void {
        $opens = time() + DAYSECS;
        $course = $this->course_with_self_window($opens, 0);
        $row = $this->tracker_row($course);

        $this->assertTrue($row['locked']);
        $this->assertSame('none', $row['state']['key']);
        $this->assertSame($opens, $row['state']['opens']);
        $this->assertSame($this->label($opens), $row['state']['openslabel']);
        // The course start chip stands down, although showlockeddate is on and the course starts later.
        $this->assertFalse($row['is_future_date']);
        $this->assertSame('', $row['formatted_start_date']);
    }

    /**
     * A window opening tomorrow gives the plan accordion's card the chip, and withdraws the course start.
     *
     * @return void
     */
    public function test_the_accordion_names_the_day_enrolment_opens(): void {
        $opens = time() + DAYSECS;
        $course = $this->course_with_self_window($opens, 0);
        $row = $this->accordion_row($course);

        $this->assertSame('none', $row['state']['key']);
        $this->assertSame($opens, $row['state']['opens']);
        $this->assertSame($this->label($opens), $row['state']['openslabel']);
        $this->assertSame(0, $row['lockdate']);
    }

    /**
     * Controls: a window that has closed, and an open route, carry no chip on either card.
     *
     * The closed window is still a locked none card, and it keeps the course start chip: the absence
     * of the opening chip is about the window, not about the card.
     *
     * @return void
     */
    public function test_a_closed_window_or_an_open_route_gives_no_chip(): void {
        $closed = $this->course_with_self_window(time() - WEEKSECS, time() - DAYSECS);
        $tracker = $this->tracker_row($closed);
        $this->assertSame(['none', 0, ''], [$tracker['state']['key'], $tracker['state']['opens'], $tracker['state']['openslabel']]);
        $this->assertTrue($tracker['is_future_date']);
        $accordion = $this->accordion_row($closed);
        $this->assertSame([0, ''], [$accordion['state']['opens'], $accordion['state']['openslabel']]);
        $this->assertSame($this->startdate, $accordion['lockdate']);

        $open = $this->course_with_self_window(0, 0);
        $tracker = $this->tracker_row($open);
        $this->assertSame(['open', 0, ''], [$tracker['state']['key'], $tracker['state']['opens'], $tracker['state']['openslabel']]);
        $accordion = $this->accordion_row($open);
        $this->assertSame(
            ['open', 0, ''],
            [$accordion['state']['key'], $accordion['state']['opens'], $accordion['state']['openslabel']]
        );
    }

    /**
     * Control: with the unlisted provider off, the same window gives no chip and the course start returns.
     *
     * @return void
     */
    public function test_the_core_provider_gives_no_chip(): void {
        set_config(enrolment_provider::SETTING, '0', 'local_dimensions');
        $course = $this->course_with_self_window(time() + DAYSECS, 0);

        $tracker = $this->tracker_row($course);
        $this->assertSame([0, ''], [$tracker['state']['opens'], $tracker['state']['openslabel']]);
        $this->assertTrue($tracker['is_future_date']);
        $accordion = $this->accordion_row($course);
        $this->assertSame([0, ''], [$accordion['state']['opens'], $accordion['state']['openslabel']]);
        $this->assertSame($this->startdate, $accordion['lockdate']);
    }

    /**
     * A course starting later, linked to a fresh competency, with an enabled self enrolment window.
     *
     * @param int $start The window's enrolstartdate, 0 for none.
     * @param int $end The window's enrolenddate, 0 for none.
     * @return \stdClass The course, with competencyid set to the linked competency.
     */
    private function course_with_self_window(int $start, int $end): \stdClass {
        global $DB;

        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ALL, 'local_dimensions');
        $course = $this->getDataGenerator()->create_course(['startdate' => $this->startdate]);
        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $DB->update_record('enrol', (object) [
            'id' => $self->id,
            'status' => ENROL_INSTANCE_ENABLED,
            'enrolstartdate' => $start,
            'enrolenddate' => $end,
        ]);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        \core_competency\api::add_competency_to_course($course->id, (int) $competency->get('id'));
        $course->competencyid = (int) $competency->get('id');
        $this->setUser(null);

        return $course;
    }

    /**
     * The tracker's row for the course, as a fresh learner, cleaned through the returns structure.
     *
     * @param \stdClass $course The course.
     * @return array The row.
     */
    private function tracker_row(\stdClass $course): array {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->reset_unlisted_caches();
        $result = external_api::clean_returnvalue(
            get_course_progress::execute_returns(),
            get_course_progress::execute([(int) $course->id])
        );
        return $result[0];
    }

    /**
     * The plan accordion's row for the course, as a fresh learner whose plan holds the competency.
     *
     * @param \stdClass $course The course.
     * @return array The row.
     */
    private function accordion_row(\stdClass $course): array {
        $user = $this->getDataGenerator()->create_user();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $this->setAdminUser();
        $planid = (int) $ccg->create_plan([
            'userid' => $user->id,
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ])->get('id');
        $ccg->create_plan_competency(['planid' => $planid, 'competencyid' => $course->competencyid]);

        $this->setUser($user);
        $this->reset_unlisted_caches();
        $result = external_api::clean_returnvalue(
            get_competency_courses::execute_returns(),
            get_competency_courses::execute($course->competencyid, $planid)
        );
        $rows = array_column($result, null, 'id');
        $this->assertArrayHasKey((int) $course->id, $rows);
        return $rows[(int) $course->id];
    }

    /**
     * Forget local_unlistedcourses' request caches, which are keyed by user and course.
     *
     * @return void
     */
    private function reset_unlisted_caches(): void {
        $api = enrolment_provider::UNLISTED_API;
        $api::reset_caches();
    }

    /**
     * The chip's sentence for a date, as the cards print it.
     *
     * @param int $opens The date.
     * @return string The sentence.
     */
    private function label(int $opens): string {
        $date = userdate($opens, get_string('strftimedatefullshort', 'langconfig'));
        return get_string('enrolmentopens', 'local_dimensions', $date);
    }
}
