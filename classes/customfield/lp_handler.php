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
 * Learning Plan Template custom field handler.
 *
 * @package   local_dimensions
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\customfield;

use core_customfield\handler;
use local_dimensions\event\template_customfields_updated;
use local_dimensions\picture_manager;
use core\context;
use core\context\system as context_system;

/**
 * Learning Plan Template custom field handler.
 *
 * @package   local_dimensions
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lp_handler extends handler {
    use instance_change_logger;

    /**
     * @var lp_handler
     */
    protected static $singleton;

    /**
     * The instance whose values are being saved right now, or 0.
     *
     * Core resolves editability with get_editable_fields($isnewinstance ? 0 : $instance->id)
     * (customfield/classes/handler.php:707), so on the create path can_edit() is asked about
     * instance 0 and cannot see which template it is about — which is exactly the case a
     * category-scoped manager hits. The id is remembered here for the duration of the save.
     *
     * @var int
     */
    protected $savinginstanceid = 0;

    /** @var context|null Context a form is creating a template in, set before the fields are rendered. */
    protected $editcontexthint = null;

    /**
     * Returns the singleton instance.
     *
     * @param int $itemid
     * @return lp_handler
     */
    public static function create(int $itemid = 0): lp_handler {
        if (static::$singleton === null) {
            static::$singleton = new static(0);
        }
        return static::$singleton;
    }

    /**
     * Run setup for the handler.
     *
     * @return context
     */
    public function get_configuration_context(): context {
        return context_system::instance();
    }

    /**
     * Returns the configuration URL.
     *
     * @param \core_customfield\field_controller|null $field
     * @return \moodle_url
     */
    public function get_configuration_url(?\core_customfield\field_controller $field = null): \moodle_url {
        return new \moodle_url('/local/dimensions/customfield_template.php');
    }

    /**
     * Returns the context for the data instance.
     *
     * Deliberately the system context, including for a template that lives in a course category.
     * This is where every customfield_data row this plugin has ever written already sits, along
     * with the file areas of the textarea and picture fields; switching new inserts to the
     * template's own context would split the plugin's data across two contexts with no upgrade
     * step, and leave the files of existing category templates unreachable. Moving it is a
     * migration in its own right, not a side effect of the editing fix in can_edit().
     *
     * @param int $instanceid
     * @return context
     */
    public function get_instance_context(int $instanceid = 0): context {
        return context_system::instance();
    }

    /**
     * Check if the current user can configure the custom fields.
     *
     * @return bool
     */
    public function can_configure(): bool {
        return has_capability('moodle/site:config', context_system::instance());
    }

    /**
     * Check if the current user can view the custom fields.
     *
     * @param \core_customfield\field_controller $field
     * @param int $instanceid
     * @return bool
     */
    public function can_view(\core_customfield\field_controller $field, int $instanceid = 0): bool {
        return true;
    }

    /**
     * Check if the current user can edit the custom fields.
     *
     * Resolved at the TEMPLATE's own context when there is one. Resolving templatemanage at the
     * system context meant handler::instance_form_save() — which saves only get_editable_fields()
     * — silently wrote ZERO custom fields for a manager who holds the capability in a course
     * category, the very place category templates live. That included the template ID number, so
     * the identity key never landed and every re-import created a duplicate.
     *
     * editcustomscss stays system-scoped: it gates RISK_XSS content that renders site-wide.
     *
     * @param \core_customfield\field_controller $field
     * @param int $instanceid
     * @return bool
     */
    public function can_edit(\core_customfield\field_controller $field, int $instanceid = 0): bool {
        if ($field->get('shortname') === \local_dimensions\constants::CFIELD_CUSTOMSCSS) {
            return has_capability('local/dimensions:editcustomscss', context_system::instance());
        }
        return has_capability('moodle/competency:templatemanage', $this->resolve_edit_context($instanceid));
    }

    /**
     * The context a template's custom-field editing rights are resolved against.
     *
     * Falls back to the system context with no instance — the field-configuration screens edit
     * the field definitions themselves, which are site-wide.
     *
     * @param int $instanceid The template id, or 0.
     * @return context
     */
    protected function resolve_edit_context(int $instanceid): context {
        $instanceid = $instanceid > 0 ? $instanceid : $this->savinginstanceid;
        if ($instanceid > 0) {
            $template = \core_competency\template::get_record(['id' => $instanceid]);
            if ($template) {
                return $template->get_context();
            }
        }
        if ($this->editcontexthint !== null) {
            return $this->editcontexthint;
        }
        return context_system::instance();
    }

    /**
     * Tell the handler which context a form is creating a template in.
     *
     * The saving latch above covers the save half of the create path; this covers the render
     * half, where core asks can_edit() about instance 0 before any template exists and a manager
     * holding templatemanage in one course category only would otherwise see no custom fields.
     *
     * @param \context|null $context The context the template is being created in, or null to clear.
     * @return void
     */
    public function set_edit_context_hint(?\context $context): void {
        $this->editcontexthint = $context;
    }

    /**
     * Returns the component name.
     *
     * @return string
     */
    public function get_component(): string {
        return 'local_dimensions';
    }

    /**
     * Returns the area name.
     *
     * @return string
     */
    public function get_area(): string {
        return 'lp';
    }

    /**
     * Returns the item itemid.
     *
     * @param int $instanceid
     * @return int
     */
    public function get_itemid(int $instanceid = 0): int {
        return $instanceid;
    }

    /**
     * Set up the form data for the custom field.
     *
     * @param \MoodleQuickForm $mform
     * @param int $instanceid
     * @param string|null $headerlangidentifier
     * @param string|null $headerlangcomponent
     */
    public function instance_form_definition(
        \MoodleQuickForm $mform,
        int $instanceid = 0,
        ?string $headerlangidentifier = null,
        ?string $headerlangcomponent = null
    ) {
        // A non-empty (or default) heading labels the custom-field block for callers that
        // want it; the hub modal passes '' to suppress it, because the core category headers
        // already label the fields inside the dialog.
        if ($headerlangidentifier !== '') {
            $heading = get_string('templatecustomfields', 'local_dimensions');
            $mform->addElement('html', '<h2 class="mt-4 mb-3">' . $heading . '</h2>');
        }
        parent::instance_form_definition($mform, $instanceid, $headerlangidentifier, $headerlangcomponent);

        // In built-in mode, add filemanagers for background and card images.
        if (picture_manager::is_builtin_mode()) {
            picture_manager::add_all_filemanagers_to_form($mform, 'lp');
        }
    }

    /**
     * Prepare form data including built-in image draft area.
     *
     * @param \stdClass $data The form data object.
     */
    public function instance_form_before_set_data_with_image(\stdClass $data): void {
        $this->instance_form_before_set_data($data);

        // In built-in mode, prepare the draft area for the image.
        if (picture_manager::is_builtin_mode() && !empty($data->id)) {
            picture_manager::prepare_all_draft_areas('lp', (int)$data->id, $data);
        }
    }

    /**
     * Save form data including built-in image.
     *
     * @param \stdClass $data The submitted form data.
     * @param int $instanceid The template ID.
     */
    public function instance_form_save_with_image(\stdClass $data, int $instanceid): void {
        try {
            $this->instance_form_save($data, true);
        } catch (\dml_write_exception $e) {
            /* Two users first-saving the same instance race the id-0 INSERT into
               customfield_data's unique index; the retry re-reads the instance
               data, finds the committed row and takes the update path. */
            $this->instance_form_save($data, true);
        }

        // In built-in mode, save the uploaded image and log a change of it.
        if (picture_manager::is_builtin_mode()) {
            $before = $this->snapshot_image_hashes('lp', $instanceid);
            picture_manager::save_all_from_form($data, 'lp', $instanceid);
            $changed = $this->diff_image_hashes($before, $this->snapshot_image_hashes('lp', $instanceid));
            $this->trigger_customfields_updated(template_customfields_updated::class, $instanceid, false, $changed);
        }
    }

    /**
     * Save custom field data and log which fields changed.
     *
     * @param \stdClass $instance Form data carrying the instance id.
     * @param bool $isnewinstance Whether the call is made during instance creation.
     */
    public function instance_form_save(\stdClass $instance, bool $isnewinstance = false) {
        $instanceid = (int) ($instance->id ?? 0);
        $before = $this->snapshot_instance_values($instanceid);
        /* Remembered for can_edit(), which core asks about instance 0 whenever $isnewinstance is
           true. Without it a manager holding templatemanage only in a course category writes ZERO
           fields on the create path — the very case the per-instance resolution exists for. */
        $this->savinginstanceid = $instanceid;
        try {
            parent::instance_form_save($instance, $isnewinstance);
        } finally {
            $this->savinginstanceid = 0;
        }
        $changed = $this->diff_instance_values($before, $this->snapshot_instance_values($instanceid));
        $this->trigger_customfields_updated(template_customfields_updated::class, $instanceid, $isnewinstance, $changed);
    }
}
