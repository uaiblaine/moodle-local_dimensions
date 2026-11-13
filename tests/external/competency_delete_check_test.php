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
 * Tests for the competency_delete_check external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\api;
use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The pre-flight must agree with api::delete_competency() and count what blocks it.
 */
#[CoversClass(\local_dimensions\external\competency_delete_check::class)]
final class competency_delete_check_test extends \advanced_testcase {
    /** @var string Web service under test. */
    private const WSNAME = 'local_dimensions_competency_delete_check';

    /**
     * Call the web service as the current user and return its cleaned data.
     *
     * @param int $competencyid Competency id.
     * @return array The cleaned response data.
     */
    private function check(int $competencyid): array {
        $_POST['sesskey'] = sesskey();
        $response = external_api::call_external_function(self::WSNAME, ['competencyid' => $competencyid]);
        $this->assertFalse($response['error'], json_encode($response));
        return $response['data'];
    }

    /**
     * Drop the sesskey the calls plant in $_POST, which resetAfterTest() does not restore.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * A framework with a parent and two children.
     *
     * @return array Keys: generator, frameworkid, parent, child1, child2 (ids as int).
     */
    private function create_tree(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $fwid = (int) $framework->get('id');
        $parent = $ccg->create_competency(['competencyframeworkid' => $fwid]);
        $child1 = $ccg->create_competency(['competencyframeworkid' => $fwid, 'parentid' => $parent->get('id')]);
        $child2 = $ccg->create_competency(['competencyframeworkid' => $fwid, 'parentid' => $parent->get('id')]);
        return [
            'generator' => $ccg,
            'frameworkid' => $fwid,
            'parent' => (int) $parent->get('id'),
            'child1' => (int) $child1->get('id'),
            'child2' => (int) $child2->get('id'),
        ];
    }

    /**
     * A leaf nothing uses can be deleted and every count is zero.
     *
     * @return void
     */
    public function test_leaf_with_nothing(): void {
        $t = $this->create_tree();
        $this->assertSame([
            'candelete' => true,
            'descendants' => 0,
            'parenthasrule' => false,
            'templates' => 0,
            'plans' => 0,
            'courses' => 0,
            'users' => 0,
            'badges' => false,
            'blockedcompetencies' => 0,
        ], $this->check($t['child1']));
    }

    /**
     * Descendants are counted, and an unused tree can be deleted; the control agrees with core.
     *
     * @return void
     */
    public function test_parent_counts_descendants(): void {
        $t = $this->create_tree();
        $data = $this->check($t['parent']);
        $this->assertSame(2, $data['descendants']);
        $this->assertTrue($data['candelete']);
        $this->assertTrue(api::delete_competency($t['parent']));
    }

    /**
     * A course link on a child blocks the parent, and the check agrees with core's refusal.
     *
     * @return void
     */
    public function test_child_linked_to_course_blocks_parent(): void {
        $t = $this->create_tree();
        $course = $this->getDataGenerator()->create_course();
        $t['generator']->create_course_competency(['courseid' => $course->id, 'competencyid' => $t['child1']]);

        $data = $this->check($t['parent']);
        $this->assertFalse($data['candelete']);
        $this->assertSame(1, $data['courses']);
        $this->assertSame(1, $data['blockedcompetencies']);
        $this->assertSame(2, $data['descendants']);
        $this->assertFalse(api::delete_competency($t['parent']));

        // The sibling stays deletable.
        $this->assertTrue($this->check($t['child2'])['candelete']);
    }

    /**
     * A user rating blocks, an archived plan row of the same user adds no second user.
     *
     * @return void
     */
    public function test_users_counted_once(): void {
        $t = $this->create_tree();
        $user = $this->getDataGenerator()->create_user();
        $t['generator']->create_user_competency(['userid' => $user->id, 'competencyid' => $t['child1']]);
        $plan = $t['generator']->create_plan(['userid' => $user->id]);
        $t['generator']->create_user_competency_plan([
            'userid' => $user->id,
            'competencyid' => $t['child2'],
            'planid' => $plan->get('id'),
        ]);

        $data = $this->check($t['parent']);
        $this->assertFalse($data['candelete']);
        $this->assertSame(1, $data['users']);
        $this->assertSame(2, $data['blockedcompetencies']);
        $this->assertFalse(api::delete_competency($t['parent']));
    }

    /**
     * A learning plan template using a descendant blocks and is counted.
     *
     * @return void
     */
    public function test_template_blocks(): void {
        $t = $this->create_tree();
        $template = $t['generator']->create_template();
        $t['generator']->create_template_competency([
            'templateid' => $template->get('id'),
            'competencyid' => $t['child2'],
        ]);

        $data = $this->check($t['parent']);
        $this->assertFalse($data['candelete']);
        $this->assertSame(1, $data['templates']);
        $this->assertFalse(api::delete_competency($t['parent']));
    }

    /**
     * A learning plan using the competency is counted.
     *
     * @return void
     */
    public function test_plan_blocks(): void {
        $t = $this->create_tree();
        $user = $this->getDataGenerator()->create_user();
        $plan = $t['generator']->create_plan(['userid' => $user->id]);
        $t['generator']->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $t['child1']]);

        $data = $this->check($t['child1']);
        $this->assertFalse($data['candelete']);
        $this->assertSame(1, $data['plans']);
    }

    /**
     * The parent's rule is reported for the parent only.
     *
     * @return void
     */
    public function test_parent_has_rule(): void {
        global $DB;
        $t = $this->create_tree();
        $this->assertFalse($this->check($t['child1'])['parenthasrule']);

        // Written directly: the test is about the flag, not about a valid rule configuration.
        $DB->set_field('competency', 'ruletype', \core_competency\competency_rule_all::class, ['id' => $t['parent']]);

        $this->assertTrue($this->check($t['child1'])['parenthasrule']);
        $this->assertFalse($this->check($t['parent'])['parenthasrule']);
    }

    /**
     * Without competencymanage the service refuses.
     *
     * @return void
     */
    public function test_requires_competencymanage(): void {
        $t = $this->create_tree();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        competency_delete_check::execute($t['parent']);
    }
}
