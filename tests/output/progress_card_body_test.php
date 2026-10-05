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

/**
 * The locked overlay of a tracker course card never draws an empty date chip or an empty link.
 *
 * The card body is rendered in the browser from the course progress service's row, and the row of a
 * course the viewer may not be told about carries no date and no course URL. The template has to
 * degrade into the plain locked message for it: "Opens" followed by nothing, or a Learn more button
 * whose href is empty and leads back to the same page, says something no row ever meant.
 *
 * The covers tag is not needed here: the test renders a template and executes no plugin class.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class progress_card_body_test extends \advanced_testcase {
    /**
     * Render the card body for a locked card, with the flags the card script derives from the row.
     *
     * @param array $overrides Context values replacing the defaults.
     * @return string The rendered HTML.
     */
    private function render_locked(array $overrides): string {
        global $OUTPUT;

        $this->resetAfterTest();

        return $OUTPUT->render_from_template('local_dimensions/progress_card_body', $overrides + [
            'courseid' => 42,
            'enabled' => false,
            'locked' => true,
            'formatted_start_date' => '15 Jan 2031',
            'is_enrolment_start' => false,
            'can_self_enrol' => false,
            'is_pending' => false,
            'is_future_date' => true,
            'islearnmore' => false,
            'showlockeddate' => true,
            'customicon' => '',
            'courseurl' => '/course/view.php?id=42',
            'learnmorebuttoncolor' => '#0f6cbf',
            'cardmode' => 'timeline',
            'istimeline' => true,
            'sections' => [],
        ]);
    }

    /**
     * A locked card in blocked mode shows the date chip only when the row names a date.
     *
     * @return void
     */
    public function test_blocked_mode_draws_no_chip_without_a_date(): void {
        // Control: a row with a date draws the chip, so the absence below is about the date.
        $withdate = $this->render_locked([]);
        $this->assertStringContainsString('local-dimensions-locked-date', $withdate);
        $this->assertStringContainsString('15 Jan 2031', $withdate);

        $withoutdate = $this->render_locked(['formatted_start_date' => '', 'courseurl' => '']);
        $this->assertStringNotContainsString('local-dimensions-locked-date', $withoutdate);
        $this->assertStringNotContainsString('fa-calendar', $withoutdate);
        $this->assertStringContainsString('local-dimensions-locked-message', $withoutdate);
    }

    /**
     * An enrolment start date takes the same guard.
     *
     * @return void
     */
    public function test_an_enrolment_start_chip_needs_a_date_too(): void {
        $withdate = $this->render_locked(['is_enrolment_start' => true]);
        $this->assertStringContainsString('local-dimensions-locked-date', $withdate);

        $withoutdate = $this->render_locked(['is_enrolment_start' => true, 'formatted_start_date' => '']);
        $this->assertStringNotContainsString('local-dimensions-locked-date', $withoutdate);
    }

    /**
     * In learn-more mode a row with no course URL gets the plain locked message, not an empty link.
     *
     * @return void
     */
    public function test_learn_more_needs_a_course_url(): void {
        // Control: with a URL the button is the message and carries the URL.
        $withurl = $this->render_locked(['islearnmore' => true]);
        $this->assertStringContainsString('local-dimensions-learnmore-btn', $withurl);
        $this->assertStringContainsString('href="/course/view.php?id=42"', $withurl);
        $this->assertStringNotContainsString('local-dimensions-locked-message', $withurl);

        $withouturl = $this->render_locked(['islearnmore' => true, 'courseurl' => '']);
        $this->assertStringNotContainsString('local-dimensions-learnmore-btn', $withouturl);
        $this->assertStringNotContainsString('href=""', $withouturl);
        $this->assertStringContainsString('local-dimensions-locked-message', $withouturl);
    }

    /**
     * The unavailable row of the web service, rendered as the card script would, draws neither.
     *
     * The row is what get_course_progress hands back for a course the viewer may not be told about;
     * both modes are checked, with the date shown as the script's setting would have it.
     *
     * @return void
     */
    public function test_the_unavailable_row_draws_neither_in_either_mode(): void {
        $row = [
            'courseid' => 42,
            'enabled' => false,
            'locked' => true,
            'formatted_start_date' => '',
            'is_enrolment_start' => false,
            'can_self_enrol' => false,
            'is_pending' => false,
            'is_future_date' => false,
            'course_url' => '',
            'sections' => [],
            'cardmode' => 'timeline',
        ];
        foreach ([false, true] as $learnmore) {
            $html = $this->render_locked([
                'islearnmore' => $learnmore,
                'showlockeddate' => !$learnmore,
                'formatted_start_date' => $row['formatted_start_date'],
                'courseurl' => $row['course_url'],
            ]);
            $this->assertStringContainsString('local-dimensions-locked-message', $html);
            $this->assertStringNotContainsString('local-dimensions-locked-date', $html);
            $this->assertStringNotContainsString('local-dimensions-learnmore-btn', $html);
        }
    }
}
