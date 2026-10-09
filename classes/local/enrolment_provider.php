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
 * Where a course card's enrolment state comes from.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * What a course card says about the viewer's enrolment, from one of two sources.
 *
 * Both course cards (the tracker's and the plan accordion's) ask this class, and nothing else in
 * the plugin knows which implementation answers. {@see enrolment_provider_core} is the rule this
 * plugin has always applied, frozen: an enrolment start date, a pending enrol_apply application,
 * and a self or apply route. {@see enrolment_provider_unlisted} asks local_unlistedcourses, which
 * owns the full rule (waiting list, suspended, ended, guest, key, prerequisite) for a site that
 * installs it and ticks the useunlistedcourses setting. The rule therefore lives in one plugin:
 * this one never grows a second copy of it.
 *
 * Neither implementation decides the lock. A card is open when the viewer is actively enrolled,
 * which is core's is_enrolled() whatever the provider ({@see \local_dimensions\calculator::is_locked()}),
 * and the provider is asked only about a card the viewer cannot open. Both answer for the current
 * user only: core's enrol_self asks $USER, and so does every method of local_unlistedcourses.
 *
 * A state is a plain array of facts, rendered by {@see enrolment_state::export()}:
 * state (one of the STATE_* constants), courseid, date (the start of a scheduled enrolment, the end
 * of an ended one, 0 otherwise), prerequisiteid (the course a conditional state waits for, 0
 * otherwise), actionurl (where the state's own action leads, null for a state with none),
 * routeurl (where the route line beside a relationship leads, null when no route is open) and
 * opens (when an enrolment window that is the only thing refusing the viewer opens, on a none,
 * suspended or expired state only, 0 otherwise; only local_unlistedcourses knows it, so the core
 * rule never sets it).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class enrolment_provider {
    /** @var string The viewer is actively enrolled: the only state of a card they can open. */
    public const STATE_ENROLLED = 'enrolled';

    /** @var string An enrolment that starts on a later date. */
    public const STATE_SCHEDULED = 'scheduled';

    /** @var string An enrol_apply application awaiting a decision. */
    public const STATE_PENDING = 'pending';

    /** @var string An enrol_apply application on the waiting list (provider only). */
    public const STATE_WAITLISTED = 'waitlisted';

    /** @var string A suspended enrolment that has not ended (provider only). */
    public const STATE_SUSPENDED = 'suspended';

    /** @var string An enrolment whose end date has passed (provider only). */
    public const STATE_EXPIRED = 'expired';

    /** @var string No enrolment, and a route that would take the viewer now. */
    public const STATE_OPEN = 'open';

    /** @var string No enrolment, and guest access with no key (provider only). */
    public const STATE_FREE = 'free';

    /** @var string No enrolment, and guest access behind a key (provider only). */
    public const STATE_KEY = 'key';

    /** @var string No enrolment until the viewer completes another course (provider only). */
    public const STATE_CONDITIONAL = 'conditional';

    /** @var string Nothing ties the viewer to the course and nothing is on offer. */
    public const STATE_NONE = 'none';

    /** @var string The setting that selects local_unlistedcourses; only a stored '1' switches it on. */
    public const SETTING = 'useunlistedcourses';

    /** @var string The class of local_unlistedcourses' public enrolment API, as class_exists() is asked. */
    public const UNLISTED_API = 'local_unlistedcourses\access';

    /** @var array The methods of that API the unlisted implementation calls; a build without any of them is not used. */
    public const UNLISTED_METHODS = ['get_enrolment_state', 'get_next_action', 'get_next_actions'];

    /** @var int The first Moodle branch local_unlistedcourses installs on. */
    public const UNLISTED_MIN_BRANCH = 502;

    /**
     * The implementation the site has chosen, falling back to the frozen core rule.
     *
     * The setting alone is not enough: a site that ticked it and later removed local_unlistedcourses,
     * or moved to a branch it does not install on, keeps a stored '1' and must still get cards.
     *
     * @return enrolment_provider
     */
    public static function get(): enrolment_provider {
        if (get_config('local_dimensions', self::SETTING) === '1' && static::unlisted_available()) {
            return new enrolment_provider_unlisted();
        }
        return new enrolment_provider_core();
    }

    /**
     * Whether local_unlistedcourses is installed with the API this plugin calls.
     *
     * Every reference to that plugin goes through here or behind it: it is optional, and it does not
     * install on Moodle 4.5 or 5.1, which this plugin supports.
     *
     * @return bool
     */
    public static function unlisted_available(): bool {
        global $CFG;

        if ((int) ($CFG->branch ?? 0) < self::UNLISTED_MIN_BRANCH) {
            return false;
        }
        /* method_exists() autoloads the class and answers false when there is none, so this also
           refuses a site without the plugin, and a build of it older than this API. */
        foreach (self::UNLISTED_METHODS as $method) {
            if (!method_exists(self::UNLISTED_API, $method)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Which implementation this is, for tests and diagnostics.
     *
     * @return string 'core' or 'unlisted'.
     */
    abstract public function name(): string;

    /**
     * The state of one course the viewer cannot open.
     *
     * @param \stdClass $course A course record with at least id and startdate.
     * @param int $viewerid The viewer, who must be the current user.
     * @return array The state facts ({@see enrolment_provider}).
     */
    abstract public function locked_state(\stdClass $course, int $viewerid): array;

    /**
     * The states of several courses the viewer cannot open, for a list that shows them together.
     *
     * @param array $courses Course id => course record with at least id and startdate.
     * @param int $viewerid The viewer, who must be the current user.
     * @return array Course id => state facts, in the order given.
     */
    abstract public function locked_states(array $courses, int $viewerid): array;

    /**
     * The state of a card the viewer can open.
     *
     * @param int $courseid The course id.
     * @return array The state facts.
     */
    public static function enrolled_state(int $courseid): array {
        return self::state(self::STATE_ENROLLED, $courseid);
    }

    /**
     * The state of a card that says nothing: no relationship, nothing on offer.
     *
     * Also what a course the viewer may not be told about gets, so its row reveals no more than a
     * course with no way in.
     *
     * @param int $courseid The course id.
     * @return array The state facts.
     */
    public static function none_state(int $courseid): array {
        return self::state(self::STATE_NONE, $courseid);
    }

    /**
     * Build the facts of one state.
     *
     * @param string $state One of the STATE_* constants.
     * @param int $courseid The course id.
     * @param int $date The start of a scheduled enrolment or the end of an ended one, else 0.
     * @param string|null $actionurl Where the state's own action leads, null when it has none.
     * @param string|null $routeurl Where the route line beside a relationship leads, null for none. Only a
     *        relationship (scheduled, pending, waitlisted, suspended, expired) is given one: an offer
     *        (open, free, key, conditional) is itself the way in.
     * @param int $prerequisiteid The course a conditional state waits for, else 0.
     * @param int $opens When the enrolment window refusing a none, suspended or expired state opens, else 0.
     * @return array The state facts.
     */
    protected static function state(
        string $state,
        int $courseid,
        int $date = 0,
        ?string $actionurl = null,
        ?string $routeurl = null,
        int $prerequisiteid = 0,
        int $opens = 0
    ): array {
        return [
            'state' => $state,
            'courseid' => $courseid,
            'date' => $date,
            'prerequisiteid' => $prerequisiteid,
            'actionurl' => $actionurl,
            'routeurl' => $routeurl,
            'opens' => $opens,
        ];
    }

    /**
     * The course page, which core sends a viewer who is not enrolled on to the enrolment page.
     *
     * @param int $courseid The course id.
     * @return string The URL.
     */
    protected static function course_url(int $courseid): string {
        return (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
    }

    /**
     * The course's enrolment page, where every enrolment method draws its form.
     *
     * Core has no page per enrolment instance: /enrol/index.php lists every instance of the course.
     *
     * @param int $courseid The course id.
     * @return string The URL.
     */
    protected static function enrol_url(int $courseid): string {
        return (new \moodle_url('/enrol/index.php', ['id' => $courseid]))->out(false);
    }
}
