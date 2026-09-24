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
 * Tests for what the competency usage web service reveals and how it spells names.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use context_coursecat;
use core_competency\api;
use core_external\external_api;

/**
 * Hidden templates stay with those who manage them, and names go out plain for double stashes.
 *
 * The covers tag stays in this docblock because the plugin still supports Moodle 4.5, whose
 * moodle-cs cannot see the CoversClass attribute and reports every method as uncovered.
 *
 * @covers \local_dimensions\external\competency_usage
 */
final class competency_usage_visibility_test extends \advanced_testcase {
    /** @var string Web service under test. */
    private const WSNAME = 'local_dimensions_competency_usage';

    /** @var string Name holding a bare ampersand and a less-than followed by a space. */
    private const NAME = 'R&D < Ops';

    /**
     * Call the web service as the current user and return its cleaned data.
     *
     * @param int $competencyid Competency id.
     * @return array The cleaned response data.
     */
    private function call_usage(int $competencyid): array {
        $_POST['sesskey'] = sesskey();
        $response = external_api::call_external_function(self::WSNAME, ['competencyid' => $competencyid]);
        $this->assertFalse($response['error'], json_encode($response));
        return $response['data'];
    }

    /**
     * A user holding the given capabilities in a course category only.
     *
     * @param \context $context Category context the role is assigned in.
     * @param array $capnames Competency capability names (without the moodle/competency: prefix).
     * @return \stdClass The user.
     */
    private function create_category_user(\context $context, array $capnames): \stdClass {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $roleid = $generator->create_role();
        foreach ($capnames as $capname) {
            assign_capability('moodle/competency:' . $capname, CAP_ALLOW, $roleid, $context->id);
        }
        role_assign($roleid, (int) $user->id, $context->id);
        return $user;
    }

    /**
     * A hidden template is listed for a user managing it in its own context, never for a viewer.
     *
     * Everything lives in one course category, so the manager's templatemanage exists only in the
     * template's own context: a check made at the site would refuse them as well.
     *
     * @return void
     */
    public function test_hidden_templates_need_templatemanage_in_their_own_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');

        $category = $generator->create_category();
        $categorycontext = context_coursecat::instance((int) $category->id);
        $framework = $ccg->create_framework(['contextid' => $categorycontext->id]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');
        $open = $ccg->create_template(['shortname' => 'Open plan', 'contextid' => $categorycontext->id]);
        $hidden = $ccg->create_template([
            'shortname' => 'Hidden plan',
            'contextid' => $categorycontext->id,
            'visible' => 0,
        ]);
        api::add_competency_to_template($open->get('id'), $competencyid);
        api::add_competency_to_template($hidden->get('id'), $competencyid);

        $viewer = $this->create_category_user($categorycontext, ['competencyview', 'templateview']);
        $manager = $this->create_category_user(
            $categorycontext,
            ['competencyview', 'templateview', 'templatemanage']
        );

        // The control: the visible template reaches the viewer, so the list itself was built.
        $this->setUser($viewer);
        $templates = $this->call_usage($competencyid)['templates'];
        $this->assertSame(['Open plan'], array_column($templates, 'name'));

        $this->setUser($manager);
        $templates = $this->call_usage($competencyid)['templates'];
        $visibility = array_combine(array_column($templates, 'name'), array_column($templates, 'visible'));
        ksort($visibility);
        $this->assertSame(['Hidden plan' => false, 'Open plan' => true], $visibility);
    }

    /**
     * Course, activity and template names go out plain and are escaped once by the modal.
     *
     * @return void
     */
    public function test_names_are_plain_and_the_modal_escapes_them_once(): void {
        global $OUTPUT;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');

        $framework = $ccg->create_framework();
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');
        $course = $generator->create_course(['fullname' => self::NAME, 'shortname' => self::NAME . ' 101']);
        $page = $generator->create_module('page', ['course' => $course->id, 'name' => self::NAME . ' page']);
        api::add_competency_to_course((int) $course->id, $competencyid);
        api::add_competency_to_course_module((int) $page->cmid, $competencyid);
        $template = $ccg->create_template(['shortname' => self::NAME . ' plan']);
        api::add_competency_to_template($template->get('id'), $competencyid);

        $usage = $this->call_usage($competencyid);
        $this->assertSame(self::NAME, $usage['courses'][0]['name']);
        $this->assertSame(self::NAME . ' 101', $usage['courses'][0]['shortname']);
        $this->assertSame(self::NAME . ' page', $usage['activities'][0]['name']);
        $this->assertSame(self::NAME, $usage['activities'][0]['coursename']);
        $this->assertSame(self::NAME . ' 101', $usage['activities'][0]['courseshortname']);
        $this->assertSame(self::NAME . ' plan', $usage['templates'][0]['name']);

        // The context structure.js hands the template, with every section shown at once.
        $html = $OUTPUT->render_from_template('local_dimensions/central/competency_usage_modal', [
            'showcourses' => true,
            'hascourses' => true,
            'courses' => $usage['courses'],
            'showactivities' => true,
            'hasactivities' => true,
            'activities' => $usage['activities'],
            'showtemplates' => true,
            'hastemplates' => true,
            'templates' => $usage['templates'],
        ]);
        $this->assertStringContainsString('R&amp;D &lt; Ops page', $html);
        $this->assertStringContainsString('R&amp;D &lt; Ops plan', $html);
        $this->assertStringContainsString('R&amp;D &lt; Ops 101', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
    }
}
