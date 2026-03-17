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
 * Event observers definition.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core_competency\event\competency_created',
        'callback' => 'local_dimensions\observer::competency_created',
    ],
    [
        'eventname' => '\core_competency\event\competency_updated',
        'callback' => 'local_dimensions\observer::competency_updated',
    ],
    [
        'eventname' => '\core\event\competency_user_competency_rated',
        'callback' => 'local_dimensions\observer::user_competency_rated',
    ],
    [
        'eventname' => '\core\event\competency_user_competency_rated_in_plan',
        'callback' => 'local_dimensions\observer::user_competency_rated_in_plan',
    ],
    [
        'eventname' => '\core\event\competency_evidence_created',
        'callback' => 'local_dimensions\observer::evidence_created',
    ],
];
