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

/**
 * Tests for the enrollmentfilter option list ordering.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\constants::enrollmentfilter_options
 */
final class enrollmentfilter_options_test extends \advanced_testcase {
    /**
     * The option keys are append-only: the first four indices must never move,
     * because the per-plan/per-competency select stores a 1-based index into this list.
     *
     * @return void
     */
    public function test_option_order_is_append_only(): void {
        $this->assertSame(
            [
                constants::ENROLLMENTFILTER_INHERIT,
                constants::ENROLLMENTFILTER_ALL,
                constants::ENROLLMENTFILTER_ENROLLED,
                constants::ENROLLMENTFILTER_ACTIVE,
                constants::ENROLLMENTFILTER_ENROLLEDORSELF,
            ],
            array_keys(constants::enrollmentfilter_options())
        );
    }
}
