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

use advanced_testcase;
use context_coursecat;
use context_system;
use local_dimensions\external\search_categories;
use local_dimensions\helper;
use local_dimensions\output\central\contextbar;

/**
 * The Competency hub's category surfaces honour local_unlistedcourses' unlisted categories.
 *
 * Any logged-in user passes the hub's readability check at a category (core grants competencyview
 * to the authenticated user everywhere), so the picker, the selected option, the context the tabs
 * resolve and the category entry would otherwise name a category the site has unlisted. Template
 * counts go only to a viewer who may read templates there, since core shows a learner none.
 *
 * The real-plugin tests skip where local_unlistedcourses is not installed (4.5 and 5.1 CI).
 *
 * The class-level covers tags stay in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\unlisted_categories
 * @covers     \local_dimensions\helper
 * @covers     \local_dimensions\external\search_categories
 * @covers     \local_dimensions\output\central\contextbar
 */
final class hub_unlisted_categories_test extends advanced_testcase {
    /**
     * Forget local_unlistedcourses' per-request memos, which outlive a test's rollback.
     *
     * The rollback reuses category ids, so a memo of this test's unlisted category would withhold
     * the next test's fresh category of the same id.
     *
     * @return void
     */
    private function reset_unlisted_memos(): void {
        if (class_exists('\local_unlistedcourses\category_access')) {
            \local_unlistedcourses\category_discoverability::reset_caches();
            \local_unlistedcourses\category_access::reset_caches();
        }
    }

    /**
     * Start every test with local_unlistedcourses' memos empty.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->reset_unlisted_memos();
    }

    /**
     * Leave local_unlistedcourses' memos empty for the next test class.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->reset_unlisted_memos();
        parent::tearDown();
    }

    /**
     * An unlisted category "Secret ops" with a child, a listed category "Open day", a template in
     * each of the first two, and a plain user who is related to none of them.
     *
     * @return array Keys: secret, child, open (stdClass categories), user (stdClass).
     */
    private function fixture(): array {
        if (!class_exists('\local_unlistedcourses\category_discoverability')) {
            $this->markTestSkipped('local_unlistedcourses is not installed on this site.');
        }
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $secret = $generator->create_category(['name' => 'Secret ops']);
        $child = $generator->create_category(['name' => 'Recruits', 'parent' => $secret->id]);
        $open = $generator->create_category(['name' => 'Open day']);
        $ccg = $generator->get_plugin_generator('core_competency');
        $ccg->create_template(['contextid' => context_coursecat::instance((int) $secret->id)->id]);
        $ccg->create_template(['contextid' => context_coursecat::instance((int) $open->id)->id]);
        \local_unlistedcourses\category_discoverability::set_state(
            (int) $secret->id,
            \local_unlistedcourses\category_discoverability::STATE_UNLISTED
        );
        return ['secret' => $secret, 'child' => $child, 'open' => $open, 'user' => $generator->create_user()];
    }

    /**
     * Ids of a search_categories result.
     *
     * @param array $result The service result.
     * @return array Category ids.
     */
    private function ids(array $result): array {
        return array_map(static fn(array $item): int => (int) $item['id'], $result['items']);
    }

