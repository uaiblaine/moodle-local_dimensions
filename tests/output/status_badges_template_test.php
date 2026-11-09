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

namespace local_dimensions\output;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests for the status badges partial.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class status_badges_template_test extends \advanced_testcase {
    /**
     * Render the partial.
     *
     * @param array $badges The pills.
     * @return string The markup.
     */
    private function render(array $badges): string {
        global $PAGE;
        return $PAGE->get_renderer('core')->render_from_template(
            'local_dimensions/status_badges',
            ['statusbadges' => $badges]
        );
    }

    /**
     * Each pill carries its tone class, and a label is escaped, never printed as markup.
     *
     * @return void
     */
    public function test_pills_carry_their_tone_and_escape_the_label(): void {
        $html = $this->render([
            ['label' => 'Validity: Compliant', 'tone' => 'success'],
            ['label' => '<b>x</b> & y', 'tone' => 'danger'],
        ]);
        $this->assertStringContainsString('local-dimensions-status-badge-success', $html);
        $this->assertStringContainsString('local-dimensions-status-badge-danger', $html);
        $this->assertStringContainsString('Validity: Compliant', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt; &amp; y', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    /**
     * No pills, no markup (control).
     *
     * @return void
     */
    public function test_no_pills_render_nothing(): void {
        $this->assertSame('', trim($this->render([])));
    }
}
