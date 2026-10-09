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
 * The tracker course card's state area: the locked overlay's contents and the open card's pill.
 *
 * The card body is rendered in the browser from the course progress service's row, which carries
 * the state area ready to print (local_dimensions\local\enrolment_state). The overlay keeps its
 * frame and its guards: no empty date chip and no empty link, which a row for a course the viewer
 * may not be told about would otherwise produce. Every state is rendered through the real template,
 * a bare ampersand in a label included, which the template must escape exactly once.
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
     * A state payload as the service sends it.
     *
     * @param string $key The state.
     * @param array $overrides Fields replacing the defaults.
     * @return array The payload.
     */
    private function state(string $key, array $overrides = []): array {
        return $overrides + [
            'key' => $key,
            'label' => 'Label of ' . $key,
            'icon' => 'fa-circle-minus',
            'family' => 'local-dimensions-state-neutral',
            'actionlabel' => '',
            'actionurl' => '',
            'routelabel' => '',
            'routelinklabel' => '',
            'routeurl' => '',
        ];
    }

    /**
     * Render the card body for a locked card, with the flags the card script derives from the row.
     *
     * The defaults are a card in the none state in blocked mode with a date to show, which is what
     * the script derives for such a row.
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
            'state' => $this->state('none', ['label' => 'No enrolment available']),
            'formatted_start_date' => '15 Jan 2031',
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
     * The overlay of the given HTML, from its opening tag to the end of the body.
     *
     * @param string $html The rendered card body.
     * @return string The overlay's markup and what follows it.
     */
    private function overlay(string $html): string {
        $start = strpos($html, 'local-dimensions-locked-overlay');
        $this->assertNotFalse($start, 'The card has no overlay.');
        return substr($html, $start);
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
     * In learn-more mode a row with no course URL gets the message box, not an empty link.
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
     * The row is what get_course_progress hands back for a course the viewer may not be told about:
     * the none state with no action and no route, no date and no course URL. Both modes are checked,
     * with the date shown as the script's setting would have it.
     *
     * @return void
     */
    public function test_the_unavailable_row_draws_neither_in_either_mode(): void {
        foreach ([false, true] as $learnmore) {
            $html = $this->render_locked([
                'islearnmore' => $learnmore,
                'showlockeddate' => !$learnmore,
                'formatted_start_date' => '',
                'courseurl' => '',
            ]);
            $this->assertStringContainsString('local-dimensions-locked-message', $html);
            $this->assertStringNotContainsString('local-dimensions-locked-date', $html);
            $this->assertStringNotContainsString('local-dimensions-learnmore-btn', $html);
            $this->assertStringNotContainsString('local-dimensions-enrol-btn', $html);
            $this->assertStringNotContainsString('href=""', $html);
        }
    }

    /**
     * The overlay holds the shared state area: the disc in the family and icon, the pill, the action, the route line.
     *
     * @return void
     */
    public function test_the_overlay_holds_the_state_area(): void {
        $html = $this->overlay($this->render_locked([
            'state' => $this->state('pending', [
                'label' => 'Application under review',
                'icon' => 'fa-hourglass-half',
                'family' => 'local-dimensions-state-pending',
                'actionlabel' => 'View status',
                'actionurl' => '/enrol/index.php?id=42',
                'routelabel' => 'You can also enrol now',
                'routelinklabel' => 'Enrol now',
                'routeurl' => '/enrol/index.php?id=42&amp;x=1',
            ]),
            'showlockeddate' => false,
        ]));

        $this->assertMatchesRegularExpression(
            '~class="local-dimensions-locked-icon local-dimensions-state-pending">\s*'
                . '<i class="fa fa-hourglass-half" aria-hidden="true">~',
            $html
        );
        $this->assertStringContainsString(
            '<div class="local-dimensions-locked-message">'
                . '<span class="local-dimensions-state-pill local-dimensions-state-pending">'
                . '<span>Application under review</span>',
            $html
        );
        // The tracker's pill carries no icon of its own; the disc above keeps the state's.
        $this->assertSame(1, preg_match('~<div class="local-dimensions-locked-message">(.*?)</div>~s', $html, $message));
        $this->assertStringNotContainsString('<i ', $message[1]);
        $this->assertStringContainsString(
            '<a href="/enrol/index.php?id=42" class="local-dimensions-enrol-btn">View status</a>',
            $html
        );
        $this->assertStringContainsString('<div class="local-dimensions-state-route">', $html);
        $this->assertStringContainsString('You can also enrol now', $html);
        $this->assertStringContainsString('>Enrol now</a>', $html);
        // The action comes before the route line: the line is the note under the action.
        $this->assertLessThan(strpos($html, 'local-dimensions-state-route'), strpos($html, 'local-dimensions-enrol-btn'));
    }

    /**
     * The pill of a learn-more card with no course URL holds the label and no icon, as the default pill does.
     *
     * @return void
     */
    public function test_the_learn_more_pill_without_a_course_url_carries_no_icon(): void {
        $html = $this->overlay($this->render_locked([
            'state' => $this->state('pending', [
                'label' => 'Application under review',
                'icon' => 'fa-hourglass-half',
                'family' => 'local-dimensions-state-pending',
            ]),
            'islearnmore' => true,
            'courseurl' => '',
            'showlockeddate' => false,
        ]));

        // Control: the disc keeps the state's icon, so this is a locked card in the learn-more branch.
        $this->assertStringContainsString('<i class="fa fa-hourglass-half" aria-hidden="true"></i>', $html);
        $this->assertStringNotContainsString('local-dimensions-learnmore-btn', $html);

        $this->assertSame(1, preg_match('~<div class="local-dimensions-locked-message">(.*?)</div>~s', $html, $message));
        $this->assertStringContainsString(
            '<span class="local-dimensions-state-pill local-dimensions-state-pending">'
                . '<span>Application under review</span>',
            $message[1]
        );
        $this->assertStringNotContainsString('<i ', $message[1]);
    }

    /**
     * A state with neither action nor route draws neither, and no learn-more mode applies to it.
     *
     * @return void
     */
    public function test_a_state_without_an_action_or_route_draws_neither(): void {
        $html = $this->overlay($this->render_locked([
            'state' => $this->state('scheduled', ['label' => 'Access from 15 Jan 2031']),
            'showlockeddate' => false,
        ]));

        $this->assertStringContainsString('Access from 15 Jan 2031', $html);
        $this->assertStringNotContainsString('local-dimensions-enrol-btn', $html);
        $this->assertStringNotContainsString('local-dimensions-state-route', $html);
        $this->assertStringNotContainsString('local-dimensions-locked-date', $html);
    }

    /**
     * The admin's icon replaces the state's icon in the disc.
     *
     * @return void
     */
    public function test_the_admins_icon_replaces_the_states_in_the_disc(): void {
        $html = $this->overlay($this->render_locked(['customicon' => 'fa fa-fw fa-star']));
        $disc = substr($html, 0, (int) strpos($html, 'local-dimensions-locked-message'));

        $this->assertStringContainsString('<i class="fa fa-fw fa-star" aria-hidden="true"></i>', $disc);
        $this->assertStringNotContainsString('fa-circle-minus', $disc);

        // Control: without it, the state's own icon.
        $html = $this->overlay($this->render_locked([]));
        $disc = substr($html, 0, (int) strpos($html, 'local-dimensions-locked-message'));
        $this->assertStringContainsString('<i class="fa fa-circle-minus" aria-hidden="true"></i>', $disc);
    }

    /**
     * A label is escaped exactly once: a bare ampersand prints as one entity, never two.
     *
     * @return void
     */
    public function test_a_label_is_escaped_once(): void {
        $html = $this->render_locked([
            'state' => $this->state('conditional', ['label' => 'Unlocks when you complete Safety & Health']),
        ]);

        $this->assertStringContainsString('Unlocks when you complete Safety &amp; Health', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('Safety & Health', $html);
    }

    /**
     * An open (enrolled) card shows no state pill and no overlay; a locked card keeps its pill.
     *
     * @return void
     */
    public function test_an_open_card_shows_no_pill_and_no_overlay(): void {
        $html = $this->render_locked([
            'locked' => false,
            'state' => $this->state('enrolled', [
                'label' => 'Enrolled',
                'icon' => 'fa-circle-check',
                'family' => 'local-dimensions-state-enrolled',
            ]),
        ]);

        $this->assertStringNotContainsString('local-dimensions-locked-overlay', $html);
        $this->assertStringNotContainsString('local-dimensions-state-area', $html);
        $this->assertStringNotContainsString('local-dimensions-state-pill', $html);
        $this->assertStringNotContainsString('Enrolled', $html);

        // Control: a locked card has its pill inside the overlay, and the disc keeps the icon.
        $locked = $this->render_locked([]);
        $this->assertStringContainsString('local-dimensions-state-pill', $locked);
    }
}
