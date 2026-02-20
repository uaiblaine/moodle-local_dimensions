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
 * External function to get comments for a user competency.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_comment\manager as comment;

/**
 * External function to get comments for a user competency.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_comments extends external_api {
    /**
     * Define input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component name (e.g., competency)'),
            'area' => new external_value(PARAM_AREA, 'Comment area (e.g., user_competency)'),
            'itemid' => new external_value(PARAM_INT, 'Item ID (e.g., user competency ID)'),
            'contextid' => new external_value(PARAM_INT, 'Context ID'),
            'page' => new external_value(PARAM_INT, 'Page number (0-based)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get comments for the specified item.
     *
     * @param string $component Component name
     * @param string $area Comment area
     * @param int $itemid Item ID
     * @param int $contextid Context ID
     * @param int $page Page number
     * @return array Comments data
     */
    public static function execute($component, $area, $itemid, $contextid, $page = 0) {
        global $USER, $DB, $PAGE;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
            'contextid' => $contextid,
            'page' => $page,
        ]);

        // Get context from ID.
        $context = \core\context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);

        // Check basic capability.
        require_capability('local/dimensions:view', $context);

        // Initialize comments.
        $args = new \stdClass();
        $args->context = $context;
        $args->component = $params['component'];
        $args->area = $params['area'];
        $args->itemid = $params['itemid'];

        try {
            $commentmanager = new comment($args);
            $commentmanager->set_view_permission(true);

            // Get comments using the internal method.
            $rawcomments = $commentmanager->get_comments($params['page']);
        } catch (\Exception $e) {
            return [
                'comments' => [],
                'count' => 0,
                'perpage' => 15,
                'canpost' => false,
                'error' => $e->getMessage(),
            ];
        }

        // Format comments for output.
        $comments = [];
        foreach ($rawcomments as $comment) {
            // Get user info for the comment author.
            $user = $DB->get_record('user', ['id' => $comment->userid], 'id, firstname, lastname, picture, imagealt, email');
            $fullname = $user ? fullname($user) : '';

            // Get profile image URL.
            $profileimageurl = '';
            if ($user) {
                $userpicture = new \user_picture($user);
                $userpicture->size = 1; // F2 size (small).
                $profileimageurl = $userpicture->get_url($PAGE)->out(false);
            }

            $comments[] = [
                'id' => (int) $comment->id,
                'content' => format_text($comment->content, FORMAT_MOODLE, ['context' => $context]),
                'userid' => (int) $comment->userid,
                'fullname' => $fullname,
                'profileimageurl' => $profileimageurl,
                'timecreated' => userdate($comment->timecreated),
                'timecreatedraw' => (int) $comment->timecreated,
            ];
        }

        return [
            'comments' => $comments,
            'count' => $commentmanager->count(),
            'perpage' => 15,
            'canpost' => $commentmanager->can_post(),
            'error' => '',
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'comments' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Comment ID'),
                    'content' => new external_value(PARAM_RAW, 'Comment content (HTML formatted)'),
                    'userid' => new external_value(PARAM_INT, 'User ID of the author'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full name of the author'),
                    'profileimageurl' => new external_value(PARAM_URL, 'Profile image URL'),
                    'timecreated' => new external_value(PARAM_TEXT, 'Formatted date/time'),
                    'timecreatedraw' => new external_value(PARAM_INT, 'Unix timestamp'),
                ]),
            ),
            'count' => new external_value(PARAM_INT, 'Total comment count'),
            'perpage' => new external_value(PARAM_INT, 'Comments per page'),
            'canpost' => new external_value(PARAM_BOOL, 'Whether user can post comments'),
            'error' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
        ]);
    }
}
