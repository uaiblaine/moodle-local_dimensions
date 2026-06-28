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
 * Modal (dynamic) form to edit a competency framework's basic fields — for the Competency hub.
 *
 * Edits shortname, idnumber, description, visibility and per-level taxonomies. The scale is shown
 * read-only (changing it / creating frameworks is the existing full-page form, replaced by Plan B).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\form;

use core_competency\api;
use core_competency\competency_framework;

/**
 * Edit a competency framework in a modal.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework_dynamic_form extends \core_form\dynamic_form {
    /**
     * Get the framework id from the request.
     *
     * @return int Framework id.
     */
    private function get_frameworkid(): int {
        return $this->optional_param('id', 0, PARAM_INT);
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
     * Submission context: the framework's own context.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $framework = competency_framework::get_record(['id' => $this->get_frameworkid()]);
        return $framework ? $framework->get_context() : \context_system::instance();
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
     * Form fields: basic info, visibility, read-only scale, and per-level taxonomies.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $framework = competency_framework::get_record(['id' => $this->get_frameworkid()]) ?: null;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'shortname', get_string('shortname', 'tool_lp'), ['maxlength' => 100]);
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addRule('shortname', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        $mform->addElement('text', 'idnumber', get_string('idnumber', 'tool_lp'), ['maxlength' => 100]);
        $mform->setType('idnumber', PARAM_RAW);
        $mform->addRule('idnumber', null, 'required', null, 'client');
        $mform->addRule('idnumber', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        $mform->addElement('editor', 'description', get_string('description', 'tool_lp'), ['rows' => 4]);
        $mform->setType('description', PARAM_CLEANHTML);

        // Scale is read-only here (Plan B adds native scale editing/create).
        $scale = $framework ? $framework->get_scale() : null;
        $scalename = $scale ? $scale->name : '';
        $mform->addElement('static', 'scalestatic', get_string('central_frameworks_scale', 'local_dimensions'), $scalename);

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
        $framework = competency_framework::get_record(['id' => $this->get_frameworkid()]);
        if (!$framework) {
            return;
        }
        $data = (object) [
            'id' => (int) $framework->get('id'),
            'shortname' => $framework->get('shortname'),
            'idnumber' => $framework->get('idnumber'),
            'visible' => (int) $framework->get('visible'),
            'description' => [
                'text' => $framework->get('description'),
                'format' => $framework->get('descriptionformat'),
            ],
        ];

        // Taxonomies are stored comma-joined (one key per level); expand to the per-level array.
        $taxonomies = array_filter(explode(',', (string) $framework->get('taxonomies')));
        $level = 1;
        $taxdata = [];
        foreach ($taxonomies as $taxonomy) {
            $taxdata[$level++] = $taxonomy;
        }
        $data->taxonomies = $taxdata;

        $this->set_data($data);
    }

    /**
     * Update the framework's editable fields (scale config and context are preserved by core).
     *
     * @return array{frameworkid: int}
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $id = (int) $data->id;

        $record = new \stdClass();
        $record->id = $id;
        $record->shortname = $data->shortname;
        $record->idnumber = $data->idnumber;
        $record->description = $data->description['text'] ?? '';
        $record->descriptionformat = $data->description['format'] ?? FORMAT_HTML;
        $record->visible = (int) ($data->visible ?? 1);
        if (!empty($data->taxonomies) && is_array($data->taxonomies)) {
            $record->taxonomies = implode(',', $data->taxonomies);
        }

        api::update_framework($record);

        return ['frameworkid' => $id];
    }

    /**
     * Validate shortname/idnumber uniqueness within the context.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $id = (int) ($data['id'] ?? 0);
        $contextid = $this->get_context_for_dynamic_submission()->id;
        $shortname = $data['shortname'] ?? '';
        if (!empty($shortname)) {
            $existing = competency_framework::get_record(['shortname' => $shortname, 'contextid' => $contextid]);
            if ($existing && (int) $existing->get('id') !== $id) {
                $errors['shortname'] = get_string('shortnametaken', 'tool_lp');
            }
        }

        return $errors;
    }
}
