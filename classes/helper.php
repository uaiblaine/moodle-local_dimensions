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
 * Helper functions for local_dimensions plugin.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use core_customfield\field_controller;
use core_customfield\handler;
use cache;
use moodle_url;
use local_dimensions\customfield\lp_handler;
use local_dimensions\customfield\competency_handler;

/**
 * Helper functions for local_dimensions plugin.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /** @var string Area for learning plan templates */
    const AREA_LP = 'lp';

    /** @var string Area for competencies */
    const AREA_COMPETENCY = 'competency';

    /**
     * Get the handler for a given area.
     *
     * @param string $area The area (lp or competency)
     * @return handler
     */
    protected static function get_handler(string $area): handler {
        if ($area === self::AREA_COMPETENCY) {
            return competency_handler::create();
        }
        return lp_handler::create();
    }

    /**
     * Find a custom field by its shortname within a handler's area.
     *
     * @param string $shortname
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function find_field_by_shortname(string $shortname, string $area = self::AREA_LP): ?field_controller {
        $handler = self::get_handler($area);
        $categories = $handler->get_categories_with_fields();
        foreach ($categories as $category) {
            foreach ($category->get_fields() as $field) {
                if ($field->get('shortname') === $shortname) {
                    return $field;
                }
            }
        }
        return null;
    }

    /**
     * Create a custom field if it does not exist.
     *
     * IMPORTANT: The $description parameter accepts only a plain string, not a lang_string.
     * Since custom field descriptions are stored in the database and don't support dynamic
     * translation, it's recommended to leave $description empty to avoid fixed-language issues.
     * Field names use lang_string and are properly localized.
     *
     * @param string $shortname
     * @param string $type Field type (text, select, picture, etc.)
     * @param string $area The area (lp or competency)
     * @param \lang_string|null $displayname Localized display name
     * @param array $config Additional field configuration
     * @param string $description Field description (leave empty to avoid i18n issues)
     * @return field_controller|null
     */
    protected static function create_custom_field(
        string $shortname,
        string $type,
        string $area,
        ?\lang_string $displayname = null,
        array $config = [],
        string $description = ''
    ): ?field_controller {
        $handler = self::get_handler($area);
        $categories = $handler->get_categories_with_fields();

        if (empty($categories)) {
            $categoryid = $handler->create_category();
            $category = \core_customfield\category_controller::create($categoryid);
        } else {
            $category = reset($categories);
        }

        $config += [
            'defaultvalue' => '',
            'defaultvalueformat' => 1,
            'visibility' => 2,
            'required' => 0,
            'uniquevalues' => 0,
            'locked' => 0,
        ];

        $record = (object) [
            'type' => $type,
            'shortname' => $shortname,
            'name' => $displayname ? (string) $displayname : $shortname,
            'descriptionformat' => FORMAT_HTML,
            'description' => $description,
            'configdata' => json_encode($config),
        ];

        try {
            $field = \core_customfield\field_controller::create(0, $record, $category);
        } catch (\moodle_exception $e) {
            return null;
        }

        $handler->save_field_configuration($field, $record);

        // Fetch the field again because the categories cache was rebuilt.
        return self::find_field_by_shortname($shortname, $area);
    }

    /**
     * Get or create the display mode custom field (templates only).
     *
     * @return field_controller|null
     */
    public static function get_display_mode_field(): ?field_controller {
        $shortname = constants::CFIELD_DISPLAYMODE;
        $field = self::find_field_by_shortname($shortname, self::AREA_LP);

        if ($field) {
            return $field;
        }

        $options = constants::display_mode_options();
        $optionstext = [];
        foreach ($options as $key => $langstring) {
            $optionstext[] = (string) $langstring;
        }

        return self::create_custom_field(
            $shortname,
            'select',
            self::AREA_LP,
            new \lang_string('displaymode', 'local_dimensions'),
            [
                'options' => join("\n", $optionstext),
                'defaultvalue' => (string) $options[constants::DISPLAYMODE_COMPETENCIES],
            ],
            '' // Description removed to avoid fixed-language issue (field name is self-explanatory).
        );
    }

    /**
     * Get or create the customcard (picture) field.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_customcard_field(string $area): ?field_controller {
        $shortname = constants::CFIELD_CUSTOMCARD;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        return self::create_custom_field(
            $shortname,
            'picture',
            $area,
            new \lang_string('customcard', 'local_dimensions'),
            [],
            '' // Description removed to avoid fixed-language issue.
        );
    }

    /**
     * Get or create the custombgimage (picture) field.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_custombgimage_field(string $area): ?field_controller {
        $shortname = constants::CFIELD_CUSTOMBGIMAGE;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        return self::create_custom_field(
            $shortname,
            'picture',
            $area,
            new \lang_string('custombgimage', 'local_dimensions'),
            [],
            '' // Description removed to avoid fixed-language issue.
        );
    }

    /**
     * Get or create the custombgcolor (text) field.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_custombgcolor_field(string $area): ?field_controller {
        $shortname = constants::CFIELD_CUSTOMBGCOLOR;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        return self::create_custom_field(
            $shortname,
            'text',
            $area,
            new \lang_string('custombgcolor', 'local_dimensions'),
            [
                'displaysize' => 50,
                'maxlength' => 255,
                'ispassword' => 0,
                'link' => '',
            ],
            '' // Description removed to avoid fixed-language issue.
        );
    }

    /**
     * Get or create the customtextcolor (text) field.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_customtextcolor_field(string $area): ?field_controller {
        $shortname = constants::CFIELD_CUSTOMTEXTCOLOR;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        return self::create_custom_field(
            $shortname,
            'text',
            $area,
            new \lang_string('customtextcolor', 'local_dimensions'),
            [
                'displaysize' => 50,
                'maxlength' => 255,
                'ispassword' => 0,
                'link' => '',
            ],
            '' // Description removed to avoid fixed-language issue.
        );
    }

    /**
     * Get or create the tag1 (select) field.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_tag1_field(string $area): ?field_controller {
        $shortname = constants::CFIELD_TAG1;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        return self::create_custom_field(
            $shortname,
            'select',
            $area,
            new \lang_string('tag1', 'local_dimensions'),
            [
                'options' => get_string('tag1_options', 'local_dimensions'),
            ],
            '' // Description removed to avoid fixed-language issue.
        );
    }

    /**
     * Get or create the tag2 (select) field.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_tag2_field(string $area): ?field_controller {
        $shortname = constants::CFIELD_TAG2;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        return self::create_custom_field(
            $shortname,
            'select',
            $area,
            new \lang_string('tag2', 'local_dimensions'),
            [
                'options' => get_string('tag2_options', 'local_dimensions'),
            ],
            '' // Description removed to avoid fixed-language issue.
        );
    }

    /**
     * Get or create the type (select) field.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_type_field(string $area): ?field_controller {
        $shortname = constants::CFIELD_TYPE;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        return self::create_custom_field(
            $shortname,
            'select',
            $area,
            new \lang_string('type', 'local_dimensions'),
            [
                'options' => get_string('type_options', 'local_dimensions'),
            ],
            '' // Description removed to avoid fixed-language issue.
        );
    }

    /**
     * Get or create the template identifier (text) field.
     *
     * Templates only — there is no native idnumber column on competency_template,
     * so this custom field fills the same role the framework's idnumber plays for
     * competency frameworks (search/lookup hint shown next to the short name).
     *
     * @return field_controller|null
     */
    public static function get_template_idnumber_field(): ?field_controller {
        $shortname = constants::CFIELD_TEMPLATE_IDNUMBER;
        $field = self::find_field_by_shortname($shortname, self::AREA_LP);

        if ($field) {
            return $field;
        }

        return self::create_custom_field(
            $shortname,
            'text',
            self::AREA_LP,
            new \lang_string('templateidnumber', 'local_dimensions'),
            [
                'displaysize' => 50,
                'maxlength' => 100,
                'ispassword' => 0,
                'link' => '',
            ],
            ''
        );
    }

    /**
     * Get or create the customscss (textarea) field.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_customscss_field(string $area = self::AREA_LP): ?field_controller {
        $shortname = constants::CFIELD_CUSTOMSCSS;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        return self::create_custom_field(
            $shortname,
            'textarea',
            $area,
            new \lang_string('customscss', 'local_dimensions'),
            [
                'defaultvalue' => '',
                'defaultvalueformat' => FORMAT_PLAIN,
            ],
            '' // Description removed to avoid fixed-language issue.
        );
    }

    /**
     * Ensure all custom fields exist for a given area.
     *
     * This is useful for initialization during plugin setup or first access.
     *
     * @param string $area The area (lp or competency)
     */
    public static function ensure_custom_fields_exist(string $area): void {
        // Display mode only for templates.
        if ($area === self::AREA_LP) {
            self::get_display_mode_field();
            self::get_subline_source_field();
            self::get_template_idnumber_field();
        }

        // Enrollment filter + single-course redirect: provisioned for both
        // templates and competencies so the cascade competency -> template ->
        // global has a place to read each layer from.
        self::get_enrollmentfilter_field($area);
        self::get_singlecourseredirect_field($area);

        // Image fields: only create if using external customfield_picture plugin.
        // In built-in mode, images are managed by picture_manager directly.
        if (!picture_manager::is_builtin_mode()) {
            self::get_customcard_field($area);
            self::get_custombgimage_field($area);
        }

        // Non-image fields are always created.
        self::get_custombgcolor_field($area);
        self::get_customtextcolor_field($area);
        self::get_tag1_field($area);
        self::get_tag2_field($area);
        self::get_type_field($area);

        // Custom SCSS field for templates and competencies.
        if (get_config('local_dimensions', 'enablecustomscss')) {
            self::get_customscss_field($area);
        }
    }

    /** @var int Re-check window for the lazy-provisioning hook fallback (seconds). */
    const FIELDS_ENSURED_TTL = 3600;

    /**
     * Ensure all custom fields exist for both LP and competency areas.
     *
     * Throttled by a session-scoped timestamp so the work runs at most once
     * per {@see self::FIELDS_ENSURED_TTL} window per admin session. The TTL
     * means a field accidentally deleted via the customfield admin UI is
     * re-created within the window — install.php, the upgrade tail block, and
     * the settings updated-callbacks cover the immediate cases.
     */
    public static function ensure_all_fields(): void {
        global $SESSION;

        $now = time();
        $last = (int) ($SESSION->local_dimensions_fields_ensured ?? 0);
        if ($last && ($now - $last) < self::FIELDS_ENSURED_TTL) {
            return;
        }

        // Only admins should create custom fields.
        if (!has_capability('moodle/site:config', \context_system::instance())) {
            return;
        }

        self::ensure_custom_fields_exist(self::AREA_LP);
        self::ensure_custom_fields_exist(self::AREA_COMPETENCY);

        $SESSION->local_dimensions_fields_ensured = $now;
    }

    /**
     * Updated-callback target for admin settings that toggle conditional fields.
     *
     * Wired from settings.php so toggling `imagehandler` or `enablecustomscss`
     * provisions any newly-conditional fields immediately, without waiting for
     * the lazy hook fallback to fire.
     */
    public static function ensure_custom_fields_on_setting_change(): void {
        self::ensure_custom_fields_exist(self::AREA_LP);
        self::ensure_custom_fields_exist(self::AREA_COMPETENCY);
    }

    /**
     * Get the display mode for a specific template.
     *
     * @param int $templateid Learning plan template ID
     * @return int Display mode constant (DISPLAYMODE_COMPETENCIES or DISPLAYMODE_PLAN)
     */
    public static function get_template_display_mode(int $templateid): int {
        $field = self::get_display_mode_field();
        if (!$field) {
            return constants::DISPLAYMODE_COMPETENCIES;
        }

        $fields = \core_customfield\api::get_instance_fields_data([$field->get('id') => $field], $templateid);
        foreach ($fields as $data) {
            $value = (int) $data->get('intvalue');
            if (array_key_exists($value, constants::display_mode_options())) {
                return $value;
            }
        }

        return constants::DISPLAYMODE_COMPETENCIES;
    }

    /**
     * Get or create the per-template "subline source" select field (lp area).
     *
     * The selected value drives which piece of information is shown beneath the
     * competency name in the view-plan accordion header.
     *
     * @return field_controller|null
     */
    public static function get_subline_source_field(): ?field_controller {
        $shortname = constants::CFIELD_SUBLINE_SOURCE;
        $field = self::find_field_by_shortname($shortname, self::AREA_LP);

        if ($field) {
            return $field;
        }

        $options = constants::subline_source_options();
        $optionstext = [];
        foreach ($options as $key => $langstring) {
            $optionstext[] = (string) $langstring;
        }

        return self::create_custom_field(
            $shortname,
            'select',
            self::AREA_LP,
            new \lang_string('subline_source', 'local_dimensions'),
            [
                'options' => join("\n", $optionstext),
                'defaultvalue' => (string) $options[constants::SUBLINE_STATUS],
            ],
            ''
        );
    }

    /**
     * Resolve the configured subline source for a learning plan template.
     *
     * Falls back to {@see constants::SUBLINE_STATUS} when no value is set, which
     * preserves the legacy behaviour (rating badge or "to do" pill).
     *
     * @param int $templateid Learning plan template ID
     * @return string One of the constants::SUBLINE_* values
     */
    public static function get_template_subline_source(int $templateid): string {
        $field = self::get_subline_source_field();
        if (!$field) {
            return constants::SUBLINE_STATUS;
        }

        $allowed = array_keys(constants::subline_source_options());

        $fields = \core_customfield\api::get_instance_fields_data([$field->get('id') => $field], $templateid);
        foreach ($fields as $data) {
            // Select fields store the option key as intvalue, but its
            // representation depends on the field type configuration. Try both
            // value and intvalue to be defensive.
            $value = $data->get_value();
            if (is_int($value)) {
                $optionkeys = array_keys(constants::subline_source_options());
                if (isset($optionkeys[$value - 1])) {
                    $value = $optionkeys[$value - 1];
                }
            }
            $value = (string) $value;
            if ($value !== '' && in_array($value, $allowed, true)) {
                return $value;
            }
        }

        return constants::SUBLINE_STATUS;
    }

    /**
     * Get or create the enrollment filter select field for the given area.
     *
     * Storage: select customfield. Default option is `inherit`, which resolves
     * to the next layer (template, then site-wide
     * `local_dimensions/enrollmentfilter` setting) at read time.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_enrollmentfilter_field(string $area = self::AREA_LP): ?field_controller {
        $shortname = constants::CFIELD_ENROLLMENTFILTER;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        $options = constants::enrollmentfilter_options();
        $optionstext = [];
        foreach ($options as $key => $langstring) {
            $optionstext[] = (string) $langstring;
        }

        return self::create_custom_field(
            $shortname,
            'select',
            $area,
            new \lang_string('enrollmentfilter', 'local_dimensions'),
            [
                'options' => join("\n", $optionstext),
                'defaultvalue' => (string) $options[constants::ENROLLMENTFILTER_INHERIT],
            ],
            ''
        );
    }

    /**
     * Get or create the single-course redirect select field for the given area.
     *
     * Storage: select customfield. Default option is `inherit`, which resolves
     * to the next layer (template, then site-wide
     * `local_dimensions/singlecourseredirect` setting) at read time.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_singlecourseredirect_field(string $area = self::AREA_LP): ?field_controller {
        $shortname = constants::CFIELD_SINGLECOURSEREDIRECT;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        $options = constants::singlecourseredirect_options();
        $optionstext = [];
        foreach ($options as $key => $langstring) {
            $optionstext[] = (string) $langstring;
        }

        return self::create_custom_field(
            $shortname,
            'select',
            $area,
            new \lang_string('singlecourseredirect', 'local_dimensions'),
            [
                'options' => join("\n", $optionstext),
                'defaultvalue' => (string) $options[constants::SINGLECOURSEREDIRECT_INHERIT],
            ],
            ''
        );
    }

    /**
     * Resolve the effective enrollment filter for a learning plan template.
     *
     * Returns one of `all` / `enrolled` / `active`. When the template stores
     * `inherit` (or no row exists), falls back to the global
     * `local_dimensions/enrollmentfilter` setting, defaulting to `all`.
     *
     * @param int $templateid Learning plan template ID
     * @return string One of constants::ENROLLMENTFILTER_ALL|ENROLLED|ACTIVE
     */
    public static function get_template_enrollmentfilter(int $templateid): string {
        $global = (string) (get_config('local_dimensions', 'enrollmentfilter') ?: constants::ENROLLMENTFILTER_ALL);

        if ($templateid <= 0) {
            return $global;
        }

        $field = self::get_enrollmentfilter_field();
        if (!$field) {
            return $global;
        }

        $allowed = array_keys(constants::enrollmentfilter_options());
        $resolved = constants::ENROLLMENTFILTER_INHERIT;

        $fields = \core_customfield\api::get_instance_fields_data([$field->get('id') => $field], $templateid);
        foreach ($fields as $data) {
            $value = $data->get_value();
            if (is_int($value)) {
                if (isset($allowed[$value - 1])) {
                    $value = $allowed[$value - 1];
                }
            }
            $value = (string) $value;
            if ($value !== '' && in_array($value, $allowed, true)) {
                $resolved = $value;
                break;
            }
        }

        if ($resolved === constants::ENROLLMENTFILTER_INHERIT) {
            return $global;
        }

        return $resolved;
    }

    /**
     * Resolve the effective single-course redirect flag for a learning plan template.
     *
     * Returns a bool. When the template stores `inherit` (or no row exists),
     * falls back to the global `local_dimensions/singlecourseredirect`.
     *
     * @param int $templateid Learning plan template ID
     * @return bool true to redirect when single active enrolment matches
     */
    public static function get_template_singlecourseredirect(int $templateid): bool {
        $global = (bool) get_config('local_dimensions', 'singlecourseredirect');

        if ($templateid <= 0) {
            return $global;
        }

        $field = self::get_singlecourseredirect_field();
        if (!$field) {
            return $global;
        }

        $allowed = array_keys(constants::singlecourseredirect_options());
        $resolved = constants::SINGLECOURSEREDIRECT_INHERIT;

        $fields = \core_customfield\api::get_instance_fields_data([$field->get('id') => $field], $templateid);
        foreach ($fields as $data) {
            $value = $data->get_value();
            if (is_int($value)) {
                if (isset($allowed[$value - 1])) {
                    $value = $allowed[$value - 1];
                }
            }
            $value = (string) $value;
            if ($value !== '' && in_array($value, $allowed, true)) {
                $resolved = $value;
                break;
            }
        }

        if ($resolved === constants::SINGLECOURSEREDIRECT_INHERIT) {
            return $global;
        }

        return $resolved === constants::SINGLECOURSEREDIRECT_YES;
    }

    /**
     * Read the raw stored option key for a competency-area filter field.
     *
     * Returns the option key as stored (one of $allowed) or $default when the
     * competency has no row, the field does not exist, or the stored value is
     * unrecognised.
     *
     * @param int $competencyid Competency ID
     * @param string $shortname Customfield shortname
     * @param string[] $allowed Allowed option keys (ordered to match field options)
     * @param string $default Fallback when no value resolves
     * @return string One of $allowed or $default
     */
    private static function get_competency_select_raw(
        int $competencyid,
        string $shortname,
        array $allowed,
        string $default
    ): string {
        if ($competencyid <= 0) {
            return $default;
        }
        $field = self::find_field_by_shortname($shortname, self::AREA_COMPETENCY);
        if (!$field) {
            return $default;
        }
        $fields = \core_customfield\api::get_instance_fields_data([$field->get('id') => $field], $competencyid);
        foreach ($fields as $data) {
            $value = $data->get_value();
            if (is_int($value) && isset($allowed[$value - 1])) {
                $value = $allowed[$value - 1];
            }
            $value = (string) $value;
            if ($value !== '' && in_array($value, $allowed, true)) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * Resolve the effective enrollment filter when viewing a competency through a plan.
     *
     * Cascade: competency-level customfield -> template-level customfield ->
     * site-wide `local_dimensions/enrollmentfilter`. The template layer is
     * consulted even when the competency is not in the template's plan (e.g.
     * related-competency links), so the user's plan context still applies.
     *
     * @param int $competencyid Competency being viewed
     * @param int $templateid Template of the plan the competency is being viewed through (0 for manual plans)
     * @return string One of constants::ENROLLMENTFILTER_ALL|ENROLLED|ACTIVE
     */
    public static function resolve_enrollmentfilter_for_view(int $competencyid, int $templateid): string {
        $compraw = self::get_competency_select_raw(
            $competencyid,
            constants::CFIELD_ENROLLMENTFILTER,
            array_keys(constants::enrollmentfilter_options()),
            constants::ENROLLMENTFILTER_INHERIT
        );
        if ($compraw !== constants::ENROLLMENTFILTER_INHERIT) {
            return $compraw;
        }
        if ($templateid > 0) {
            return self::get_template_enrollmentfilter($templateid);
        }
        return (string) (get_config('local_dimensions', 'enrollmentfilter') ?: constants::ENROLLMENTFILTER_ALL);
    }

    /**
     * Resolve the effective single-course redirect flag when viewing a competency through a plan.
     *
     * Cascade: competency-level customfield -> template-level customfield ->
     * site-wide `local_dimensions/singlecourseredirect`.
     *
     * @param int $competencyid Competency being viewed
     * @param int $templateid Template of the plan the competency is being viewed through (0 for manual plans)
     * @return bool true when the single-course redirect should fire
     */
    public static function resolve_singlecourseredirect_for_view(int $competencyid, int $templateid): bool {
        $compraw = self::get_competency_select_raw(
            $competencyid,
            constants::CFIELD_SINGLECOURSEREDIRECT,
            array_keys(constants::singlecourseredirect_options()),
            constants::SINGLECOURSEREDIRECT_INHERIT
        );
        if ($compraw !== constants::SINGLECOURSEREDIRECT_INHERIT) {
            return $compraw === constants::SINGLECOURSEREDIRECT_YES;
        }
        if ($templateid > 0) {
            return self::get_template_singlecourseredirect($templateid);
        }
        return (bool) get_config('local_dimensions', 'singlecourseredirect');
    }

    /**
     * Return the localized taxonomy metadata for a framework level.
     *
     * Mirrors Moodle core's competency_summary_exporter logic:
     * competency level -> framework taxonomy constant -> localized lang string.
     *
     * @param \core_competency\competency_framework $framework The framework
     * @param int $level The framework level
     * @return array<string, mixed>
     */
    public static function get_taxonomy_at_level(\core_competency\competency_framework $framework, int $level): array {
        $taxonomykey = $framework->get_taxonomy($level);
        $taxonomies = \core_competency\competency_framework::get_taxonomies_list();
        $taxonomyterm = isset($taxonomies[$taxonomykey]) ? (string) $taxonomies[$taxonomykey] : '';

        return [
            'level' => $level,
            'key' => $taxonomykey,
            'term' => $taxonomyterm,
        ];
    }

    /**
     * Return the localized taxonomy mapping for a framework.
     *
     * @param \core_competency\competency_framework $framework The framework
     * @return array<int, array<string, mixed>>
     */
    public static function get_framework_taxonomy_map(\core_competency\competency_framework $framework): array {
        $configuredlevels = array_filter((array) $framework->get('taxonomies'));
        $maxlevel = max(1, count($configuredlevels), (int) $framework->get_depth());
        $map = [];

        for ($level = 1; $level <= $maxlevel; $level++) {
            $map[$level] = self::get_taxonomy_at_level($framework, $level);
        }

        return $map;
    }

    /**
     * Return taxonomy metadata for a competency and its direct children.
     *
     * @param \core_competency\competency $competency The competency
     * @param \core_competency\competency_framework|null $framework Optional framework
     * @return array<string, mixed>
     */
    public static function get_competency_taxonomy_data(
        \core_competency\competency $competency,
        ?\core_competency\competency_framework $framework = null
    ): array {
        if (!$framework) {
            $framework = \core_competency\api::read_framework($competency->get('competencyframeworkid'));
        }

        $currentlevel = (int) $competency->get_level();
        $current = self::get_taxonomy_at_level($framework, $currentlevel);
        $children = self::get_taxonomy_at_level($framework, $currentlevel + 1);

        return [
            'currentlevel' => $currentlevel,
            'current' => $current,
            'children' => $children,
            'bylevel' => self::get_framework_taxonomy_map($framework),
        ];
    }

    /**
     * Return the native tool_lp competency rule modules this plugin can display.
     *
     * @return array<int, array<string, string>> Rule module descriptors.
     */
    public static function get_competency_rule_modules(): array {
        $rulesmodules = [];
        $rules = \core_competency\competency::get_available_rules();

        foreach ($rules as $type => $rulename) {
            $amd = null;
            if ($type === 'core_competency\\competency_rule_all') {
                $amd = 'tool_lp/competency_rule_all';
            } else if ($type === 'core_competency\\competency_rule_points') {
                $amd = 'tool_lp/competency_rule_points';
            } else {
                continue;
            }

            $rulesmodules[] = [
                'name' => (string)$rulename,
                'type' => $type,
                'amd' => $amd,
            ];
        }

        return $rulesmodules;
    }

    /**
     * Return a localized rule type label.
     *
     * @param string|null $ruletype Native rule class name.
     * @return string
     */
    public static function get_competency_rule_label(?string $ruletype): string {
        if (empty($ruletype)) {
            return get_string('managecompetencies_norule', 'local_dimensions');
        }

        $rules = \core_competency\competency::get_available_rules();
        if (isset($rules[$ruletype])) {
            return (string)$rules[$ruletype];
        }

        return get_string('competencyrule', 'tool_lp');
    }

    /**
     * Return a localized rule outcome label.
     *
     * @param int $ruleoutcome Native rule outcome id.
     * @return string
     */
    public static function get_competency_rule_outcome_label(int $ruleoutcome): string {
        if ($ruleoutcome === \core_competency\competency::OUTCOME_EVIDENCE) {
            return get_string('competencyoutcome_evidence', 'tool_lp');
        } else if ($ruleoutcome === \core_competency\competency::OUTCOME_COMPLETE) {
            return get_string('competencyoutcome_complete', 'tool_lp');
        } else if ($ruleoutcome === \core_competency\competency::OUTCOME_RECOMMEND) {
            return get_string('competencyoutcome_recommend', 'tool_lp');
        }

        return get_string('competencyoutcome_none', 'tool_lp');
    }

    /**
     * Build a compact tree model for the native tool_lp rule configuration widget.
     *
     * @param \core_competency\competency $competency The target competency.
     * @return array<int, array<string, mixed>> Target competency followed by direct children.
     */
    public static function get_competency_rule_model(\core_competency\competency $competency): array {
        $models = [self::export_competency_rule_model($competency)];
        $children = \core_competency\competency::get_records(
            ['parentid' => $competency->get('id')],
            'sortorder'
        );

        foreach ($children as $child) {
            $models[] = self::export_competency_rule_model($child);
        }

        return $models;
    }

    /**
     * Export a competency record for the native rule JS model.
     *
     * @param \core_competency\competency $competency The competency.
     * @return array<string, mixed>
     */
    protected static function export_competency_rule_model(\core_competency\competency $competency): array {
        return [
            'id' => (int)$competency->get('id'),
            'parentid' => (int)$competency->get('parentid'),
            'competencyframeworkid' => (int)$competency->get('competencyframeworkid'),
            'shortname' => $competency->get('shortname'),
            'path' => $competency->get('path'),
            'ruletype' => $competency->get('ruletype'),
            'ruleoutcome' => (int)$competency->get('ruleoutcome'),
            'ruleconfig' => $competency->get('ruleconfig'),
        ];
    }

    /**
     * Return the localized rule outcome text for a competency.
     *
     * @param string $ruletype Simplified rule type: points|all
     * @param int $ruleoutcome Outcome id: 1|2|3
     * @param \core_competency\competency $competency The competency
     * @param \core_competency\competency_framework|null $framework Optional framework
     * @return string
     */
    public static function get_rule_outcome_text(
        string $ruletype,
        int $ruleoutcome,
        \core_competency\competency $competency,
        ?\core_competency\competency_framework $framework = null
    ): string {
        $taxonomydata = self::get_competency_taxonomy_data($competency, $framework);
        $taxonomyterm = $taxonomydata['current']['term'] ?? '';
        $prefix = $ruletype === 'points' ? 'rules_points_outcome_' : 'rules_all_outcome_';
        $suffix = '';

        if ($ruleoutcome === 1) {
            $suffix = 'attach';
        } else if ($ruleoutcome === 2) {
            $suffix = 'complete';
        } else if ($ruleoutcome === 3) {
            $suffix = 'recommend';
        }

        if ($suffix === '') {
            return '';
        }

        return get_string($prefix . $suffix, 'local_dimensions', $taxonomyterm);
    }

    /**
     * Store a per-course return URL in session cache.
     *
     * Each course ID gets its own cache entry keyed as 'course_{id}'.
     * This avoids key collisions when the same course belongs to multiple
     * plans or is accessed from different views (view-competency / view-plan).
     *
     * @param moodle_url $url The URL to store as return destination.
     * @param array $validcourseids Array of course IDs where the button should appear.
     */
    public static function set_return_context(moodle_url $url, array $validcourseids = []): void {
        $cache = cache::make('local_dimensions', 'returncontext');
        $returnurl = $url->out(false);
        foreach ($validcourseids as $courseid) {
            $cache->set('course_' . (int) $courseid, ['url' => $returnurl]);
        }
    }

    /**
     * Store a return URL for a single course in session cache.
     *
     * Convenience wrapper used by block_dimensions and other external callers
     * that already know the specific course being navigated to.
     *
     * @param int $courseid The course ID.
     * @param moodle_url $returnurl The URL to return to (typically a plan view page).
     */
    public static function set_return_context_for_course(int $courseid, moodle_url $returnurl): void {
        $cache = cache::make('local_dimensions', 'returncontext');
        $cache->set('course_' . $courseid, ['url' => $returnurl->out(false)]);
    }

    /**
     * Get the stored return context for a specific course.
     *
     * @param int $courseid The course ID to look up.
     * @return array|null Array with 'url' key, or null if not set.
     */
    public static function get_return_context_for_course(int $courseid): ?array {
        $cache = cache::make('local_dimensions', 'returncontext');
        $data = $cache->get('course_' . $courseid);
        if (empty($data) || empty($data['url'])) {
            return null;
        }
        return $data;
    }

    /**
     * Count visible competency frameworks per course category context.
     *
     * Single aggregate query (chunked only as a placeholder-limit safeguard).
     * Categories with no visible frameworks are absent from the result; the
     * caller treats missing entries as zero.
     *
     * @param int[] $categoryids Course category IDs.
     * @return array<int, int> categoryid => visible framework count
     */
    public static function count_frameworks_by_category(array $categoryids): array {
        global $DB;

        $categoryids = array_values(array_unique(array_filter(array_map('intval', $categoryids))));
        if (empty($categoryids)) {
            return [];
        }

        $counts = [];
        foreach (array_chunk($categoryids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'cat');
            $params['ctxlevel'] = CONTEXT_COURSECAT;
            $params['visible'] = 1;
            $sql = "SELECT ctx.instanceid AS categoryid, COUNT(cf.id) AS cnt
                      FROM {context} ctx
                      JOIN {competency_framework} cf ON cf.contextid = ctx.id
                     WHERE ctx.contextlevel = :ctxlevel
                       AND cf.visible = :visible
                       AND ctx.instanceid $insql
                  GROUP BY ctx.instanceid";
            foreach ($DB->get_records_sql($sql, $params) as $row) {
                $counts[(int)$row->categoryid] = (int)$row->cnt;
            }
        }
        return $counts;
    }

    /**
     * Count visible learning plan templates per course category context.
     *
     * Single aggregate query (chunked only as a placeholder-limit safeguard).
     * Categories with no visible templates are absent from the result.
     *
     * @param int[] $categoryids Course category IDs.
     * @return array<int, int> categoryid => visible template count
     */
    public static function count_templates_by_category(array $categoryids): array {
        global $DB;

        $categoryids = array_values(array_unique(array_filter(array_map('intval', $categoryids))));
        if (empty($categoryids)) {
            return [];
        }

        $counts = [];
        foreach (array_chunk($categoryids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'cat');
            $params['ctxlevel'] = CONTEXT_COURSECAT;
            $params['visible'] = 1;
            $sql = "SELECT ctx.instanceid AS categoryid, COUNT(ct.id) AS cnt
                      FROM {context} ctx
                      JOIN {competency_template} ct ON ct.contextid = ctx.id
                     WHERE ctx.contextlevel = :ctxlevel
                       AND ct.visible = :visible
                       AND ctx.instanceid $insql
                  GROUP BY ctx.instanceid";
            foreach ($DB->get_records_sql($sql, $params) as $row) {
                $counts[(int)$row->categoryid] = (int)$row->cnt;
            }
        }
        return $counts;
    }

    /**
     * Count learning plans per template.
     *
     * Single aggregate query (chunked only as a placeholder-limit safeguard).
     * Templates with no plans are absent from the result.
     *
     * @param int[] $templateids Template IDs.
     * @return array<int, int> templateid => plan count
     */
    public static function count_plans_by_template(array $templateids): array {
        global $DB;

        $templateids = array_values(array_unique(array_filter(array_map('intval', $templateids))));
        if (empty($templateids)) {
            return [];
        }

        $counts = [];
        foreach (array_chunk($templateids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'tpl');
            $sql = "SELECT templateid, COUNT(id) AS cnt
                      FROM {competency_plan}
                     WHERE templateid $insql
                  GROUP BY templateid";
            foreach ($DB->get_records_sql($sql, $params) as $row) {
                $counts[(int)$row->templateid] = (int)$row->cnt;
            }
        }
        return $counts;
    }

    /**
     * Count cohorts linked to each template.
     *
     * Single aggregate query (chunked only as a placeholder-limit safeguard).
     * Templates with no linked cohorts are absent from the result.
     *
     * @param int[] $templateids Template IDs.
     * @return array<int, int> templateid => linked cohort count
     */
    public static function count_cohorts_by_template(array $templateids): array {
        global $DB;

        $templateids = array_values(array_unique(array_filter(array_map('intval', $templateids))));
        if (empty($templateids)) {
            return [];
        }

        $counts = [];
        foreach (array_chunk($templateids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'tpl');
            $sql = "SELECT templateid, COUNT(id) AS cnt
                      FROM {competency_templatecohort}
                     WHERE templateid $insql
                  GROUP BY templateid";
            foreach ($DB->get_records_sql($sql, $params) as $row) {
                $counts[(int)$row->templateid] = (int)$row->cnt;
            }
        }
        return $counts;
    }

    /**
     * Whether the user can read competency frameworks or learning plan templates in a context.
     *
     * The Competency hub context selector is shared by the Structure (frameworks) and
     * Plans (templates) tabs, so a context is offered when either area is readable there.
     *
     * @param \context $context The context to test.
     * @return bool
     */
    public static function can_read_competency_context(\context $context): bool {
        return \core_competency\competency_framework::can_read_context($context)
            || \core_competency\template::can_read_context($context);
    }

    /**
     * Resolve the shared "context selector" state for the Competency hub.
     *
     * Both the Structure and Plans tabs are governed by a single context selector
     * (System or a course category). Centralising the resolution keeps the context
     * bar renderable and both tabs in agreement on the working context.
     *
     * The returned context is the system context unless a readable course category is
     * selected. The needscategory flag is true when course-category mode is active but
     * no readable category is chosen yet (guided empty state). Returned array keys:
     * context (\context), contexttype (string), categoryid (int), iscoursecat (bool),
     * needscategory (bool).
     *
     * @param string $contexttype Either 'system' or 'coursecat'.
     * @param int $categoryid Selected course category id (course-category mode only).
     * @return array The resolved context selector state.
     */
    public static function resolve_central_context(string $contexttype, int $categoryid): array {
        $contexttype = $contexttype === 'coursecat' ? 'coursecat' : 'system';
        $context = \core\context\system::instance();
        $iscoursecat = false;

        if ($contexttype === 'coursecat' && $categoryid > 0) {
            try {
                $candidate = \context_coursecat::instance($categoryid);
                if (self::can_read_competency_context($candidate)) {
                    $context = $candidate;
                    $iscoursecat = true;
                } else {
                    $categoryid = 0;
                }
            } catch (\moodle_exception $e) {
                $categoryid = 0;
            }
        }

        return [
            'context' => $context,
            'contexttype' => $contexttype,
            'categoryid' => $iscoursecat ? $categoryid : 0,
            'iscoursecat' => $iscoursecat,
            'needscategory' => $contexttype === 'coursecat' && !$iscoursecat,
        ];
    }

    /**
     * Course category options for the Competency hub context selector.
     *
     * Each readable category carries both adaptive counts so the client can switch the
     * displayed count between frameworks (Structure) and learning plans (Plans) without a
     * round-trip. Counts come from two aggregate queries (no N+1). Each entry holds:
     * id, name, selected, frameworkcount, templatecount, hasframeworks, hastemplates.
     *
     * @param int $selectedid Currently selected category id.
     * @return array The list of category options.
     */
    public static function central_category_options(int $selectedid): array {
        $catids = [];
        $catnames = [];
        foreach (\core_course_category::make_categories_list() as $catid => $catname) {
            try {
                if (self::can_read_competency_context(\context_coursecat::instance((int) $catid))) {
                    $catids[] = (int) $catid;
                    $catnames[(int) $catid] = $catname;
                }
            } catch (\moodle_exception $e) {
                continue;
            }
        }

        $frameworkcounts = self::count_frameworks_by_category($catids);
        $templatecounts = self::count_templates_by_category($catids);

        $options = [];
        foreach ($catids as $catid) {
            $frameworkcount = (int) ($frameworkcounts[$catid] ?? 0);
            $templatecount = (int) ($templatecounts[$catid] ?? 0);
            $options[] = [
                'id' => $catid,
                'name' => $catnames[$catid],
                'selected' => $catid === $selectedid,
                'frameworkcount' => $frameworkcount,
                'templatecount' => $templatecount,
                'hasframeworks' => $frameworkcount > 0,
                'hastemplates' => $templatecount > 0,
            ];
        }
        return $options;
    }

    /**
     * Pin the custom SCSS editor field to plain text in a modal form.
     *
     * The SCSS field is a textarea customfield rendered as a core editor element. On a new
     * instance the editor defaults to the rich (TinyMCE) editor, and once the value is plain it
     * still exposes a format selector. SCSS is always plain text, so this pins the editor value's
     * format to FORMAT_PLAIN (which renders the plain textarea editor). The now-redundant format
     * selector is hidden for the hub modals in styles.css. Call from definition_after_data().
     *
     * @param \MoodleQuickForm $mform The form being rendered.
     * @return void
     */
    public static function force_customscss_plain(\MoodleQuickForm $mform): void {
        $name = 'customfield_' . constants::CFIELD_CUSTOMSCSS . '_editor';
        if (!$mform->elementExists($name)) {
            return;
        }
        $element = $mform->getElement($name);
        $value = (array) $element->getValue();
        $value['text'] = $value['text'] ?? '';
        $value['format'] = FORMAT_PLAIN;
        $element->setValue($value);
    }

    /**
     * Server-side validation of the submitted custom SCSS (when the feature is enabled).
     *
     * Shared by the competency and template modal forms so both block saving invalid SCSS
     * identically. Returns an errors array (form field name => message) to merge into the
     * form's validation() result, or an empty array when SCSS is disabled, empty, or valid.
     *
     * @param array $data Submitted form data.
     * @return array Validation errors keyed by form field name.
     */
    public static function validate_customscss(array $data): array {
        if (!get_config('local_dimensions', 'enablecustomscss')) {
            return [];
        }
        [$scssvalue, $errorfield] = self::extract_submitted_scss($data);
        if (trim($scssvalue) === '') {
            return [];
        }
        $result = scss_manager::validate_scss($scssvalue);
        if ($result !== true) {
            return [$errorfield => $result];
        }
        return [];
    }

    /**
     * Extract the submitted custom SCSS value from the possible form field structures.
     *
     * The SCSS customfield may submit as an editor array/object ({text}/{value}) or a plain
     * string depending on the editor in use. Returns the SCSS text and the field name to map
     * any error onto.
     *
     * @param array $data Submitted form data.
     * @return array Two-element list: the SCSS value (string) and the field name (string).
     */
    public static function extract_submitted_scss(array $data): array {
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
