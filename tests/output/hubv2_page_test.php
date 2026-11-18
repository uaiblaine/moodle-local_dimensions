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
 * Tests for the Competency hub v2 page: its framework choice, the canvas's props and its strings.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use advanced_testcase;
use context_coursecat;
use context_system;
use local_dimensions\external\browse_structure;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What hubv2.php renders around the canvas, and what the canvas receives.
 */
#[CoversClass(hubv2_page::class)]
final class hubv2_page_test extends advanced_testcase {
    /**
     * Export the page for a context and a framework.
     *
     * @param int $frameworkid The framework asked for.
     * @param string $contexttype 'system' or 'coursecat'.
     * @param int $categoryid The category, in course-category mode.
     * @return array The template context, with the props decoded under 'decoded'.
     */
    private function export(int $frameworkid, string $contexttype = 'system', int $categoryid = 0): array {
        global $PAGE;
        $page = new hubv2_page($contexttype, $categoryid, $frameworkid);
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $data['decoded'] = $data['props'] === '' ? null : json_decode($data['props'], true);
        return $data;
    }

    /**
     * The asked-for framework is shown with its first page of roots, which carries the web service's node shape.
     *
     * @return void
     */
    public function test_the_requested_framework_comes_with_its_first_page_of_roots(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ccg->create_framework(['shortname' => 'Alpha']);
        $beta = $ccg->create_framework(['shortname' => 'Beta', 'idnumber' => 'BETA']);
        $betaid = (int) $beta->get('id');
        $roots = [];
        for ($i = 0; $i < 27; $i++) {
            $roots[] = $ccg->create_competency(['competencyframeworkid' => $betaid, 'shortname' => sprintf('Root %02d', $i)]);
        }
        $ccg->create_competency(['competencyframeworkid' => $betaid, 'parentid' => $roots[0]->get('id')]);

        $data = $this->export($betaid);

        $this->assertTrue($data['hasframework']);
        $this->assertSame(['Alpha', 'Beta'], array_column($data['frameworks'], 'name'));
        $this->assertSame([false, true], array_column($data['frameworks'], 'selected'));
        $this->assertTrue($data['issystem']);

        $props = $data['decoded'];
        $this->assertSame(
            ['framework', 'roots', 'pagesize', 'rulesmodules', 'courseoutcomes', 'moduleoutcomes', 'listurl'],
            array_keys($props)
        );
        $this->assertSame([
            'id' => $betaid,
            'shortname' => 'Beta',
            'idnumber' => 'BETA',
            'competencycount' => 28,
            'canmanage' => true,
        ], $props['framework']);
        $this->assertSame(27, $props['roots']['total']);
        $this->assertCount(25, $props['roots']['items']);
        $this->assertSame(25, $props['pagesize']);
        $this->assertSame('Root 00', $props['roots']['items'][0]['shortname']);
        $this->assertTrue($props['roots']['items'][0]['haschildren']);
        $this->assertFalse($props['roots']['items'][1]['haschildren']);
        // A first-paint node and a lazily fetched one are the same shape.
        $this->assertSame(
            array_keys(browse_structure::node_structure()->keys),
            array_keys($props['roots']['items'][0])
        );
        $this->assertNotEmpty($props['rulesmodules']);
        $this->assertNotEmpty($props['courseoutcomes']);
        $this->assertNotEmpty($props['moduleoutcomes']);
        $this->assertStringContainsString('/local/dimensions/central.php', $props['listurl']);
        $this->assertStringContainsString('frameworkid=' . $betaid, $props['listurl']);
        $this->assertStringContainsString('tab=structure', $props['listurl']);
        $this->assertSame($props['listurl'], $data['listurl']);
    }

    /**
     * An unknown framework falls back to the first visible one, before a hidden one that sorts first.
     *
     * @return void
     */
    public function test_an_unknown_framework_falls_back_to_the_first_visible_one(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ccg->create_framework(['shortname' => 'Aaa hidden', 'visible' => 0]);
        $visible = $ccg->create_framework(['shortname' => 'Bbb visible']);

        $data = $this->export(999999);

        $this->assertSame((int) $visible->get('id'), $data['decoded']['framework']['id']);
        $this->assertSame([true, false], array_column($data['frameworks'], 'hidden'));
    }

    /**
     * A reader who may not manage gets the canvas without the rule modules, and canmanage false.
     *
     * @return void
     */
    public function test_a_reader_gets_no_management(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['shortname' => 'Read only']);
        $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        // The authenticated user's role holds competencyview at the site, and not competencymanage.
        $this->setUser($this->getDataGenerator()->create_user());

        $data = $this->export((int) $framework->get('id'));

