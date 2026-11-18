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
 * The permanent restrictions of an activity, judged for one learner.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_availability\capability_checker;
use core_availability\condition;
use core_availability\info;
use core_availability\info_module;
use core_availability\info_section;
use core_availability\tree;

/**
 * Whether one learner passes the restrictions of an activity that are permanent for a person.
 *
 * The answer is the one {@see info_module::filter_user_list()} gives for a list holding only this
 * learner, read by the current user: the section's and the activity's trees, keeping only the
 * conditions that {@see condition::is_applied_to_user_lists()} marks (group, grouping, profile),
 * and a learner who holds moodle/course:ignoreavailabilityrestrictions passes either way.
 *
 * Core's filter is built for long lists. Its group and grouping conditions read every member of
 * the group, the grouping or every group of the course, and the capability steps list everyone
 * holding the capability, whatever the list's length: for one learner in a course of 100,000,
 * up to 100,000 rows per activity on every card. Here those two conditions are answered from the
 * learner's own groups ({@see self::group_passes()}, {@see self::grouping_passes()}), and each
 * capability is one has_capability(). Every other condition still goes through its own
 * filter_user_list(), with a one-person list, so a condition plugin keeps its own rule.
 *
 * The condition's per-user test, is_available(), is not used for groups: it gives a different
 * answer when the course has groups whose visibility is not "all" (it ignores visibility for a
 * group and applies the viewer's to a grouping, where the list filter does the opposite).
 *
 * The tree walk mirrors {@see tree::filter_user_list()} and must stay in step with it. Each node
 * and each condition is decoded by core's own tree, so the operators, the lax skipping of a
 * disabled condition type and the validation are core's.
 *
 * Stores: the learner's and the viewer's groups are read once per modinfo object, the lifetime
 * core gives its own copy ({@see \course_modinfo::get_groups()}); the group records come from the
 * shared groupdata cache. Nothing is written, so a cold node or a fresh cron gives the same answer.
 *
 * block_compass carries a twin of this class (local\progress\learner_restrictions), copied whole: a change to
 * one is made to the other, and docs/core-parity.md's audit reads both against core.
 */
final class learner_restrictions {
    /** @var \WeakMap|null Raw group membership by modinfo, then by user id. */
    private static ?\WeakMap $groups = null;

    /**
     * Whether the learner passes the permanent restrictions of the activity and its section.
     *
     * @param \cm_info $cm The activity, from any user's modinfo.
     * @param int $userid The learner.
     * @return bool
     */
    public static function passes(\cm_info $cm, int $userid): bool {
        global $CFG;

        if (empty($CFG->enableavailability)) {
            return true;
        }

        $modinfo = $cm->get_modinfo();
        $section = $modinfo->get_section_info($cm->sectionnum, MUST_EXIST);

        return self::info_passes(new info_section($section), $section->availability, $modinfo, $userid)
            && self::info_passes(new info_module($cm), $cm->availability, $modinfo, $userid);
    }

    /**
     * Whether the learner passes one restriction set, the section's or the activity's.
     *
     * Mirrors {@see info::filter_user_list()}: no restriction lets everybody through, and the
     * capability to ignore restrictions lets the holder through a tree they fail.
     *
     * @param info $info The section's or the activity's availability information.
     * @param string|null $availability The raw restriction set, null when there is none.
     * @param \course_modinfo $modinfo The modinfo the activity came from, used as the memo key.
     * @param int $userid The learner.
     * @return bool
     */
    private static function info_passes(info $info, ?string $availability, \course_modinfo $modinfo, int $userid): bool {
        if ($availability === null) {
            return true;
        }

        // Validates the set as core does and throws the same exception for a broken one.
        $info->get_availability_tree();

        $checker = new single_user_capability_checker($info->get_context(), $userid);
        if (self::node_passes(json_decode($availability), false, $info, $modinfo, $userid, $checker)) {
            return true;
        }

        return has_capability('moodle/course:ignoreavailabilityrestrictions', $info->get_context(), $userid, false);
    }

