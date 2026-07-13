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
 * Modal (dynamic) form to create or edit a competency framework — for the Competency hub.
 *
 * Create and edit of basic fields + per-level taxonomies. Mirrors core tool_lp: the scale and its
 * proficiency configuration are always editable; only WHICH scale is frozen (readonly select + form
 * constant) once the framework has user competencies, exactly like the native edit page.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\form;

use core_competency\api;
use core_competency\competency_framework;
use local_dimensions\helper;

defined('MOODLE_INTERNAL') || die();

// FILE_INTERNAL (used by the description editor options) lives in repository/lib.php, not loaded on the AJAX path.
global $CFG;
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Create/edit a competency framework in a modal.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework_dynamic_form extends \core_form\dynamic_form {
    /**
     * Get the framework id from the request.
     *
     * @return int Framework id (0 when creating).
     */
    private function get_frameworkid(): int {
        return $this->optional_param('id', 0, PARAM_INT);
    }

    /**
     * Load the framework being edited, or null on create.
     *
     * @return competency_framework|null
     */
    private function get_framework(): ?competency_framework {
        $id = $this->get_frameworkid();
        return $id ? (competency_framework::get_record(['id' => $id]) ?: null) : null;
    }

    /**
     * Whether the scale CHOICE is frozen: once a framework has user competencies core forbids
     * switching scales, but the proficiency configuration stays editable (native parity).
     *
     * @param competency_framework|null $framework The framework, or null on create.
     * @return bool
     */
    private function scale_frozen(?competency_framework $framework): bool {
        return $framework !== null && $framework->has_user_competencies();
    }

    /**
     * Number of taxonomy levels to show (matches the core form: at least the framework depth, min 4).
     *
     * @param competency_framework|null $framework The framework being edited, or null.
     * @return int
     */
    private function taxonomy_levels(?competency_framework $framework): int {
        return max($framework ? $framework->get_depth() : 4, 4);
    }

    /**
     * Submission context: the framework's own context on edit, else the requested context on create.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $framework = $this->get_framework();
        if ($framework) {
            return $framework->get_context();
        }
        $contextid = $this->optional_param('contextid', 0, PARAM_INT);
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
     * Only framework managers may submit.
     *
     * @return void
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
        return new \moodle_url('/local/dimensions/central.php');
    }

    /**
     * Form fields: basic info, scale (+ proficiency config when editable), visibility, taxonomies.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $framework = $this->get_framework();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $this->get_context_for_dynamic_submission()->id);

        $mform->addElement('text', 'shortname', get_string('shortname', 'tool_lp'), ['maxlength' => 100]);
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addRule('shortname', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        $mform->addElement('text', 'idnumber', get_string('idnumber', 'tool_lp'), ['maxlength' => 100]);
        $mform->setType('idnumber', PARAM_RAW);
        $mform->addRule('idnumber', null, 'required', null, 'client');
        $mform->addRule('idnumber', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        /* FILE_INTERNAL only: the default return_types includes FILE_EXTERNAL, which keeps tiny_media
           enabled without a filepicker and crashes on Moodle 5.0-5.2 (MDL-78428); no file area here. */
        $mform->addElement(
            'editor',
            'description',
            get_string('description', 'tool_lp'),
            ['rows' => 4],
            ['return_types' => FILE_INTERNAL]
        );
        $mform->setType('description', PARAM_CLEANHTML);

        $scaleel = $mform->addElement(
            'select',
            'scaleid',
            get_string('central_frameworks_scale', 'local_dimensions'),
            get_scales_menu()
        );
        $mform->setType('scaleid', PARAM_INT);
        if ($this->scale_frozen($framework)) {
            /* The scale is in use, so only WHICH scale is frozen; the proficiency config stays
               editable via the Configure scale button. Unlike the native form (readonly only,
               which a select ignores visually), disabled truly locks the UI: the field then
               stays out of the POST, but the constant supplies scaleid to get_data() and the
               scale-config JS still reads .value from a disabled select. No required rule here:
               it validates the SUBMITTED values, where a disabled field never appears. */
            $scaleel->updateAttributes(['readonly' => 'readonly', 'disabled' => 'disabled']);
            $mform->setConstant('scaleid', (int) $framework->get('scaleid'));
        } else {
            $mform->addRule('scaleid', null, 'required', null, 'client');
        }

        $mform->addElement('hidden', 'scaleconfiguration', '', ['id' => 'id_scaleconfiguration']);
        $mform->setType('scaleconfiguration', PARAM_RAW);

        $configured = $framework && helper::scaleconfig_is_complete((string) $framework->get('scaleconfiguration'));
        $summary = $configured ? get_string('central_frameworks_scaleconfigured', 'local_dimensions') : '';
        $configbutton = '<button type="button" class="btn btn-secondary btn-sm" '
            . 'data-action="configure-scale">'
            . get_string('central_frameworks_configurescale', 'local_dimensions') . '</button>'
            . ' <span class="text-muted small ms-2" data-region="scaleconfig-summary">' . $summary . '</span>';
        $mform->addElement('static', 'scaleconfig', '', $configbutton);

        $mform->addElement('selectyesno', 'visible', get_string('visible', 'tool_lp'));
        $mform->setDefault('visible', 1);

        $taxonomies = competency_framework::get_taxonomies_list();
        $levels = $this->taxonomy_levels($framework);
        for ($i = 1; $i <= $levels; $i++) {
            $mform->addElement('select', "taxonomies[$i]", get_string('levela', 'tool_lp', $i), $taxonomies);
        }
    }

    /**
     * Load existing values when editing.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $framework = $this->get_framework();
        if (!$framework) {
            $this->set_data((object) ['id' => 0, 'contextid' => $this->get_context_for_dynamic_submission()->id]);
            return;
        }
        $data = (object) [
            'id' => (int) $framework->get('id'),
            'contextid' => (int) $framework->get('contextid'),
            'shortname' => $framework->get('shortname'),
            'idnumber' => $framework->get('idnumber'),
            'visible' => (int) $framework->get('visible'),
            'description' => [
                'text' => $framework->get('description'),
                'format' => $framework->get('descriptionformat'),
            ],
        ];
        $data->scaleid = (int) $framework->get('scaleid');
        $data->scaleconfiguration = $framework->get('scaleconfiguration');

        /* The persistent's magic getter already explodes the comma-joined column into the
           per-level array indexed from 1 — casting that array to string was a warning, which
           developer debugging (Behat) escalates into an exception before the modal opens. */
        $data->taxonomies = $framework->get('taxonomies');

        $this->set_data($data);
    }

    /**
     * Create or update the framework.
     *
     * @return array{frameworkid: int}
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $id = (int) $data->id;

        $record = new \stdClass();
        $record->shortname = $data->shortname;
        $record->idnumber = $data->idnumber;
        $record->description = $data->description['text'] ?? '';
        $record->descriptionformat = $data->description['format'] ?? FORMAT_HTML;
        $record->visible = (int) ($data->visible ?? 1);
        if (!empty($data->taxonomies) && is_array($data->taxonomies)) {
            $record->taxonomies = implode(',', $data->taxonomies);
        }
        // Scale choice + proficiency config always persist; on a frozen framework the form
        // constant already pinned scaleid to its current value (native parity).
        $record->scaleid = (int) $data->scaleid;
        $record->scaleconfiguration = $data->scaleconfiguration;

        if ($id === 0) {
            $record->contextid = (int) $data->contextid;
            $id = (int) api::create_framework($record)->get('id');
        } else {
            $record->id = $id;
            api::update_framework($record);
        }

        return ['frameworkid' => $id];
    }

    /**
     * Validate shortname uniqueness and the scale-proficiency config (when editable).
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $id = (int) ($data['id'] ?? 0);
        $contextid = (int) ($data['contextid'] ?? $this->get_context_for_dynamic_submission()->id);
        $shortname = $data['shortname'] ?? '';
        if (!empty($shortname)) {
            $existing = competency_framework::get_record(['shortname' => $shortname, 'contextid' => $contextid]);
            if ($existing && (int) $existing->get('id') !== $id) {
                $errors['shortname'] = get_string('shortnametaken', 'tool_lp');
            }
        }

        if (!helper::scaleconfig_is_complete($data['scaleconfiguration'] ?? '')) {
            $errors['scaleid'] = get_string('central_frameworks_scaleincomplete', 'local_dimensions');
        }

        return $errors;
    }
}
