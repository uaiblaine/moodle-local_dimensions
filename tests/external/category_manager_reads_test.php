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
 * Tests for the hub's read web services as a manager scoped to one course category.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use advanced_testcase;
use context_coursecat;
use context_system;
use core_competency\api;

/**
 * The read services validate in the framework's context, never at the site.
 *
 * The fixture withdraws the authenticated-user default for competencyview at the system context,
 * which is what makes these cases mutation-checkable: with the default in place a site-level
 * require_capability() passes for everyone and a test could not tell the two gates apart.
 *
 * @covers \local_dimensions\external\browse_competencies
 * @covers \local_dimensions\external\browse_structure
 * @covers \local_dimensions\external\get_structure_node
 * @covers \local_dimensions\external\search_structure
 * @covers \local_dimensions\external\search_competencies
 * @covers \local_dimensions\external\get_competency_links
 * @covers \local_dimensions\external\search_linkable_courses
 * @covers \local_dimensions\external\competency_usage
 */
final class category_manager_reads_test extends advanced_testcase {
    /**
     * A category manager, a framework with one competency in their category, and a system framework.
     *
     * @return array Keys: user, category, catframework, catcompetency, sysframework, syscompetency.
     */
    private function fixture(): array {
        global $CFG;
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');

        $category = $generator->create_category();
        $categorycontext = context_coursecat::instance((int) $category->id);
        $catframework = $ccg->create_framework(['shortname' => 'Category framework', 'contextid' => $categorycontext->id]);
        $catcompetency = $ccg->create_competency([
            'competencyframeworkid' => $catframework->get('id'),
            'shortname' => 'Alpha in category',
        ]);
        $sysframework = $ccg->create_framework(['shortname' => 'Site framework']);
        $syscompetency = $ccg->create_competency([
            'competencyframeworkid' => $sysframework->get('id'),
            'shortname' => 'Alpha at site',
        ]);

        $user = $generator->create_user();
        $roleid = $generator->create_role();
        foreach (['competencyview', 'competencymanage', 'templateview', 'templatemanage'] as $capname) {
            assign_capability('moodle/competency:' . $capname, CAP_ALLOW, $roleid, $categorycontext->id);
        }
        role_assign($roleid, (int) $user->id, $categorycontext->id);
        assign_capability(
            'moodle/competency:competencyview',
            CAP_PREVENT,
            (int) $CFG->defaultuserroleid,
            context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        return [
            'user' => $user,
            'category' => $category,
            'catframework' => $catframework,
            'catcompetency' => $catcompetency,
            'sysframework' => $sysframework,
            'syscompetency' => $syscompetency,
        ];
    }

    /**
     * The framework-scoped services answer for the category's framework and read empty for the site's.
     *
     * @return void
     */
    public function test_framework_scoped_services_answer_in_the_category_only(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $catid = (int) $f['catframework']->get('id');
        $sysid = (int) $f['sysframework']->get('id');

        $this->assertSame(1, browse_structure::execute($catid)['total']);
        $this->assertSame(0, browse_structure::execute($sysid)['total']);

        $this->assertSame(1, browse_competencies::execute($catid)['total']);
        $this->assertSame(0, browse_competencies::execute($sysid)['total']);

        $this->assertSame(1, search_structure::execute($catid, 'Alpha')['total']);
        $this->assertSame(0, search_structure::execute($sysid, 'Alpha')['total']);

        $this->assertTrue(get_structure_node::execute((int) $f['catcompetency']->get('id'))['found']);
        $this->assertFalse(get_structure_node::execute((int) $f['syscompetency']->get('id'))['found']);
    }

    /**
     * The cross-framework search returns the category's hits and nothing from the site.
     *
     * @return void
     */
    public function test_the_cross_framework_search_is_filtered_per_framework(): void {
        $this->resetAfterTest();
        $f = $this->fixture();

        $result = search_competencies::execute('Alpha', 0, 25);
        $this->assertSame(1, $result['total']);
        $this->assertSame((int) $f['catcompetency']->get('id'), (int) $result['items'][0]['id']);
    }

    /**
     * The competency-scoped services validate in the framework's context and refuse the site's.
     *
     * @return void
     */
    public function test_competency_scoped_services_validate_in_the_framework_context(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $catcompetencyid = (int) $f['catcompetency']->get('id');
        $syscompetencyid = (int) $f['syscompetency']->get('id');

        $this->assertSame(0, get_competency_links::execute($catcompetencyid)['total']);
        $this->assertSame(0, search_linkable_courses::execute($catcompetencyid)['total']);
        $usage = competency_usage::execute($catcompetencyid);
        $this->assertSame([], $usage['courses']);

        $refused = 0;
        foreach ([get_competency_links::class, search_linkable_courses::class, competency_usage::class] as $service) {
            try {
                $service::execute($syscompetencyid);
            } catch (\required_capability_exception $e) {
                $refused++;
            }
        }
        $this->assertSame(3, $refused, 'Every competency-scoped service must refuse a site competency');
    }

    /**
     * The usage popover lists the category's templates through their own context, not the site's.
     *
     * Core's api::list_templates_using_competency() requires template read access at the SYSTEM
     * context and throws otherwise; a category manager used to lose the whole popover to it.
     *
     * @return void
     */
    public function test_usage_lists_readable_templates_without_a_site_level_check(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $competencyid = (int) $f['catcompetency']->get('id');

        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $categorycontext = context_coursecat::instance((int) $f['category']->id);
        $cattemplate = $ccg->create_template(['shortname' => 'Category template', 'contextid' => $categorycontext->id]);
        $systemplate = $ccg->create_template(['shortname' => 'Site template']);
        api::add_competency_to_template($cattemplate->get('id'), $competencyid);
        api::add_competency_to_template($systemplate->get('id'), $competencyid);
        $this->setUser($f['user']);

        $usage = competency_usage::execute($competencyid);
        $names = array_column($usage['templates'], 'name');
        $this->assertSame(['Category template'], $names);

        $this->setAdminUser();
        $names = array_column(competency_usage::execute($competencyid)['templates'], 'name');
        sort($names);
        $this->assertSame(['Category template', 'Site template'], $names);
    }
}
