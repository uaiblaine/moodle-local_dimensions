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
 * Tests for the learner return-navigation helpers.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use advanced_testcase;
use moodle_url;

/**
 * Tests for the return-context classification and the tracker's return button.
 *
 * @covers \local_dimensions\helper::return_destination_kind
 * @covers \local_dimensions\helper::tracker_return_context
 */
final class helper_return_navigation_test extends advanced_testcase {
    /**
     * A stored plan URL classifies as the plan.
     *
     * @return void
     */
    public function test_return_destination_kind_classifies_plan_url(): void {
        $url = (new moodle_url('/local/dimensions/view-plan.php', ['id' => 7]))->out(false);

        $this->assertSame('plan', helper::return_destination_kind($url));
    }

    /**
     * A stored tracker URL classifies as the competency, with or without the anti-loop flag.
     *
     * @return void
     */
    public function test_return_destination_kind_classifies_tracker_url(): void {
        $plain = (new moodle_url('/local/dimensions/view-competency.php', [
            'id' => 7,
            'competencyid' => 3,
        ]))->out(false);
        $flagged = (new moodle_url('/local/dimensions/view-competency.php', [
            'id' => 7,
            'competencyid' => 3,
            'noredirect' => 1,
        ]))->out(false);

        $this->assertSame('competency', helper::return_destination_kind($plain));
        $this->assertSame('competency', helper::return_destination_kind($flagged));
    }

    /**
     * Anything unrecognised falls back to the plan, the root of the journey.
     *
     * @return void
     */
    public function test_return_destination_kind_defaults_to_plan(): void {
        $this->assertSame('plan', helper::return_destination_kind('https://example.invalid/course/view.php?id=2'));
        $this->assertSame('plan', helper::return_destination_kind(''));
    }
}
