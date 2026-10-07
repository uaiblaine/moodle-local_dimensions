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

namespace local_dimensions\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/dimensions/tests/fixtures/testable_enrolment_provider.php');

/**
 * Which enrolment provider the cards ask, and the frozen rule of the core one.
 *
 * The selection runs on every site: the availability of local_unlistedcourses is a seam
 * (testable_enrolment_provider), so the setting's rule is proved where that plugin is absent, and
 * the real availability check is proved both ways. The core provider is the rule this plugin always
 * had; the control of its frozen size is that the rows local_unlistedcourses reads as suspended or
 * ended read here as none.
 *
 * The class-level covers tag stays in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\enrolment_provider
 * @covers     \local_dimensions\local\enrolment_provider_core
 */
final class enrolment_provider_test extends \advanced_testcase {
    /**
     * Forget what a test made the stand-in answer.
     *
     * @return void
     */
    protected function tearDown(): void {
        testable_enrolment_provider::$available = false;
        parent::tearDown();
    }

    /**
     * The setting selects local_unlistedcourses only when it is on and the plugin is there.
     *
     * @return void
     */
    public function test_the_setting_selects_the_unlisted_provider_only_where_it_is_available(): void {
        $this->resetAfterTest();

        // Off by default: nothing stored.
        testable_enrolment_provider::$available = true;
        $this->assertSame('core', testable_enrolment_provider::get()->name());

        // On, and the plugin is there: the plugin's rules.
        set_config(enrolment_provider::SETTING, '1', 'local_dimensions');
        $this->assertSame('unlisted', testable_enrolment_provider::get()->name());

        // On, but the plugin is gone: the setting outlives it harmlessly.
        testable_enrolment_provider::$available = false;
        $this->assertSame('core', testable_enrolment_provider::get()->name());

        // Only a stored '1' is on.
        testable_enrolment_provider::$available = true;
        foreach (['0', '', 'yes'] as $value) {
            set_config(enrolment_provider::SETTING, $value, 'local_dimensions');
            $this->assertSame('core', testable_enrolment_provider::get()->name(), 'Stored ' . var_export($value, true));
        }
    }

    /**
     * The real availability check answers no wherever local_unlistedcourses cannot run, and yes where it is.
     *
     * Every Moodle 4.5 and 5.1 site is the first case, and so is a Moodle 5.2 site without the plugin;
     * with the plugin installed, the branch is the guard that still says no below 5.2.
     *
     * @return void
     */
    public function test_availability_follows_the_plugin_and_the_branch(): void {
        global $CFG;

        $this->resetAfterTest();
        set_config(enrolment_provider::SETTING, '1', 'local_dimensions');
        $installed = \core_component::get_component_directory('local_unlistedcourses') !== null;

        if (!$installed) {
            $this->assertFalse(enrolment_provider::unlisted_available());
            // The stored setting cannot reach an API that is not there.
            $this->assertSame('core', enrolment_provider::get()->name());
            return;
        }

        $this->assertTrue(enrolment_provider::unlisted_available());
        $this->assertSame('unlisted', enrolment_provider::get()->name());
        $branch = $CFG->branch;
        try {
            $CFG->branch = '501';
            $this->assertFalse(enrolment_provider::unlisted_available());
            $this->assertSame('core', enrolment_provider::get()->name());
        } finally {
            $CFG->branch = $branch;
        }
    }

    /**
     * The core provider reads a later enrolment as scheduled, on its own date.
     *
     * @return void
     */
    public function test_core_reads_a_later_enrolment_as_scheduled(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $start = time() + WEEKSECS;
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'student', 'manual', $start);
        $this->setUser($user);

        $state = (new enrolment_provider_core())->locked_state($course, (int) $user->id);

