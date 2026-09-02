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
 * Tests for who may see competency and template pictures.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use advanced_testcase;
use context_coursecat;
use context_system;
use core_competency\api;

/**
 * The picture read check that local_dimensions_pluginfile() applies before serving a file.
 *
 * @covers \local_dimensions\picture_manager
 */
final class picture_manager_can_view_test extends advanced_testcase {
    /**
     * A category holding a visible and a hidden template and one competency, plus a plain user.
     *
     * @return array Keys: user, categorycontext, visible (template), hidden (template), competency.
     */
    private function fixture(): array {
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');
        $category = $generator->create_category();
        $categorycontext = context_coursecat::instance((int) $category->id);
        $visible = $ccg->create_template(['contextid' => $categorycontext->id, 'visible' => 1]);
        $hidden = $ccg->create_template(['contextid' => $categorycontext->id, 'visible' => 0]);
        $framework = $ccg->create_framework(['contextid' => $categorycontext->id]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $user = $generator->create_user();
        return [
            'user' => $user,
            'categorycontext' => $categorycontext,
            'visible' => $visible,
            'hidden' => $hidden,
            'competency' => $competency,
        ];
    }

    /**
     * A visible template's pictures go to any logged-in user; a hidden one's do not.
     *
     * @return void
     */
    public function test_template_pictures_follow_the_template_visibility(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->setUser($f['user']);

        $this->assertTrue(picture_manager::can_view(picture_manager::FILEAREA_TEMPLATE, (int) $f['visible']->get('id')));
        $this->assertTrue(picture_manager::can_view(picture_manager::FILEAREA_TEMPLATE_CARD, (int) $f['visible']->get('id')));
        $this->assertFalse(picture_manager::can_view(picture_manager::FILEAREA_TEMPLATE, (int) $f['hidden']->get('id')));
        $this->assertFalse(picture_manager::can_view(picture_manager::FILEAREA_TEMPLATE_CARD, (int) $f['hidden']->get('id')));
    }

    /**
     * A hidden template's pictures reach a learner with a plan based on it, and a manager of its context.
     *
     * @return void
     */
    public function test_hidden_template_pictures_reach_plan_holders_and_managers(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $hiddenid = (int) $f['hidden']->get('id');

        // The learner: refused until a plan based on the template exists for them. Core refuses
        // to create a plan from a hidden template, so the plan is created first and the template
        // hidden afterwards - the everyday order when a template is retired under its learners.
        $this->setUser($f['user']);
        $this->assertFalse(picture_manager::can_view(picture_manager::FILEAREA_TEMPLATE, $hiddenid));
        $this->setAdminUser();
        api::update_template((object) ['id' => $hiddenid, 'visible' => 1]);
        api::create_plan_from_template($hiddenid, (int) $f['user']->id);
        api::update_template((object) ['id' => $hiddenid, 'visible' => 0]);
        $this->setUser($f['user']);
        $this->assertTrue(picture_manager::can_view(picture_manager::FILEAREA_TEMPLATE, $hiddenid));

        // A manager who may read templates in the category, with no plan.
        $manager = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:templateview', CAP_ALLOW, $roleid, $f['categorycontext']->id);
        role_assign($roleid, (int) $manager->id, $f['categorycontext']->id);
        $this->setUser($manager);
        $this->assertTrue(picture_manager::can_view(picture_manager::FILEAREA_TEMPLATE, $hiddenid));
    }

    /**
     * A competency's pictures follow its framework's read rule, and unknown items are refused.
     *
     * @return void
     */
    public function test_competency_pictures_follow_the_framework_and_unknown_items_are_refused(): void {
        global $CFG;
        $this->resetAfterTest();
        $f = $this->fixture();
        $competencyid = (int) $f['competency']->get('id');
        $this->setUser($f['user']);

        // Reading is an authenticated-user default at every category.
        $this->assertTrue(picture_manager::can_view(picture_manager::FILEAREA_COMPETENCY, $competencyid));
        $this->assertTrue(picture_manager::can_view(picture_manager::FILEAREA_COMPETENCY_CARD, $competencyid));

        $this->assertFalse(picture_manager::can_view(picture_manager::FILEAREA_COMPETENCY, 987654));
        $this->assertFalse(picture_manager::can_view(picture_manager::FILEAREA_TEMPLATE, 987654));
        $this->assertFalse(picture_manager::can_view('somewhere_else', $competencyid));
        $this->assertFalse(picture_manager::can_view(picture_manager::FILEAREA_COMPETENCY, 0));

        // Withdraw the default: the framework is no longer readable, so neither is the picture.
        $this->setAdminUser();
        assign_capability(
            'moodle/competency:competencyview',
            CAP_PREVENT,
            (int) $CFG->defaultuserroleid,
            context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($f['user']);
        $this->assertFalse(picture_manager::can_view(picture_manager::FILEAREA_COMPETENCY, $competencyid));
    }
}