    /**
     * Export a context bar as the current user.
     *
     * @param contextbar $bar
     * @return array
     */
    private function export(contextbar $bar): array {
        global $PAGE;
        return $bar->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * The picker search leaves an unlisted category and its child out for an unrelated viewer.
     *
     * @return void
     */
    public function test_the_search_withholds_an_unlisted_category_and_its_child(): void {
        $this->resetAfterTest();
        $f = $this->fixture();

        // Control: the administrator gets all three, so the query reaches them.
        $all = $this->ids(search_categories::execute(''));
        $this->assertContains((int) $f['secret']->id, $all);
        $this->assertContains((int) $f['child']->id, $all);

        $this->setUser($f['user']);
        $this->assertTrue(helper::can_read_competency_context(context_coursecat::instance((int) $f['secret']->id)), 'Precondition');
        $ids = $this->ids(search_categories::execute(''));
        $this->assertContains((int) $f['open']->id, $ids);
        $this->assertNotContains((int) $f['secret']->id, $ids);
        $this->assertNotContains((int) $f['child']->id, $ids, 'The child would name its parent');
        $this->assertSame([], $this->ids(search_categories::execute('Recruits')));
    }

    /**
     * A role in the unlisted category is the plugin's relationship: its holder keeps the category.
     *
     * @return void
     */
    public function test_a_role_in_the_unlisted_category_keeps_it(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $roleid = $this->getDataGenerator()->create_role();
        role_assign($roleid, (int) $f['user']->id, context_coursecat::instance((int) $f['secret']->id)->id);

        $this->setUser($f['user']);
        $this->assertContains((int) $f['secret']->id, $this->ids(search_categories::execute('Secret')));
        $this->assertNotNull(helper::central_category_option((int) $f['secret']->id));
    }

    /**
     * The selected option and the context the tabs resolve fall back as for an unreadable category.
     *
     * @return void
     */
    public function test_the_option_and_the_resolved_context_withhold_an_unlisted_category(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->assertNotNull(helper::central_category_option((int) $f['secret']->id), 'Control: the administrator');
        $this->assertTrue(helper::resolve_central_context('coursecat', (int) $f['secret']->id)['iscoursecat']);

        $this->setUser($f['user']);
        $this->assertNull(helper::central_category_option((int) $f['secret']->id));
        $this->assertNull(helper::central_category_option((int) $f['child']->id));
        $this->assertNotNull(helper::central_category_option((int) $f['open']->id), 'Control: a listed category');

        $resolved = helper::resolve_central_context('coursecat', (int) $f['secret']->id);
        $this->assertFalse($resolved['iscoursecat']);
        $this->assertSame(0, $resolved['categoryid']);
        $this->assertTrue($resolved['needscategory']);
        $this->assertTrue(helper::resolve_central_context('coursecat', (int) $f['open']->id)['iscoursecat']);

        $bar = $this->export(new contextbar('coursecat', (int) $f['secret']->id, false, true));
        $this->assertFalse($bar['locked']);
        $this->assertSame([], $bar['categoryoptions']);
    }

    /**
     * The category entry refuses an unlisted category exactly as it refuses a missing one.
     *
     * @return void
     */
    public function test_the_entry_refuses_an_unlisted_category_like_a_missing_one(): void {
        global $DB;
        $this->resetAfterTest();
        $f = $this->fixture();
        $secretcontext = context_coursecat::instance((int) $f['secret']->id);
        $missingid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {context}') + 100;

        $missing = $this->refusal(static fn() => helper::central_entry_context($missingid));
        $this->assertSame('invalidcontext', $missing);
        $this->assertNull(helper::central_entry_context(0));

        helper::require_central_category($secretcontext);
        $this->setUser($f['user']);
        $this->assertSame($secretcontext->id, helper::central_entry_context($secretcontext->id)->id);
        $this->assertSame($missing, $this->refusal(static fn() => helper::require_central_category($secretcontext)));
        helper::require_central_category(context_coursecat::instance((int) $f['open']->id));
    }

    /**
     * An entry the viewer may discover but not read is refused for the capability.
     *
     * @return void
     */
    public function test_the_entry_still_asks_for_competency_readability(): void {
        global $CFG;
        $this->resetAfterTest();
        $f = $this->fixture();
        assign_capability(
            'moodle/competency:competencyview',
            CAP_PREVENT,
            (int) $CFG->defaultuserroleid,
            context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($f['user']);
        $this->expectException(\required_capability_exception::class);
        helper::require_central_category(context_coursecat::instance((int) $f['open']->id));
    }

    /**
     * Template counts go only to a viewer who may read templates in the category.
     *
     * @return void
     */
    public function test_template_counts_need_template_readability(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $openid = (int) $f['open']->id;
        $this->assertSame(1, helper::central_category_option($openid)['templatecount'], 'Control: the administrator');
        $adminbar = $this->export(new contextbar('coursecat', $openid, false, true));
        $this->assertSame(1, $adminbar['categoryoptions'][0]['templatecount']);

        $this->setUser($f['user']);
        $option = helper::central_category_option($openid);
        $this->assertSame(0, $option['templatecount']);
        $this->assertFalse($option['hastemplates']);
        foreach (search_categories::execute('Open')['items'] as $item) {
            $this->assertSame(0, $item['templatecount']);
        }
        $bar = $this->export(new contextbar('coursecat', $openid, false, true));
        $this->assertTrue($bar['locked'], 'Precondition: the learner reaches the locked bar');
        $this->assertSame(0, $bar['categoryoptions'][0]['templatecount']);
        $this->assertSame(0, $bar['selectedtemplatecount']);
    }

    /**
     * The site's template total goes only to a viewer who may read templates at the site.
     *
     * @return void
     */
    public function test_the_system_template_total_needs_template_readability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_template();
        $this->assertSame(1, $this->export(new contextbar('system', 0))['systemtemplatecount'], 'Control');

        $this->setUser($this->getDataGenerator()->create_user());
        $bar = $this->export(new contextbar('system', 0));
        $this->assertTrue($bar['cansystem'], 'Precondition: the learner reads frameworks at the site');
        $this->assertSame(0, $bar['systemtemplatecount']);
    }

    /**
     * Without local_unlistedcourses every category is kept, and nothing is asked.
     *
     * @return void
     */
    public function test_a_site_without_the_plugin_keeps_every_category(): void {
        $property = new \ReflectionProperty(unlisted_categories::class, 'categoryapi');
        try {
            $property->setValue(null, 'local_dimensions\\local\\no_such_category_api');
            $this->assertFalse(unlisted_categories::available());
            $this->assertSame([7, 3], unlisted_categories::filter_ids(['7', 3]));
            $this->assertTrue(unlisted_categories::is_discoverable(7));
        } finally {
            $property->setValue(null, unlisted_categories::CATEGORY_API);
        }
    }

    /**
     * The error code a call throws.
     *
     * @param callable $call
     * @return string
     */
    private function refusal(callable $call): string {
        try {
            $call();
        } catch (\moodle_exception $e) {
            return $e->errorcode;
        }
        $this->fail('Expected a refusal');
    }
}
