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
 * External function to add a comment to a user competency.
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
use core_comment\manager as comment;

/**
 * External function to add a comment to a user competency.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_comment extends external_api {
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
            'content' => new external_value(PARAM_RAW, 'Comment content'),
        ]);
    }

    /**
     * Add a comment to the specified item.
     *
     * @param string $component Component name
     * @param string $area Comment area
     * @param int $itemid Item ID
     * @param int $contextid Context ID
     * @param string $content Comment content
     * @return array Result with the new comment
     */
    public static function execute($component, $area, $itemid, $contextid, $content) {
        global $USER, $DB, $PAGE;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
            'contextid' => $contextid,
            'content' => $content,
        ]);

        // Get context from ID.
        $context = \context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);

        // Check basic capability.
        require_capability('local/dimensions:view', $context);

        // Sanitize content.
        $content = trim($params['content']);
        if (empty($content)) {
            return [
                'success' => false,
                'commentid' => 0,
                'content' => '',
                'fullname' => '',
                'userid' => 0,
                'profileimageurl' => '',
                'timecreated' => '',
                'error' => get_string('emptycomment', 'local_dimensions'),
            ];
        }

        // Initialize comments.
        $args = new \stdClass();
        $args->context = $context;
        $args->component = $params['component'];
        $args->area = $params['area'];
        $args->itemid = $params['itemid'];

        try {
            $commentmanager = new comment($args);

            // Check if user can post.
            if (!$commentmanager->can_post()) {
                return [
                    'success' => false,
                    'commentid' => 0,
                    'content' => '',
                    'fullname' => '',
                    'userid' => 0,
                    'profileimageurl' => '',
                    'timecreated' => '',
                    'error' => get_string('nopermissiontocomment', 'local_dimensions'),
                ];
            }

            // Add the comment.
            $newcomment = $commentmanager->add($content);

            if ($newcomment) {
                // Get user info.
                $user = $DB->get_record('user', ['id' => $USER->id], 'id, firstname, lastname, picture, imagealt, email');
                $fullname = $user ? fullname($user) : '';

                // Get profile image URL.
                $profileimageurl = '';
                if ($user) {
                    $userpicture = new \user_picture($user);
                    $userpicture->size = 1;
                    $profileimageurl = $userpicture->get_url($PAGE)->out(false);
                }

                return [
                    'success' => true,
                    'commentid' => (int) $newcomment->id,
                    'content' => format_text($newcomment->content, FORMAT_MOODLE, ['context' => $context]),
                    'fullname' => $fullname,
                    'userid' => (int) $USER->id,
                    'profileimageurl' => $profileimageurl,
                    'timecreated' => userdate($newcomment->timecreated),
                    'error' => '',
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'commentid' => 0,
                'content' => '',
                'fullname' => '',
                'userid' => 0,
                'profileimageurl' => '',
                'timecreated' => '',
                'error' => $e->getMessage(),
            ];
        }

        return [
            'success' => false,
            'commentid' => 0,
            'content' => '',
            'fullname' => '',
            'userid' => 0,
            'profileimageurl' => '',
            'timecreated' => '',
            'error' => get_string('erroraddingcomment', 'local_dimensions'),
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the comment was added successfully'),
            'commentid' => new external_value(PARAM_INT, 'New comment ID'),
            'content' => new external_value(PARAM_RAW, 'Formatted comment content'),
            'fullname' => new external_value(PARAM_TEXT, 'Full name of the author'),
            'userid' => new external_value(PARAM_INT, 'User ID of the author'),
            'profileimageurl' => new external_value(PARAM_URL, 'Profile image URL', VALUE_OPTIONAL),
            'timecreated' => new external_value(PARAM_TEXT, 'Formatted date/time'),
            'error' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
        ]);
    }
}
