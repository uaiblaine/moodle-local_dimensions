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

namespace local_dimensions;

use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests for helper::sync_enrollmentfilter_option.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversMethod(\local_dimensions\helper::class, 'sync_enrollmentfilter_option')]
final class sync_enrollmentfilter_option_test extends \advanced_testcase {
    /**
     * Sync appends the fifth option to a pre-upgrade (four-option) field, idempotently.
     *
     * @return void
     */
    public function test_sync_appends_missing_option_idempotently(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $field = helper::find_field_by_shortname(constants::CFIELD_ENROLLMENTFILTER, helper::AREA_LP);
        $this->assertNotNull($field);
        $fieldid = (int) $field->get('id');

        // The freshly provisioned field already carries all five options.
        $full = json_decode($DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $lines = explode("\n", $full['options']);
        $this->assertCount(5, $lines);
        $label = (string) new \lang_string('enrollmentfilter_enrolledorself', 'local_dimensions');
        $this->assertSame($label, end($lines));

        // Simulate a site provisioned before the option existed: strip the fifth line.
        $four = $full;
        array_pop($lines);
        $four['options'] = implode("\n", $lines);
        $DB->set_field('customfield_field', 'configdata', json_encode($four), ['id' => $fieldid]);

        // Sync appends the fifth option back.
        helper::sync_enrollmentfilter_option(helper::AREA_LP);
        $after = json_decode($DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $afterlines = explode("\n", $after['options']);
        $this->assertCount(5, $afterlines);
        $this->assertSame($label, end($afterlines));

        // Idempotent: a second run changes nothing.
        helper::sync_enrollmentfilter_option(helper::AREA_LP);
        $again = json_decode($DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $this->assertSame($after['options'], $again['options']);
    }

    /**
     * A fifth option spelled in another language counts as present.
     *
     * db/upgrade.php runs the sync on every upgrade, so a field provisioned in one language
     * meets upgrades run in another; the label alone would append the option a second time.
     *
     * @return void
     */
    public function test_sync_leaves_a_fifth_option_in_another_language(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $field = helper::find_field_by_shortname(constants::CFIELD_ENROLLMENTFILTER, helper::AREA_COMPETENCY);
        $fieldid = (int) $field->get('id');

        $config = json_decode($DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $lines = explode("\n", $config['options']);
        $lines[4] = 'Matriculado ou com autoinscrição';
        $config['options'] = implode("\n", $lines);
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $fieldid]);

        helper::sync_enrollmentfilter_option(helper::AREA_COMPETENCY);
        $after = json_decode($DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $this->assertSame($config['options'], $after['options']);
    }
}