        $this->assertFalse($data['decoded']['framework']['canmanage']);
        $this->assertSame([], $data['decoded']['rulesmodules']);
        $this->assertFalse($data['decoded']['roots']['items'][0]['canmanage']);
        // Control: the same framework, exported for the administrator, is manageable.
        $this->setAdminUser();
        $this->assertTrue($this->export((int) $framework->get('id'))['decoded']['framework']['canmanage']);
    }

    /**
     * A viewer who may not read competencies is refused with competencyview, as central.php refuses.
     *
     * @return void
     */
    public function test_a_viewer_without_competencyview_is_refused(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        // Control: the authenticated user reads competencies at the site.
        (new hubv2_page('system', 0, 0))->require_access();

        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:competencyview', CAP_PROHIBIT, $roleid, context_system::instance()->id);
        role_assign($roleid, (int) $user->id, context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        try {
            (new hubv2_page('system', 0, 0))->require_access();
            $this->fail('A viewer who may not read competencies was let in.');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
            $this->assertStringContainsString(get_capability_string('moodle/competency:competencyview'), $e->getMessage());
        }
    }

    /**
     * A framework in a course category is reached through contexttype and categoryid, and only there.
     *
     * @return void
     */
    public function test_a_category_framework_is_reached_in_its_category(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $category = $this->getDataGenerator()->create_category(['name' => 'Sciences']);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework([
            'shortname' => 'In the category',
            'contextid' => context_coursecat::instance((int) $category->id)->id,
        ]);

        $incategory = $this->export((int) $framework->get('id'), 'coursecat', (int) $category->id);
        $this->assertTrue($incategory['iscoursecat']);
        $this->assertSame((int) $framework->get('id'), $incategory['decoded']['framework']['id']);
        $this->assertSame(
            [['id' => (int) $category->id, 'name' => 'Sciences', 'selected' => true]],
            $incategory['categoryoptions']
        );
        $this->assertStringContainsString('contexttype=coursecat', $incategory['listurl']);
        $this->assertStringContainsString('categoryid=' . $category->id, $incategory['listurl']);

        $atsite = $this->export((int) $framework->get('id'));
        $this->assertFalse($atsite['hasframeworks']);
        $this->assertFalse($atsite['hasframework']);
        $this->assertSame('', $atsite['props']);
    }

    /**
     * Course-category mode without a category asks for one and lists nothing.
     *
     * @return void
     */
    public function test_category_mode_without_a_category_lists_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_framework();

        $data = $this->export(0, 'coursecat', 0);

        $this->assertTrue($data['needscategory']);
        $this->assertFalse($data['hasframeworks']);
        $this->assertSame('', $data['props']);
    }

    /**
     * A list of quoted strings in a TypeScript array literal.
     *
     * @param string $source The source.
     * @param string $name The constant's name.
     * @return string The array literal's body.
     */
    private function ts_array(string $source, string $name): string {
        $this->assertSame(
            1,
            preg_match('/export const ' . $name . '(?::[^=]+)? = \[(.*?)\n\];/s', $source, $m),
            "strings.ts declares no {$name} list"
        );
        return $m[1];
    }

    /**
     * The canvas reads exactly the strings the page hands over, and every one of them exists.
     *
     * The page passes hubv2_page::STRINGS and OTHER_STRINGS to strings_for_js(), and the canvas reads
     * KEYS and OTHER of js/esm/src/hubv2/strings.ts from that cache in one call; a key on one side
     * only is a string fetched by a request, or one handed over for nothing.
     *
     * @return void
     */
    public function test_the_canvas_reads_the_strings_the_page_hands_over(): void {
        $source = (string) file_get_contents(__DIR__ . '/../../js/esm/src/hubv2/strings.ts');

        preg_match_all("/'([a-z0-9_]+)'/", $this->ts_array($source, 'KEYS'), $keys);
        $this->assertSame(hubv2_page::STRINGS, $keys[1]);

        preg_match_all("/\['([a-z_]+)', '([a-z0-9_]+)'\]/", $this->ts_array($source, 'OTHER'), $pairs, PREG_SET_ORDER);
        $other = [];
        foreach ($pairs as [, $component, $key]) {
            $other[$component][] = $key;
        }
        $this->assertSame(hubv2_page::OTHER_STRINGS, $other);

        foreach (['en', 'pt_br'] as $lang) {
            $string = [];
            include(__DIR__ . "/../../lang/{$lang}/local_dimensions.php");
            $missing = array_values(array_diff(hubv2_page::STRINGS, array_keys($string)));
            $this->assertSame([], $missing, "lang/{$lang} lacks strings the canvas reads");
            // The page's own strings, read by hubv2.php and its template.
            foreach (['hubv2', 'hubv2_apply', 'hubv2_listview', 'hubv2_loading'] as $key) {
                $this->assertArrayHasKey($key, $string, "lang/{$lang} lacks {$key}");
            }
        }
        $manager = get_string_manager();
        foreach (hubv2_page::OTHER_STRINGS as $component => $componentkeys) {
            foreach ($componentkeys as $key) {
                $this->assertTrue($manager->string_exists($key, $component), "{$component}:{$key} does not exist");
            }
        }
    }
}
