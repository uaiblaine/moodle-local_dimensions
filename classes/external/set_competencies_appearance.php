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
 * Set the appearance fields of several competencies of one framework at once (the workspace's bulk dock).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\api;
use core_competency\competency_framework;
use core_customfield\field_controller;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\competency_metadata_cache;
use local_dimensions\constants;
use local_dimensions\customfield\competency_handler;
use local_dimensions\helper;
use local_dimensions\local\appearance_writer;

/**
 * Web service: set, or clear, the appearance custom fields of up to 200 competencies of one framework.
 *
 * The bulk twin of the competency form's save ({@see \local_dimensions\form\competency_dynamic_form}): the same
 * capability in the framework's own context, the same field rules (the handler's can_edit() per competency, each
 * field controller's instance_form_validation(), the select's own option list), the same write
 * ({@see appearance_writer}, the handler's instance_form_save(), which fires competency_customfields_updated per
 * competency whose effective values changed) and the same cache invalidation. Everything is decided before the
 * transaction opens; the writes then go in one transaction, so a failure leaves every competency as it was.
 *
 * Cost: the handler is told each competency's context (the framework's, proved by one query), so its can_edit()
 * reads nothing per field; the editable fields are read once per competency for the guard and the validation; the
 * effective values before and after are one query each for the whole set. What remains per competency is core's
 * save (its data read, the field list it reloads after clearing its cache, the writes) and the audit log row, about
 * 18 statements by reading the code (set_competencies_appearance_test pins it under 30), so the bound of 200 keeps a
 * call near 4,000.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_competencies_appearance extends external_api {
    /** @var int The most competencies one call may name; the dock's selection is bounded by what is loaded. */
    public const int MAX_COMPETENCIES = 200;

    /**
     * @var array The appearance fields the call may write, by the name the client uses (a StructureNode field), and the
     *     custom field each one is. Images are not among them.
     */
    public const array FIELDS = [
        'bgcolor' => constants::CFIELD_CUSTOMBGCOLOR,
        'textcolor' => constants::CFIELD_CUSTOMTEXTCOLOR,
        'tag1' => constants::CFIELD_TAG1,
        'tag2' => constants::CFIELD_TAG2,
        'type' => constants::CFIELD_TYPE,
    ];

    /** @var string[] The fields that hold a hex colour; the others are selects. */
    private const array COLOURS = ['bgcolor', 'textcolor'];

    /** @var string[] What a field entry may ask: a value, or no value. */
    private const array ACTIONS = ['set', 'clear'];

    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'frameworkid' => new external_value(PARAM_INT, 'The framework every competency belongs to'),
            'competencyids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'A competency of the framework'),
                'The competencies to change, at most ' . self::MAX_COMPETENCIES
            ),
            'fields' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUM, 'bgcolor, textcolor, tag1, tag2 or type'),
                    'action' => new external_value(PARAM_ALPHA, 'set or clear'),
                    'value' => new external_value(
                        PARAM_RAW_TRIMMED,
                        'With set: a hex colour (#rgb or #rrggbb), or a select\'s option position (1 first)',
                        VALUE_DEFAULT,
                        ''
                    ),
                ]),
                'The fields to change; a field not named is left as it is'
            ),
        ]);
    }

    /**
     * Set or clear the named appearance fields of every named competency, all or nothing.
     *
     * @param int $frameworkid The framework.
     * @param array $competencyids The competencies, ints.
     * @param array $fields The fields, each with name, action and value.
     * @return array Keys: changed (ids whose effective values changed), competencies (id and the effective value of
     *     each field named, plain text).
     * @throws \invalid_parameter_exception When the call names too many or no competencies, an unknown field or
     *     action, an invalid value, or a competency that is not the framework's.
     * @throws \required_capability_exception When the caller does not manage competencies in the framework's context.
     */
    public static function execute(int $frameworkid, array $competencyids, array $fields): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'frameworkid' => $frameworkid,
            'competencyids' => $competencyids,
            'fields' => $fields,
        ]);
        $ids = array_values(array_unique(array_map('intval', $params['competencyids'])));
        if (count($ids) > self::MAX_COMPETENCIES) {
            throw new \invalid_parameter_exception('At most ' . self::MAX_COMPETENCIES . ' competencies may be changed at once.');
        }
        if (!$ids || !$params['fields']) {
            throw new \invalid_parameter_exception('Name at least one competency and one field.');
        }

        // The context is the framework's own, read from its row: nothing the client sends names a context.
        $framework = competency_framework::get_record(['id' => $params['frameworkid']]);
        if (!$framework) {
            throw new \invalid_parameter_exception('The framework does not exist.');
        }
        $context = $framework->get_context();
        self::validate_context($context);
        if (isguestuser()) {
            throw new \require_login_exception('Guests cannot change competencies.');
        }
        api::require_enabled();
        require_capability('moodle/competency:competencymanage', $context);

        $requested = self::requested_fields($params['fields']);
        $frameworkid = (int) $framework->get('id');
        self::require_members($frameworkid, $ids);

        $handler = competency_handler::create();
        $controllers = self::field_controllers($handler, array_keys($requested));
        $values = self::posted_values($requested, $controllers, count($ids));
        // Every competency is the framework's (above), so its context is the framework's: the handler's can_edit()
        // takes it from here instead of reading the competency and the framework for each field of each save.
        $handler->remember_edit_contexts(array_fill_keys($ids, $context));
        try {
            foreach ($ids as $id) {
                self::require_writable($handler, $controllers, $values, $id);
            }

            // Decided: now write, all or nothing.
            $before = self::effective_values($handler, $controllers, $ids);
            $writer = \core\di::get(appearance_writer::class);
            $transaction = $DB->start_delegated_transaction();
            try {
                foreach ($ids as $id) {
                    $record = (object) ['id' => $id];
                    foreach ($values as $name => $value) {
                        $record->{'customfield_' . self::FIELDS[$name]} = $value;
                    }
                    $writer->save($record);
                }
                // Read the set again before committing: a competency deleted since the check above would keep the
                // row just written for it, which core's deletion swept before this write existed.
                self::require_members($frameworkid, $ids);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                // Rethrows: the caller hears the failure, and no competency keeps a write.
                $transaction->rollback($e);
            }
        } finally {
            $handler->remember_edit_contexts([]);
        }

        // As the competency form does after its save: the card metadata the learner views read is read again.
        $after = self::effective_values($handler, $controllers, $ids);
        $changed = [];
        $competencies = [];
        foreach ($ids as $id) {
            competency_metadata_cache::invalidate_competency($id);
            if ($after[$id] !== $before[$id]) {
                $changed[] = $id;
            }
            $competencies[] = [
                'id' => $id,
                'fields' => array_map(
                    static fn(string $name, string $value): array => ['name' => $name, 'value' => $value],
                    array_keys($after[$id]),
                    array_values($after[$id])
                ),
            ];
        }
        return ['changed' => $changed, 'competencies' => $competencies];
    }

    /**
     * Refuse the call unless every id is a competency of the framework, in one query. A foreign id and a missing one
     * get the same answer, so the call tells nothing about competencies outside the framework.
     *
     * Asked before anything is decided and again inside the transaction, after the writes and before the commit.
     * Moodle's DML offers no portable row lock (no SELECT ... FOR UPDATE), so a deletion that commits after this
     * second read and before the commit is not seen: that window is the time a commit takes. Core's deletion then
     * sweeps nothing of ours (its transaction cannot see the rows), and the data rows written in that window outlive
     * their competency until the next sweep by instance id.
     *
     * @param int $frameworkid The framework.
     * @param array $ids The competencies, ints.
     * @return void
     * @throws \invalid_parameter_exception When one of them is not a competency of the framework.
     */
    private static function require_members(int $frameworkid, array $ids): void {
        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cid');
        $found = $DB->count_records_select(
            'competency',
            "id $insql AND competencyframeworkid = :frameworkid",
            $inparams + ['frameworkid' => $frameworkid]
        );
        if ((int) $found !== count($ids)) {
            throw new \invalid_parameter_exception('Every competency must belong to the framework.');
        }
    }

    /**
     * The posted field entries by name, each named once, with a known name and action.
     *
     * @param array $fields The posted entries.
     * @return array Map of name to ['action' => string, 'value' => string].
     * @throws \invalid_parameter_exception On an unknown name or action, or a name given twice.
     */
    private static function requested_fields(array $fields): array {
        $requested = [];
        foreach ($fields as $field) {
            $name = (string) $field['name'];
            if (!array_key_exists($name, self::FIELDS)) {
                throw new \invalid_parameter_exception('Unknown appearance field: ' . $name);
            }
            if (isset($requested[$name])) {
                throw new \invalid_parameter_exception('A field may be named once: ' . $name);
            }
            if (!in_array($field['action'], self::ACTIONS, true)) {
                throw new \invalid_parameter_exception('Unknown action: ' . $field['action']);
            }
            $requested[$name] = ['action' => (string) $field['action'], 'value' => (string) $field['value']];
        }
        return $requested;
    }

    /**
     * The custom field controller of each requested field, as the handler lists its fields.
     *
     * @param competency_handler $handler The competency handler.
     * @param array $names The requested names.
     * @return field_controller[] Keyed by name.
     * @throws \invalid_parameter_exception When a field is not provisioned on this site.
     */
    private static function field_controllers(competency_handler $handler, array $names): array {
        $byshortname = [];
        foreach ($handler->get_fields() as $field) {
            $byshortname[$field->get('shortname')] = $field;
        }
        $controllers = [];
        foreach ($names as $name) {
            $field = $byshortname[self::FIELDS[$name]] ?? null;
            if (!$field) {
                throw new \invalid_parameter_exception('The field is not available: ' . $name);
            }
            $controllers[$name] = $field;
        }
        return $controllers;
    }

    /**
     * The value each requested field is stored with: a set colour as helper::normalise_hex_color() reads it, a set
     * select's option position, and the field's empty value for a clear.
     *
     * The form offers a select's own options and nothing else (QuickForm drops a value outside them), so a position
     * outside the option list is refused here; position 0 is the form's empty choice, which is what clear stores.
     *
     * @param array $requested The requested fields ({@see requested_fields()}).
     * @param field_controller[] $controllers Their controllers, by name.
     * @param int $count How many competencies the call names.
     * @return array Map of name to the value stored.
     * @throws \invalid_parameter_exception On an invalid value, or one value for several competencies of a field that
     *     takes unique values.
     */
    private static function posted_values(array $requested, array $controllers, int $count): array {
        $values = [];
        foreach ($requested as $name => ['action' => $action, 'value' => $value]) {
            $colour = in_array($name, self::COLOURS, true);
            if ($action === 'clear') {
                $values[$name] = $colour ? '' : 0;
                continue;
            }
            if ($colour) {
                $hex = helper::normalise_hex_color($value);
                if ($hex === '') {
                    throw new \invalid_parameter_exception('Not a hex colour: ' . $name);
                }
                $values[$name] = $hex;
            } else {
                $options = helper::select_raw_options($controllers[$name]);
                if (!preg_match('/^[1-9]\d*$/', $value) || (int) $value > count($options)) {
                    throw new \invalid_parameter_exception('Not an option of the field: ' . $name);
                }
                $values[$name] = (int) $value;
            }
            // The form saves one competency at a time, so a field taking unique values refuses the second of two.
            if ($count > 1 && (int) $controllers[$name]->get_configdata_property('uniquevalues') === 1) {
                throw new \invalid_parameter_exception('The field takes a unique value per competency: ' . $name);
            }
        }
        return $values;
    }

    /**
     * Refuse the call unless the handler lets the caller edit every field for this competency and the field
     * controllers accept the values, as the form's validation asks them. The handler's editable fields are read once
     * and serve both.
     *
     * @param competency_handler $handler The competency handler.
     * @param field_controller[] $controllers The requested fields' controllers, by name.
     * @param array $values The values to store, by name.
     * @param int $id The competency.
     * @return void
     * @throws \invalid_parameter_exception When can_edit() refuses a field in the competency's own context, or a field
     *     controller refuses a value.
     */
    private static function require_writable(competency_handler $handler, array $controllers, array $values, int $id): void {
        $editable = $handler->get_editable_fields($id);
        $data = ['id' => $id];
        foreach ($controllers as $name => $field) {
            if (!isset($editable[(int) $field->get('id')])) {
                throw new \invalid_parameter_exception('The field cannot be edited for this competency: ' . $name);
            }
            $data['customfield_' . self::FIELDS[$name]] = $values[$name];
        }
        $errors = helper::validate_present_customfields($handler, $data, [], $editable);
        if ($errors) {
            throw new \invalid_parameter_exception(implode(' ', array_map('strval', $errors)));
        }
    }

    /**
     * The effective value of each requested field of every competency, plain text: a colour as stored, a select's
     * option label, '' for none. One query for the set.
     *
     * @param competency_handler $handler The competency handler.
     * @param field_controller[] $controllers The requested fields' controllers, by name.
     * @param array $ids The competencies.
     * @return array Map of competency id to (map of name to value, in the order of $controllers).
     */
    private static function effective_values(competency_handler $handler, array $controllers, array $ids): array {
        $byid = [];
        foreach ($controllers as $field) {
            $byid[(int) $field->get('id')] = $field;
        }
        $data = \core_customfield\api::get_instances_fields_data(
            $byid,
            $ids,
            true,
            $handler->get_component(),
            $handler->get_area(),
            $handler->get_itemid()
        );
        $plain = ['context' => \core\context\system::instance(), 'escape' => false];
        $values = [];
        foreach ($ids as $id) {
            foreach ($controllers as $name => $field) {
                $value = $data[$id][(int) $field->get('id')]?->get_value();
                if (in_array($name, self::COLOURS, true)) {
                    $values[$id][$name] = self::plain_text((string) $value);
                    continue;
                }
                $label = helper::select_raw_options($field)[(int) $value - 1] ?? '';
                $values[$id][$name] = $label === '' ? '' : self::plain_text(format_string($label, true, $plain));
            }
        }
        return $values;
    }

    /**
     * Text a PARAM_TEXT return field accepts whatever the site's settings, and the client can show as plain text.
     *
     * format_string() strips every tag only while formatstringstriptags is on; off, it returns cleaned HTML with its
     * entities, and the response's own check (clean_param() must leave the value unchanged) would refuse a label
     * holding a tag after the transaction committed. So entities are decoded and the text is cleaned as PARAM_TEXT
     * until cleaning changes nothing; a label that never settles is answered empty, never as an error.
     *
     * @param string $text The formatted text.
     * @return string The plain text.
     */
    private static function plain_text(string $text): string {
        $text = clean_param(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PARAM_TEXT);
        for ($pass = 0; $pass < 3; $pass++) {
            $clean = clean_param($text, PARAM_TEXT);
            if ($clean === $text) {
                return $text;
            }
            $text = $clean;
        }
        return '';
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'changed' => new external_multiple_structure(
                new external_value(PARAM_INT, 'A competency whose effective values changed')
            ),
            'competencies' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'The competency'),
                    'fields' => new external_multiple_structure(
                        new external_single_structure([
                            'name' => new external_value(PARAM_ALPHANUM, 'The field named in the call'),
                            'value' => new external_value(
                                PARAM_TEXT,
                                'Its effective value, plain text: the hex colour, the option\'s label, or empty for none'
                            ),
                        ])
                    ),
                ])
            ),
        ]);
    }
}
