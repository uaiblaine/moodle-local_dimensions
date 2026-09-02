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

/**
 * Tests for what happens to competency data when a course category is deleted.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use advanced_testcase;
use context_coursecat;
use core_competency\api;
use core_competency\competency_framework;
use core_competency\template;

/**
 * Category deletion refuses in-use competency data, deletes the rest, and moves what moves.
 *
 * The scenarios go through core's own delete_full() and delete_move(), so they prove the
 * lib.php callbacks are discovered and wired, not only the class.
 *
 * @covers \local_dimensions\local\category_lifecycle
 */
final class category_lifecycle_test extends advanced_testcase {
    /**
     * A category with one framework (one competency) and one template.
     *
     * @return array Keys: category (stdClass), framework, competency, template (persistents).
     */
    private function fixture(): array {
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');
        $category = $generator->create_category(['name' => 'Doomed']);
        $contextid = context_coursecat::instance((int) $category->id)->id;
        $framework = $ccg->create_framework(['shortname' => 'Doomed framework', 'contextid' => $contextid]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $template = $ccg->create_template(['shortname' => 'Doomed template', 'contextid' => $contextid]);
        return ['category' => $category, 'framework' => $framework, 'competency' => $competency, 'template' => $template];
    }

    /**
     * The deletion form learns what the category holds.
     *
     * @return void
     */
    public function test_the_form_line_counts_frameworks_templates_and_what_is_in_use(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $categoryid = (int) $f['category']->id;

        $this->assertSame(['frameworks' => 1, 'templates' => 1, 'inuse' => 0], category_lifecycle::summary($categoryid));
        $this->assertStringContainsString('1', category_lifecycle::describe($categoryid));
        $this->assertSame('', category_lifecycle::describe((int) $this->getDataGenerator()->create_category()->id));

        $course = $this->getDataGenerator()->create_course();
        api::add_competency_to_course($course->id, $f['competency']->get('id'));
        $this->assertSame(1, category_lifecycle::summary($categoryid)['inuse']);
    }

    /**
     * Deleting a category whose competency data is unused deletes that data with it.
     *
     * @return void
     */
    public function test_delete_all_removes_unused_frameworks_and_templates(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $frameworkid = (int) $f['framework']->get('id');
        $templateid = (int) $f['template']->get('id');

        \core_course_category::get((int) $f['category']->id)->delete_full(false);

        $this->assertFalse(competency_framework::record_exists($frameworkid));
        $this->assertFalse(template::record_exists($templateid));
        $this->assertNull(\core_course_category::get((int) $f['category']->id, IGNORE_MISSING));
    }

    /**
     * A category holding a competency that a course uses cannot be deleted, and nothing is touched.
     *
     * @return void
     */
    public function test_delete_all_is_refused_while_a_competency_is_in_use(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $course = $this->getDataGenerator()->create_course();
        api::add_competency_to_course($course->id, $f['competency']->get('id'));

        try {
            \core_course_category::get((int) $f['category']->id)->delete_full(false);
            $this->fail('Deleting a category with an in-use competency must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('central_categorydelete_blocked', $e->errorcode);
        }
        $this->assertTrue(competency_framework::record_exists((int) $f['framework']->get('id')));
        $this->assertTrue(template::record_exists((int) $f['template']->get('id')));
        $this->assertNotNull(\core_course_category::get((int) $f['category']->id, IGNORE_MISSING));
    }

    /**
     * A template with a linked plan counts as in use too.
     *
     * @return void
     */
    public function test_delete_all_is_refused_while_a_template_has_plans(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $learner = $this->getDataGenerator()->create_user();
        api::create_plan_from_template($f['template']->get('id'), (int) $learner->id);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/Doomed/');
        \core_course_category::get((int) $f['category']->id)->delete_full(false);
    }

    /**
     * Deleting with "move contents" re-homes the frameworks and templates to the destination.
     *
     * @return void
     */
    public function test_delete_move_rehomes_frameworks_and_templates(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $destination = $this->getDataGenerator()->create_category(['name' => 'Destination']);
        $destinationcontext = context_coursecat::instance((int) $destination->id);
        $learner = $this->getDataGenerator()->create_user();
        $plan = api::create_plan_from_template($f['template']->get('id'), (int) $learner->id);

        $this->assertTrue(category_lifecycle::can_move_contents((int) $f['category']->id, (int) $destination->id));
        \core_course_category::get((int) $f['category']->id)->delete_move((int) $destination->id, false);

        $framework = new competency_framework((int) $f['framework']->get('id'));
        $template = new template((int) $f['template']->get('id'));
        $this->assertSame($destinationcontext->id, (int) $framework->get('contextid'));
        $this->assertSame($destinationcontext->id, (int) $template->get('contextid'));
        $this->assertSame((int) $template->get('id'), (int) (new \core_competency\plan((int) $plan->get('id')))->get('templateid'));
        $this->assertNull(\core_course_category::get((int) $f['category']->id, IGNORE_MISSING));
    }

    /**
     * The move option is not offered to someone who could not manage the objects at the destination.
     *
     * @return void
     */
    public function test_the_move_option_needs_management_rights_at_the_destination(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $destination = $this->getDataGenerator()->create_category();
        $sourcecontext = context_coursecat::instance((int) $f['category']->id);

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $roleid, $sourcecontext->id);
        assign_capability('moodle/competency:templatemanage', CAP_ALLOW, $roleid, $sourcecontext->id);
        role_assign($roleid, (int) $user->id, $sourcecontext->id);
        $this->setUser($user);

        $this->assertFalse(category_lifecycle::can_move_contents((int) $f['category']->id, (int) $destination->id));
        // A category holding nothing of ours moves freely, whatever the rights at the destination.
        $empty = $this->getDataGenerator()->create_category();
        $this->assertTrue(category_lifecycle::can_move_contents((int) $empty->id, (int) $destination->id));
    }
}
