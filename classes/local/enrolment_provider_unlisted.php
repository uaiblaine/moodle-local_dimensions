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
 * The enrolment state local_unlistedcourses works out.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * Delegates to local_unlistedcourses' public API and copies none of its rule.
 *
 * The plugin answers two independent questions (its access class docblock): the relationship the
 * viewer's enrolment rows give them ({@see \local_unlistedcourses\access::get_enrolment_state()})
 * and the next action the course's enrolment methods offer
 * ({@see \local_unlistedcourses\access::get_next_action()}, batched as get_next_actions() for a
 * list of courses). This class only translates the two answers into a card state, in one place
 * ({@see translate()}): a relationship is the state and an open route beside it the route line;
 * with no relationship, the next action is the state.
 *
 * The plugin's values are strings, and they are mapped from literals here rather than from its
 * class constants, because this class is loaded on Moodle 4.5 and 5.1, where that plugin cannot
 * install and naming one of its constants would throw. enrolment_provider_unlisted_test holds the
 * literals against the constants wherever the plugin is installed. {@see enrolment_provider::get()}
 * selects this class only when {@see enrolment_provider::unlisted_available()} says the API is there.
 *
 * Destinations: an offer or a route leads to the course's enrolment page, where the method's own
 * form is; free guest access to the course itself; a prerequisite to that course, which the plugin
 * names only to a viewer actively enrolled in it.
 *
 * A blocked answer stays the none state, and carries the plugin's `opens` date when it has one: the
 * day an enrolment window that is the only refusal opens. The date is the plugin's to work out; a
 * build of it older than that key answers without one, which reads as no date.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_provider_unlisted extends enrolment_provider {
    /**
     * @var array local_unlistedcourses' relationship values (access::RELATIONSHIP_*) => this plugin's
     *            state. enrolled and none have no entry: an enrolled viewer is never asked about (the
     *            lock is core's), and none hands the card over to the next action.
     */
    public const RELATIONSHIPS = [
        'scheduled' => self::STATE_SCHEDULED,
        'pending' => self::STATE_PENDING,
        'waitlisted' => self::STATE_WAITLISTED,
        'suspended' => self::STATE_SUSPENDED,
        'expired' => self::STATE_EXPIRED,
    ];

    /**
     * @var array The relationship values the plugin answers that carry no state here
     *            (access::RELATIONSHIP_ENROLLED and access::RELATIONSHIP_NONE).
     */
    public const NO_RELATIONSHIP = ['enrolled', 'none'];

    /**
     * @var array local_unlistedcourses' next-action values (access::NEXT_*) => this plugin's state.
     *            guest is resolved by the kind of guest access; blocked reads as none, as it does on
     *            the theme's card.
     */
    public const NEXT_ACTIONS = [
        'open' => self::STATE_OPEN,
        'guest' => self::STATE_FREE,
        'conditional' => self::STATE_CONDITIONAL,
        'blocked' => self::STATE_NONE,
        'none' => self::STATE_NONE,
    ];

    /** @var string The next action's guest value for access behind a key (access::GUEST_KEY). */
    public const GUEST_KEY = 'key';

    /** @var string The next action of a viewer every method refuses now (access::NEXT_BLOCKED). */
    public const BLOCKED = 'blocked';

    /**
     * Which implementation this is.
     *
     * @return string
     */
    public function name(): string {
        return 'unlisted';
    }

    /**
     * The class whose static methods answer: local_unlistedcourses' access class.
     *
     * A seam, so a test can point the translation at a stand-in on a site without the plugin.
     *
     * @return string A class name.
     */
    protected function api(): string {
        return self::UNLISTED_API;
    }

    /**
     * The state of one course the viewer cannot open, from the plugin's per-course answers.
     *
     * @param \stdClass $course A course record with at least an id.
     * @param int $viewerid The viewer, who must be the current user: the plugin answers for $USER.
     * @return array The state facts.
     */
    public function locked_state(\stdClass $course, int $viewerid): array {
        $api = $this->api();
        $courseid = (int) $course->id;

        return self::translate($courseid, $api::get_enrolment_state($courseid), $api::get_next_action($courseid));
    }

    /**
     * The states of several courses, with the next actions asked in one batch.
     *
     * The plugin's batch reads every course's instances in a fixed number of statements and may err
     * towards open where a rule cannot be read in SQL; the relationship is asked per course, which
     * is one statement each.
     *
     * @param array $courses Course id => course record with at least an id.
     * @param int $viewerid The viewer, who must be the current user.
     * @return array Course id => state facts.
     */
    public function locked_states(array $courses, int $viewerid): array {
        if (!$courses) {
            return [];
        }
        $api = $this->api();
        $courseids = array_map('intval', array_keys($courses));
        $nextactions = $api::get_next_actions($courseids);

        $states = [];
        foreach ($courseids as $courseid) {
            $next = $nextactions[$courseid] ?? ['type' => 'none', 'routes' => []];
            $states[$courseid] = self::translate($courseid, $api::get_enrolment_state($courseid), $next);
        }
        return $states;
    }

    /**
     * Turn the plugin's two answers about one course into a card state.
     *
     * Pure: no database, no $USER, so the mapping is tested on every site, the plugin or not.
     *
     * @param int $courseid The course id.
     * @param array $relationship The answer of access::get_enrolment_state(): type, startsat, endsat.
     * @param array $next The answer of access::get_next_action(): type, routes, guest, conditional,
     *        and opens on a build that has it.
     * @return array The state facts.
     */
    public static function translate(int $courseid, array $relationship, array $next): array {
        $enrolurl = self::enrol_url($courseid);
        $route = empty($next['routes']) ? null : $enrolurl;

        $state = self::RELATIONSHIPS[(string) ($relationship['type'] ?? '')] ?? null;
        if ($state !== null) {
            $date = match ($state) {
                self::STATE_SCHEDULED => (int) ($relationship['startsat'] ?? 0),
                self::STATE_EXPIRED => (int) ($relationship['endsat'] ?? 0),
                default => 0,
            };
            // An application is decided elsewhere; its own page says where it stands.
            $isapplication = $state === self::STATE_PENDING || $state === self::STATE_WAITLISTED;
            return self::state($state, $courseid, $date, $isapplication ? $enrolurl : null, $route);
        }

        $state = self::NEXT_ACTIONS[(string) ($next['type'] ?? '')] ?? self::STATE_NONE;
        if ($state === self::STATE_FREE && ($next['guest'] ?? null) === self::GUEST_KEY) {
            $state = self::STATE_KEY;
        }
        switch ($state) {
            case self::STATE_OPEN:
                return self::state($state, $courseid, 0, $enrolurl);
            case self::STATE_FREE:
                return self::state($state, $courseid, 0, self::course_url($courseid));
            case self::STATE_KEY:
                // The key is asked for by enrol_guest's form, on the enrolment page.
                return self::state($state, $courseid, 0, $enrolurl);
            case self::STATE_CONDITIONAL:
                $prerequisiteid = (int) ($next['conditional']['prerequisiteid'] ?? 0);
                if ($prerequisiteid <= 0) {
                    // A conditional state names its prerequisite or is not one.
                    return self::none_state($courseid);
                }
                return self::state($state, $courseid, 0, self::course_url($prerequisiteid), null, $prerequisiteid);
            default:
                $opens = ($next['type'] ?? null) === self::BLOCKED ? (int) ($next['opens'] ?? 0) : 0;
                if ($opens > 0) {
                    return self::state(self::STATE_NONE, $courseid, 0, null, null, 0, $opens);
                }
                return self::none_state($courseid);
        }
    }
}