        $this->assertSame(enrolment_provider::STATE_SCHEDULED, $state['state']);
        $this->assertSame($start, $state['date']);
        $this->assertNull($state['actionurl']);
        $this->assertNull($state['routeurl']);
    }

    /**
     * An open self enrolment beside a later enrolment is the route line, not the state.
     *
     * The control is the same viewer with the self instance shut: the state is unchanged, and the
     * route line goes.
     *
     * @return void
     */
    public function test_core_puts_an_open_route_beside_a_relationship(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'student', 'manual', time() + WEEKSECS);
        $self = $this->open_self($course);
        $this->setUser($user);

        $state = (new enrolment_provider_core())->locked_state($course, (int) $user->id);
        $this->assertSame(enrolment_provider::STATE_SCHEDULED, $state['state']);
        $this->assertSame((new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false), $state['routeurl']);

        enrol_get_plugin('self')->update_status($self, ENROL_INSTANCE_DISABLED);
        $state = (new enrolment_provider_core())->locked_state($course, (int) $user->id);
        $this->assertSame(enrolment_provider::STATE_SCHEDULED, $state['state']);
        $this->assertNull($state['routeurl']);
    }

    /**
     * With no relationship an open route is the state itself, leading to the course page.
     *
     * @return void
     */
    public function test_core_reads_an_open_route_as_open_and_nothing_as_none(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertSame(
            enrolment_provider::none_state((int) $course->id),
            (new enrolment_provider_core())->locked_state($course, (int) $user->id)
        );

        $this->open_self($course);
        $state = (new enrolment_provider_core())->locked_state($course, (int) $user->id);
        $this->assertSame(enrolment_provider::STATE_OPEN, $state['state']);
        $this->assertSame((new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false), $state['actionurl']);
        $this->assertNull($state['routeurl']);
    }

    /**
     * The core provider stays at its size: suspended and ended enrolments read as none.
     *
     * local_unlistedcourses reads these rows as suspended and expired; this plugin never told them
     * apart and does not start now. The control is the same translation of the plugin's answers,
     * which does name both states, so the absence below is the core rule's and not the vocabulary's.
     *
     * @return void
     */
    public function test_core_adds_no_state_of_its_own(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $suspended = $this->getDataGenerator()->create_user();
        $ended = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator();
        $generator->enrol_user((int) $suspended->id, (int) $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $generator->enrol_user((int) $ended->id, (int) $course->id, 'student', 'manual', time() - 2 * WEEKSECS, time() - WEEKSECS);
        $core = new enrolment_provider_core();

        $this->setUser($suspended);
        $this->assertSame(enrolment_provider::STATE_NONE, $core->locked_state($course, (int) $suspended->id)['state']);
        $this->setUser($ended);
        $this->assertSame(enrolment_provider::STATE_NONE, $core->locked_state($course, (int) $ended->id)['state']);

        // Control: the plugin's answers for the same rows are states of their own.
        $none = ['type' => 'none', 'routes' => []];
        $this->assertSame(
            enrolment_provider::STATE_SUSPENDED,
            enrolment_provider_unlisted::translate((int) $course->id, ['type' => 'suspended'], $none)['state']
        );
        $this->assertSame(
            enrolment_provider::STATE_EXPIRED,
            enrolment_provider_unlisted::translate((int) $course->id, ['type' => 'expired', 'endsat' => 1], $none)['state']
        );
    }

    /**
     * The batch answers what the per-course call answers, course by course.
     *
     * @return void
     */
    public function test_core_batch_matches_the_per_course_answers(): void {
        $this->resetAfterTest();
        $open = $this->getDataGenerator()->create_course();
        $this->open_self($open);
        $later = $this->getDataGenerator()->create_course();
        $nothing = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $later->id, 'student', 'manual', time() + DAYSECS);
        $this->setUser($user);
        $core = new enrolment_provider_core();

        $courses = [(int) $open->id => $open, (int) $later->id => $later, (int) $nothing->id => $nothing];
        $batch = $core->locked_states($courses, (int) $user->id);

        $this->assertSame(array_keys($courses), array_keys($batch));
        foreach ($courses as $id => $course) {
            $this->assertSame($core->locked_state($course, (int) $user->id), $batch[$id]);
        }
        $this->assertSame(
            [enrolment_provider::STATE_OPEN, enrolment_provider::STATE_SCHEDULED, enrolment_provider::STATE_NONE],
            array_column($batch, 'state')
        );
    }

    /**
     * An offer carries no route line: the route line is a second way in beside a relationship.
     *
     * @return void
     */
    public function test_only_a_relationship_keeps_a_route_line(): void {
        $this->resetAfterTest();
        $relationship = enrolment_provider_unlisted::translate(7, ['type' => 'suspended'], ['type' => 'open', 'routes' => [1]]);
        $offer = enrolment_provider_unlisted::translate(7, ['type' => 'none'], ['type' => 'open', 'routes' => [1]]);

        $this->assertNotNull($relationship['routeurl']);
        $this->assertSame(enrolment_provider::STATE_OPEN, $offer['state']);
        $this->assertNull($offer['routeurl']);
    }

    /**
     * Enable the course's self enrolment instance.
     *
     * @param \stdClass $course The course.
     * @return \stdClass The instance.
     */
    private function open_self(\stdClass $course): \stdClass {
        global $DB;

        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        enrol_get_plugin('self')->update_status($self, ENROL_INSTANCE_ENABLED);
        return $DB->get_record('enrol', ['id' => $self->id], '*', MUST_EXIST);
    }
}
