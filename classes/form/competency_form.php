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
 * Custom competency form with custom fields support.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;
use core_competency\competency;
use core_competency\competency_framework;
use local_dimensions\customfield\competency_handler;

/**
 * Custom competency form with custom fields support.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_form extends moodleform {
    /** @var competency|null The competency being edited */
    protected $competency = null;

    /** @var competency_framework The competency framework */
    protected $framework = null;

    /** @var competency|null The parent competency */
    protected $parent = null;

    /** @var int Page context ID */
    protected $pagecontextid = 0;

    /**
     * Form definition.
     *
     */
    public function definition() {
        $mform = $this->_form;

        // Get custom data.
        $this->competency = $this->_customdata['competency'] ?? null;
        $this->framework = $this->_customdata['framework'];
        $this->parent = $this->_customdata['parent'] ?? null;
        $this->pagecontextid = $this->_customdata['pagecontextid'] ?? 0;

        // Hidden fields.
        $mform->addElement('hidden', 'competencyframeworkid');
        $mform->setType('competencyframeworkid', PARAM_INT);
        $mform->setDefault('competencyframeworkid', $this->framework->get('id'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $this->competency ? $this->competency->get('id') : 0);

        if ($this->parent) {
            $mform->addElement('hidden', 'parentid');
            $mform->setType('parentid', PARAM_INT);
            $mform->setDefault('parentid', $this->parent->get('id'));
        }

        // Short name.
        $mform->addElement('text', 'shortname', get_string('shortname'), ['maxlength' => 100]);
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addRule('shortname', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        // ID Number.
        $mform->addElement('text', 'idnumber', get_string('idnumber'), ['maxlength' => 100]);
        $mform->setType('idnumber', PARAM_RAW);
        $mform->addRule('idnumber', null, 'required', null, 'client');
        $mform->addRule('idnumber', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        // Description.
        $mform->addElement('editor', 'description', get_string('description'), ['rows' => 4]);
        $mform->setType('description', PARAM_CLEANHTML);

        // Scale configuration (if framework allows).
        $scaleid = $this->framework->get('scaleid');
        if ($scaleid) {
            $scale = \grade_scale::fetch(['id' => $scaleid]);
            if ($scale) {
                $scaleitems = $scale->load_items();
                $scaleconfig = [];
                $scaleconfig[0] = get_string('inheritfromframework', 'tool_lp');
                foreach ($scaleitems as $key => $item) {
                    $scaleconfig[$key + 1] = $item;
                }
                $mform->addElement('select', 'scaleid', get_string('scale'), [
                    0 => get_string('inheritfromframework', 'tool_lp'),
                ]);
                $mform->setDefault('scaleid', 0);
            }
        }

        // Custom fields section.
        $handler = competency_handler::create();
        $instanceid = $this->competency ? $this->competency->get('id') : 0;
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
        $this->add_action_buttons(true, $this->competency ? get_string('savechanges') : get_string('add'));

        // Set default values from existing competency.
        if ($this->competency) {
            $defaultdata = new \stdClass();
            $defaultdata->id = $this->competency->get('id');
            $defaultdata->shortname = $this->competency->get('shortname');
            $defaultdata->idnumber = $this->competency->get('idnumber');
            $defaultdata->description = [
                'text' => $this->competency->get('description'),
                'format' => $this->competency->get('descriptionformat'),
            ];
            $defaultdata->competencyframeworkid = $this->competency->get('competencyframeworkid');

            // Load custom field data.
            $handler->instance_form_before_set_data_with_image($defaultdata);

            $this->set_data($defaultdata);
        }
    }

    /**
     * Extra validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check ID number uniqueness within the framework.
        $frameworkid = $data['competencyframeworkid'];
        $idnumber = $data['idnumber'];
        $id = $data['id'] ?? 0;

        $existing = competency::get_record([
            'competencyframeworkid' => $frameworkid,
            'idnumber' => $idnumber,
        ]);

        if ($existing && $existing->get('id') != $id) {
            $errors['idnumber'] = get_string('idnumberexists');
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
            $handler = competency_handler::create();
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
