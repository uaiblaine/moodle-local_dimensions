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

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use core_competency\course_competency;
use core_competency\course_module_competency;
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

    /** @var int Page size for the lazy Structure tree (roots + children). */
    const STRUCTURE_PAGE_SIZE = 25;

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
        /* Provisioning is check-then-act and neither customfield_field nor
           customfield_category has a unique index (the shortname check only
           runs in the admin form, not on the programmatic create path), so two
           concurrent first requests would silently create duplicate field
           definitions. The lock serialises provisioning; the per-field
           existence checks below then see whatever the winner created. */
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_dimensions');
        $lock = $lockfactory->get_lock('provisionfields', 10);
        if (!$lock) {
            // Another request is provisioning right now; nothing to do here.
            return;
        }
        try {
            /* The plugin handlers are singletons (they override create()), so a
               category/field list cached earlier in this request — before the
               lock wait — would hide what the lock winner just created and the
               existence checks below would re-create duplicates. Re-read fresh. */
            self::get_handler($area)->reset_configuration_cache();

            // Display mode only for templates.
            if ($area === self::AREA_LP) {
                self::get_display_mode_field();
                self::get_subline_source_field();
                self::get_template_idnumber_field();
                self::get_showrelated_field();
                self::get_showrelatedlink_field();
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
        } finally {
            $lock->release();
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
     * Get or create the per-template "show related competencies" select field (lp area).
     *
     * @return field_controller|null
     */
    public static function get_showrelated_field(): ?field_controller {
        $shortname = constants::CFIELD_SHOWRELATED;
        $field = self::find_field_by_shortname($shortname, self::AREA_LP);
        if ($field) {
            return $field;
        }
        $options = constants::showrelated_options();
        $optionstext = [];
        foreach ($options as $key => $langstring) {
            $optionstext[] = (string) $langstring;
        }
        return self::create_custom_field(
            $shortname,
            'select',
            self::AREA_LP,
            new \lang_string('showrelated', 'local_dimensions'),
            [
                'options' => join("\n", $optionstext),
                'defaultvalue' => (string) $options[constants::SHOWRELATED_INHERIT],
            ],
            ''
        );
    }

    /**
     * Get or create the per-template "link related competencies" select field (lp area).
     *
     * @return field_controller|null
     */
    public static function get_showrelatedlink_field(): ?field_controller {
        $shortname = constants::CFIELD_SHOWRELATEDLINK;
        $field = self::find_field_by_shortname($shortname, self::AREA_LP);
        if ($field) {
            return $field;
        }
        $options = constants::showrelatedlink_options();
        $optionstext = [];
        foreach ($options as $key => $langstring) {
            $optionstext[] = (string) $langstring;
        }
        return self::create_custom_field(
            $shortname,
            'select',
            self::AREA_LP,
            new \lang_string('showrelatedlink', 'local_dimensions'),
            [
                'options' => join("\n", $optionstext),
                'defaultvalue' => (string) $options[constants::SHOWRELATED_INHERIT],
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
     * Resolve whether related competencies are shown for a template (plan -> global).
     *
     * @param int $templateid Learning plan template ID.
     * @return bool
     */
    public static function resolve_showrelated_for_template(int $templateid): bool {
        return self::resolve_lp_bool_toggle(
            $templateid,
            self::get_showrelated_field(),
            constants::showrelated_options(),
            (bool) get_config('local_dimensions', 'showrelated')
        );
    }

    /**
     * Resolve whether related-competency links are shown for a template (plan -> global).
     *
     * @param int $templateid Learning plan template ID.
     * @return bool
     */
    public static function resolve_showrelatedlink_for_template(int $templateid): bool {
        return self::resolve_lp_bool_toggle(
            $templateid,
            self::get_showrelatedlink_field(),
            constants::showrelatedlink_options(),
            (bool) get_config('local_dimensions', 'showrelatedlink')
        );
    }

    /**
     * Resolve an lp inherit/yes/no toggle field to a bool, falling back to the global default.
     *
     * @param int $templateid Learning plan template ID.
     * @param field_controller|null $field The customfield, or null.
     * @param array $options The inherit/yes/no options map (keys are the option ids).
     * @param bool $global The global default used when the field is unset or inherits.
     * @return bool
     */
    private static function resolve_lp_bool_toggle(
        int $templateid,
        ?field_controller $field,
        array $options,
        bool $global
    ): bool {
        if ($templateid <= 0 || !$field) {
            return $global;
        }
        $allowed = array_keys($options);
        $resolved = constants::SHOWRELATED_INHERIT;
        $fields = \core_customfield\api::get_instance_fields_data([$field->get('id') => $field], $templateid);
        foreach ($fields as $data) {
            $value = $data->get_value();
            if (is_int($value) && isset($allowed[$value - 1])) {
                $value = $allowed[$value - 1];
            }
            $value = (string) $value;
            if ($value !== '' && in_array($value, $allowed, true)) {
                $resolved = $value;
                break;
            }
        }
        if ($resolved === constants::SHOWRELATED_INHERIT) {
            return $global;
        }
        return $resolved === constants::SHOWRELATED_YES;
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
     * Read a competency's stored custom-field values as CSV tokens for export.
     *
     * Only real stored values are emitted (never a synthesised default): text for the
     * colours and SCSS, the option label for the admin-defined selects (tag1/tag2/type),
     * and the canonical option key for the cascade selects (enrollmentfilter/singlecourseredirect).
     * The picture fields are file-backed and deliberately skipped (not round-trippable in CSV).
     *
     * @param int $competencyid Competency id.
     * @return array<string, string> Keyed by cf_* column token (see framework_csv_serializer::CF_HEADERS).
     */
    public static function export_competency_customfields(int $competencyid): array {
        $result = array_fill_keys([
            'cf_bgcolor', 'cf_textcolor', 'cf_tag1', 'cf_tag2', 'cf_type',
            'cf_enrollmentfilter', 'cf_singlecourseredirect', 'cf_customscss',
        ], '');
        if ($competencyid <= 0) {
            return $result;
        }
        $result['cf_bgcolor'] = self::read_competency_text_cf($competencyid, constants::CFIELD_CUSTOMBGCOLOR);
        $result['cf_textcolor'] = self::read_competency_text_cf($competencyid, constants::CFIELD_CUSTOMTEXTCOLOR);
        $result['cf_customscss'] = self::read_competency_text_cf($competencyid, constants::CFIELD_CUSTOMSCSS);
        $result['cf_tag1'] = self::read_competency_select_label($competencyid, constants::CFIELD_TAG1);
        $result['cf_tag2'] = self::read_competency_select_label($competencyid, constants::CFIELD_TAG2);
        $result['cf_type'] = self::read_competency_select_label($competencyid, constants::CFIELD_TYPE);
        $result['cf_enrollmentfilter'] = self::read_competency_select_key(
            $competencyid,
            constants::CFIELD_ENROLLMENTFILTER,
            array_keys(constants::enrollmentfilter_options())
        );
        $result['cf_singlecourseredirect'] = self::read_competency_select_key(
            $competencyid,
            constants::CFIELD_SINGLECOURSEREDIRECT,
            array_keys(constants::singlecourseredirect_options())
        );
        return $result;
    }

    /**
     * Convert CSV cf_* tokens into the customfield_* form-data an instance_form_save expects.
     *
     * Every managed select is set explicitly (index 0 = cleared) so an empty CSV cell clears the
     * value rather than leaving a stale one; selects map their label/key to the stored 1-based
     * index, falling back to a bare numeric cell. SCSS is set only when its column is present.
     *
     * @param array $cfrow Map of cf_* token => raw CSV cell value.
     * @return array Form-data keyed by customfield_<shortname> (+ _editor for the SCSS textarea).
     */
    public static function customfields_to_formdata(array $cfrow): array {
        $data = [];
        // Only set a customfield_* key when its column is present in the CSV: an ABSENT column
        // leaves the field untouched, while a present-but-empty cell clears it. (An unconditional
        // set would wipe fields whose column a partial/hand-authored CSV happens to omit.)
        if (array_key_exists('cf_bgcolor', $cfrow)) {
            $data['customfield_' . constants::CFIELD_CUSTOMBGCOLOR] = (string) $cfrow['cf_bgcolor'];
        }
        if (array_key_exists('cf_textcolor', $cfrow)) {
            $data['customfield_' . constants::CFIELD_CUSTOMTEXTCOLOR] = (string) $cfrow['cf_textcolor'];
        }
        if (array_key_exists('cf_tag1', $cfrow)) {
            $data['customfield_' . constants::CFIELD_TAG1] =
                self::select_label_to_index(constants::CFIELD_TAG1, (string) $cfrow['cf_tag1']);
        }
        if (array_key_exists('cf_tag2', $cfrow)) {
            $data['customfield_' . constants::CFIELD_TAG2] =
                self::select_label_to_index(constants::CFIELD_TAG2, (string) $cfrow['cf_tag2']);
        }
        if (array_key_exists('cf_type', $cfrow)) {
            $data['customfield_' . constants::CFIELD_TYPE] =
                self::select_label_to_index(constants::CFIELD_TYPE, (string) $cfrow['cf_type']);
        }
        if (array_key_exists('cf_enrollmentfilter', $cfrow)) {
            $data['customfield_' . constants::CFIELD_ENROLLMENTFILTER] = self::select_key_to_index(
                array_keys(constants::enrollmentfilter_options()),
                (string) $cfrow['cf_enrollmentfilter']
            );
        }
        if (array_key_exists('cf_singlecourseredirect', $cfrow)) {
            $data['customfield_' . constants::CFIELD_SINGLECOURSEREDIRECT] = self::select_key_to_index(
                array_keys(constants::singlecourseredirect_options()),
                (string) $cfrow['cf_singlecourseredirect']
            );
        }
        if (array_key_exists('cf_customscss', $cfrow)) {
            $data['customfield_' . constants::CFIELD_CUSTOMSCSS . '_editor'] = [
                'text' => (string) $cfrow['cf_customscss'],
                'format' => FORMAT_PLAIN,
            ];
        }
        return $data;
    }

    /**
     * The data_controller carrying a competency's real stored value for a field, or null.
     *
     * @param int $competencyid Competency id.
     * @param string $shortname Custom-field shortname.
     * @return \core_customfield\data_controller|null
     */
    private static function read_competency_cf_data(int $competencyid, string $shortname): ?\core_customfield\data_controller {
        $field = self::find_field_by_shortname($shortname, self::AREA_COMPETENCY);
        if (!$field) {
            return null;
        }
        $datas = \core_customfield\api::get_instance_fields_data([$field->get('id') => $field], $competencyid);
        foreach ($datas as $data) {
            if ((int) $data->get('id') > 0) {
                return $data;
            }
        }
        return null;
    }

    /**
     * A competency text custom-field value, or empty string when unset.
     *
     * @param int $competencyid Competency id.
     * @param string $shortname Custom-field shortname.
     * @return string
     */
    private static function read_competency_text_cf(int $competencyid, string $shortname): string {
        $data = self::read_competency_cf_data($competencyid, $shortname);
        return $data ? (string) $data->get_value() : '';
    }

    /**
     * The option label of a competency select custom-field, or empty string when unset.
     *
     * @param int $competencyid Competency id.
     * @param string $shortname Custom-field shortname.
     * @return string
     */
    public static function read_competency_select_label(int $competencyid, string $shortname): string {
        $data = self::read_competency_cf_data($competencyid, $shortname);
        if (!$data) {
            return '';
        }
        $index = (int) $data->get_value();
        if ($index <= 0) {
            return '';
        }
        $options = self::select_raw_options($data->get_field());
        return $options[$index - 1] ?? '';
    }

    /**
     * The canonical option key (from $keys) of a competency cascade select, or empty string.
     *
     * @param int $competencyid Competency id.
     * @param string $shortname Custom-field shortname.
     * @param array $keys Ordered option keys matching the field's option order.
     * @return string
     */
    private static function read_competency_select_key(int $competencyid, string $shortname, array $keys): string {
        $data = self::read_competency_cf_data($competencyid, $shortname);
        if (!$data) {
            return '';
        }
        $index = (int) $data->get_value();
        if ($index <= 0) {
            return '';
        }
        return $keys[$index - 1] ?? '';
    }

    /**
     * Map a select option label to its stored 1-based index (0 = none), with a numeric fallback.
     *
     * @param string $shortname Custom-field shortname.
     * @param string $label Option label from the CSV cell.
     * @return int
     */
    private static function select_label_to_index(string $shortname, string $label): int {
        $label = trim($label);
        if ($label === '') {
            return 0;
        }
        $field = self::find_field_by_shortname($shortname, self::AREA_COMPETENCY);
        if ($field) {
            $pos = array_search($label, self::select_raw_options($field), true);
            if ($pos !== false) {
                return $pos + 1;
            }
        }
        return ctype_digit($label) ? (int) $label : 0;
    }

    /**
     * Map a canonical option key to its 1-based index within $keys (0 = none), numeric fallback.
     *
     * @param array $keys Ordered option keys matching the field's option order.
     * @param string $key Key from the CSV cell.
     * @return int
     */
    private static function select_key_to_index(array $keys, string $key): int {
        $key = trim($key);
        if ($key === '') {
            return 0;
        }
        $pos = array_search($key, $keys, true);
        if ($pos !== false) {
            return $pos + 1;
        }
        return ctype_digit($key) ? (int) $key : 0;
    }

    /**
     * The raw (unformatted, newline-split) option list of a select custom-field.
     *
     * @param field_controller $field Select field controller.
     * @return string[] Zero-based list of option labels.
     */
    private static function select_raw_options(field_controller $field): array {
        $optstr = (string) $field->get_configdata_property('options');
        if (trim($optstr) === '') {
            return [];
        }
        return preg_split("/\s*\n\s*/", trim($optstr), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Resolve the effective enrollment filter when viewing a competency through a plan.
     *
     * Cascade: competency-level customfield -> template-level customfield ->
     * site-wide `local_dimensions/enrollmentfilter`. Pass `templateid = 0` to skip the
     * template layer (competency -> global): callers do this for a competency that is not
     * in the plan (e.g. a related-competency link), so the plan's rule does not leak onto it.
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
     * Whether a competency belongs to a plan (directly or via its template).
     *
     * Used to decide if the plan layer of the cascade applies: a related-competency page reached
     * from the accordion may point at a competency that is not in the plan, in which case only the
     * competency's own value and the global setting apply (the plan layer is skipped).
     *
     * @param int $competencyid Competency id.
     * @param \core_competency\plan $plan The plan.
     * @return bool
     */
    public static function competency_in_plan(int $competencyid, \core_competency\plan $plan): bool {
        foreach (\core_competency\api::list_plan_competencies($plan->get('id')) as $pc) {
            if ((int) $pc->competency->get('id') === $competencyid) {
                return true;
            }
        }
        return false;
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
     * Build ancestor breadcrumbs for a set of competencies in one batch query.
     *
     * Each competency's `path` holds only its ancestors with a leading sentinel 0
     * (root `/0/`, child of X `/0/<X>/`), so the breadcrumb is the ancestor shortnames
     * root to parent. Shared by search_structure and list_related_competencies.
     *
     * @param array $pathsbyid Map of competency id to its `path` string.
     * @param \context $context Context used to format the ancestor shortnames.
     * @return array Map of competency id to ['path' => string, 'pathids' => int[]].
     */
    public static function competency_breadcrumbs(array $pathsbyid, \context $context): array {
        global $DB;

        $perid = [];
        $ancestorids = [];
        foreach ($pathsbyid as $id => $path) {
            $segments = array_values(array_filter(
                explode('/', trim((string) $path, '/')),
                static fn(string $segment): bool => $segment !== '' && $segment !== '0'
            ));
            $ancestors = array_map('intval', $segments);
            $perid[(int) $id] = $ancestors;
            foreach ($ancestors as $ancestorid) {
                $ancestorids[$ancestorid] = true;
            }
        }

        $names = [];
        if (!empty($ancestorids)) {
            $names = $DB->get_records_list('competency', 'id', array_keys($ancestorids), '', 'id, shortname');
        }

        $result = [];
        foreach ($perid as $id => $ancestors) {
            $crumbs = [];
            foreach ($ancestors as $ancestorid) {
                if (isset($names[$ancestorid])) {
                    $crumbs[] = format_string($names[$ancestorid]->shortname, true, ['context' => $context]);
                }
            }
            $result[$id] = [
                'path' => implode(' / ', $crumbs),
                'pathids' => $ancestors,
            ];
        }

        return $result;
    }

    /**
     * Ensure the PostgreSQL `unaccent` extension is available, creating it when missing.
     *
     * On non-PostgreSQL databases this is a no-op returning false (accent-insensitivity there
     * comes from the collation, not unaccent()). On PostgreSQL it checks the pg_extension
     * catalogue and, if unaccent is absent, attempts CREATE EXTENSION IF NOT EXISTS unaccent;
     * if creation fails (insufficient privilege / contrib missing) it returns false so callers
     * can degrade to an accent-sensitive search rather than erroring.
     *
     * @return bool True when unaccent() can be used in SQL (PostgreSQL only).
     */
    public static function ensure_unaccent(): bool {
        global $DB;
        if ($DB->get_dbfamily() !== 'postgres') {
            return false;
        }
        // Check the catalogue on each call rather than caching: PostgreSQL PHPUnit wraps each test
        // in a rolled-back transaction, so a cached "created" flag would go stale once the CREATE
        // EXTENSION is undone, and a later query would reference a now-missing unaccent().
        if ($DB->record_exists_sql("SELECT 1 FROM pg_extension WHERE extname = 'unaccent'")) {
            return true;
        }
        try {
            $DB->execute('CREATE EXTENSION IF NOT EXISTS unaccent');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Return a case- and accent-insensitive LIKE fragment that works on MySQL/MariaDB and
     * PostgreSQL. On PostgreSQL it wraps both operands in unaccent() (when the extension is
     * available; otherwise it falls back to an accent-sensitive comparison); on other databases
     * it relies on the collation via core sql_like(). The bound parameter value must still be
     * built with sql_like_escape() and the surrounding wildcards by the caller.
     *
     * @param string $fieldname The column or SQL expression to match.
     * @param string $param The bound parameter placeholder (e.g. ':q1').
     * @return string The SQL LIKE fragment.
     */
    public static function sql_like_ai(string $fieldname, string $param): string {
        global $DB;
        if (self::ensure_unaccent()) {
            return "unaccent($fieldname) ILIKE unaccent($param) ESCAPE '\\'";
        }
        return $DB->sql_like($fieldname, $param, false, false);
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
     * Read and sanitise the Competency hub's per-user view-state preferences.
     *
     * Returns a two-key array: 'nav' (last tab, context, category and the selected framework /
     * template) and 'display' (the visibility toggles). Each is decoded from its JSON user
     * preference and validated against defaults, so a missing, empty or corrupt preference
     * always yields safe defaults. Booleans/ints are coerced; unknown values fall back.
     *
     * @return array ['nav' => array, 'display' => array]
     */
    public static function get_central_prefs(): array {
        $navraw = json_decode((string) get_user_preferences(constants::PREF_CENTRAL_NAV, ''), true);
        $displayraw = json_decode((string) get_user_preferences(constants::PREF_CENTRAL_DISPLAY, ''), true);
        if (!is_array($navraw)) {
            $navraw = [];
        }
        if (!is_array($displayraw)) {
            $displayraw = [];
        }

        $tab = (string) ($navraw['tab'] ?? 'frameworks');
        if (!in_array($tab, ['frameworks', 'structure', 'plans'], true)) {
            $tab = 'frameworks';
        }
        $nav = [
            'tab' => $tab,
            'contexttype' => ($navraw['contexttype'] ?? 'system') === 'coursecat' ? 'coursecat' : 'system',
            'categoryid' => (int) ($navraw['categoryid'] ?? 0),
            'frameworkid' => (int) ($navraw['frameworkid'] ?? 0),
            'templateid' => (int) ($navraw['templateid'] ?? 0),
        ];

        $dispbool = static function (array $src, string $key, bool $default): bool {
            return array_key_exists($key, $src) ? (bool) $src[$key] : $default;
        };
        $structsrc = is_array($displayraw['structure'] ?? null) ? $displayraw['structure'] : [];
        $listsrc = is_array($displayraw['planslist'] ?? null) ? $displayraw['planslist'] : [];
        $detailsrc = is_array($displayraw['plansdetail'] ?? null) ? $displayraw['plansdetail'] : [];
        $panelsrc = is_array($displayraw['panels'] ?? null) ? $displayraw['panels'] : [];
        $display = [
            'structure' => [
                'tax' => $dispbool($structsrc, 'tax', false),
                'id' => $dispbool($structsrc, 'id', false),
                'rule' => $dispbool($structsrc, 'rule', true),
                'showhidden' => $dispbool($structsrc, 'showhidden', false),
            ],
            'planslist' => [
                'id' => $dispbool($listsrc, 'id', false),
                'duedate' => $dispbool($listsrc, 'duedate', false),
            ],
            'plansdetail' => [
                'tax' => $dispbool($detailsrc, 'tax', false),
                'path' => $dispbool($detailsrc, 'path', false),
                'id' => $dispbool($detailsrc, 'id', false),
            ],
            'panels' => [
                'structure' => $dispbool($panelsrc, 'structure', true),
                'planslist' => $dispbool($panelsrc, 'planslist', true),
                'plansdetail' => $dispbool($panelsrc, 'plansdetail', true),
            ],
            'plansshowdisabled' => $dispbool($displayraw, 'plansshowdisabled', false),
            'frameworksshowhidden' => $dispbool($displayraw, 'frameworksshowhidden', false),
        ];

        return ['nav' => $nav, 'display' => $display];
    }

    /**
     * Delete every user preference this plugin owns, for all users.
     *
     * Moodle does not purge a component's user_preferences rows on uninstall (the table has no
     * component column), so the uninstall hook calls this to avoid orphaned rows. Deletes by the
     * plugin's frankenstyle name prefix.
     *
     * @return void
     */
    public static function purge_user_preferences(): void {
        global $DB;
        $DB->delete_records_select(
            'user_preferences',
            $DB->sql_like('name', ':pattern'),
            ['pattern' => $DB->sql_like_escape('local_dimensions_') . '%']
        );
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
     * Shape a set of sibling competency records into Structure-tree nodes.
     *
     * Used by both the server-rendered first page of roots and the browse_structure web
     * service, so a server-rendered node and a lazily-fetched node are identical. Each node
     * is: id, parentid (int), shortname, idnumber, taxonomy (string), coursecount,
     * activitycount, templatecount (int), depth, indent (int), haschildren (bool),
     * canmanage (bool), ruletype, ruleconfig (string|null), ruleoutcome (int),
     * rulelabel (string). Four batch queries (has-children, linked-course counts,
     * linked-activity counts, linked-template counts) cover the whole page; depth and
     * taxonomy are derived from each record's path.
     *
     * @param array $records Sibling competency persistent objects (core_competency\competency).
     * @param competency_framework $framework The owning framework (for taxonomy + context).
     * @param \context $context Context for format_string.
     * @return array List of node arrays in input order.
     */
    public static function structure_nodes(array $records, competency_framework $framework, \context $context): array {
        global $DB;
        if (empty($records)) {
            return [];
        }
        $ids = array_map(static fn(competency $c): int => (int) $c->get('id'), $records);

        // Batch: which of these ids are themselves a parent.
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'p');
        $parents = $DB->get_fieldset_select('competency', 'DISTINCT parentid', "parentid $insql", $inparams);
        $haschildren = [];
        foreach ($parents as $pid) {
            $haschildren[(int) $pid] = true;
        }

        // Resolve the viewer's manageable-course scope once; both the course-link and
        // activity-link counts reuse it (null = site admin / no restriction, [] = none).
        $manageable = self::manageable_course_ids();

        // Batch: linked-course counts, scoped to the courses the viewer may manage.
        $counts = [];
        if ($manageable !== []) {
            [$csql, $cparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cid');
            $where = "competencyid $csql";
            if ($manageable !== null) {
                [$ccsql, $ccparams] = $DB->get_in_or_equal($manageable, SQL_PARAMS_NAMED, 'mgc');
                $where .= " AND courseid $ccsql";
                $cparams += $ccparams;
            }
            $counts = $DB->get_records_sql_menu(
                "SELECT competencyid, COUNT(1)
                   FROM {competency_coursecomp}
                  WHERE $where
               GROUP BY competencyid",
                $cparams
            );
        }

        // Batch: linked-activity counts, scoped to the modules in courses the viewer may manage.
        $actcounts = [];
        if ($manageable !== []) {
            [$msql, $mparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'mid');
            $where = "mc.competencyid $msql";
            if ($manageable !== null) {
                [$mcsql, $mcparams] = $DB->get_in_or_equal($manageable, SQL_PARAMS_NAMED, 'mgm');
                $where .= " AND cm.course $mcsql";
                $mparams += $mcparams;
            }
            $actcounts = $DB->get_records_sql_menu(
                "SELECT mc.competencyid, COUNT(1)
                   FROM {competency_modulecomp} mc
                   JOIN {course_modules} cm ON cm.id = mc.cmid
                  WHERE $where
               GROUP BY mc.competencyid",
                $mparams
            );
        }

        // Batch: linked learning plan template counts (how many templates bundle each competency).
        [$tsql, $tparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'tid');
        $tplcounts = $DB->get_records_sql_menu(
            "SELECT competencyid, COUNT(1)
               FROM {competency_templatecomp}
              WHERE competencyid $tsql
           GROUP BY competencyid",
            $tparams
        );

        $canmanage = has_capability('moodle/competency:competencymanage', $framework->get_context());

        // Batch: resolve scale display names once. A competency with scaleid 0 inherits the
        // framework default; the detail pane shows that effective scale.
        $frameworkscaleid = (int) $framework->get('scaleid');
        $scaleids = [$frameworkscaleid];
        foreach ($records as $record) {
            $sid = (int) $record->get('scaleid');
            if ($sid > 0) {
                $scaleids[] = $sid;
            }
        }
        $scaleids = array_values(array_unique(array_filter($scaleids)));
        $scalenames = [];
        if (!empty($scaleids)) {
            foreach ($DB->get_records_list('scale', 'id', $scaleids) as $scale) {
                $scalenames[(int) $scale->id] = format_string($scale->name, true, ['context' => $context]);
            }
        }

        // Batch: per-competency custom-field data for this page — the type/tag1/tag2 select
        // labels (metadata chips) plus the custom background/text colours the detail header
        // wears (mirrors the Plans tab), in one grouped query.
        $cfdata = self::structure_customfield_data($ids);

        $nodes = [];
        foreach ($records as $record) {
            $id = (int) $record->get('id');
            $depth = self::path_depth((string) $record->get('path'));
            $level = $depth + 1;
            $taxonomy = $framework->get_taxonomy($level) ?: competency_framework::TAXONOMY_COMPETENCY;
            $scaleid = (int) $record->get('scaleid');
            $effectivescaleid = $scaleid > 0 ? $scaleid : $frameworkscaleid;
            $description = format_text(
                (string) $record->get('description'),
                (int) $record->get('descriptionformat'),
                ['context' => $context]
            );
            // Keep the formatted HTML for the detail pane, but treat a description
            // that is empty once tags are stripped as blank so the pane can hide it.
            if (trim(strip_tags($description)) === '') {
                $description = '';
            }
            $nodes[] = [
                'id' => $id,
                'parentid' => (int) $record->get('parentid'),
                'shortname' => format_string($record->get('shortname'), true, ['context' => $context]),
                'idnumber' => (string) $record->get('idnumber'),
                'taxonomy' => get_string('taxonomy_' . $taxonomy, 'core_competency'),
                'scale' => (string) ($scalenames[$effectivescaleid] ?? ''),
                'description' => $description,
                'coursecount' => (int) ($counts[$id] ?? 0),
                'activitycount' => (int) ($actcounts[$id] ?? 0),
                'templatecount' => (int) ($tplcounts[$id] ?? 0),
                'depth' => $depth,
                'indent' => $depth * 22,
                'haschildren' => !empty($haschildren[$id]),
                'canmanage' => $canmanage,
                'ruletype' => $record->get('ruletype'),
                'ruleoutcome' => (int) $record->get('ruleoutcome'),
                'ruleconfig' => $record->get('ruleconfig'),
                'rulelabel' => self::get_competency_rule_label($record->get('ruletype')),
                'type' => (string) ($cfdata[$id][constants::CFIELD_TYPE] ?? ''),
                'tag1' => (string) ($cfdata[$id][constants::CFIELD_TAG1] ?? ''),
                'tag2' => (string) ($cfdata[$id][constants::CFIELD_TAG2] ?? ''),
                'bgcolor' => (string) ($cfdata[$id][constants::CFIELD_CUSTOMBGCOLOR] ?? ''),
                'textcolor' => (string) ($cfdata[$id][constants::CFIELD_CUSTOMTEXTCOLOR] ?? ''),
            ];
        }
        return $nodes;
    }

    /**
     * Batch-read per-competency custom-field data for a set of competencies: the type/tag1/tag2
     * select-field labels (metadata chips) and the custom background/text colours (detail header).
     *
     * The selects store a 1-based option index (`intvalue`) plus the option list in `configdata`;
     * the colours are text fields whose `value` holds the hex. One grouped query pulls the rows
     * for the whole page.
     *
     * @param array $ids Competency ids.
     * @return array Map keyed by competency id then shortname => decoded value (only set values).
     */
    private static function structure_customfield_data(array $ids): array {
        global $DB;

        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $colors = [constants::CFIELD_CUSTOMBGCOLOR, constants::CFIELD_CUSTOMTEXTCOLOR];
        $shortnames = array_merge(
            [constants::CFIELD_TYPE, constants::CFIELD_TAG1, constants::CFIELD_TAG2],
            $colors
        );
        [$idsql, $idparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cid');
        [$snsql, $snparams] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED, 'sn');
        $params = $idparams + $snparams + [
            'component' => 'local_dimensions',
            'area' => self::AREA_COMPETENCY,
        ];

        // Direct query against the core {customfield_*} tables — intentional for the grouped
        // batch shape (the customfield API has no batch read across instances). Mirrors
        // template_metadata_cache; re-validate if core changes the customfield schema.
        $sql = "SELECT d.id AS dataid, d.instanceid, f.shortname, f.configdata, d.intvalue, d.value
                  FROM {customfield_data} d
                  JOIN {customfield_field} f ON f.id = d.fieldid
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE d.instanceid $idsql
                   AND f.shortname $snsql
                   AND c.component = :component
                   AND c.area = :area";

        // Membership map for the colour shortnames; isset() (never !empty) reads it, since
        // array_flip values start at 0.
        $iscolor = array_flip($colors);
        $result = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $instanceid = (int) $row->instanceid;
            if (isset($iscolor[$row->shortname])) {
                $hex = self::normalise_hex_color((string) ($row->value ?? ''));
                if ($hex !== '') {
                    $result[$instanceid][$row->shortname] = $hex;
                }
                continue;
            }
            $label = self::decode_customfield_select_label($row);
            if ($label !== '') {
                $result[$instanceid][$row->shortname] = $label;
            }
        }
        return $result;
    }

    /**
     * Validate and normalise a hex colour string to a leading-'#' form, or '' when invalid.
     *
     * @param string $value Raw stored colour value.
     * @return string Normalised colour with a leading '#', or '' when not a valid 3/6-digit hex.
     */
    private static function normalise_hex_color(string $value): string {
        $value = trim($value);
        if (!preg_match('/^#?([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
            return '';
        }
        return $value[0] === '#' ? $value : '#' . $value;
    }

    /**
     * Decode a select custom field's option label from a customfield_data row.
     *
     * @param \stdClass $row Row with intvalue (1-based option index) and configdata (options JSON).
     * @return string The option label at the stored index, or '' when unset/out of range.
     */
    private static function decode_customfield_select_label(\stdClass $row): string {
        $index = isset($row->intvalue) ? (int) $row->intvalue : 0;
        if ($index <= 0 || empty($row->configdata)) {
            return '';
        }
        $config = json_decode($row->configdata, true);
        if (!is_array($config) || empty($config['options'])) {
            return '';
        }
        $options = explode("\n", $config['options']);
        return isset($options[$index - 1]) ? trim($options[$index - 1]) : '';
    }

    /**
     * Compute a competency's tree depth from its path (root = 0).
     *
     * Core stores competency.path as the ancestor chain only (with a leading 0) and never the node's
     * own id: a root is /0/, a child of root is /0/<rootid>/, a grandchild is /0/<rootid>/<childid>/.
     * The depth therefore equals the number of ancestor competencies, i.e. the count of non-zero path
     * segments.
     *
     * @param string $path The competency.path value (e.g. /0/5/ for a child of competency 5).
     * @return int Depth, 0 for a root.
     */
    private static function path_depth(string $path): int {
        $segments = array_filter(
            explode('/', trim($path, '/')),
            static fn(string $segment): bool => $segment !== '' && $segment !== '0'
        );
        return count($segments);
    }

    /**
     * Course-competency rule-outcome options for a select, localized via the core list.
     *
     * @return array List of ['value' => int, 'label' => string].
     */
    public static function course_outcome_options(): array {
        $options = [];
        foreach (course_competency::get_ruleoutcome_list() as $value => $label) {
            $options[] = ['value' => (int) $value, 'label' => (string) $label];
        }
        return $options;
    }

    /**
     * Course-module-competency rule-outcome options for a select, localized via the core list.
     *
     * @return array List of ['value' => int, 'label' => string].
     */
    public static function module_outcome_options(): array {
        $options = [];
        foreach (course_module_competency::get_ruleoutcome_list() as $value => $label) {
            $options[] = ['value' => (int) $value, 'label' => (string) $label];
        }
        return $options;
    }

    /**
     * Ids of courses where the current user may manage course competencies.
     *
     * Returns null when the user is a site admin (no restriction — every course is manageable),
     * an empty array when the user manages none, or the list of manageable course ids otherwise.
     *
     * @return array|null Manageable course ids, or null for "no restriction".
     */
    public static function manageable_course_ids(): ?array {
        global $USER;
        if (is_siteadmin()) {
            return null;
        }
        $courses = get_user_capability_course(
            'moodle/competency:coursecompetencymanage',
            $USER->id,
            true,
            'shortname'
        );
        if ($courses === false) {
            return [];
        }
        return array_map(static fn($course): int => (int) $course->id, $courses);
    }

    /**
     * Build a SQL constraint restricting a course-id column to the courses the current user may manage.
     *
     * Wraps manageable_course_ids() into a reusable WHERE fragment. Returns one of:
     * - ['', []]                            no restriction (site admin) — every row matches;
     * - null                                the user manages no course — the caller returns an empty result;
     * - [" AND <column> <insql>", $params]  restricted to the manageable course ids.
     *
     * @param string $column Fully-qualified course-id column (e.g. 'courseid' or 'cc.courseid').
     * @param string $prefix Unique named-parameter prefix to avoid collisions with the caller's params.
     * @return array|null [sql fragment, params], or null when the user manages no course.
     */
    public static function manageable_course_constraint(string $column, string $prefix): ?array {
        global $DB;
        $manageable = self::manageable_course_ids();
        if ($manageable === null) {
            return ['', []];
        }
        if ($manageable === []) {
            return null;
        }
        [$insql, $params] = $DB->get_in_or_equal($manageable, SQL_PARAMS_NAMED, $prefix);
        return [" AND $column $insql", $params];
    }

    /**
     * Build the framework management rows for a context (Frameworks tab).
     *
     * @param \context $context The resolved page context (system or course category).
     * @param bool $includehidden Whether to include hidden frameworks (default visible-only).
     * @return array List of ['id' => int, 'shortname' => string, 'idnumber' => string,
     *               'description' => string, 'competencycount' => int, 'visible' => bool,
     *               'deletable' => bool, 'canmanage' => bool].
     */
    public static function framework_rows(\context $context, bool $includehidden = false): array {
        $rows = [];
        foreach (api::list_frameworks('shortname', 'ASC', 0, 0, $context, 'self', !$includehidden) as $framework) {
            if (!competency_framework::can_read_context($framework->get_context())) {
                continue;
            }
            $id = (int) $framework->get('id');
            $competencyids = competency::get_ids_by_frameworkid($id);
            // Plain, single-line description for the card (the template truncates it with an
            // ellipsis and keeps the full text in a title tooltip).
            $description = content_to_text(
                (string) $framework->get('description'),
                (int) $framework->get('descriptionformat')
            );
            $description = trim(preg_replace('/\s+/', ' ', $description));
            $rows[] = [
                'id' => $id,
                'shortname' => format_string($framework->get('shortname')),
                'idnumber' => s($framework->get('idnumber')),
                'description' => shorten_text($description, 300),
                'competencycount' => count($competencyids),
                'visible' => (bool) $framework->get('visible'),
                'deletable' => competency::can_all_be_deleted($competencyids),
                'canmanage' => competency_framework::can_manage_context($framework->get_context()),
            ];
        }
        return $rows;
    }

    /**
     * Whether a framework scale configuration has at least one default and one proficient value.
     *
     * @param string $json The scaleconfiguration JSON ([{scaleid}, {id, scaledefault, proficient}, ...]).
     * @return bool
     */
    public static function scaleconfig_is_complete(string $json): bool {
        $config = json_decode($json);
        if (!is_array($config) || count($config) < 2) {
            return false;
        }
        array_shift($config);
        $hasdefault = false;
        $hasproficient = false;
        foreach ($config as $value) {
            if (!empty($value->scaledefault)) {
                $hasdefault = true;
            }
            if (!empty($value->proficient)) {
                $hasproficient = true;
            }
        }
        return $hasdefault && $hasproficient;
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

    /**
     * Darken a hex colour by mixing it towards black.
     *
     * Used to build the Learning plans detail-header gradient, which shades the
     * template's custom background colour progressively darker (mirrors the design
     * kit: base 0% -> ~16% at 48% -> ~34% at the end). Accepts 3- or 6-digit hex
     * with or without a leading '#'; an unparseable value falls back to black so
     * the caller always gets a valid colour to emit.
     *
     * @param string $hex Source colour, e.g. "#2274c6" or "2274c6" or "#27c".
     * @param float $amount Fraction to darken by, 0 (unchanged) to 1 (black).
     * @return string Normalised "#rrggbb" darkened colour.
     */
    public static function darken_hex(string $hex, float $amount): string {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            return '#000000';
        }

        $amount = max(0.0, min(1.0, $amount));
        $red = (int) round(hexdec(substr($hex, 0, 2)) * (1 - $amount));
        $green = (int) round(hexdec(substr($hex, 2, 2)) * (1 - $amount));
        $blue = (int) round(hexdec(substr($hex, 4, 2)) * (1 - $amount));

        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }

    /**
     * Copy all plugin-side data from one learning plan template to another.
     *
     * Complements core's api::duplicate_template(), which copies only the
     * template row and its competency links: this clones the lp-area custom
     * field rows (with any files embedded in them) and the built-in card /
     * background images. Cohort links are intentionally NOT copied — core's
     * sync_plans_from_template_cohorts_task would mass-create plans for every
     * cohort member on the next cron run.
     *
     * The customfield_data rows are cloned by direct SQL rather than through
     * the customfield handler: handler reads silently skip fields whose type
     * plugin is disabled (e.g. legacy customfield_picture rows), while the
     * metadata cache still serves their files.
     *
     * @param int $sourceid Source template id.
     * @param int $targetid Target (freshly duplicated) template id.
     * @return array Copy counts: keys fields (customfield rows) and files.
     */
    public static function copy_template_plugin_data(int $sourceid, int $targetid): array {
        global $DB;

        $copiedfields = 0;
        $copiedfiles = 0;

        $fs = get_file_storage();
        $syscontextid = \core\context\system::instance()->id;

        // Files embedded in a custom field's data are keyed by the DATA row id,
        // not the instance id, under the field type's own component.
        $embeddedfileareas = [
            'textarea' => ['customfield_textarea', 'value'],
            'picture' => ['customfield_picture', 'file'],
        ];

        $sql = "SELECT d.*, f.type AS fieldtype
                  FROM {customfield_data} d
                  JOIN {customfield_field} f ON f.id = d.fieldid
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE c.component = :component AND c.area = :area AND d.instanceid = :instanceid";
        $rows = $DB->get_records_sql($sql, [
            'component' => 'local_dimensions',
            'area' => self::AREA_LP,
            'instanceid' => $sourceid,
        ]);
        foreach ($rows as $row) {
            $fieldtype = $row->fieldtype;
            $olddataid = (int) $row->id;
            unset($row->id, $row->fieldtype);
            $row->instanceid = $targetid;
            $row->timecreated = time();
            $row->timemodified = time();
            /* The unique index instanceid-fieldid-component-area-itemid forbids a
               second row; the target may already have one (re-run, or the
               observer's form-repost path), so replace instead of colliding. */
            $DB->delete_records('customfield_data', ['fieldid' => $row->fieldid, 'instanceid' => $targetid]);
            $newdataid = (int) $DB->insert_record('customfield_data', $row);
            $copiedfields++;

            if (isset($embeddedfileareas[$fieldtype])) {
                [$component, $filearea] = $embeddedfileareas[$fieldtype];
                $files = $fs->get_area_files((int) $row->contextid, $component, $filearea, $olddataid, 'id', false);
                foreach ($files as $file) {
                    $fs->create_file_from_storedfile(['itemid' => $newdataid], $file);
                    $copiedfiles++;
                }
            }
        }

        // Built-in card/background images are keyed by template id.
        foreach ([picture_manager::FILEAREA_TEMPLATE, picture_manager::FILEAREA_TEMPLATE_CARD] as $filearea) {
            $fs->delete_area_files($syscontextid, picture_manager::COMPONENT, $filearea, $targetid);
            $files = $fs->get_area_files($syscontextid, picture_manager::COMPONENT, $filearea, $sourceid, 'id', false);
            foreach ($files as $file) {
                $fs->create_file_from_storedfile(['itemid' => $targetid], $file);
                $copiedfiles++;
            }
        }

        /* Mandatory, not defensive: template_scss has no TTL and caches an empty
           string on a miss, so a learner render between core duplication and this
           copy would poison css_{target} permanently. */
        template_metadata_cache::invalidate_template($targetid);
        scss_manager::invalidate_cache($targetid, self::AREA_LP);

        return ['fields' => $copiedfields, 'files' => $copiedfiles];
    }
}