    /**
     * Whether the learner passes one node of the tree, as {@see tree::filter_user_list()} decides.
     *
     * @param \stdClass $node The decoded node, holding op and c.
     * @param bool $not Whether the parent checks this node negatively.
     * @param info $info The availability information the tree belongs to.
     * @param \course_modinfo $modinfo The memo key.
     * @param int $userid The learner.
     * @param capability_checker $checker A checker that knows only this learner.
     * @return bool
     */
    private static function node_passes(
        \stdClass $node,
        bool $not,
        info $info,
        \course_modinfo $modinfo,
        int $userid,
        capability_checker $checker
    ): bool {
        [$innernot, $andoperator] = (new tree($node, true, false))->get_logic_flags($not);

        $anyconditions = false;
        foreach ($node->c as $child) {
            if (isset($child->type)) {
                /* Decoded by core inside a one-child tree, so a disabled or missing condition
                   type is dropped exactly as core's lax decoding drops it. */
                $wrapper = new tree((object) ['op' => tree::OP_AND, 'c' => [$child]], true, false);
                $conditions = $wrapper->get_all_children(condition::class);
                if (!$conditions) {
                    continue;
                }
                $condition = reset($conditions);

                if (!$condition->is_applied_to_user_lists()) {
                    if ($andoperator) {
                        continue;
                    }
                    // An OR with one option that never restricts a list lets everybody through.
                    return true;
                }

                $passes = self::condition_passes($condition, $child, $innernot, $info, $modinfo, $userid, $checker);
            } else {
                // A subtree always applies to user lists ({@see tree::is_applied_to_user_lists()}).
                $passes = self::node_passes($child, $innernot, $info, $modinfo, $userid, $checker);
            }

            if ($andoperator && !$passes) {
                return false;
            }
            if (!$andoperator && $passes) {
                return true;
            }
            $anyconditions = true;
        }

        // AND: nothing excluded the learner. OR: with no condition that applies, everybody passes.
        return $andoperator || !$anyconditions;
    }

    /**
     * Whether the learner passes one condition that applies to user lists.
     *
     * @param condition $condition The decoded condition.
     * @param \stdClass $structure Its stored form, which names the group or grouping.
     * @param bool $not Whether it is checked negatively.
     * @param info $info The availability information it belongs to.
     * @param \course_modinfo $modinfo The memo key.
     * @param int $userid The learner.
     * @param capability_checker $checker A checker that knows only this learner.
     * @return bool
     */
    private static function condition_passes(
        condition $condition,
        \stdClass $structure,
        bool $not,
        info $info,
        \course_modinfo $modinfo,
        int $userid,
        capability_checker $checker
    ): bool {
        if ($condition instanceof \availability_group\condition) {
            $groupid = (int) ($structure->id ?? 0);
            /* A group this course does not hold (deleted, or another course's after a restore) is left
               to core's filter, which reads it wherever it lives. */
            if (!$groupid || isset(groups_get_course_data((int) $info->get_course()->id)->groups[$groupid])) {
                return self::group_passes($groupid, $not, $info, $modinfo, $userid, $checker);
            }
        }

        if ($condition instanceof \availability_grouping\condition) {
            $groupingid = isset($structure->id) ? (int) $structure->id
                : ($info instanceof info_module ? (int) $info->get_course_module()->groupingid : 0);
            if ($groupingid > 0) {
                return self::grouping_passes($groupingid, $not, $info, $modinfo, $userid, $checker);
            }
            /* Grouping 0, which core lists as nobody (the memo's key 0 means every group), or an
               activity grouping with none set, for which core's filter throws its exception. */
        }

        /* As in info::filter_user_list(), a condition may read the current user's modinfo, and only
           that method sets it: core has no setter, and its public filter walks the whole tree for the
           course's members (the cost this class exists to avoid). So the protected property is set
           here on purpose. tests/fixtures/core_parity.json fingerprints both info::filter_user_list()
           and info::get_modinfo(), so a change to either fails the parity check. */
        $one = [$userid => (object) ['id' => $userid]];
        $property = new \ReflectionProperty(info::class, 'modinfo');
        $property->setValue($info, get_fast_modinfo($info->get_course()));
        try {
            return array_key_exists($userid, $condition->filter_user_list($one, $not, $info, $checker));
        } finally {
            $property->setValue($info, null);
        }
    }

