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
use local_dimensions\constants;
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

    /** @var array Rule section display data */
    protected $rulecontext = [];

    /**
     * Form definition.
     *
     */
    public function definition() {
        global $OUTPUT, $PAGE;

        $mform = $this->_form;

        // Get custom data.
        $this->competency = $this->_customdata['competency'] ?? null;
        $this->framework = $this->_customdata['framework'];
        $this->parent = $this->_customdata['parent'] ?? null;
        $this->pagecontextid = $this->_customdata['pagecontextid'] ?? 0;
        $this->rulecontext = $this->_customdata['rulecontext'] ?? [];

        // Hidden fields.
        $mform->addElement('hidden', 'competencyframeworkid');
        $mform->setType('competencyframeworkid', PARAM_INT);
        $mform->setDefault('competencyframeworkid', $this->framework->get('id'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $this->competency ? $this->competency->get('id') : 0);

        $this->add_section_open(
            $mform,
            'sec-basic',
            get_string('editcompetency_section_basic', 'local_dimensions'),
            get_string('editcompetency_section_basic_desc', 'local_dimensions')
        );

        $mform->addElement('static', 'frameworkdesc', get_string('competencyframework', 'tool_lp'),
            s($this->framework->get('shortname')));

        $mform->addElement('hidden', 'parentid', '', ['id' => 'tool_lp_parentcompetency']);
        $mform->setType('parentid', PARAM_INT);
        $mform->setDefault('parentid', $this->parent ? $this->parent->get('id') : 0);

        $parentlevel = $this->parent ? $this->parent->get_level() : 0;
        $parentname = $this->parent ? $this->parent->get('shortname') : get_string('competencyframeworkroot', 'tool_lp');
        if ($this->competency) {
            $taxonomy = $this->framework->get_taxonomy($parentlevel);
            $parentlabel = get_string('taxonomy_parent_' . $taxonomy, 'tool_lp');
        } else {
            $parentlabel = get_string('parentcompetency', 'tool_lp');
        }

        $editaction = '';
        if (!$this->competency) {
            $icon = $OUTPUT->pix_icon('t/editinline', get_string('parentcompetency_edit', 'tool_lp'));
            $editaction = $OUTPUT->action_link('#', $icon, null, ['id' => 'id_parentcompetencybutton']);
        }
        $mform->addElement('static', 'parentdesc', $parentlabel,
            $OUTPUT->render_from_template('local_dimensions/edit_competency_parent', [
                'parentname' => $parentname,
                'editaction' => $editaction,
                'haseditaction' => $editaction !== '',
            ]));

        $currentlevel = $this->competency ? $this->competency->get_level() : $parentlevel + 1;
        $taxonomy = $this->framework->get_taxonomy($currentlevel);
        $taxonomylabel = get_string('taxonomy_' . $taxonomy, 'core_competency');
        $mform->addElement('static', 'taxonomydesc', get_string('editcompetency_taxonomy', 'local_dimensions'),
            s($taxonomylabel));

        if (!$this->competency) {
            $PAGE->requires->js_call_amd('tool_lp/parentcompetency_form', 'init', [
                '#id_parentcompetencybutton',
                '#tool_lp_parentcompetency',
                '#id_parentdesc',
                $this->framework->get('id'),
                $this->pagecontextid,
            ]);
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

        $this->add_section_close($mform);

        $this->add_section_open(
            $mform,
            'sec-eval',
            get_string('editcompetency_section_evaluation', 'local_dimensions'),
            get_string('editcompetency_section_evaluation_desc', 'local_dimensions')
        );

        $scales = [null => get_string('inheritfromframework', 'tool_lp')] + \get_scales_menu();
        $scaleid = $mform->addElement('select', 'scaleid', get_string('scale', 'tool_lp'), $scales);
        $mform->setType('scaleid', PARAM_INT);
        $mform->addHelpButton('scaleid', 'scale', 'tool_lp');

        $mform->addElement('hidden', 'scaleconfiguration', '', ['id' => 'tool_lp_scaleconfiguration']);
        $mform->setType('scaleconfiguration', PARAM_RAW);
        $mform->addElement('button', 'scaleconfigbutton', get_string('configurescale', 'tool_lp'));
        $PAGE->requires->js_call_amd('tool_lp/scaleconfig', 'init', [
            '#id_scaleid',
            '#tool_lp_scaleconfiguration',
            '#id_scaleconfigbutton',
        ]);

        if ($this->competency && $this->competency->has_user_competencies()) {
            $scaleid->updateAttributes(['disabled' => 'disabled']);
            $mform->setConstant('scaleid', $this->competency->get('scaleid'));
        }

        $this->add_section_close($mform);

        $this->add_section_open(
            $mform,
            'sec-rule',
            get_string('editcompetency_section_rule', 'local_dimensions'),
            get_string('editcompetency_section_rule_desc', 'local_dimensions')
        );
        $this->add_rule_summary($mform);
        $this->add_section_close($mform);

        $this->add_section_open(
            $mform,
            'sec-fields',
            get_string('customfields', 'local_dimensions'),
            get_string('editcompetency_section_fields_desc', 'local_dimensions')
        );

        // Custom fields section.
        $handler = competency_handler::create();
        $instanceid = $this->competency ? $this->competency->get('id') : 0;
        $handler->instance_form_definition($mform, $instanceid, '');

        $mform->addElement('html', $OUTPUT->render_from_template('local_dimensions/edit_competency_section_note', [
            'content' => get_string('editcompetency_customfields_note', 'local_dimensions'),
        ]));
        $this->add_section_close($mform);

        // Add SCSS frontend validation and hide format selector.
        if (get_config('local_dimensions', 'enablecustomscss')) {
            $PAGE->requires->js_call_amd('local_dimensions/scss_validation', 'init', [
                [
                    'fieldname' => constants::CFIELD_CUSTOMSCSS,
                    'closingbracewithoutopen' => get_string('customscss_js_closingbracewithoutopen', 'local_dimensions'),
                    'closingparenwithoutopen' => get_string('customscss_js_closingparenwithoutopen', 'local_dimensions'),
                    'unbalancedbraces' => get_string('customscss_js_unbalancedbraces', 'local_dimensions'),
                    'unbalancedparentheses' => get_string('customscss_js_unbalancedparentheses', 'local_dimensions'),
                    'punctuationwarning' => get_string('customscss_js_punctuationwarning', 'local_dimensions'),
                    'errortitle' => get_string('customscss_js_errortitle', 'local_dimensions'),
                    'warningtitle' => get_string('customscss_js_warningtitle', 'local_dimensions'),
                    'saveanyway' => get_string('customscss_js_saveanyway', 'local_dimensions'),
                    'goback' => get_string('customscss_js_goback', 'local_dimensions'),
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
            $defaultdata->parentid = $this->competency->get('parentid');
            $defaultdata->scaleid = $this->competency->get('scaleid');
            $defaultdata->scaleconfiguration = $this->competency->get('scaleconfiguration');

            // Load custom field data.
            $handler->instance_form_before_set_data_with_image($defaultdata);

            $this->set_data($defaultdata);
        }
    }

    /**
     * Open a visual form section.
     *
     * @param \MoodleQuickForm $mform The form object.
     * @param string $id Section anchor id.
     * @param string $title Section title.
     * @param string $description Section description.
     */
    protected function add_section_open(\MoodleQuickForm $mform, string $id, string $title, string $description): void {
        global $OUTPUT;

        $mform->addElement('html', $OUTPUT->render_from_template('local_dimensions/edit_competency_section_open', [
            'id' => $id,
            'title' => $title,
            'description' => $description,
        ]));
    }

    /**
     * Close a visual form section.
     *
     * @param \MoodleQuickForm $mform The form object.
     */
    protected function add_section_close(\MoodleQuickForm $mform): void {
        global $OUTPUT;

        $mform->addElement('html', $OUTPUT->render_from_template('local_dimensions/edit_competency_section_close', []));
    }

    /**
     * Add the native rule summary and action area.
     *
     * @param \MoodleQuickForm $mform The form object.
     */
    protected function add_rule_summary(\MoodleQuickForm $mform): void {
        global $OUTPUT;

        $status = $this->rulecontext['status'] ?? get_string('managecompetencies_norule', 'local_dimensions');
        $summary = $this->rulecontext['summary'] ?? get_string('editcompetency_rule_new_summary', 'local_dimensions');
        $detail = $this->rulecontext['detail'] ?? '';
        $canconfigure = !empty($this->rulecontext['canconfigure']);

        $mform->addElement('html', $OUTPUT->render_from_template('local_dimensions/edit_competency_rule_summary', [
            'status' => $status,
            'summary' => $summary,
            'detail' => $detail,
            'hasdetail' => $detail !== '',
            'canconfigure' => $canconfigure,
            'competencyid' => (int)($this->rulecontext['competencyid'] ?? 0),
        ]));
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
            if (empty($data->scaleid)) {
                $data->scaleid = null;
                $data->scaleconfiguration = null;
            }

            // Keep custom SCSS in plain format to avoid accidental editor format changes.
            $editorprop = 'customfield_' . constants::CFIELD_CUSTOMSCSS . '_editor';
            $plainprop  = 'customfield_' . constants::CFIELD_CUSTOMSCSS;
            if (isset($data->$editorprop) && is_array($data->$editorprop)) {
                $data->{$editorprop}['format'] = FORMAT_PLAIN;
            } else if (isset($data->$editorprop) && is_object($data->$editorprop)) {
                $data->$editorprop->format = FORMAT_PLAIN;
            } else if (isset($data->$plainprop) && is_array($data->$plainprop)) {
                $data->{$plainprop}['format'] = FORMAT_PLAIN;
            } else if (isset($data->$plainprop) && is_object($data->$plainprop)) {
                $data->$plainprop->format = FORMAT_PLAIN;
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
            'customfield_' . constants::CFIELD_CUSTOMSCSS . '_editor',
            'customfield_' . constants::CFIELD_CUSTOMSCSS,
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
