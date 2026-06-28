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
     * @return string
     */
    public static function label(int $status): string {
        $names = [
            plan::STATUS_DRAFT => 'draft',
            plan::STATUS_ACTIVE => 'active',
            plan::STATUS_COMPLETE => 'complete',
            plan::STATUS_WAITING_FOR_REVIEW => 'waitingforreview',
            plan::STATUS_IN_REVIEW => 'inreview',
        ];
        if (!isset($names[$status])) {
            return '';
        }
        return get_string('planstatus' . $names[$status], 'core_competency');
    }
}
