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

namespace local_dimensions\hook;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the competency_status_badges hook object.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_dimensions\hook\competency_status_badges::class)]
final class competency_status_badges_test extends \advanced_testcase {
    /**
     * The accessors return the owner and the ids as ints.
     *
     * @return void
     */
    public function test_the_accessors_return_the_owner_and_the_ids(): void {
        $hook = new competency_status_badges(7, [3, '4']);
        $this->assertSame(7, $hook->get_userid());
        $this->assertSame([3, 4], $hook->get_competencyids());
        $this->assertSame([], $hook->get_badges(3));
    }

    /**
     * Badges come back per competency in the order they were added.
     *
     * @return void
     */
    public function test_badges_are_kept_per_competency_in_order(): void {
        $hook = new competency_status_badges(7, [3, 4]);
        $hook->add_badge(3, 'First', competency_status_badges::TONE_SUCCESS);
        $hook->add_badge(4, 'Other', competency_status_badges::TONE_INFO);
        $hook->add_badge(3, 'Second', competency_status_badges::TONE_NEUTRAL);
        $this->assertSame([
            ['label' => 'First', 'tone' => 'success'],
            ['label' => 'Second', 'tone' => 'neutral'],
        ], $hook->get_badges(3));
        $this->assertSame([['label' => 'Other', 'tone' => 'info']], $hook->get_badges(4));
    }

    /**
     * Every documented tone is accepted (control for the refusal below).
     *
     * @return void
     */
    public function test_the_five_tones_are_accepted(): void {
        $hook = new competency_status_badges(7, [3]);
        foreach (['success', 'warning', 'danger', 'info', 'neutral'] as $tone) {
            $hook->add_badge(3, 'Label ' . $tone, $tone);
        }
        $this->assertSame(
            ['success', 'warning', 'danger', 'info', 'neutral'],
            array_column($hook->get_badges(3), 'tone')
        );
    }

    /**
     * A tone that is not one of the five is refused, because it becomes a CSS class.
     *
     * @return void
     */
    public function test_an_unknown_tone_is_refused(): void {
        $hook = new competency_status_badges(7, [3]);
        $this->expectException(\coding_exception::class);
        $hook->add_badge(3, 'Label', 'success local-evil');
    }

    /**
     * An unknown tone is refused even for an id that is not on the page.
     *
     * @return void
     */
    public function test_an_unknown_tone_is_refused_for_an_id_off_the_page_too(): void {
        $hook = new competency_status_badges(7, [3]);
        $this->expectException(\coding_exception::class);
        $hook->add_badge(99, 'Label', 'purple');
    }

    /**
     * An id that is not on the page is ignored without an exception.
     *
     * @return void
     */
    public function test_an_id_not_on_the_page_is_ignored(): void {
        $hook = new competency_status_badges(7, [3]);
        $hook->add_badge(99, 'Label', competency_status_badges::TONE_SUCCESS);
        $this->assertSame([], $hook->get_badges(99));
        $this->assertSame([], $hook->get_badges(3));
    }

    /**
     * An empty label is ignored; a non-empty one on the same id is kept (control).
     *
     * @return void
     */
    public function test_an_empty_label_is_ignored(): void {
        $hook = new competency_status_badges(7, [3]);
        $hook->add_badge(3, '', competency_status_badges::TONE_SUCCESS);
        $hook->add_badge(3, '   ', competency_status_badges::TONE_SUCCESS);
        $this->assertSame([], $hook->get_badges(3));
        $hook->add_badge(3, 'Kept', competency_status_badges::TONE_SUCCESS);
        $this->assertCount(1, $hook->get_badges(3));
    }
}
