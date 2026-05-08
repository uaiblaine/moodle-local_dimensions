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
 * Hook callbacks for local_dimensions.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use core\hook\output\before_footer_html_generation;

/**
 * Hook callbacks for local_dimensions.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add the "Return to Plan" floating button before the footer.
     *
     * This displays a FAB button when:
     * 1. The feature is enabled in settings
     * 2. User is currently on a course or activity page
     * 3. There is a stored return context for the current course (came from a plan view)
     * 4. Not running inside an iframe (H5P, etc.)
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE;

        // Ensure all custom fields exist (runs once per session).
        if (get_config('core_competency', 'enabled')) {
            helper::ensure_all_fields();
        }

        // Check if feature is enabled.
        if (!get_config('local_dimensions', 'enablereturnbutton')) {
            return;
        }

        // Don't show for guests or not logged in.
        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Check if we're on a valid course/activity page.
        $currentcourseid = self::get_current_course_id();
        if ($currentcourseid === null) {
            return;
        }

        // Look up return context for this specific course.
        $context = helper::get_return_context_for_course($currentcourseid);
        if (empty($context) || empty($context['url'])) {
            return;
        }

        // Get configured button color.
        $buttoncolor = get_config('local_dimensions', 'returnbuttoncolor') ?: '#667eea';

        // Render the return button with iframe detection script.
        $renderer = $hook->renderer;
        $html = $renderer->render_from_template('local_dimensions/return_button', [
            'returnurl' => $context['url'],
            'label' => get_string('returntoplan', 'local_dimensions'),
            'buttoncolor' => $buttoncolor,
        ]);
        $hook->add_html($html);

        // Initialise FAB visibility logic via AMD (main window only).
        $PAGE->requires->js_call_amd('local_dimensions/return_button', 'init');
    }

    /**
     * Get the current course ID from the page context.
     *
     * @return int|null The course ID or null if not on a course/activity page.
     */
    private static function get_current_course_id(): ?int {
        global $PAGE, $COURSE;

        // First, check if we have a course set.
        if (!empty($COURSE->id) && $COURSE->id != SITEID) {
            return (int) $COURSE->id;
        }

        // Check page context.
        $context = $PAGE->context;
        if ($context instanceof \core\context\course) {
            return (int) $context->instanceid;
        }
        if ($context instanceof \core\context\module) {
            // Get the course from the module context.
            $coursecontext = $context->get_course_context(false);
            if ($coursecontext) {
                return (int) $coursecontext->instanceid;
            }
        }

        return null;
    }
}
