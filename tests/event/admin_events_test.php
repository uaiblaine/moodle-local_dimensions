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

namespace local_dimensions\event;

use local_dimensions\constants;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Tests for the hub's administrative audit events.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\event\template_cohort_added
 * @covers     \local_dimensions\event\template_cohort_removed
 * @covers     \local_dimensions\event\cohort_role_added
 * @covers     \local_dimensions\event\cohort_role_removed
 * @covers     \local_dimensions\event\template_customfields_updated
 * @covers     \local_dimensions\event\course_link_added
 * @covers     \local_dimensions\event\course_link_outcome_updated
 * @covers     \local_dimensions\event\course_link_removed
 * @covers     \local_dimensions\event\module_link_added
 * @covers     \local_dimensions\event\module_link_outcome_updated
 * @covers     \local_dimensions\event\module_link_removed
 * @covers     \local_dimensions\event\template_duplicated
 * @covers     \local_dimensions\customfield\lp_handler
 */
final class admin_events_test extends \advanced_testcase {
    /**
     * Attaching and detaching a cohort fires the template_cohort events.
     *
     * @return void
     */
    public function test_template_cohort_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $lpg->create_template(['shortname' => 'T1']);
        $templateid = (int) $template->get('id');
        $cohort = $this->getDataGenerator()->create_cohort();

