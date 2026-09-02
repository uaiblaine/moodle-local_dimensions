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
 * Tests for the Competency hub tabs' availability in a course-category context.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output\dynamictabs;

use advanced_testcase;
use context_coursecat;
use context_system;
use core_external\external_api;

/**
 * The three hub tabs must answer is_available() for the context the pane names.
 *
 * The fixture is a manager who holds the competency capabilities in ONE course category and has
 * the authenticated-user default for competencyview withdrawn at the system context. That last
 * step is what makes the frameworks and structure cases mutation-checkable: with the default in
 * place a system-context check passes for everyone, and a test could not tell the two apart.
 *
 * Class-level docblock annotations rather than attributes: moodle-cs on the 4.05 CI leg cannot
 * see PHP attributes and reports every method as uncovered.
 *
 * @covers \local_dimensions\output\dynamictabs\frameworks
 * @covers \local_dimensions\output\dynamictabs\structure
 * @covers \local_dimensions\output\dynamictabs\plans
 */
final class availability_test extends advanced_testcase {
    /**
     * Create a manager scoped to a fresh course category, with no competency access at the site.
     *
     * @return array Keys: user (stdClass), category (stdClass), other (stdClass, a category the
     *               user holds nothing in).
     */
    private function create_category_manager(): array {
        global $CFG;

        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $other = $generator->create_category();
        $categorycontext = context_coursecat::instance((int) $category->id);

        $user = $generator->create_user();
        $roleid = $generator->create_role();
        foreach (['competencyview', 'competencymanage', 'templateview', 'templatemanage'] as $capname) {
            assign_capability('moodle/competency:' . $capname, CAP_ALLOW, $roleid, $categorycontext->id);
        }
        role_assign($roleid, (int) $user->id, $categorycontext->id);

        // Withdraw the authenticated-user default for competencyview at the site, so the system
        // context is genuinely unreadable to this manager; the category ALLOW still wins there.
        assign_capability(
            'moodle/competency:competencyview',
            CAP_PREVENT,
            (int) $CFG->defaultuserroleid,
            context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();

        return ['user' => $user, 'category' => $category, 'other' => $other];
    }

    /**
     * The three tab classes, for the provider-driven tests.
     *
     * @return array
     */
    public static function tab_classes(): array {
        return [
            'frameworks' => [frameworks::class],
            'structure' => [structure::class],
            'plans' => [plans::class],
        ];
    }

    /**
     * A category manager gets every tab in their category and none at the site.
     *
     * @dataProvider tab_classes
     * @param string $tabclass The tab class under test.
     * @return void
     */
    public function test_a_category_manager_gets_the_tab_in_their_category_only(string $tabclass): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $fixture = $this->create_category_manager();
        $this->setUser($fixture['user']);

        $incategory = new $tabclass(['contexttype' => 'coursecat', 'categoryid' => (int) $fixture['category']->id]);
        $this->assertTrue($incategory->is_available(), "$tabclass must be available in the manager's own category");

        $atsystem = new $tabclass(['contexttype' => 'system', 'categoryid' => 0]);
        $this->assertFalse($atsystem->is_available(), "$tabclass must be refused at the system context");

        // Nothing named: the pane defaults to the system context, and is refused the same way.
        $bare = new $tabclass([]);
        $this->assertFalse($bare->is_available(), "$tabclass with no pane data must fall back to the system context");
    }

    /**
     * A category the manager holds nothing in is refused, and so is a category that does not exist.
     *
     * The resolver downgrades both to the system context, which the fixture has made unreadable,
     * so the answer is a refusal rather than a silent system listing.
     *
     * @dataProvider tab_classes
     * @param string $tabclass The tab class under test.
     * @return void
     */
    public function test_an_unreadable_or_missing_category_is_refused(string $tabclass): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $fixture = $this->create_category_manager();
        $this->setUser($fixture['user']);

        $elsewhere = new $tabclass(['contexttype' => 'coursecat', 'categoryid' => (int) $fixture['other']->id]);
        $this->assertFalse($elsewhere->is_available(), "$tabclass must be refused in a category the manager cannot read");

        $missing = new $tabclass(['contexttype' => 'coursecat', 'categoryid' => 987654]);
        $this->assertFalse($missing->is_available(), "$tabclass must be refused for a category that does not exist");
    }

    /**
     * Control: the site administrator is admitted everywhere, including the guided empty state.
     *
     * @dataProvider tab_classes
     * @param string $tabclass The tab class under test.
     * @return void
     */
    public function test_an_administrator_is_admitted_in_every_context(string $tabclass): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $category = $this->getDataGenerator()->create_category();

        $this->assertTrue((new $tabclass(['contexttype' => 'system', 'categoryid' => 0]))->is_available());
        $this->assertTrue((new $tabclass(['contexttype' => 'coursecat', 'categoryid' => (int) $category->id]))->is_available());
        $this->assertTrue((new $tabclass(['contexttype' => 'coursecat', 'categoryid' => 0]))->is_available());
    }

    /**
     * The path the browser actually takes: core's dynamic-tabs web service serves the Learning plans
     * pane to a category manager in their category, and refuses it at the site.
     *
     * The Plans pane is the one measured failing before the change, because templateview carries
     * no authenticated-user default; the service returns the exception rather than throwing.
     *
     * @return void
     */
    public function test_the_plans_pane_is_served_over_the_web_service_in_the_category(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $fixture = $this->create_category_manager();
        $this->setUser($fixture['user']);
        $_POST['sesskey'] = sesskey();

        $incategory = external_api::call_external_function('core_dynamic_tabs_get_content', [
            'tab' => plans::class,
            'jsondata' => json_encode(['contexttype' => 'coursecat', 'categoryid' => (int) $fixture['category']->id]),
        ]);
        $this->assertFalse($incategory['error'], 'The Plans pane must be served in the manager\'s own category');
        $content = json_decode($incategory['data']['content'], true);
        $categorycontext = context_coursecat::instance((int) $fixture['category']->id);
        $this->assertSame($categorycontext->id, (int) $content['contextid']);
        $this->assertSame('coursecat', $content['contexttype']);

        $atsystem = external_api::call_external_function('core_dynamic_tabs_get_content', [
            'tab' => plans::class,
            'jsondata' => json_encode(['contexttype' => 'system', 'categoryid' => 0]),
        ]);
        $this->assertTrue($atsystem['error'], 'The Plans pane must be refused at the system context');
        $this->assertSame('nopermissiontoaccesspage', $atsystem['exception']->errorcode);
    }
}
