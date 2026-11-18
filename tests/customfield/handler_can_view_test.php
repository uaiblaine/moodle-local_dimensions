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

namespace local_dimensions\customfield;

use local_dimensions\constants;
use local_dimensions\helper;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for who may view a competency's or a template's custom-field data.
 *
 * Core's textarea and customfield_picture file callbacks ask can_view() and nothing else, behind
 * no login, so this answer is the whole access check for the files embedded in the fields.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_dimensions\customfield\lp_handler::class)]
#[CoversClass(\local_dimensions\customfield\competency_handler::class)]
final class handler_can_view_test extends \advanced_testcase {
    /**
     * A category holding a visible and a hidden template and one competency, the fields of both areas.
     *
     * @return array Keys: visible, hidden (templates), competency, lpfield, competencyfield.
     */
    private function fixture(): array {
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $category = $this->getDataGenerator()->create_category();
        $contextid = \context_coursecat::instance((int) $category->id)->id;
        $framework = $ccg->create_framework(['contextid' => $contextid]);
        $f = [
            'visible' => (int) $ccg->create_template(['contextid' => $contextid, 'visible' => 1])->get('id'),
            'hidden' => (int) $ccg->create_template(['contextid' => $contextid, 'visible' => 0])->get('id'),
            'competency' => (int) $ccg->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id'),
            'lpfield' => helper::find_field_by_shortname(constants::CFIELD_CUSTOMBGCOLOR, helper::AREA_LP),
            'competencyfield' => helper::find_field_by_shortname(constants::CFIELD_CUSTOMBGCOLOR, helper::AREA_COMPETENCY),
        ];
        $this->assertNotNull($f['lpfield']);
        $this->assertNotNull($f['competencyfield']);
        return $f;
    }

    /**
     * A visitor who is not logged in sees no instance's data, though the instances are readable
     * to a logged-in user (the control).
     *
     * @return void
     */
    public function test_a_visitor_who_is_not_logged_in_sees_no_instance_data(): void {
        $this->resetAfterTest();
        $f = $this->fixture();

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(lp_handler::create()->can_view($f['lpfield'], $f['visible']));
        $this->assertTrue(competency_handler::create()->can_view($f['competencyfield'], $f['competency']));

        $this->setUser(0);
        $this->assertFalse(lp_handler::create()->can_view($f['lpfield'], $f['visible']));
        $this->assertFalse(competency_handler::create()->can_view($f['competencyfield'], $f['competency']));
    }

    /**
     * A competency's data follows its framework's read rule for a logged-in user, and needs a
     * login even on a site whose not-logged-in role reads competencies.
     *
     * @return void
     */
    public function test_competency_data_follows_the_framework_and_needs_a_login(): void {
        global $CFG;
        $this->resetAfterTest();
        $f = $this->fixture();
        $handler = competency_handler::create();
        $system = \context_system::instance()->id;
        // Forced login would refuse the not-logged-in visitor in has_capability() itself.
        set_config('forcelogin', 0);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertTrue($handler->can_view($f['competencyfield'], $f['competency']), 'Control: the default reader');
        $this->setAdminUser();
        assign_capability('moodle/competency:competencyview', CAP_PROHIBIT, (int) $CFG->defaultuserroleid, $system, true);
        assign_capability('moodle/competency:competencyview', CAP_ALLOW, (int) $CFG->notloggedinroleid, $system, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($user);
        $this->assertFalse($handler->can_view($f['competencyfield'], $f['competency']), 'The framework is not readable');
        $this->setUser(0);
        $this->assertTrue(
            \core_competency\competency_framework::can_read_context(\context_system::instance()),
            'Control: the not-logged-in role reads competencies'
        );
        $this->assertFalse($handler->can_view($f['competencyfield'], $f['competency']), 'A login comes first');
    }

    /**
     * A hidden template's data stays with those who may read it; a visible one's goes to any
     * logged-in user, the guest included, as its pictures do.
     *
     * @return void
     */
    public function test_template_data_follows_the_template_visibility(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $handler = lp_handler::create();

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue($handler->can_view($f['lpfield'], $f['visible']));
        $this->assertFalse($handler->can_view($f['lpfield'], $f['hidden']));

        $this->setGuestUser();
        $this->assertTrue($handler->can_view($f['lpfield'], $f['visible']));
        $this->assertFalse($handler->can_view($f['lpfield'], $f['hidden']));

        $this->setAdminUser();
        $this->assertTrue($handler->can_view($f['lpfield'], $f['hidden']));
    }

    /**
     * Instance 0 asks whether the field exists for the viewer (the report builder asks so), and
     * every field does, for anyone.
     *
     * @return void
     */
    public function test_instance_zero_reports_the_field_as_available(): void {
        $this->resetAfterTest();
        $f = $this->fixture();

        $this->setUser(0);
        $this->assertTrue(lp_handler::create()->can_view($f['lpfield'], 0));
        $this->assertTrue(competency_handler::create()->can_view($f['competencyfield'], 0));
    }
}
