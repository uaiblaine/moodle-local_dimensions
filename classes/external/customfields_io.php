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

declare(strict_types=1);

namespace local_dimensions\external;

use core_customfield\data_controller;
use core_customfield\handler;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\customfield\competency_handler;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Shared input/output structures + read/apply helpers for local_dimensions
 * web services that round-trip customfield data.
 *
 * MVP scope: `text`, `select`, `textarea` fields. Picture fields are silently
 * skipped (they require draft-area uploads which are outside the API scope).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait customfields_io {

    /** Customfield types accepted on write and exposed on read. */
    private static array $supportedcftypes = ['text', 'select', 'textarea'];

    /**
     * Input shape for write endpoints: [{shortname, value}].
     */
    protected static function customfields_input_structure(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'shortname' => new external_value(
                    PARAM_ALPHANUMEXT,
                    'Customfield shortname (e.g. local_dimensions_tag1)'
                ),
                'value' => new external_value(
                    PARAM_RAW,
                    '1-based option index for select fields; raw string for text/textarea fields'
                ),
            ]),
            'Local dimensions custom fields. Picture-typed fields are not accepted by this API.',
            VALUE_DEFAULT,
            []
        );
    }

    /**
     * Output shape for read endpoints: [{shortname, name, type, value, displayvalue}].
     */
    protected static function customfields_output_structure(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Customfield shortname'),
                'name' => new external_value(PARAM_TEXT, 'Localised field display name'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'Field type: text, select, or textarea'),
                'value' => new external_value(
                    PARAM_RAW,
                    'Raw stored value (intvalue for select, text/textarea body otherwise)'
                ),
                'displayvalue' => new external_value(
                    PARAM_RAW,
                    'Resolved display label (option label for select; same as value otherwise)'
                ),
            ]),
            'Local dimensions custom field values'
        );
    }

    /**
     * Persist customfield data by handing form-shaped data to the area handler.
     *
     * Unknown shortnames, non-editable fields (per-field can_edit checked inside
     * the handler), and out-of-scope types are silently skipped.
     */
    protected static function apply_customfields(int $instanceid, string $area, array $input, bool $isnew): void {
        if (empty($input)) {
            return;
        }
        $handler = self::get_handler_for_area($area);
        $editable = $handler->get_editable_fields($isnew ? 0 : $instanceid);

        $byshortname = [];
        foreach ($editable as $field) {
            $byshortname[$field->get('shortname')] = $field;
        }

        $data = (object) ['id' => $instanceid];
        foreach ($input as $row) {
            $sn = $row['shortname'];
            if (!isset($byshortname[$sn])) {
                continue;
            }
            $field = $byshortname[$sn];
            if (!in_array($field->get('type'), self::$supportedcftypes, true)) {
                continue;
            }
            $controller = data_controller::create(0, null, $field);
            $data->{$controller->get_form_element_name()} = $row['value'];
        }

        $handler->instance_form_save($data, $isnew);
    }

    /**
     * Read stored customfield values for an instance, in MVP-scope types only.
     *
     * @return array<int, array{shortname:string, name:string, type:string, value:string, displayvalue:string}>
     */
    protected static function read_customfields(int $instanceid, string $area): array {
        $handler = self::get_handler_for_area($area);
        $output = [];
        foreach ($handler->get_instance_data($instanceid, true) as $data) {
            $field = $data->get_field();
            $type = $field->get('type');
            if (!in_array($type, self::$supportedcftypes, true)) {
                continue;
            }
            $value = $type === 'select'
                ? (string) ($data->get('intvalue') ?? '')
                : (string) ($data->get('value') ?? '');
            $output[] = [
                'shortname' => $field->get('shortname'),
                'name' => $field->get_formatted_name(),
                'type' => $type,
                'value' => $value,
                'displayvalue' => (string) $data->export_value(),
            ];
        }
        return $output;
    }

    /**
     * Append a `customfields` key to an existing external_single_structure.
     *
     * Relies on `external_single_structure::$keys` being publicly accessible
     * (verified in core/external/classes/external_single_structure.php).
     */
    protected static function with_customfields(external_single_structure $base): external_single_structure {
        $keys = $base->keys;
        $keys['customfields'] = self::customfields_output_structure();
        return new external_single_structure($keys);
    }

    /**
     * Resolve the customfield handler for a given local_dimensions area.
     */
    private static function get_handler_for_area(string $area): handler {
        return $area === helper::AREA_LP ? lp_handler::create() : competency_handler::create();
    }
}
