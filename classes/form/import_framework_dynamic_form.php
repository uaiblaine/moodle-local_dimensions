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
 * Modal (dynamic) form to import a competency framework CSV into a chosen context.
 *
 * Runs inside a modal (core_form/modalform); the uploaded file is read from the user's
 * draft area in process_dynamic_submission() (the proven core dynamic-form + filepicker
 * pattern), parsed, and imported synchronously into the requested context — which may be
 * a course category, unlike the core tool.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\form;

use local_dimensions\local\framework_csv_importer;
use local_dimensions\local\framework_csv_serializer;

/**
 * Import a competency framework CSV in a modal.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_framework_dynamic_form extends \core_form\dynamic_form {
    /**
     * The context the framework will be imported into (system or a course category).
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
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
     * Only framework managers, and only in system or course-category context, may import.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        if ($context->contextlevel !== CONTEXT_SYSTEM && $context->contextlevel !== CONTEXT_COURSECAT) {
            throw new \moodle_exception('invalidcontext', 'error');
        }
        require_capability('moodle/competency:competencymanage', $context);
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
     * Form fields: the CSV file, encoding/delimiter, the update-existing toggle.
     *
     * @return void
     */
    public function definition() {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $mform = $this->_form;

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $this->get_context_for_dynamic_submission()->id);

        $mform->addElement(
            'filepicker',
            'importfile',
            get_string('central_frameworks_import_file', 'local_dimensions'),
            null,
            ['accepted_types' => ['.csv', '.txt']]
        );
        $mform->addRule('importfile', null, 'required', null, 'client');

        $mform->addElement(
            'select',
            'delimiter',
            get_string('central_frameworks_import_delimiter', 'local_dimensions'),
            \csv_import_reader::get_delimiter_list()
        );
        $mform->setType('delimiter', PARAM_ALPHA);
        // Default to the separator the viewer's language uses in spreadsheets (';' in pt_br etc.).
        $mform->setDefault('delimiter', get_string('listsep', 'langconfig') === ';' ? 'semicolon' : 'comma');

        $mform->addElement(
            'select',
            'encoding',
            get_string('central_frameworks_import_encoding', 'local_dimensions'),
            \core_text::get_encodings()
        );
        $mform->setType('encoding', PARAM_RAW);
        $mform->setDefault('encoding', 'UTF-8');

        $mform->addElement(
            'advcheckbox',
            'updateexisting',
            get_string('central_frameworks_import_updateexisting', 'local_dimensions')
        );
        $mform->setType('updateexisting', PARAM_BOOL);
        $mform->addHelpButton('updateexisting', 'central_frameworks_import_updateexisting', 'local_dimensions');
    }

    /**
     * Seed the context on first render.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data((object) ['contextid' => $this->get_context_for_dynamic_submission()->id]);
    }

    /**
     * Read the uploaded CSV from the draft area and import it into the target context.
     *
     * @return array{frameworkid: int, competencycount: int}
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $text = $this->read_uploaded_csv((int) $data->importfile);
        $parsed = framework_csv_serializer::parse($text, $data->encoding, $data->delimiter);
        $importer = new framework_csv_importer(
            $parsed,
            $this->get_context_for_dynamic_submission(),
            !empty($data->updateexisting)
        );
        return $importer->import();
    }

    /**
     * Validate that a parseable CSV carrying a framework row was uploaded.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $text = $this->read_uploaded_csv((int) ($data['importfile'] ?? 0));
        if (trim($text) === '') {
            $errors['importfile'] = get_string('central_frameworks_import_invalidfile', 'local_dimensions');
            return $errors;
        }
        $parsed = framework_csv_serializer::parse($text, $data['encoding'] ?? 'UTF-8', $data['delimiter'] ?? 'comma');
        if ($parsed['error'] !== '') {
            $errors['importfile'] = get_string('central_frameworks_import_invalidfile', 'local_dimensions');
        } else if (empty($parsed['framework'])) {
            $errors['importfile'] = get_string('central_frameworks_import_noframeworkrow', 'local_dimensions');
        }
        return $errors;
    }

    /**
     * Read the raw content of the CSV uploaded to the user's draft area.
     *
     * @param int $draftid The filepicker draft item id.
     * @return string The file content, or '' when none.
     */
    private function read_uploaded_csv(int $draftid): string {
        global $USER;
        if ($draftid <= 0) {
            return '';
        }
        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'sortorder, id', false);
        $file = reset($files);
        return $file ? $file->get_content() : '';
    }
}
