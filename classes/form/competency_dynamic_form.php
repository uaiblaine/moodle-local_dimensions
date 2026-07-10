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
 * Modal (dynamic) form to create or edit a competency — for the Competency hub.
 *
 * Runs inside a modal (core_form/modalform) with no page reload; the plugin's
 * competency custom fields are rendered and saved by competency_handler. Rule
 * configuration is handled separately by the Structure tab's rule_config modal.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\form;

use core\context\system as context_system;
use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use local_dimensions\constants;
use local_dimensions\customfield\competency_handler;
use local_dimensions\helper;

/**
 * Create/edit competency in a modal.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_dynamic_form extends \core_form\dynamic_form {
    /**
     * Get the competency framework id from the request.
     *
     * @return int Framework id from the request.
     */
    private function get_frameworkid(): int {
        return $this->optional_param('competencyframeworkid', 0, PARAM_INT);
    }

    /**
     * Get the competency id from the request.
     *
     * @return int Competency id (0 when creating).
     */
    private function get_competencyid(): int {
        return $this->optional_param('id', 0, PARAM_INT);
    }

    /**
     * Parent competency options for the framework (root + competencies), excluding the
     * competency being edited and its descendants so it cannot be moved under its own child.
     *
     * @param int $frameworkid
     * @param int $excludeid Competency being edited (0 when creating).
     * @return array<int, string>
     */
    private function get_parent_options(int $frameworkid, int $excludeid): array {
        $options = [0 => get_string('competencyframeworkroot', 'tool_lp')];
        if ($frameworkid <= 0) {
            return $options;
        }
        foreach (competency::get_records(['competencyframeworkid' => $frameworkid], 'path, sortorder') as $record) {
            $id = (int) $record->get('id');
            $path = (string) $record->get('path');
            if ($id === $excludeid) {
                continue;
            }
            if ($excludeid > 0 && strpos($path, '/' . $excludeid . '/') !== false) {
                continue;
            }
            $depth = max(0, count(array_filter(explode('/', trim($path, '/')), 'strlen')) - 1);
            $options[$id] = str_repeat('— ', $depth) . format_string($record->get('shortname'));
        }
        return $options;
    }

    /**
     * The submission happens in the framework's context.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $framework = competency_framework::get_record(['id' => $this->get_frameworkid()]);
        return $framework ? $framework->get_context() : context_system::instance();
    }

    /**
     * Only competency managers may submit.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/competency:competencymanage', $this->get_context_for_dynamic_submission());
    }

    /**
     * Page URL used while the form is rendered or submitted via AJAX.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/dimensions/central.php', ['frameworkid' => $this->get_frameworkid()]);
    }

    /**
     * Form fields: basic info, evaluation scale, and the plugin custom fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'competencyframeworkid');
        $mform->setType('competencyframeworkid', PARAM_INT);
        $mform->setDefault('competencyframeworkid', $this->get_frameworkid());
        $mform->addElement(
            'select',
            'parentid',
            get_string('parentcompetency', 'tool_lp'),
            $this->get_parent_options($this->get_frameworkid(), $this->get_competencyid())
        );
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('text', 'shortname', get_string('shortname'), ['maxlength' => 100]);
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addRule('shortname', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        $mform->addElement('text', 'idnumber', get_string('idnumber'), ['maxlength' => 100]);
        $mform->setType('idnumber', PARAM_RAW);
        $mform->addRule('idnumber', null, 'required', null, 'client');

        $mform->addElement('editor', 'description', get_string('description'), ['rows' => 4]);
        $mform->setType('description', PARAM_CLEANHTML);

        // Fixed element ids: dynamic_form sets data-random-ids, which appends a random suffix
        // to every auto-generated id. tool_lp/scaleconfig binds to these exact selectors, so the
        // select and button must carry explicit ids or the dialogue trigger never binds.
        $scales = [null => get_string('inheritfromframework', 'tool_lp')] + \get_scales_menu();
        $mform->addElement('select', 'scaleid', get_string('scale', 'tool_lp'), $scales, ['id' => 'id_scaleid_central']);
        $mform->setType('scaleid', PARAM_INT);
        $mform->addHelpButton('scaleid', 'scale', 'tool_lp');

        $mform->addElement('hidden', 'scaleconfiguration', '', ['id' => 'tool_lp_scaleconfiguration_central']);
        $mform->setType('scaleconfiguration', PARAM_RAW);
        $mform->addElement(
            'button',
            'scaleconfigbutton',
            get_string('configurescale', 'tool_lp'),
            ['id' => 'id_scaleconfigbutton_central']
        );

        // Plugin custom fields (the core category headers label them; no extra plugin heading).
        competency_handler::create()->instance_form_definition($mform, $this->get_competencyid());

        // Explain the competency -> plan -> global cascade to the editor.
        $mform->addElement(
            'static',
            'local_dimensions_cascadehelp',
            '',
            get_string('cascade_help_competency', 'local_dimensions')
        );
    }

    /**
     * Attach the scale-configuration dialogue JS (tool_lp/scaleconfig).
     *
     * Requested here rather than in definition() for timing: definition() runs in the
     * moodleform constructor, which core_form\external\dynamic_form invokes BEFORE it calls
     * $PAGE->start_collecting_javascript_requirements(). A js_call_amd there is queued on the
     * page's normal requirements and never reaches the modal. definition_after_data() runs
     * during render() — inside the collection window — so get_end_code() captures it and
     * core_form/modalform executes it after inserting the body.
     *
     * The selectors below are the fixed ids set in definition() (see the data-random-ids note);
     * scaleconfig binds the dialogue trigger to these exact ids.
     */
    public function definition_after_data() {
        global $PAGE;

        parent::definition_after_data();

        // SCSS is plain text: pin its editor to FORMAT_PLAIN so it never opens as a rich editor.
        helper::force_customscss_plain($this->_form);

        $PAGE->requires->js_call_amd('tool_lp/scaleconfig', 'init', [
            '#id_scaleid_central',
            '#tool_lp_scaleconfiguration_central',
            '#id_scaleconfigbutton_central',
        ]);

        // Live swatch next to the bg/text colour custom fields (same timing window).
        $PAGE->requires->js_call_amd('local_dimensions/central/colour_swatch', 'init', [
            constants::CFIELD_CUSTOMBGCOLOR,
            constants::CFIELD_CUSTOMTEXTCOLOR,
        ]);

        // Real-time WCAG contrast panel beside the same two colour inputs.
        $PAGE->requires->js_call_amd('local_dimensions/central/contrast', 'init', [
            constants::CFIELD_CUSTOMBGCOLOR,
            constants::CFIELD_CUSTOMTEXTCOLOR,
        ]);
    }

    /**
     * Load existing values (and custom field data) when editing.
     */
    public function set_data_for_dynamic_submission(): void {
        $id = $this->get_competencyid();

        $data = (object) [
            'id' => $id,
            'competencyframeworkid' => $this->get_frameworkid(),
            'parentid' => $this->optional_param('parentid', 0, PARAM_INT),
        ];

        if ($id > 0 && ($competency = competency::get_record(['id' => $id]))) {
            $data->shortname = $competency->get('shortname');
            $data->idnumber = $competency->get('idnumber');
            $data->description = [
                'text' => $competency->get('description'),
                'format' => $competency->get('descriptionformat'),
            ];
            $data->scaleid = $competency->get('scaleid');
            $data->scaleconfiguration = $competency->get('scaleconfiguration');
            $data->parentid = (int) $competency->get('parentid');
            competency_handler::create()->instance_form_before_set_data_with_image($data);
        }

        $this->set_data($data);
    }

    /**
     * Create or update the competency and persist its custom fields.
     *
     * @return array{competencyid: int}
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $id = (int) ($data->id ?? 0);
        $submittedparent = (int) ($data->parentid ?? 0);

        // Keep the original parent for an edited competency so update_competency does not
        // move it; reparenting is done explicitly via set_parent_competency below.
        $originalparent = $submittedparent;
        if ($id > 0 && ($existing = competency::get_record(['id' => $id]))) {
            $originalparent = (int) $existing->get('parentid');
        }

        $record = new \stdClass();
        $record->shortname = $data->shortname;
        $record->idnumber = $data->idnumber;
        $record->description = $data->description['text'] ?? '';
        $record->descriptionformat = $data->description['format'] ?? FORMAT_HTML;
        $record->competencyframeworkid = (int) $data->competencyframeworkid;
        $record->parentid = $id > 0 ? $originalparent : $submittedparent;
        $record->scaleid = !empty($data->scaleid) ? (int) $data->scaleid : null;
        $record->scaleconfiguration = !empty($data->scaleid) ? ($data->scaleconfiguration ?? null) : null;

        if ($id > 0) {
            $record->id = $id;
            api::update_competency($record);
            $competencyid = $id;
            if ($submittedparent !== $originalparent) {
                api::set_parent_competency($competencyid, $submittedparent);
            }
        } else {
            $competencyid = (int) api::create_competency($record)->get('id');
        }

        $data->id = $competencyid;
        competency_handler::create()->instance_form_save_with_image($data, $id <= 0, $competencyid);

        \local_dimensions\competency_metadata_cache::invalidate_competency($competencyid);
        if (get_config('local_dimensions', 'enablecustomscss')) {
            \local_dimensions\scss_manager::invalidate_cache($competencyid, 'competency');
        }

        return ['competencyid' => $competencyid];
    }

    /**
     * Validate id number uniqueness within the framework.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $existing = competency::get_record([
            'competencyframeworkid' => $data['competencyframeworkid'],
            'idnumber' => $data['idnumber'],
        ]);
        if ($existing && (int) $existing->get('id') !== (int) ($data['id'] ?? 0)) {
            $errors['idnumber'] = get_string('idnumberexists');
        }

        // Block saving invalid custom SCSS (parity with the plan modal and the legacy form).
        $errors = array_merge($errors, helper::validate_customscss($data));

        return $errors;
    }
}
