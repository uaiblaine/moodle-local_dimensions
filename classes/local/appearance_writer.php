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
 * The one write of a competency's appearance fields.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use local_dimensions\customfield\competency_handler;

/**
 * Writes one competency's appearance fields through the competency handler, as the competency form saves them.
 *
 * The handler's instance_form_save() is the form's own save path: it writes only the fields can_edit() allows in
 * the competency's own context, leaves every field the record does not name as it is, and fires
 * competency_customfields_updated with the diff of the effective values. The bulk service
 * ({@see \local_dimensions\external\set_competencies_appearance}) resolves this class through core's container, so a
 * test can swap it for one that fails on a chosen competency and prove that the service's transaction leaves nothing
 * half written; production code never swaps it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appearance_writer {
    /**
     * Save the custom-field values a record names for one competency.
     *
     * @param \stdClass $record The competency id as `id`, and one `customfield_<shortname>` property per field written.
     * @return void
     */
    public function save(\stdClass $record): void {
        competency_handler::create()->instance_form_save($record, false);
    }
}
