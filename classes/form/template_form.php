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
 * Learning plan template form with native fields and custom fields support.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;
use core_competency\template;
use local_dimensions\customfield\lp_handler;

/**
 * Learning plan template form with native fields and custom fields support.
 *
 * This form combines the native template fields from tool_lp with custom fields
 * from local_dimensions, allowing full template editing in a single form.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_form extends moodleform {
    /** @var template The template being edited */
    protected $template = null;

    /** @var \context The context */
    protected $context = null;

    /**
     * Form definition.
     *
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // Get custom data.
        $this->template = $this->_customdata['template'];
        $this->context = $this->_customdata['context'] ?? $this->template->get_context();

        // Hidden field for template ID.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $this->template->get('id'));

        // Hidden field for context ID.
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $this->context->id);

        // General section - native template fields.
        $mform->addElement('header', 'generalhdr', get_string('general'));

        // Short name (editable).
        $mform->addElement('text', 'shortname', get_string('shortname', 'tool_lp'), 'maxlength="100"');
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addRule('shortname', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');
        $mform->setDefault('shortname', $this->template->get('shortname'));

        // Description (editable).
        $mform->addElement('editor', 'description', get_string('description', 'tool_lp'), ['rows' => 4]);
        $mform->setType('description', PARAM_CLEANHTML);

        // Visible (editable).
        $mform->addElement('selectyesno', 'visible', get_string('visible', 'tool_lp'));
        $mform->setDefault('visible', $this->template->get('visible'));
        $mform->addHelpButton('visible', 'visible', 'tool_lp');

        // Due date (editable).
        $mform->addElement('date_time_selector', 'duedate', get_string('duedate', 'tool_lp'), ['optional' => true]);
        $mform->addHelpButton('duedate', 'duedate', 'tool_lp');

        // Context display (read-only).
        $mform->addElement('static', 'contextdisplay', get_string('category', 'tool_lp'));
        $mform->setDefault('contextdisplay', $this->context->get_context_name(false));

        // Custom fields section.
        $handler = lp_handler::create();
        $instanceid = $this->template->get('id');
        $handler->instance_form_definition($mform, $instanceid);

        // Add SCSS frontend validation and hide format selector.
        if (get_config('local_dimensions', 'enablecustomscss')) {
            global $PAGE;
            $PAGE->requires->js_call_amd('local_dimensions/scss_validation', 'init', [
                [
                    'closingbracewithoutopen' => get_string('customscss_js_closingbracewithoutopen', 'local_dimensions'),
                    'closingparenwithoutopen' => get_string('customscss_js_closingparenwithoutopen', 'local_dimensions'),
                    'unbalancedbraces' => get_string('customscss_js_unbalancedbraces', 'local_dimensions'),
                    'unbalancedparentheses' => get_string('customscss_js_unbalancedparentheses', 'local_dimensions'),
                    'punctuationwarning' => get_string('customscss_js_punctuationwarning', 'local_dimensions'),
                ],
            ]);
        }

        // Action buttons.
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Form definition after data.
     *
     */
    public function definition_after_data() {
        $mform = $this->_form;

        // Set description default value with format.
        $description = $this->template->get('description');
        $descriptionformat = $this->template->get('descriptionformat');
        $mform->setDefault('description', [
            'text' => $description,
            'format' => $descriptionformat,
        ]);

        // Set due date.
        $duedate = $this->template->get('duedate');
        if ($duedate) {
            $mform->setDefault('duedate', $duedate);
        }

        // Load custom field data.
        $handler = lp_handler::create();
        $defaultdata = new \stdClass();
        $defaultdata->id = $this->template->get('id');
        $handler->instance_form_before_set_data_with_image($defaultdata);

        // Apply custom field defaults.
        foreach ((array) $defaultdata as $key => $value) {
            if ($key !== 'id' && $mform->elementExists($key)) {
                $mform->setDefault($key, $value);
            }
        }
    }

    /**
     * Validate the form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate shortname is unique within context.
        $shortname = $data['shortname'] ?? '';
        if (!empty($shortname)) {
            $existing = template::get_record([
                'shortname' => $shortname,
                'contextid' => $data['contextid'],
            ]);
            if ($existing && $existing->get('id') != $data['id']) {
                $errors['shortname'] = get_string('shortnametaken', 'tool_lp');
            }
        }

        // Validate custom SCSS if the feature is enabled.
        if (get_config('local_dimensions', 'enablecustomscss')) {
            [$scssvalue, $errorfield] = self::extract_submitted_scss($data);
            if ($scssvalue !== '' && trim($scssvalue) !== '') {
                $result = \local_dimensions\scss_manager::validate_scss($scssvalue);
                if ($result !== true) {
                    $errors[$errorfield] = $result;
                }
            }
        }

        return $errors;
    }

    /**
     * Get submitted data, including custom field data.
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();

        if ($data) {
            // Keep custom SCSS in plain format to avoid accidental editor format changes.
            if (isset($data->customfield_customscss_editor) && is_array($data->customfield_customscss_editor)) {
                $data->customfield_customscss_editor['format'] = FORMAT_PLAIN;
            } else if (isset($data->customfield_customscss_editor) && is_object($data->customfield_customscss_editor)) {
                $data->customfield_customscss_editor->format = FORMAT_PLAIN;
            } else if (isset($data->customfield_customscss) && is_array($data->customfield_customscss)) {
                $data->customfield_customscss['format'] = FORMAT_PLAIN;
            } else if (isset($data->customfield_customscss) && is_object($data->customfield_customscss)) {
                $data->customfield_customscss->format = FORMAT_PLAIN;
            }

            // Process custom field data.
            $handler = lp_handler::create();
            $handler->instance_form_definition_after_data($this->_form, $data->id ?? 0);
        }

        return $data;
    }

    /**
     * Extract submitted custom SCSS data from possible form field structures.
     *
     * @param array $data Form data.
     * @return array [0 => scss value, 1 => field name used for error mapping]
     */
    protected static function extract_submitted_scss(array $data): array {
        $fieldcandidates = [
            'customfield_customscss_editor',
            'customfield_customscss',
        ];

        foreach ($fieldcandidates as $fieldname) {
            if (!array_key_exists($fieldname, $data)) {
                continue;
            }

            $value = $data[$fieldname];
            if (is_array($value)) {
                if (array_key_exists('text', $value)) {
                    return [(string) $value['text'], $fieldname];
                }
                if (array_key_exists('value', $value)) {
                    return [(string) $value['value'], $fieldname];
                }
                return ['', $fieldname];
            }

            if (is_object($value)) {
                if (property_exists($value, 'text')) {
                    return [(string) $value->text, $fieldname];
                }
                if (property_exists($value, 'value')) {
                    return [(string) $value->value, $fieldname];
                }
                return ['', $fieldname];
            }

            if (is_string($value)) {
                return [$value, $fieldname];
            }

            if (is_scalar($value)) {
                return [(string) $value, $fieldname];
            }

            return ['', $fieldname];
        }

        return ['', $fieldcandidates[0]];
    }
}