    /**
     * The group condition's list filter for one learner.
     *
     * Mirrors {@see \availability_group\condition::filter_user_list()}: a named group lists its
     * members as {@see groups_get_members()} shows them to the current user, so where the course
     * has groups of restricted visibility and the current user may not see them all, a member of
     * a group whose visibility is "none" is not listed, "members" is listed only to a member and
     * "own" only to the member themself; "any group" lists every group member of the course,
     * whatever the visibility. A holder of moodle/site:accessallgroups passes, $not or not.
     *
     * @param int $groupid The group, or 0 for any group.
     * @param bool $not Whether the condition is negated.
     * @param info $info The availability information.
     * @param \course_modinfo $modinfo The memo key.
     * @param int $userid The learner.
     * @param capability_checker $checker A checker that knows only this learner.
     * @return bool
     */
    private static function group_passes(
        int $groupid,
        bool $not,
        info $info,
        \course_modinfo $modinfo,
        int $userid,
        capability_checker $checker
    ): bool {
        global $USER;

        if ($checker->get_users_by_capability('moodle/site:accessallgroups')) {
            return true;
        }

        $courseid = (int) $info->get_course()->id;
        $learnergroups = self::raw_groups($modinfo, $courseid, $userid)[0] ?? [];
        if (!$groupid) {
            $allow = !empty($learnergroups);
        } else {
            $allow = in_array($groupid, $learnergroups);
            if ($allow && !\core_group\visibility::can_view_all_groups($courseid)) {
                $group = groups_get_course_data($courseid)->groups[$groupid] ?? null;
                $visibility = (int) ($group->visibility ?? GROUPS_VISIBILITY_ALL);
                $allow = $visibility === GROUPS_VISIBILITY_ALL
                    || ($visibility === GROUPS_VISIBILITY_MEMBERS
                        && in_array($groupid, self::raw_groups($modinfo, $courseid, (int) $USER->id)[0] ?? []))
                    || ($visibility === GROUPS_VISIBILITY_OWN && $userid === (int) $USER->id);
            }
        }

        return $not ? !$allow : $allow;
    }

    /**
     * The grouping condition's list filter for one learner.
     *
     * Mirrors {@see \availability_grouping\condition::filter_user_list()}: a member of any group
     * of the grouping, whatever its visibility, and a holder of moodle/site:accessallgroups,
     * $not or not.
     *
     * @param int $groupingid The grouping.
     * @param bool $not Whether the condition is negated.
     * @param info $info The availability information.
     * @param \course_modinfo $modinfo The memo key.
     * @param int $userid The learner.
     * @param capability_checker $checker A checker that knows only this learner.
     * @return bool
     */
    private static function grouping_passes(
        int $groupingid,
        bool $not,
        info $info,
        \course_modinfo $modinfo,
        int $userid,
        capability_checker $checker
    ): bool {
        if ($checker->get_users_by_capability('moodle/site:accessallgroups')) {
            return true;
        }

        $allow = !empty(self::raw_groups($modinfo, (int) $info->get_course()->id, $userid)[$groupingid]);

        return $not ? !$allow : $allow;
    }

    /**
     * A user's groups in the course, whatever their visibility, by grouping (0 for all).
     *
     * groups_get_user_groups() skips its shared cache when the current user may not see every
     * group of the course, so each learner is read once per modinfo object, as modinfo itself
     * reads the current user's groups once ({@see \course_modinfo::get_groups()}).
     *
     * @param \course_modinfo $modinfo The memo key.
     * @param int $courseid The course.
     * @param int $userid The user.
     * @return array Group ids by grouping id.
     */
    private static function raw_groups(\course_modinfo $modinfo, int $courseid, int $userid): array {
        self::$groups ??= new \WeakMap();
        $known = self::$groups[$modinfo] ?? [];
        if (!array_key_exists($userid, $known)) {
            $known[$userid] = groups_get_user_groups($courseid, $userid, true);
            self::$groups[$modinfo] = $known;
        }
        return $known[$userid];
    }
}
