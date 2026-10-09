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
 * The shared state area of the course cards.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\local\enrolment_provider as provider;

/**
 * Turns an enrolment state into what both course cards print: one pill, an action, a route line.
 *
 * The tracker's card renders it through progress_card_body.mustache and the plan accordion's card
 * through accordion.js; both receive the same payload from their web service, so a state reads the
 * same in both and whichever provider answered ({@see enrolment_provider}). The labels are the
 * theme's (theme_boost_union_fundaseg category_state_*), as the enrolment matrix's shared state area
 * asks. Every text travels in the plain spelling: the template's double stash and the script's
 * escapeHtml() each escape it once.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_state {
    /**
     * @var array State => [the colour family class, the Font Awesome icon]. The family classes
     *            read the --local-dimensions-state-* tokens in styles.css; the icons are the theme
     *            card's, in names Moodle 4.5, 5.1 and 5.2 all ship.
     */
    public const LOOK = [
        provider::STATE_ENROLLED => ['local-dimensions-state-enrolled', 'fa-circle-check'],
        provider::STATE_SCHEDULED => ['local-dimensions-state-scheduled', 'fa-calendar'],
        provider::STATE_PENDING => ['local-dimensions-state-pending', 'fa-hourglass-half'],
        provider::STATE_WAITLISTED => ['local-dimensions-state-pending', 'fa-list-ul'],
        provider::STATE_SUSPENDED => ['local-dimensions-state-neutral', 'fa-circle-pause'],
        provider::STATE_EXPIRED => ['local-dimensions-state-neutral', 'fa-clock'],
        provider::STATE_OPEN => ['local-dimensions-state-open', 'fa-right-to-bracket'],
        provider::STATE_FREE => ['local-dimensions-state-guest', 'fa-globe'],
        provider::STATE_KEY => ['local-dimensions-state-guest', 'fa-key'],
        provider::STATE_CONDITIONAL => ['local-dimensions-state-scheduled', 'fa-lock'],
        provider::STATE_NONE => ['local-dimensions-state-neutral', 'fa-circle-minus'],
    ];

    /** @var array The states that may name the day an enrolment window opens ({@see opening_date()}). */
    public const OPENING_STATES = [provider::STATE_NONE, provider::STATE_SUSPENDED, provider::STATE_EXPIRED];

    /**
     * The payload a card renders for one state.
     *
     * A conditional state whose prerequisite no longer exists is printed as none: it names its
     * prerequisite or offers nothing.
     *
     * @param array $facts A state from {@see enrolment_provider}.
     * @return array key, label, icon, family, actionlabel, actionurl, routelabel, routelinklabel,
     *               routeurl, opens and openslabel; a text or URL the state does not carry is the
     *               empty string, a date it does not carry 0.
     */
    public static function export(array $facts): array {
        $state = isset(self::LOOK[$facts['state'] ?? '']) ? $facts['state'] : provider::STATE_NONE;
        $prerequisite = null;
        if ($state === provider::STATE_CONDITIONAL) {
            $prerequisite = self::prerequisite_name((int) ($facts['prerequisiteid'] ?? 0));
            if ($prerequisite === null) {
                $state = provider::STATE_NONE;
            }
        }
        $islive = $state === ($facts['state'] ?? '');
        $actionurl = $islive ? (string) ($facts['actionurl'] ?? '') : '';
        $actionlabel = $actionurl === '' ? '' : self::action_label($state);
        $routeurl = $islive ? (string) ($facts['routeurl'] ?? '') : '';
        [$family, $icon] = self::LOOK[$state];
        $opens = self::opening_date($facts);

        return [
            'key' => $state,
            'label' => self::label($state, (int) ($facts['date'] ?? 0), (string) $prerequisite),
            'icon' => $icon,
            'family' => $family,
            'actionlabel' => $actionlabel,
            'actionurl' => $actionlabel === '' ? '' : $actionurl,
            'routelabel' => $routeurl === '' ? '' : get_string('state_route_enrol', 'local_dimensions'),
            'routelinklabel' => $routeurl === '' ? '' : get_string('state_cta_enrolnow', 'local_dimensions'),
            'routeurl' => $routeurl,
            'opens' => $opens,
            // The plugin's own string: the theme has no sentence for this chip yet.
            'openslabel' => $opens === 0 ? '' : get_string('enrolmentopens', 'local_dimensions', self::date($opens)),
        ];
    }

    /**
     * When the enrolment window refusing a card opens, the date its chip names, or 0.
     *
     * Only a card with no way in now carries one: none, or a suspended or ended enrolment. A
     * scheduled, pending or waitlisted card names its own date or way in, an offer is itself the way
     * in, and a state export() demotes to none (a conditional state without its prerequisite) never
     * had the date.
     *
     * @param array $facts A state from {@see enrolment_provider}.
     * @return int The timestamp, or 0.
     */
    public static function opening_date(array $facts): int {
        if (!in_array($facts['state'] ?? '', self::OPENING_STATES, true)) {
            return 0;
        }
        return max(0, (int) ($facts['opens'] ?? 0));
    }

    /**
     * Whether a card's course start date chip stands down for the enrolment window's own date.
     *
     * The one place the precedence is decided: a none card that names the day its enrolment opens
     * shows that chip only, whatever the showlockeddate setting says, so both card services withdraw
     * the course start date they would otherwise send (the tracker's formatted_start_date and
     * is_future_date, the accordion's lockdate) when this answers true. Only a none card ever shows
     * the course start, so only a none card is asked about.
     *
     * @param array $facts A state from {@see enrolment_provider}.
     * @return bool True when the course start date must not be shown.
     */
    public static function course_start_yields(array $facts): bool {
        return ($facts['state'] ?? '') === provider::STATE_NONE && self::opening_date($facts) > 0;
    }

    /**
     * The structure both card services declare for the payload of {@see export()}.
     *
     * @return external_single_structure
     */
    public static function returns(): external_single_structure {
        return new external_single_structure([
            'key' => new external_value(
                PARAM_ALPHA,
                'The state: enrolled, scheduled, pending, waitlisted, suspended, expired, open, free, key, conditional or none'
            ),
            'label' => new external_value(PARAM_RAW, 'The pill\'s sentence, plain text'),
            'icon' => new external_value(PARAM_ALPHANUMEXT, 'The Font Awesome icon class of the state'),
            'family' => new external_value(PARAM_ALPHANUMEXT, 'The class of the state\'s colour family'),
            'actionlabel' => new external_value(PARAM_RAW, 'The label of the state\'s own action, plain text, empty for none'),
            'actionurl' => new external_value(PARAM_URL, 'Where the state\'s own action leads, empty for none'),
            'routelabel' => new external_value(PARAM_RAW, 'The route line beside a relationship, plain text, empty for none'),
            'routelinklabel' => new external_value(PARAM_RAW, 'The label of the route line\'s link, plain text'),
            'routeurl' => new external_value(PARAM_URL, 'Where the route line leads, empty for none'),
            'opens' => new external_value(PARAM_INT, 'When the window refusing a none, suspended or expired card opens, else 0'),
            'openslabel' => new external_value(PARAM_RAW, 'The chip naming that date, plain text, empty for none'),
        ]);
    }

    /**
     * The pill's sentence: one literal string per state, with the date or the course it names.
     *
     * @param string $state One of the STATE_* constants.
     * @param int $date The date a scheduled or ended state names.
     * @param string $prerequisite The prerequisite's name, plain, for a conditional state.
     * @return string The plain sentence.
     */
    private static function label(string $state, int $date, string $prerequisite): string {
        switch ($state) {
            case provider::STATE_ENROLLED:
                return get_string('state_enrolled', 'local_dimensions');
            case provider::STATE_SCHEDULED:
                return get_string('state_scheduled', 'local_dimensions', self::date($date));
            case provider::STATE_PENDING:
                return get_string('state_pending', 'local_dimensions');
            case provider::STATE_WAITLISTED:
                return get_string('state_waitlisted', 'local_dimensions');
            case provider::STATE_SUSPENDED:
                return get_string('state_suspended', 'local_dimensions');
            case provider::STATE_EXPIRED:
                return get_string('state_expired', 'local_dimensions', self::date($date));
            case provider::STATE_OPEN:
                return get_string('state_open', 'local_dimensions');
            case provider::STATE_FREE:
                return get_string('state_free', 'local_dimensions');
            case provider::STATE_KEY:
                return get_string('state_key', 'local_dimensions');
            case provider::STATE_CONDITIONAL:
                return get_string('state_conditional', 'local_dimensions', $prerequisite);
            default:
                return get_string('state_none', 'local_dimensions');
        }
    }

    /**
     * The label of a state's own action, or the empty string for a state that has none.
     *
     * @param string $state One of the STATE_* constants.
     * @return string The plain label.
     */
    private static function action_label(string $state): string {
        switch ($state) {
            case provider::STATE_OPEN:
                return get_string('state_cta_enrol', 'local_dimensions');
            case provider::STATE_PENDING:
            case provider::STATE_WAITLISTED:
                return get_string('state_cta_status', 'local_dimensions');
            case provider::STATE_FREE:
                return get_string('state_cta_access', 'local_dimensions');
            case provider::STATE_KEY:
                return get_string('state_cta_key', 'local_dimensions');
            case provider::STATE_CONDITIONAL:
                return get_string('state_cta_prerequisite', 'local_dimensions');
            default:
                return '';
        }
    }

    /**
     * A date as the cards print it, in the viewer's time zone.
     *
     * @param int $timestamp The time.
     * @return string The short date.
     */
    private static function date(int $timestamp): string {
        return userdate($timestamp, get_string('strftimedatefullshort', 'langconfig'));
    }

    /**
     * The name of the course a conditional state waits for, in the plain spelling.
     *
     * local_unlistedcourses names a prerequisite only to a viewer actively enrolled in it, so the name
     * reveals nothing; it is formatted in the prerequisite's own context and left unescaped, because
     * the pill escapes it once.
     *
     * @param int $courseid The prerequisite's id.
     * @return string|null The name, or null when there is no such course.
     */
    private static function prerequisite_name(int $courseid): ?string {
        global $DB;

        if ($courseid <= 0) {
            return null;
        }
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname');
        if (!$course) {
            return null;
        }
        return format_string($course->fullname, true, [
            'context' => \core\context\course::instance($courseid),
            'escape' => false,
        ]);
    }
}
