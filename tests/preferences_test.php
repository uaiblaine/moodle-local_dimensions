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
 * Tests for the Competency hub view-state user preferences.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use advanced_testcase;

/**
 * Unit tests for the preference callback, helper reader and uninstall purge.
 *
 * @covers \local_dimensions\helper::get_central_prefs
 * @covers \local_dimensions\helper::purge_user_preferences
 */
final class preferences_test extends advanced_testcase {
    /**
     * The lib callback declares every preference with the expected type and owner gate.
     *
     * @return void
     */
    public function test_user_preferences_callback_declares_every_preference(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/dimensions/lib.php');
        $prefs = local_dimensions_user_preferences();
        $expected = [
            constants::PREF_CENTRAL_NAV,
            constants::PREF_CENTRAL_DISPLAY,
            constants::PREF_LEARNER_VIEW,
            constants::PREF_LEARNER_FAV,
        ];
        foreach ($expected as $name) {
            $this->assertArrayHasKey($name, $prefs);
            $this->assertSame(PARAM_RAW, $prefs[$name]['type']);
            $this->assertSame(
                [\core_user::class, 'is_current_user'],
                $prefs[$name]['permissioncallback']
            );
        }
    }

    /**
     * With nothing stored, defaults are returned (frameworks / system, rule on).
     *
     * @return void
     */
    public function test_get_central_prefs_returns_defaults_when_unset(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $prefs = helper::get_central_prefs();
        $this->assertSame('frameworks', $prefs['nav']['tab']);
        $this->assertSame('system', $prefs['nav']['contexttype']);
        $this->assertSame(0, $prefs['nav']['categoryid']);
        $this->assertTrue($prefs['display']['structure']['rule']);
        $this->assertFalse($prefs['display']['structure']['tax']);
        $this->assertFalse($prefs['display']['frameworksshowhidden']);
        $this->assertFalse($prefs['display']['modalexpanded']);
        $this->assertTrue($prefs['display']['panels']['structure']);
        $this->assertTrue($prefs['display']['panels']['planslist']);
        $this->assertTrue($prefs['display']['panels']['plansdetail']);
    }

    /**
     * Stored values are read and coerced to the right types; unset keys keep defaults.
     *
     * @return void
     */
    public function test_get_central_prefs_reads_and_sanitises_stored_values(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_NAV, json_encode([
            'tab' => 'plans', 'contexttype' => 'coursecat', 'categoryid' => '7',
            'frameworkid' => '3', 'templateid' => '9',
        ]), $USER->id);
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode([
            'structure' => ['tax' => true, 'rule' => false],
            'panels' => ['structure' => false, 'plansdetail' => 0],
            'plansshowdisabled' => true,
            'modalexpanded' => true,
        ]), $USER->id);
        $prefs = helper::get_central_prefs();
        $this->assertSame('plans', $prefs['nav']['tab']);
        $this->assertSame('coursecat', $prefs['nav']['contexttype']);
        $this->assertSame(7, $prefs['nav']['categoryid']);
        $this->assertSame(3, $prefs['nav']['frameworkid']);
        $this->assertSame(9, $prefs['nav']['templateid']);
        $this->assertTrue($prefs['display']['structure']['tax']);
        $this->assertFalse($prefs['display']['structure']['rule']);
        $this->assertFalse($prefs['display']['structure']['id']);
        $this->assertFalse($prefs['display']['panels']['structure']);
        $this->assertFalse($prefs['display']['panels']['plansdetail']);
        $this->assertTrue($prefs['display']['panels']['planslist']);
        $this->assertTrue($prefs['display']['plansshowdisabled']);
        $this->assertTrue($prefs['display']['modalexpanded']);
    }

    /**
     * Corrupt JSON and wrong-shaped sections fall back to defaults without error.
     *
     * @return void
     */
    public function test_get_central_prefs_falls_back_on_corrupt_data(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_NAV, 'not-json', $USER->id);
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode([
            'structure' => 'wrong-shape',
        ]), $USER->id);
        $prefs = helper::get_central_prefs();
        $this->assertSame('frameworks', $prefs['nav']['tab']);
        $this->assertTrue($prefs['display']['structure']['rule']);
    }

    /**
     * An unknown tab value is rejected in favour of the default.
     *
     * @return void
     */
    public function test_get_central_prefs_rejects_unknown_tab(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_NAV, json_encode(['tab' => 'bogus']), $USER->id);
        $this->assertSame('frameworks', helper::get_central_prefs()['nav']['tab']);
    }

    /**
     * The uninstall purge removes only this plugin's preference rows.
     *
     * @return void
     */
    public function test_purge_user_preferences_removes_only_plugin_rows(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_NAV, json_encode(['tab' => 'plans']), $USER->id);
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode([]), $USER->id);
        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode(['sort' => 'name']), $USER->id);
        set_user_preference(constants::PREF_LEARNER_FAV, json_encode(['7' => [1, 2]]), $USER->id);
        set_user_preference('somethingelse_pref', 'keep', $USER->id);
        helper::purge_user_preferences();
        $remaining = $DB->count_records_select(
            'user_preferences',
            $DB->sql_like('name', ':p'),
            ['p' => $DB->sql_like_escape('local_dimensions_') . '%']
        );
        $this->assertEquals(0, $remaining);
        $this->assertEquals('keep', get_user_preferences('somethingelse_pref', null, $USER->id));
    }
}
