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
 * Learning plan status labels.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_competency\plan;

/**
 * Maps competency plan status codes to their core_competency labels.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_status {
    /**
     * Localised label for a plan status code.
     *
     * @param int $status One of the plan::STATUS_* constants.
     * @return string The label, or '' for an unknown status.
     */
    public static function label(int $status): string {
        // Literal string ids, so the string checker can verify each one.
        return match ($status) {
            plan::STATUS_DRAFT => get_string('planstatusdraft', 'core_competency'),
            plan::STATUS_ACTIVE => get_string('planstatusactive', 'core_competency'),
            plan::STATUS_COMPLETE => get_string('planstatuscomplete', 'core_competency'),
            plan::STATUS_WAITING_FOR_REVIEW => get_string('planstatuswaitingforreview', 'core_competency'),
            plan::STATUS_IN_REVIEW => get_string('planstatusinreview', 'core_competency'),
            default => '',
        };
    }
}
