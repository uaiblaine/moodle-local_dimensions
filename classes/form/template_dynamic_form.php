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
 * Modal (dynamic) form to create or edit a learning plan template — for the Competency hub.
 *
 * Runs inside a modal (core_form/modalform) with no page reload. Custom (lp)
 * fields are rendered and saved by lp_handler.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\form;

use core_competency\api;
use core_competency\template;
use local_dimensions\constants;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Create/edit a learning plan template in a modal.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_dynamic_form extends \core_form\dynamic_form {
    /**
     * Get the template id from the request.
     *
     * @return int Template id (0 when creating).
     */
    private function get_templateid(): int {
        return $this->optional_param('id', 0, PARAM_INT);
    }

    /**
     * Get the context id from the request.
     *
     * @return int Context id from the request (used on the create flow).
     */
    private function get_contextid(): int {
        return $this->optional_param('contextid', 0, PARAM_INT);
    }

    /**
     * Submission context: the template's own context when editing, else the requested context.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $id = $this->get_templateid();
        if ($id > 0 && ($template = template::get_record(['id' => $id]))) {
            return $template->get_context();
        }
        $contextid = $this->get_contextid();
        if ($contextid > 0) {
            try {
                return \context::instance_by_id($contextid);
            } catch (\moodle_exception $e) {
                return \context_system::instance();
            }
        }
        return \context_system::instance();
    }

    /**
     * Only template managers may submit.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/competency:templatemanage', $this->get_context_for_dynamic_submission());
    }

    /**
     * Page URL used while the form is rendered or submitted via AJAX.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/dimensions/central.php');
    }

    /**
     * Form fields: basic info, publication, and the plugin (lp) custom fields.
     */
    public function definition() {
        $mform = $this->_form;
        $context = $this->get_context_for_dynamic_submission();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $context->id);

        $mform->addElement('text', 'shortname', get_string('shortname', 'tool_lp'), ['maxlength' => 100]);
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addRule('shortname', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        $mform->addElement('editor', 'description', get_string('description', 'tool_lp'), ['rows' => 4]);
        $mform->setType('description', PARAM_CLEANHTML);

        $mform->addElement('selectyesno', 'visible', get_string('visible', 'tool_lp'));
        $mform->setDefault('visible', 1);
        $mform->addHelpButton('visible', 'visible', 'tool_lp');

        $mform->addElement('date_time_selector', 'duedate', get_string('duedate', 'tool_lp'), ['optional' => true]);
        $mform->addHelpButton('duedate', 'duedate', 'tool_lp');

        // Plugin custom fields. Pass '' to suppress the handler's page-level heading: inside the
        // modal the core category headers already label the fields (parity with the competency modal).
        lp_handler::create()->instance_form_definition($mform, $this->get_templateid(), '');
    }

    /**
     * Apply the lp handler's after-data field tweaks (runs at render and at submit).
     *
     * In a dynamic_form this is the correct place — get_data() only runs on submit, so the
     * handler's after-data customisations would never apply when the modal is first rendered.
     */
    public function definition_after_data() {
        global $PAGE;

        parent::definition_after_data();
        lp_handler::create()->instance_form_definition_after_data($this->_form, $this->get_templateid());

        // SCSS is plain text: pin its editor to FORMAT_PLAIN so it never opens as a rich editor.
        helper::force_customscss_plain($this->_form);

        // Live swatch next to the bg/text colour custom fields; js_call_amd here reaches the
        // modal (definition_after_data runs inside the JS-collection window, unlike definition()).
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
        $id = $this->get_templateid();

        $data = (object) [
            'id' => $id,
            'contextid' => $this->get_context_for_dynamic_submission()->id,
        ];

        if ($id > 0 && ($template = template::get_record(['id' => $id]))) {
            $data->shortname = $template->get('shortname');
            $data->visible = $template->get('visible');
            $data->duedate = (int) $template->get('duedate');
            $data->description = [
                'text' => $template->get('description'),
                'format' => $template->get('descriptionformat'),
            ];
            lp_handler::create()->instance_form_before_set_data_with_image($data);
        }

        $this->set_data($data);
    }

    /**
     * Submitted data: force the custom SCSS field to plain format.
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $editorprop = 'customfield_' . constants::CFIELD_CUSTOMSCSS . '_editor';
            $plainprop = 'customfield_' . constants::CFIELD_CUSTOMSCSS;
            if (isset($data->$editorprop) && is_array($data->$editorprop)) {
                $data->{$editorprop}['format'] = FORMAT_PLAIN;
            } else if (isset($data->$editorprop) && is_object($data->$editorprop)) {
                $data->$editorprop->format = FORMAT_PLAIN;
            } else if (isset($data->$plainprop) && is_array($data->$plainprop)) {
                $data->{$plainprop}['format'] = FORMAT_PLAIN;
            } else if (isset($data->$plainprop) && is_object($data->$plainprop)) {
                $data->$plainprop->format = FORMAT_PLAIN;
            }
        }
        return $data;
    }

    /**
     * Create or update the template and persist its custom fields.
     *
     * @return array{templateid: int}
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $id = (int) ($data->id ?? 0);

        $record = new \stdClass();
        $record->shortname = $data->shortname;
        $record->description = $data->description['text'] ?? '';
        $record->descriptionformat = $data->description['format'] ?? FORMAT_HTML;
        $record->visible = (int) ($data->visible ?? 1);
        $record->duedate = (int) ($data->duedate ?? 0);
        $record->contextid = (int) $data->contextid;

        if ($id > 0) {
            $record->id = $id;
            api::update_template($record);
            $templateid = $id;
        } else {
            $templateid = (int) api::create_template($record)->get('id');
        }

        $data->id = $templateid;
        // The lp_handler uses a 2-arg signature (data, instanceid), unlike competency_handler's 3-arg.
        lp_handler::create()->instance_form_save_with_image($data, $templateid);

        \local_dimensions\template_metadata_cache::invalidate_template($templateid);
        if (get_config('local_dimensions', 'enablecustomscss')) {
            \local_dimensions\scss_manager::invalidate_cache($templateid, 'lp');
        }

        return ['templateid' => $templateid];
    }

    /**
     * Validate shortname uniqueness within the context and the custom SCSS.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $shortname = $data['shortname'] ?? '';
        if (!empty($shortname)) {
            $existing = template::get_record([
                'shortname' => $shortname,
                'contextid' => $data['contextid'],
            ]);
            if ($existing && (int) $existing->get('id') !== (int) ($data['id'] ?? 0)) {
                $errors['shortname'] = get_string('shortnametaken', 'tool_lp');
            }
        }

        // Block saving invalid custom SCSS (shared with the competency modal and legacy form).
        $errors = array_merge($errors, helper::validate_customscss($data));

        return $errors;
    }
}
