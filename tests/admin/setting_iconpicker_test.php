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
 * Tests for the icon picker admin setting.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\admin;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The stored value reaches the widget's inputs escaped exactly once.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_dimensions\admin\setting_iconpicker::class)]
final class setting_iconpicker_test extends \advanced_testcase {
    /**
     * Both inputs carry the value escaped once, never twice.
     *
     * Changes that must make it fail: escape the value in output_html() again (s() or
     * format_string()) before the template's double stashes.
     *
     * @return void
     */
    public function test_value_is_escaped_once(): void {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/settings.php');
        $PAGE->set_context(\context_system::instance());

        $setting = new setting_iconpicker('local_dimensions/testicon', 'Test icon', '', '');
        $html = $setting->output_html('R&D < Ops');

        $this->assertSame(2, substr_count($html, 'value="R&amp;D &lt; Ops"'), 'Both inputs carry the value escaped once.');
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
    }
}