        $sink = $this->redirectEvents();
        \local_dimensions\external\add_template_cohort::execute($templateid, (int) $cohort->id);
        $added = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof template_cohort_added;
        }));
        $this->assertCount(1, $added);
        $this->assertSame($templateid, (int) $added[0]->other['templateid']);
        $this->assertSame((int) $cohort->id, (int) $added[0]->other['cohortid']);
        $this->assertTrue((bool) $added[0]->other['syncqueued']);
        $this->assertGreaterThan(0, (int) $added[0]->objectid);
        $sink->clear();

        \local_dimensions\external\remove_template_cohort::execute($templateid, (int) $cohort->id);
        $removed = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof template_cohort_removed;
        }));
        $this->assertCount(1, $removed);
        $this->assertSame((int) $cohort->id, (int) $removed[0]->other['cohortid']);
        $sink->close();
    }

    /**
     * Adding and removing a cohort role rule fires the cohort_role events.
     *
     * @return void
     */
    public function test_cohort_role_events(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $templateid = (int) $lpg->create_template(['shortname' => 'T1'])->get('id');
        $cohort = $this->getDataGenerator()->create_cohort();
        $holder = $this->getDataGenerator()->create_user();
        $roleid = create_role('Plan supervisor', 'plansupervisor', '');
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        \local_dimensions\external\add_template_cohort::execute($templateid, (int) $cohort->id);

        $sink = $this->redirectEvents();
        \local_dimensions\external\add_cohort_role::execute($templateid, (int) $holder->id, $roleid, (int) $cohort->id);
        $added = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof cohort_role_added;
        }));
        $this->assertCount(1, $added);
        $this->assertSame((int) $holder->id, (int) $added[0]->relateduserid);
        $this->assertSame($roleid, (int) $added[0]->other['roleid']);
        $assignmentid = (int) $added[0]->objectid;
        $this->assertGreaterThan(0, $assignmentid);
        $sink->clear();

        \local_dimensions\external\remove_cohort_role::execute($templateid, $assignmentid);
        $removed = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof cohort_role_removed;
        }));
        $this->assertCount(1, $removed);
        $this->assertSame((int) $holder->id, (int) $removed[0]->relateduserid);
        $this->assertSame($assignmentid, (int) $removed[0]->objectid);
        $sink->close();
    }

    /**
     * A handler save fires one customfields event carrying the redacted diff.
     *
     * @return void
     */
    public function test_template_customfields_updated_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecustomscss', 1, 'local_dimensions');
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $templateid = (int) $lpg->create_template(['shortname' => 'T1'])->get('id');

        $sink = $this->redirectEvents();
        $formdata = (object) [
            'id' => $templateid,
            'customfield_' . constants::CFIELD_DISPLAYMODE => 2,
            'customfield_' . constants::CFIELD_CUSTOMSCSS . '_editor' => [
                'text' => '.hero { color: red; }',
                'format' => FORMAT_PLAIN,
            ],
        ];
        lp_handler::create()->instance_form_save($formdata, true);
        $events = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof template_customfields_updated;
        }));
        $this->assertCount(1, $events);
        $this->assertSame($templateid, (int) $events[0]->objectid);
        $changed = $events[0]->other['changed'];
        $this->assertSame(2, (int) $changed[constants::CFIELD_DISPLAYMODE]['new']);
        // The SCSS body must be redacted, never stored in the log.
        $this->assertSame('(updated)', $changed[constants::CFIELD_CUSTOMSCSS]);
        $sink->clear();

        // Saving the same values again must not fire a no-change event.
        lp_handler::create()->instance_form_save($formdata, false);
        $again = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof template_customfields_updated;
        }));
        $this->assertCount(0, $again);
        $sink->clear();

        // Re-submitting unchanged fields alongside one real edit lists ONLY the
        // edit: the diff compares effective values, so rows materialised at
        // their default by the full-form submit are not reported as changes.
        $formdata->{'customfield_' . constants::CFIELD_CUSTOMBGCOLOR} = '#112233';
        lp_handler::create()->instance_form_save($formdata, false);
        $third = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof template_customfields_updated;
        }));
        $this->assertCount(1, $third);
        $thirdchanged = $third[0]->other['changed'];
        $this->assertSame([constants::CFIELD_CUSTOMBGCOLOR], array_keys($thirdchanged));
        $this->assertSame('#112233', $thirdchanged[constants::CFIELD_CUSTOMBGCOLOR]['new']);
        $sink->close();
    }

    /**
     * Course link add / outcome change / remove fire the course_link events.
     *
     * @return void
     */
    public function test_course_link_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $competencyid = (int) $lpg->create_competency([
            'competencyframeworkid' => $framework->get('id'),
        ])->get('id');
        $courseid = (int) $this->getDataGenerator()->create_course()->id;

        $sink = $this->redirectEvents();
        \local_dimensions\external\link_competency_course::execute($competencyid, $courseid);
        $added = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof course_link_added;
        }));
        $this->assertCount(1, $added);
        $this->assertSame($competencyid, (int) $added[0]->other['competencyid']);
        $this->assertSame($courseid, (int) $added[0]->courseid);
        $sink->clear();

        \local_dimensions\external\set_course_link_outcome::execute(
            $competencyid,
            $courseid,
            \core_competency\course_competency::OUTCOME_COMPLETE
        );
        $updated = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof course_link_outcome_updated;
        }));
        $this->assertCount(1, $updated);
        $this->assertSame(
            \core_competency\course_competency::OUTCOME_COMPLETE,
            (int) $updated[0]->other['newoutcome']
        );
        $sink->clear();

        \local_dimensions\external\unlink_competency_course::execute($competencyid, $courseid);
        $removed = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof course_link_removed;
        }));
        $this->assertCount(1, $removed);
        $this->assertSame($competencyid, (int) $removed[0]->other['competencyid']);
        $sink->close();
    }

    /**
     * Module link add / outcome change / remove fire the module_link events.
     *
     * @return void
     */
    public function test_module_link_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $competencyid = (int) $lpg->create_competency([
            'competencyframeworkid' => $framework->get('id'),
        ])->get('id');
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cmid = (int) $module->cmid;
        // Core requires the competency on the course before any of its modules.
        \local_dimensions\external\link_competency_course::execute($competencyid, (int) $course->id);

        $sink = $this->redirectEvents();
        \local_dimensions\external\link_competency_module::execute($competencyid, $cmid);
        $added = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof module_link_added;
        }));
        $this->assertCount(1, $added);
        $this->assertSame($cmid, (int) $added[0]->other['cmid']);
        $sink->clear();

        \local_dimensions\external\set_module_link_outcome::execute(
            $competencyid,
            $cmid,
            \core_competency\course_module_competency::OUTCOME_COMPLETE
        );
        $updated = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof module_link_outcome_updated;
        }));
        $this->assertCount(1, $updated);
        $sink->clear();

        \local_dimensions\external\unlink_competency_module::execute($competencyid, $cmid);
        $removed = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof module_link_removed;
        }));
        $this->assertCount(1, $removed);
        $sink->close();
    }

    /**
     * Duplicating a template fires template_duplicated with the copy counts.
     *
     * @return void
     */
    public function test_template_duplicated_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $sourceid = (int) $lpg->create_template(['shortname' => 'Source'])->get('id');
        $formdata = (object) [
            'id' => $sourceid,
            'customfield_' . constants::CFIELD_DISPLAYMODE => 2,
        ];
        lp_handler::create()->instance_form_save($formdata, true);

        $sink = $this->redirectEvents();
        $result = \local_dimensions\external\duplicate_template::execute($sourceid);
        $events = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof template_duplicated;
        }));
        $this->assertCount(1, $events);
        $this->assertSame((int) $result['id'], (int) $events[0]->objectid);
        $this->assertSame($sourceid, (int) $events[0]->other['sourceid']);
        $this->assertGreaterThanOrEqual(1, (int) $events[0]->other['copiedfields']);
        $sink->close();
    }
}
