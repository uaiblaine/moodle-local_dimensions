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
 * Tests for the learner views' stored layout.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use local_dimensions\constants;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The stored layout reads Journey as a third value, keeps the two it had, and falls back to the list on anything else.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(learner_view::class)]
final class learner_view_test extends \advanced_testcase {
    /**
     * The layout resolved from a stored preference value.
     *
     * @param mixed $stored What the preference holds, JSON-encoded as the client writes it; null for none.
     * @return string
     */
    private function view_of($stored): string {
        if ($stored === null) {
            unset_user_preference(constants::PREF_LEARNER_VIEW);
        } else {
            set_user_preference(constants::PREF_LEARNER_VIEW, json_encode($stored));
        }
        return learner_view::resolve()['view'];
    }

    /**
     * Journey is a stored layout of its own, and the two older values still resolve as they did.
     *
     * @return void
     */
    public function test_the_three_layouts_resolve(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertSame('list', $this->view_of(null));
        $this->assertSame('journey', $this->view_of(['view' => 'journey']));
        $this->assertSame('grid', $this->view_of(['view' => 'grid', 'sort' => 'name']));
        $this->assertSame('list', $this->view_of(['view' => 'list']));
        $this->assertSame(learner_view::VIEWS, ['list', 'grid', 'journey']);
    }

    /**
     * Anything else falls back to the list: an unknown word, another case, a number, an array, no JSON at all.
     *
     * @return void
     */
    public function test_an_unknown_layout_falls_back_to_the_list(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        foreach (['carousel', 'Journey', ' journey', 3, ['journey'], true, ''] as $value) {
            $this->assertSame('list', $this->view_of(['view' => $value]), json_encode($value));
        }
        set_user_preference(constants::PREF_LEARNER_VIEW, 'journey');
        $this->assertSame('list', learner_view::resolve()['view']);
    }
}
