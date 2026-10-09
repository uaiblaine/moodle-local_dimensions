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

namespace local_dimensions\local;

use core_external\external_api;
use local_dimensions\local\enrolment_provider as provider;

/**
 * The payload of the shared state area, state by state.
 *
 * The class-level covers tag stays in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\enrolment_state
 */
final class enrolment_state_test extends \advanced_testcase {
    /** @var string A destination, for the states that lead somewhere. */
    private const URL = 'https://example.com/enrol/index.php?id=9';

    /**
     * Build state facts as a provider would.
     *
     * @param string $state The state.
     * @param array $overrides Facts replacing the defaults.
     * @return array The facts.
     */
    private function facts(string $state, array $overrides = []): array {
        return $overrides + [
            'state' => $state,
            'courseid' => 9,
            'date' => 0,
            'prerequisiteid' => 0,
            'actionurl' => null,
            'routeurl' => null,
            'opens' => 0,
        ];
    }

    /**
     * Each state's sentence, icon and colour family are the shared vocabulary's.
     *
     * @return void
     */
    public function test_each_state_reads_with_its_label_icon_and_family(): void {
        $this->resetAfterTest();
        $date = make_timestamp(2031, 1, 15, 12);
        $day = userdate($date, get_string('strftimedatefullshort', 'langconfig'));
        $expected = [
            provider::STATE_ENROLLED => ['Enrolled', 'fa-circle-check', 'local-dimensions-state-enrolled'],
            provider::STATE_SCHEDULED => ['Access from ' . $day, 'fa-calendar', 'local-dimensions-state-scheduled'],
            provider::STATE_PENDING => ['Application under review', 'fa-hourglass-half', 'local-dimensions-state-pending'],
            provider::STATE_WAITLISTED => ['On the waiting list', 'fa-list-ul', 'local-dimensions-state-pending'],
            provider::STATE_SUSPENDED => ['Enrolment suspended', 'fa-circle-pause', 'local-dimensions-state-neutral'],
            provider::STATE_EXPIRED => ['Enrolment ended ' . $day, 'fa-clock', 'local-dimensions-state-neutral'],
            provider::STATE_OPEN => ['Open for enrolment', 'fa-right-to-bracket', 'local-dimensions-state-open'],
            provider::STATE_FREE => ['Free access', 'fa-globe', 'local-dimensions-state-guest'],
            provider::STATE_KEY => ['Access with a key', 'fa-key', 'local-dimensions-state-guest'],
            provider::STATE_NONE => ['No enrolment available', 'fa-circle-minus', 'local-dimensions-state-neutral'],
        ];
        foreach ($expected as $state => [$label, $icon, $family]) {
            $payload = enrolment_state::export($this->facts($state, ['date' => $date]));
            $this->assertSame([$state, $label, $icon, $family], [
                $payload['key'],
                $payload['label'],
                $payload['icon'],
                $payload['family'],
            ], $state);
        }
    }

    /**
     * A state's own action is labelled by the state, and only a state that has one draws it.
     *
     * @return void
     */
    public function test_an_action_is_drawn_only_for_a_state_that_has_one(): void {
        $this->resetAfterTest();
        $labelled = [
            provider::STATE_OPEN => 'Enrol',
            provider::STATE_PENDING => 'View status',
            provider::STATE_WAITLISTED => 'View status',
            provider::STATE_FREE => 'Access',
            provider::STATE_KEY => 'Enter with key',
        ];
        foreach ($labelled as $state => $label) {
            $payload = enrolment_state::export($this->facts($state, ['actionurl' => self::URL]));
            $this->assertSame([$label, self::URL], [$payload['actionlabel'], $payload['actionurl']], $state);
            // Control: with no destination the same state draws no action.
            $payload = enrolment_state::export($this->facts($state));
            $this->assertSame(['', ''], [$payload['actionlabel'], $payload['actionurl']], $state);
        }
        // A state with no action of its own ignores a destination handed to it.
        foreach ([provider::STATE_ENROLLED, provider::STATE_SCHEDULED, provider::STATE_SUSPENDED, provider::STATE_NONE] as $state) {
            $payload = enrolment_state::export($this->facts($state, ['actionurl' => self::URL]));
            $this->assertSame(['', ''], [$payload['actionlabel'], $payload['actionurl']], $state);
        }
    }

    /**
     * A route beside a relationship becomes the route line, with its link.
     *
     * @return void
     */
    public function test_a_route_becomes_the_route_line(): void {
        $this->resetAfterTest();
        $payload = enrolment_state::export($this->facts(provider::STATE_SUSPENDED, ['routeurl' => self::URL]));
        $this->assertSame('You can also enrol now', $payload['routelabel']);
        $this->assertSame('Enrol now', $payload['routelinklabel']);
        $this->assertSame(self::URL, $payload['routeurl']);

        // Control: without a route there is no line.
        $payload = enrolment_state::export($this->facts(provider::STATE_SUSPENDED));
        $this->assertSame(['', '', ''], [$payload['routelabel'], $payload['routelinklabel'], $payload['routeurl']]);
    }

    /**
     * A conditional state names its prerequisite in the plain spelling, which the pill escapes once.
     *
     * A bare ampersand is the fixture: format_string() rewrites it only when asked to escape, so the
     * label tells the two spellings apart where a tag would be stripped by both.
     *
     * @return void
     */
    public function test_a_conditional_state_names_its_prerequisite_plainly(): void {
        $this->resetAfterTest();
        $prerequisite = $this->getDataGenerator()->create_course(['fullname' => 'Safety & Health']);
        $url = (new \moodle_url('/course/view.php', ['id' => $prerequisite->id]))->out(false);

        $payload = enrolment_state::export($this->facts(provider::STATE_CONDITIONAL, [
            'prerequisiteid' => (int) $prerequisite->id,
            'actionurl' => $url,
        ]));

        $this->assertSame('conditional', $payload['key']);
        $this->assertSame('Unlocks when you complete Safety & Health', $payload['label']);
        $this->assertSame(['View prerequisite', $url], [$payload['actionlabel'], $payload['actionurl']]);
        $this->assertSame(['fa-lock', 'local-dimensions-state-scheduled'], [$payload['icon'], $payload['family']]);
    }

    /**
     * A conditional state whose prerequisite is gone offers nothing, and an unknown state is none.
     *
     * @return void
     */
    public function test_a_state_that_names_nothing_is_none(): void {
        global $DB;

        $this->resetAfterTest();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course}') + 100;
        $payload = enrolment_state::export($this->facts(provider::STATE_CONDITIONAL, [
            'prerequisiteid' => $missing,
            'actionurl' => self::URL,
        ]));
        $this->assertSame(['none', 'No enrolment available', '', ''], [
            $payload['key'],
            $payload['label'],
            $payload['actionlabel'],
            $payload['actionurl'],
        ]);

        $payload = enrolment_state::export($this->facts('graduated', ['actionurl' => self::URL]));
        $this->assertSame(['none', ''], [$payload['key'], $payload['actionurl']]);
    }

    /**
     * A none state carrying the day its enrolment window opens exports that date and its chip.
     *
     * @return void
     */
    public function test_a_none_state_names_the_day_its_enrolment_opens(): void {
        $this->resetAfterTest();
        $opens = 1893456000;
        $payload = enrolment_state::export($this->facts(provider::STATE_NONE, ['opens' => $opens]));

        $date = userdate($opens, get_string('strftimedatefullshort', 'langconfig'));
        $this->assertSame($opens, $payload['opens']);
        $this->assertSame('Enrolment opens on ' . $date, $payload['openslabel']);
        // The pill keeps the none state's own sentence: the card is still one that offers nothing now.
        $this->assertSame(['none', 'No enrolment available'], [$payload['key'], $payload['label']]);
        $this->assertTrue(enrolment_state::course_start_yields($this->facts(provider::STATE_NONE, ['opens' => $opens])));

        // Control: the same none state without the date has no chip and keeps the course start.
        $payload = enrolment_state::export($this->facts(provider::STATE_NONE));
        $this->assertSame([0, ''], [$payload['opens'], $payload['openslabel']]);
        $this->assertFalse(enrolment_state::course_start_yields($this->facts(provider::STATE_NONE)));
        // Facts from before the key existed read as no date.
        $old = $this->facts(provider::STATE_NONE);
        unset($old['opens']);
        $this->assertSame([0, ''], [enrolment_state::export($old)['opens'], enrolment_state::export($old)['openslabel']]);
    }

    /**
     * Only a none state carries an opening date; any other state, or one demoted to none, does not.
     *
     * @return void
     */
    public function test_only_a_none_state_carries_an_opening_date(): void {
        $this->resetAfterTest();
        foreach (array_keys(enrolment_state::LOOK) as $state) {
            if ($state === provider::STATE_NONE) {
                continue;
            }
            $facts = $this->facts($state, ['opens' => 1893456000, 'date' => 1893456000]);
            $payload = enrolment_state::export($facts);
            $this->assertSame([0, ''], [$payload['opens'], $payload['openslabel']], $state);
            $this->assertFalse(enrolment_state::course_start_yields($facts), $state);
        }
    }

    /**
     * Every payload survives the returns structure both card services declare, a bare ampersand included.
     *
     * @return void
     */
    public function test_every_payload_passes_the_returns_structure(): void {
        $this->resetAfterTest();
        $prerequisite = $this->getDataGenerator()->create_course(['fullname' => 'R&D <3 Ops']);
        foreach (array_keys(enrolment_state::LOOK) as $state) {
            $payload = enrolment_state::export($this->facts($state, [
                'date' => time(),
                'prerequisiteid' => (int) $prerequisite->id,
                'actionurl' => self::URL,
                'routeurl' => self::URL,
                'opens' => time(),
            ]));
            $this->assertSame($payload, external_api::clean_returnvalue(enrolment_state::returns(), $payload), $state);
        }
    }
}
