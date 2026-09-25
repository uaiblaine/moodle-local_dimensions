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
use core_customfield\category_controller;
use core_customfield\field_controller;
use core_customfield\handler;
use cache;
use moodle_url;
use local_dimensions\customfield\lp_handler;
use local_dimensions\customfield\competency_handler;

/**
 * Custom-field provisioning and cascade resolution, CSV custom-field conversion, the return-button
 * context and the Competency hub's queries.
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
     * Create a custom field in the area's first own category, creating a category when there is none.
     *
     * Field names and descriptions are stored as plain text, so $displayname is resolved once, in
     * the current user's language. Callers pass an empty $description so that no text in a single
     * language is stored.
     *
     * Only the handler's own categories qualify: on Moodle 5.1+ get_categories_with_fields() also
     * lists the core shared categories enabled for the area, and a field created in one of those
     * makes {@see self::organize_customfield_categories()} fail, because core's move_field()
     * refuses a move across components.
     *
     * @param string $shortname
     * @param string $type Field type (text, select, picture, etc.)
     * @param string $area The area (lp or competency)
     * @param \lang_string|null $displayname Localized display name
     * @param array $config Additional field configuration
     * @param string $description Field description (leave empty to avoid i18n issues)
     * @return field_controller|null Null when core refuses the field, e.g. its type plugin
     *     (customfield_picture) is not installed.
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
        $categories = array_filter(
            $handler->get_categories_with_fields(),
            static fn(category_controller $category): bool => $category->get('component') === $handler->get_component()
                && $category->get('area') === $handler->get_area()
        );

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

        // Saving cleared the handler's category cache; read the saved field back.
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
            ''
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
            ''
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
            ''
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
            ''
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
            ''
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
            ''
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
            ''
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
            ''
        );
    }

    /**
     * Get or create the template identifier (text) field.
     *
     * Templates only: competency_template has no idnumber column, so this field plays the role
     * a framework's idnumber plays. It is shown next to the template name in the hub and is the
     * identity column that the template CSV import matches existing templates on.
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
            ''
        );
    }

    /**
     * Ensure every managed custom field of an area exists, then sort them into their categories.
     *
     * Waits up to 10 s for the provisioning lock and does nothing when it is not granted.
     *
     * The get-or-create getters belong to this method and to upgrade steps. Read paths look a
     * field up with {@see self::find_field_by_shortname()} and fall back when it is missing, so
     * no request creates a field outside the lock.
     *
     * @param string $area The area (lp or competency)
     */
    public static function ensure_custom_fields_exist(string $area): void {
        /* Provisioning is check-then-act and neither customfield_field nor customfield_category
           has a unique index (core checks shortname uniqueness only in the admin form), so two
           concurrent first requests would create duplicate fields. The lock serialises them. */
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_dimensions');
        $lock = $lockfactory->get_lock('provisionfields', 10);
        if (!$lock) {
            // Another request is provisioning the same fields.
            return;
        }
        try {
            /* The plugin handlers are singletons, so a field list cached before the lock wait
               would hide what the lock winner created and the checks below would duplicate it. */
            self::get_handler($area)->reset_configuration_cache();

            // Template-only fields.
            if ($area === self::AREA_LP) {
                self::get_display_mode_field();
                self::get_subline_source_field();
                self::get_template_idnumber_field();
                self::get_showrelated_field();
                self::get_showrelatedlink_field();
            }

            // Enrollment filter + single-course redirect + locked-card settings:
            // provisioned for both templates and competencies so the cascade
            // competency -> template -> global has a place to read each layer from.
            self::get_enrollmentfilter_field($area);
            self::get_singlecourseredirect_field($area);
            self::get_lockedcardmode_field($area);
            self::get_showlockeddate_field($area);

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

            // Sort the fields into the Feel/Look categories (also repairs sites whose fields
            // were provisioned before those categories existed).
            self::organize_customfield_categories($area);
        } finally {
            $lock->release();
        }
    }

    /**
     * Ordered "Feel" custom-field shortnames (behaviour settings).
     *
     * Fields absent in a given area (e.g. the lp-only display mode on competencies)
     * are skipped by {@see self::organize_customfield_categories()}. The order here is
     * the order they appear inside the category accordion.
     *
     * @return string[] Shortnames in display order.
     */
    private static function feel_category_fields(): array {
        return [
            constants::CFIELD_DISPLAYMODE,
            constants::CFIELD_SUBLINE_SOURCE,
            constants::CFIELD_TEMPLATE_IDNUMBER,
            constants::CFIELD_ENROLLMENTFILTER,
            constants::CFIELD_SINGLECOURSEREDIRECT,
            constants::CFIELD_LOCKEDCARDMODE,
            constants::CFIELD_SHOWLOCKEDDATE,
            constants::CFIELD_SHOWRELATED,
            constants::CFIELD_SHOWRELATEDLINK,
        ];
    }

    /**
     * Ordered "Look" custom-field shortnames (visual styling).
     *
     * The two picture fields exist only in external image-handler mode; in built-in mode the
     * images are filemanagers the handlers add instead ({@see self::organize_customfield_categories()}).
     *
     * @return string[] Shortnames in display order.
     */
    private static function look_category_fields(): array {
        return [
            constants::CFIELD_CUSTOMBGCOLOR,
            constants::CFIELD_CUSTOMTEXTCOLOR,
            constants::CFIELD_TAG1,
            constants::CFIELD_TAG2,
            constants::CFIELD_TYPE,
            constants::CFIELD_CUSTOMSCSS,
            constants::CFIELD_CUSTOMBGIMAGE,
            constants::CFIELD_CUSTOMCARD,
        ];
    }

    /**
     * Find or create one of the plugin's managed field categories for an area.
     *
     * The name is taken from a lang string once, in the provisioning user's language, and never
     * re-synced. The category is therefore tracked by id in plugin config (`cfcat_<slug>_<area>`),
     * so a later run in another language, or after an admin renamed it, reuses it instead of
     * creating a duplicate. A remembered id that no longer exists gets a new category.
     *
     * @param string $area The area (lp or competency)
     * @param string $slug Short category identifier ('feel' or 'look')
     * @param string $langkey Lang string key for the category name
     * @return category_controller
     */
    private static function get_managed_category(string $area, string $slug, string $langkey): category_controller {
        $handler = self::get_handler($area);
        $configname = 'cfcat_' . $slug . '_' . $area;
        $storedid = (int) get_config('local_dimensions', $configname);

        if ($storedid > 0) {
            foreach ($handler->get_categories_with_fields() as $category) {
                if ((int) $category->get('id') === $storedid) {
                    return $category;
                }
            }
        }

        $newid = $handler->create_category((string) new \lang_string($langkey, 'local_dimensions'));
        set_config($configname, $newid, 'local_dimensions');
        $handler->reset_configuration_cache();

        return category_controller::create($newid);
    }

    /**
     * Sort the plugin's provisioned custom fields into the Feel/Look categories.
     *
     * Idempotent: creates the two categories once, moves each existing managed field into its
     * category in the declared order, deletes any other empty category of the area (the
     * auto-created default), then orders Feel before Look. Look must be the last category: in
     * built-in image mode the handlers append the image filemanagers after the custom fields,
     * so they land inside Look's form section.
     *
     * @param string $area The area (lp or competency)
     * @return void
     */
    public static function organize_customfield_categories(string $area): void {
        $handler = self::get_handler($area);
        $feel = self::get_managed_category($area, 'feel', 'fieldcategory_feel');
        $look = self::get_managed_category($area, 'look', 'fieldcategory_look');
        $feelid = (int) $feel->get('id');
        $lookid = (int) $look->get('id');

        // A move appends to the target category, so the first run leaves the fields in list
        // order; moving only fields that are elsewhere keeps later runs free of writes.
        foreach ([$feelid => self::feel_category_fields(), $lookid => self::look_category_fields()] as $categoryid => $shortnames) {
            foreach ($shortnames as $shortname) {
                $field = self::find_field_by_shortname($shortname, $area);
                if ($field && (int) $field->get('categoryid') !== $categoryid) {
                    \core_customfield\api::move_field($field, $categoryid);
                }
            }
        }

        // Re-read so the moves above are visible. On Moodle 5.1+ the list also holds core's
        // shared categories, whose stored component/area stay core_customfield/shared; the
        // component/area check below keeps them from being deleted.
        $handler->reset_configuration_cache();
        foreach ($handler->get_categories_with_fields() as $category) {
            $categoryid = (int) $category->get('id');
            if ($categoryid === $feelid || $categoryid === $lookid) {
                continue;
            }
            if ($category->get('component') !== 'local_dimensions' || $category->get('area') !== $area) {
                continue;
            }
            if (count($category->get_fields()) === 0) {
                $handler->delete_category($category);
            }
        }

        // Feel before Look, and Look to the end (beforeid 0).
        $handler->move_category($feel, $lookid);
        $handler->move_category($look, 0);
    }

    /** @var int Re-check window for the lazy-provisioning hook fallback (seconds). */
    const FIELDS_ENSURED_TTL = 3600;

    /**
     * Ensure all custom fields exist for both LP and competency areas.
     *
     * Lazy fallback run from the footer hook, only for holders of moodle/site:config. A session
     * timestamp limits it to once per {@see self::FIELDS_ENSURED_TTL} per session, so a field
     * deleted in the custom field admin UI comes back within that window; install, upgrade and
     * the settings callbacks provision immediately.
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
     * Set on `imagehandler` and `enablecustomscss` in settings.php, so the fields those settings
     * make conditional are provisioned as soon as they change.
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
     * Falls back to {@see constants::SUBLINE_STATUS} (rating badge or "to do" pill) when no
     * value is set.
     *
     * @param int $templateid Learning plan template ID
     * @return string One of the constants::SUBLINE_* values
     */
    public static function get_template_subline_source(int $templateid): string {
        $field = self::find_field_by_shortname(constants::CFIELD_SUBLINE_SOURCE, self::AREA_LP);
        if (!$field) {
            return constants::SUBLINE_STATUS;
        }

        $allowed = array_keys(constants::subline_source_options());

        $fields = \core_customfield\api::get_instance_fields_data([$field->get('id') => $field], $templateid);
        foreach ($fields as $data) {
            // A select stores its 1-based option index; get_value() returns it as an int.
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
     * Append the "enrolled and joinable" option to an already-provisioned
     * enrollmentfilter select field, if missing.
     *
     * Provisioning never re-syncs the option list of an existing field, so a field created
     * before the option existed keeps four options. This appends the fifth (index 5), leaving
     * the first four indices, and so every stored value, untouched. Reads configdata from the
     * DB rather than from the cached controller. Does nothing when the field does not exist or
     * already lists the option label as the current language spells it.
     *
     * @param string $area One of self::AREA_LP or self::AREA_COMPETENCY.
     * @return void
     */
    public static function sync_enrollmentfilter_option(string $area): void {
        global $DB;

        $field = self::find_field_by_shortname(constants::CFIELD_ENROLLMENTFILTER, $area);
        if (!$field) {
            return;
        }
        $fieldid = (int) $field->get('id');

        $configjson = $DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]);
        $config = json_decode((string) $configjson, true);
        if (!is_array($config) || !isset($config['options'])) {
            return;
        }

        $lines = explode("\n", (string) $config['options']);
        $label = (string) new \lang_string('enrollmentfilter_enrolledorself', 'local_dimensions');
        if (in_array($label, $lines, true)) {
            return;
        }

        $lines[] = $label;
        $config['options'] = implode("\n", $lines);
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $fieldid]);
        self::get_handler($area)->reset_configuration_cache();
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
     * Get or create the locked-card display mode select field for the given area.
     *
     * Storage: select customfield. Default option is `inherit`, which resolves to
     * the next layer (template, then the site-wide `local_dimensions/lockedcardmode`
     * setting) at read time. Applies to locked course cards in both learner views.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_lockedcardmode_field(string $area = self::AREA_LP): ?field_controller {
        $shortname = constants::CFIELD_LOCKEDCARDMODE;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        $options = constants::lockedcardmode_options();
        $optionstext = [];
        foreach ($options as $key => $langstring) {
            $optionstext[] = (string) $langstring;
        }

        return self::create_custom_field(
            $shortname,
            'select',
            $area,
            new \lang_string('lockedcardmode', 'local_dimensions'),
            [
                'options' => join("\n", $optionstext),
                'defaultvalue' => (string) $options[constants::LOCKEDCARDMODE_INHERIT],
            ],
            ''
        );
    }

    /**
     * Get or create the "show availability date" select field for the given area.
     *
     * Storage: select customfield. Default option is `inherit`, which resolves to
     * the next layer (template, then the site-wide `local_dimensions/showlockeddate`
     * setting) at read time. Applies to locked course cards in both learner views.
     *
     * @param string $area The area (lp or competency)
     * @return field_controller|null
     */
    public static function get_showlockeddate_field(string $area = self::AREA_LP): ?field_controller {
        $shortname = constants::CFIELD_SHOWLOCKEDDATE;
        $field = self::find_field_by_shortname($shortname, $area);

        if ($field) {
            return $field;
        }

        $options = constants::showlockeddate_options();
        $optionstext = [];
        foreach ($options as $key => $langstring) {
            $optionstext[] = (string) $langstring;
        }

        return self::create_custom_field(
            $shortname,
            'select',
            $area,
            new \lang_string('showlockeddate', 'local_dimensions'),
            [
                'options' => join("\n", $optionstext),
                'defaultvalue' => (string) $options[constants::SHOWLOCKEDDATE_INHERIT],
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
     * Returns one of `all` / `enrolled` / `active` / `enrolledorself`. When the template stores
     * `inherit` (or no row exists), falls back to the global
     * `local_dimensions/enrollmentfilter` setting, defaulting to `all`.
     *
     * @param int $templateid Learning plan template ID
     * @return string One of constants::ENROLLMENTFILTER_ALL|ENROLLED|ACTIVE|ENROLLEDORSELF
     */
    public static function get_template_enrollmentfilter(int $templateid): string {
        $global = (string) (get_config('local_dimensions', 'enrollmentfilter') ?: constants::ENROLLMENTFILTER_ALL);

        if ($templateid <= 0) {
            return $global;
        }

        $field = self::find_field_by_shortname(constants::CFIELD_ENROLLMENTFILTER, self::AREA_LP);
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

        $field = self::find_field_by_shortname(constants::CFIELD_SINGLECOURSEREDIRECT, self::AREA_LP);
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
     * Resolve the effective locked-card display mode for a learning plan template.
     *
     * Returns one of `blocked` / `learnmore`. When the template stores `inherit`
     * (or no row exists), falls back to the global `local_dimensions/lockedcardmode`
     * setting, defaulting to `blocked`.
     *
     * @param int $templateid Learning plan template ID
     * @return string One of constants::LOCKEDCARDMODE_BLOCKED|LEARNMORE
     */
    public static function get_template_lockedcardmode(int $templateid): string {
        $global = (string) (get_config('local_dimensions', 'lockedcardmode') ?: constants::LOCKEDCARDMODE_BLOCKED);

        if ($templateid <= 0) {
            return $global;
        }

        $field = self::find_field_by_shortname(constants::CFIELD_LOCKEDCARDMODE, self::AREA_LP);
        if (!$field) {
            return $global;
        }

        $allowed = array_keys(constants::lockedcardmode_options());
        $resolved = constants::LOCKEDCARDMODE_INHERIT;

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

        if ($resolved === constants::LOCKEDCARDMODE_INHERIT) {
            return $global;
        }

        return $resolved;
    }

    /**
     * Resolve the effective "show availability date" flag for a learning plan template.
     *
     * Returns a bool. When the template stores `inherit` (or no row exists), falls back
     * to the global `local_dimensions/showlockeddate`, which counts as true until the
     * setting is first saved (get_config() returns false only for a missing setting).
     *
     * @param int $templateid Learning plan template ID
     * @return bool true to show the availability date on locked cards
     */
    public static function get_template_showlockeddate(int $templateid): bool {
        $raw = get_config('local_dimensions', 'showlockeddate');
        $global = ($raw === false) ? true : (bool) $raw;

        if ($templateid <= 0) {
            return $global;
        }

        $field = self::find_field_by_shortname(constants::CFIELD_SHOWLOCKEDDATE, self::AREA_LP);
        if (!$field) {
            return $global;
        }

        $allowed = array_keys(constants::showlockeddate_options());
        $resolved = constants::SHOWLOCKEDDATE_INHERIT;

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

        if ($resolved === constants::SHOWLOCKEDDATE_INHERIT) {
            return $global;
        }

        return $resolved === constants::SHOWLOCKEDDATE_YES;
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
            self::find_field_by_shortname(constants::CFIELD_SHOWRELATED, self::AREA_LP),
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
            self::find_field_by_shortname(constants::CFIELD_SHOWRELATEDLINK, self::AREA_LP),
            constants::showrelatedlink_options(),
            (bool) get_config('local_dimensions', 'showrelatedlink')
        );
    }

    /**
     * Resolve an lp inherit/yes/no toggle field to a bool, falling back to the global default.
     *
     * @param int $templateid Learning plan template ID.
     * @param field_controller|null $field The customfield, or null.
     * @param array $options Options keyed by the SHOWRELATED_INHERIT/YES/NO constants, in stored order.
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
     * and the canonical option key for the cascade selects (enrollmentfilter,
     * singlecourseredirect, lockedcardmode, showlockeddate). The picture fields are
     * file-backed and deliberately skipped (not round-trippable in CSV).
     *
     * @param int $competencyid Competency id.
     * @return array<string, string> Keyed by cf_* column token (see framework_csv_serializer::CF_HEADERS).
     */
    public static function export_competency_customfields(int $competencyid): array {
        $result = array_fill_keys([
            'cf_bgcolor', 'cf_textcolor', 'cf_tag1', 'cf_tag2', 'cf_type',
            'cf_enrollmentfilter', 'cf_singlecourseredirect', 'cf_lockedcardmode', 'cf_showlockeddate',
            'cf_customscss',
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
        $result['cf_lockedcardmode'] = self::read_competency_select_key(
            $competencyid,
            constants::CFIELD_LOCKEDCARDMODE,
            array_keys(constants::lockedcardmode_options())
        );
        $result['cf_showlockeddate'] = self::read_competency_select_key(
            $competencyid,
            constants::CFIELD_SHOWLOCKEDDATE,
            array_keys(constants::showlockeddate_options())
        );
        return $result;
    }

    /**
     * Convert CSV cf_* tokens into the customfield_* form-data an instance_form_save expects.
     *
     * Only columns present in $cfrow are converted, so a partial CSV cannot wipe the fields it
     * omits: an absent column leaves its field untouched, a present but empty cell clears it
     * (select index 0, empty text). Selects map their label (tag1, tag2, type) or canonical key
     * (the cascade selects) to the stored 1-based index, falling back to a bare numeric cell.
     *
     * @param array $cfrow Map of cf_* token => raw CSV cell value.
     * @param string $area One of self::AREA_COMPETENCY (default) or self::AREA_LP.
     * @return array Form-data keyed by customfield_<shortname> (+ _editor for the SCSS textarea).
     */
    public static function customfields_to_formdata(array $cfrow, string $area = self::AREA_COMPETENCY): array {
        $data = [];
        if (array_key_exists('cf_bgcolor', $cfrow)) {
            $data['customfield_' . constants::CFIELD_CUSTOMBGCOLOR] = (string) $cfrow['cf_bgcolor'];
        }
        if (array_key_exists('cf_textcolor', $cfrow)) {
            $data['customfield_' . constants::CFIELD_CUSTOMTEXTCOLOR] = (string) $cfrow['cf_textcolor'];
        }
        if (array_key_exists('cf_tag1', $cfrow)) {
            $data['customfield_' . constants::CFIELD_TAG1] =
                self::select_label_to_index(constants::CFIELD_TAG1, (string) $cfrow['cf_tag1'], $area);
        }
        if (array_key_exists('cf_tag2', $cfrow)) {
            $data['customfield_' . constants::CFIELD_TAG2] =
                self::select_label_to_index(constants::CFIELD_TAG2, (string) $cfrow['cf_tag2'], $area);
        }
        if (array_key_exists('cf_type', $cfrow)) {
            $data['customfield_' . constants::CFIELD_TYPE] =
                self::select_label_to_index(constants::CFIELD_TYPE, (string) $cfrow['cf_type'], $area);
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
        if (array_key_exists('cf_lockedcardmode', $cfrow)) {
            $data['customfield_' . constants::CFIELD_LOCKEDCARDMODE] = self::select_key_to_index(
                array_keys(constants::lockedcardmode_options()),
                (string) $cfrow['cf_lockedcardmode']
            );
        }
        if (array_key_exists('cf_showlockeddate', $cfrow)) {
            $data['customfield_' . constants::CFIELD_SHOWLOCKEDDATE] = self::select_key_to_index(
                array_keys(constants::showlockeddate_options()),
                (string) $cfrow['cf_showlockeddate']
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
     * Read a learning plan template's stored custom-field values as CSV tokens for export.
     *
     * Only real stored values are emitted, never a synthesised default
     * ({@see self::read_competency_cf_data()}). The cascade selects carry their raw `inherit`
     * key rather than what the get_template_* resolvers return, which would bake this site's
     * global settings into every exported row. The picture fields are file-backed and skipped
     * (not round-trippable in CSV).
     *
     * The returned map also carries the `template_idnumber` key, which is the identity
     * column of the template CSV rather than one of its cf_* columns.
     *
     * @param int $templateid Learning plan template id.
     * @return array<string, string> Keyed by cf_* column token, plus template_idnumber.
     */
    public static function export_template_customfields(int $templateid): array {
        $result = array_fill_keys([
            'template_idnumber',
            'cf_displaymode', 'cf_subline_source', 'cf_showrelated', 'cf_showrelatedlink',
            'cf_bgcolor', 'cf_textcolor', 'cf_tag1', 'cf_tag2', 'cf_type',
            'cf_enrollmentfilter', 'cf_singlecourseredirect', 'cf_lockedcardmode', 'cf_showlockeddate',
            'cf_customscss',
        ], '');
        if ($templateid <= 0) {
            return $result;
        }
        $area = self::AREA_LP;
        $result['template_idnumber'] = self::read_competency_text_cf(
            $templateid,
            constants::CFIELD_TEMPLATE_IDNUMBER,
            $area
        );
        $result['cf_bgcolor'] = self::read_competency_text_cf($templateid, constants::CFIELD_CUSTOMBGCOLOR, $area);
        $result['cf_textcolor'] = self::read_competency_text_cf($templateid, constants::CFIELD_CUSTOMTEXTCOLOR, $area);
        $result['cf_customscss'] = self::read_competency_text_cf($templateid, constants::CFIELD_CUSTOMSCSS, $area);
        $result['cf_tag1'] = self::read_competency_select_label($templateid, constants::CFIELD_TAG1, $area);
        $result['cf_tag2'] = self::read_competency_select_label($templateid, constants::CFIELD_TAG2, $area);
        $result['cf_type'] = self::read_competency_select_label($templateid, constants::CFIELD_TYPE, $area);
        /* The display mode options are keyed by the DISPLAYMODE_* integers (1, 2) in stored
           order, so the stored 1-based index equals the constant. */
        $result['cf_displaymode'] = self::read_competency_select_key(
            $templateid,
            constants::CFIELD_DISPLAYMODE,
            array_keys(constants::display_mode_options()),
            $area
        );
        $result['cf_subline_source'] = self::read_competency_select_key(
            $templateid,
            constants::CFIELD_SUBLINE_SOURCE,
            array_keys(constants::subline_source_options()),
            $area
        );
        $result['cf_showrelated'] = self::read_competency_select_key(
            $templateid,
            constants::CFIELD_SHOWRELATED,
            array_keys(constants::showrelated_options()),
            $area
        );
        $result['cf_showrelatedlink'] = self::read_competency_select_key(
            $templateid,
            constants::CFIELD_SHOWRELATEDLINK,
            array_keys(constants::showrelatedlink_options()),
            $area
        );
        $result['cf_enrollmentfilter'] = self::read_competency_select_key(
            $templateid,
            constants::CFIELD_ENROLLMENTFILTER,
            array_keys(constants::enrollmentfilter_options()),
            $area
        );
        $result['cf_singlecourseredirect'] = self::read_competency_select_key(
            $templateid,
            constants::CFIELD_SINGLECOURSEREDIRECT,
            array_keys(constants::singlecourseredirect_options()),
            $area
        );
        $result['cf_lockedcardmode'] = self::read_competency_select_key(
            $templateid,
            constants::CFIELD_LOCKEDCARDMODE,
            array_keys(constants::lockedcardmode_options()),
            $area
        );
        $result['cf_showlockeddate'] = self::read_competency_select_key(
            $templateid,
            constants::CFIELD_SHOWLOCKEDDATE,
            array_keys(constants::showlockeddate_options()),
            $area
        );
        return $result;
    }

    /**
     * Convert template CSV tokens into the customfield_* form-data an instance_form_save expects.
     *
     * Converts the ten fields both areas provision through {@see self::customfields_to_formdata()},
     * then the five lp-only fields, with the same contract: an absent key leaves the field
     * untouched, a present but empty cell clears it (index 0 / empty text).
     *
     * @param array $cfrow Map of cf_* token => raw CSV cell value, optionally plus template_idnumber.
     * @return array Form-data keyed by customfield_<shortname> (+ _editor for the SCSS textarea).
     */
    public static function template_customfields_to_formdata(array $cfrow): array {
        $area = self::AREA_LP;
        $data = self::customfields_to_formdata($cfrow, $area);
        if (array_key_exists('template_idnumber', $cfrow)) {
            $data['customfield_' . constants::CFIELD_TEMPLATE_IDNUMBER] = (string) $cfrow['template_idnumber'];
        }
        if (array_key_exists('cf_displaymode', $cfrow)) {
            /* display_mode_options() is keyed by the DISPLAYMODE_* integers; cast the keys to
               strings for select_key_to_index()'s strict search. */
            $data['customfield_' . constants::CFIELD_DISPLAYMODE] = self::select_key_to_index(
                array_map('strval', array_keys(constants::display_mode_options())),
                (string) $cfrow['cf_displaymode']
            );
        }
        if (array_key_exists('cf_subline_source', $cfrow)) {
            $data['customfield_' . constants::CFIELD_SUBLINE_SOURCE] = self::select_key_to_index(
                array_keys(constants::subline_source_options()),
                (string) $cfrow['cf_subline_source']
            );
        }
        if (array_key_exists('cf_showrelated', $cfrow)) {
            $data['customfield_' . constants::CFIELD_SHOWRELATED] = self::select_key_to_index(
                array_keys(constants::showrelated_options()),
                (string) $cfrow['cf_showrelated']
            );
        }
        if (array_key_exists('cf_showrelatedlink', $cfrow)) {
            $data['customfield_' . constants::CFIELD_SHOWRELATEDLINK] = self::select_key_to_index(
                array_keys(constants::showrelatedlink_options()),
                (string) $cfrow['cf_showrelatedlink']
            );
        }
        return $data;
    }

    /**
     * The data_controller carrying an instance's real stored value for a field, or null.
     *
     * api::get_instance_fields_data() adds a default controller (id 0) when no row is stored;
     * that one is skipped, so callers never see a synthesised default.
     *
     * @param int $instanceid Competency id, or template id when reading the lp area.
     * @param string $shortname Custom-field shortname.
     * @param string $area One of self::AREA_COMPETENCY (default) or self::AREA_LP.
     * @return \core_customfield\data_controller|null
     */
    private static function read_competency_cf_data(
        int $instanceid,
        string $shortname,
        string $area = self::AREA_COMPETENCY
    ): ?\core_customfield\data_controller {
        $field = self::find_field_by_shortname($shortname, $area);
        if (!$field) {
            return null;
        }
        $datas = \core_customfield\api::get_instance_fields_data([$field->get('id') => $field], $instanceid);
        foreach ($datas as $data) {
            if ((int) $data->get('id') > 0) {
                return $data;
            }
        }
        return null;
    }

    /**
     * A text custom-field value, or empty string when unset.
     *
     * @param int $instanceid Competency id, or template id when reading the lp area.
     * @param string $shortname Custom-field shortname.
     * @param string $area One of self::AREA_COMPETENCY (default) or self::AREA_LP.
     * @return string
     */
    private static function read_competency_text_cf(
        int $instanceid,
        string $shortname,
        string $area = self::AREA_COMPETENCY
    ): string {
        $data = self::read_competency_cf_data($instanceid, $shortname, $area);
        return $data ? (string) $data->get_value() : '';
    }

    /**
     * The option label of a select custom-field, or empty string when unset.
     *
     * @param int $instanceid Competency id, or template id when reading the lp area.
     * @param string $shortname Custom-field shortname.
     * @param string $area One of self::AREA_COMPETENCY (default) or self::AREA_LP.
     * @return string
     */
    public static function read_competency_select_label(
        int $instanceid,
        string $shortname,
        string $area = self::AREA_COMPETENCY
    ): string {
        $data = self::read_competency_cf_data($instanceid, $shortname, $area);
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
     * The canonical option key (from $keys) of a cascade select, or empty string.
     *
     * @param int $instanceid Competency id, or template id when reading the lp area.
     * @param string $shortname Custom-field shortname.
     * @param array $keys Ordered option keys matching the field's option order.
     * @param string $area One of self::AREA_COMPETENCY (default) or self::AREA_LP.
     * @return string
     */
    private static function read_competency_select_key(
        int $instanceid,
        string $shortname,
        array $keys,
        string $area = self::AREA_COMPETENCY
    ): string {
        $data = self::read_competency_cf_data($instanceid, $shortname, $area);
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
     * @param string $area One of self::AREA_COMPETENCY (default) or self::AREA_LP.
     * @return int
     */
    private static function select_label_to_index(
        string $shortname,
        string $label,
        string $area = self::AREA_COMPETENCY
    ): int {
        $label = trim($label);
        if ($label === '') {
            return 0;
        }
        $field = self::find_field_by_shortname($shortname, $area);
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
    public static function select_raw_options(field_controller $field): array {
        return self::split_select_options((string) $field->get_configdata_property('options'));
    }

    /**
     * Split a select custom field's stored option text into its option labels.
     *
     * Splits the way {@see \customfield_select\field_controller::get_options()} does, without
     * its format_string() and its leading empty option, so position + 1 is the stored index.
     * Blank lines are not options there, so they must not count here either: "A\n\nB" stores
     * B as index 2.
     *
     * @param string $options The field's configdata 'options' text.
     * @return string[] Zero-based list of option labels.
     */
    public static function split_select_options(string $options): array {
        if (trim($options) === '') {
            return [];
        }
        return preg_split("/\s*\n\s*/", trim($options), -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
     * @return string One of constants::ENROLLMENTFILTER_ALL|ENROLLED|ACTIVE|ENROLLEDORSELF
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
     * Resolve the effective locked-card display mode when viewing a competency through a plan.
     *
     * Cascade: competency-level customfield -> template-level customfield ->
     * site-wide `local_dimensions/lockedcardmode` (default `blocked`). Pass `templateid = 0`
     * to skip the template layer (competency -> global), as callers do for a competency that
     * is not in the plan (a related-competency link).
     *
     * @param int $competencyid Competency being viewed
     * @param int $templateid Template of the plan the competency is being viewed through (0 for manual plans)
     * @return string One of constants::LOCKEDCARDMODE_BLOCKED|LEARNMORE
     */
    public static function resolve_lockedcardmode_for_view(int $competencyid, int $templateid): string {
        $compraw = self::get_competency_select_raw(
            $competencyid,
            constants::CFIELD_LOCKEDCARDMODE,
            array_keys(constants::lockedcardmode_options()),
            constants::LOCKEDCARDMODE_INHERIT
        );
        if ($compraw !== constants::LOCKEDCARDMODE_INHERIT) {
            return $compraw;
        }
        if ($templateid > 0) {
            return self::get_template_lockedcardmode($templateid);
        }
        return (string) (get_config('local_dimensions', 'lockedcardmode') ?: constants::LOCKEDCARDMODE_BLOCKED);
    }

    /**
     * Resolve the effective "show availability date" flag when viewing a competency through a plan.
     *
     * Cascade: competency-level customfield -> template-level customfield ->
     * site-wide `local_dimensions/showlockeddate` (default true). Pass `templateid = 0`
     * to skip the template layer (competency -> global).
     *
     * @param int $competencyid Competency being viewed
     * @param int $templateid Template of the plan the competency is being viewed through (0 for manual plans)
     * @return bool true when the availability date should be shown on locked cards
     */
    public static function resolve_showlockeddate_for_view(int $competencyid, int $templateid): bool {
        $compraw = self::get_competency_select_raw(
            $competencyid,
            constants::CFIELD_SHOWLOCKEDDATE,
            array_keys(constants::showlockeddate_options()),
            constants::SHOWLOCKEDDATE_INHERIT
        );
        if ($compraw !== constants::SHOWLOCKEDDATE_INHERIT) {
            return $compraw === constants::SHOWLOCKEDDATE_YES;
        }
        if ($templateid > 0) {
            return self::get_template_showlockeddate($templateid);
        }
        $raw = get_config('local_dimensions', 'showlockeddate');
        return ($raw === false) ? true : (bool) $raw;
    }

    /**
     * Whether a competency belongs to a plan (directly or via its template).
     *
     * A related-competency link can open the tracker for a competency outside the plan; callers
     * then skip the plan layer of the cascade and log no view in the plan.
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
     * Resolves the term the way {@see \tool_lp\external\competency_summary_exporter} does:
     * level -> framework taxonomy constant -> localized name.
     *
     * @param \core_competency\competency_framework $framework The framework
     * @param int $level The framework level
     * @return array<string, mixed> Keys: level (int), key (taxonomy constant), term (localized, '' if unknown).
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
     * root to parent. Shared by the hub's competency search, browse and related-competency
     * web services and the Plans tab.
     *
     * The path is the plain spelling (tags stripped, nothing escaped): every consumer escapes it
     * once itself, through a Mustache double stash, textContent or an explicit escape.
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
                    $crumbs[] = format_string($names[$ancestorid]->shortname, true, ['context' => $context, 'escape' => false]);
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
     * The courses from a raw id list that the current viewer may be told about at all.
     *
     * The two card web services (get_course_progress, get_courses_completion_status) take their
     * ids straight from the client, and the site-wide local/dimensions:view capability that
     * gates them is held by every authenticated user - so passing that gate says nothing about
     * the courses being asked for. A course survives here only when all three hold:
     *
     * - it exists;
     * - core would let this viewer see it listed, which is what can_view_course_info() answers -
     *   a hidden course, or one in a category the viewer cannot browse, drops out;
     * - it carries at least one competency link, the only reason these services would ever be
     *   asked about it (both views build their card lists from competency_coursecomp).
     *
     * Existence and the link check are one query for the whole list.
     *
     * @param array $courseids Raw course ids as received from the client.
     * @return array Course id => full course record, for the courses that survived.
     */
    public static function readable_competency_courses(array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        if (empty($courseids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $courses = $DB->get_records_sql(
            "SELECT c.*
               FROM {course} c
              WHERE c.id $insql
                AND EXISTS (SELECT 1 FROM {competency_coursecomp} cc WHERE cc.courseid = c.id)",
            $inparams
        );

        $readable = [];
        foreach ($courses as $course) {
            if (\core_course_category::can_view_course_info($course)) {
                $readable[(int) $course->id] = $course;
            }
        }

        return $readable;
    }

    /**
     * Whether the PostgreSQL `unaccent` extension is installed and usable right now.
     *
     * Read-only (one pg_extension lookup), so it is safe on a request path. Returns false on
     * other databases, where accent-insensitivity comes from the collation. The extension is
     * created only by {@see self::ensure_unaccent()}, at install and upgrade.
     *
     * @return bool True when unaccent() can be used in SQL (PostgreSQL only).
     */
    public static function has_unaccent(): bool {
        global $DB;
        if ($DB->get_dbfamily() !== 'postgres') {
            return false;
        }
        // Not cached: on PostgreSQL, advanced_testcase runs each test in a transaction it rolls
        // back, CREATE EXTENSION included, so a cached flag would outlive the extension.
        return $DB->record_exists_sql("SELECT 1 FROM pg_extension WHERE extname = 'unaccent'");
    }

    /**
     * Provision the PostgreSQL `unaccent` extension, creating it when it is missing.
     *
     * DDL, so call it from install and upgrade only. A least-privilege database account cannot
     * create extensions, so failure is swallowed: the site keeps accent-sensitive search, which
     * sql_like_ai() learns from has_unaccent().
     *
     * @return bool True when unaccent() can be used in SQL afterwards (PostgreSQL only).
     */
    public static function ensure_unaccent(): bool {
        global $DB;
        if (self::has_unaccent()) {
            return true;
        }
        if ($DB->get_dbfamily() !== 'postgres') {
            return false;
        }
        try {
            $DB->execute('CREATE EXTENSION IF NOT EXISTS unaccent');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Return a case- and accent-insensitive LIKE fragment for MySQL/MariaDB and PostgreSQL.
     *
     * On PostgreSQL both operands are wrapped in unaccent() when the extension is installed
     * (never installed from here: this runs in search web services); without it the comparison
     * stays accent-sensitive. Other databases rely on the collation via core sql_like(). The
     * caller builds the bound value with sql_like_escape() and its own wildcards.
     *
     * The PostgreSQL unaccent() approach (which core otherwise reports as unsupported) follows
     * the technique of the local_aise plugin, "Accent Insensitive Search Enabler", copyright
     * 2023 Austrian Federal Ministry of Education, released under the GNU GPL v3 or later:
     * https://github.com/Bildungsportal/moodle-local_aise
     *
     * @param string $fieldname The column or SQL expression to match.
     * @param string $param The bound parameter placeholder (e.g. ':q1').
     * @return string The SQL LIKE fragment.
     */
    public static function sql_like_ai(string $fieldname, string $param): string {
        global $DB;
        if (self::has_unaccent()) {
            return "unaccent($fieldname) ILIKE unaccent($param) ESCAPE '\\'";
        }
        return $DB->sql_like($fieldname, $param, false, false);
    }

    /**
     * Return the localized rule outcome text for a competency.
     *
     * @param string $ruletype Simplified rule type: points|all
     * @param int $ruleoutcome competency::OUTCOME_EVIDENCE (1), OUTCOME_COMPLETE (2) or OUTCOME_RECOMMEND (3);
     *     any other value returns ''.
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
        $term = $taxonomydata['current']['term'] ?? '';

        // Literal keys, one per arm, so the string checker can verify each of them.
        if ($ruletype === 'points') {
            return match ($ruleoutcome) {
                competency::OUTCOME_EVIDENCE => get_string('rules_points_outcome_attach', 'local_dimensions', $term),
                competency::OUTCOME_COMPLETE => get_string('rules_points_outcome_complete', 'local_dimensions', $term),
                competency::OUTCOME_RECOMMEND => get_string('rules_points_outcome_recommend', 'local_dimensions', $term),
                default => '',
            };
        }
        return match ($ruleoutcome) {
            competency::OUTCOME_EVIDENCE => get_string('rules_all_outcome_attach', 'local_dimensions', $term),
            competency::OUTCOME_COMPLETE => get_string('rules_all_outcome_complete', 'local_dimensions', $term),
            competency::OUTCOME_RECOMMEND => get_string('rules_all_outcome_recommend', 'local_dimensions', $term),
            default => '',
        };
    }

    /**
     * Store a per-course return URL in session cache.
     *
     * One entry per course, keyed 'course_{id}': the last page that listed a course decides
     * where that course's return button leads, whichever plan or view it came from.
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
     * For callers that know the course being entered: view-competency.php's single-course
     * redirect and block_dimensions' set_return_context web service.
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
     * Classify a stored return URL by the page it points at.
     *
     * The return context holds a bare URL string and no origin, so the button's
     * label is derived from its destination. Anything unrecognised is treated as
     * the plan: the plan is the root of the journey and every writer except the
     * tracker stores it.
     *
     * @param string $url The stored return URL.
     * @return string Either 'competency' or 'plan'.
     */
    public static function return_destination_kind(string $url): string {
        if (str_contains($url, '/local/dimensions/view-competency.php')) {
            return 'competency';
        }
        return 'plan';
    }

    /**
     * Whether the plan overview is a page this plan's learners are routed to.
     *
     * The display mode is a template custom field, and block_dimensions routes on
     * it: DISPLAYMODE_PLAN yields a plan card leading to the overview, anything
     * else (including a template without the field) yields competency cards leading
     * straight to the tracker. A plan with no template counts as plan mode. Keep in step
     * with block_dimensions' \block_dimensions\local\dataset_provider::resolve_plan_display_context().
     *
     * @param int $templateid The plan's template id, or 0 when it has none.
     * @return bool True when the overview is part of this learner's journey.
     */
    public static function plan_overview_is_routed(int $templateid): bool {
        if (!$templateid) {
            return true;
        }

        $metadata = template_metadata_cache::get_template_metadata($templateid);
        $displaymode = (int) ($metadata['displaymode'] ?? constants::DISPLAYMODE_COMPETENCIES);

        return $displaymode === constants::DISPLAYMODE_PLAN;
    }

    /**
     * The URL a single-course redirect leaves behind for the destination course.
     *
     * When the plan overview is routed to, the course points back at it, because
     * the tracker would only redirect again. Otherwise (competency-card mode) the learner
     * never sees the overview, so the course points at the tracker with noredirect=1,
     * which renders it instead of bouncing the learner back into the course.
     *
     * The tracker URL is rebuilt from ids so this stays testable without a $PAGE, while
     * view-competency.php's non-redirect branch builds it from $PAGE->url plus noredirect.
     * Keep them in step: a parameter added to that page's $PAGE->set_url() must be added here.
     *
     * @param int $planid The plan being viewed.
     * @param int $competencyid The competency being viewed.
     * @param int $templateid The plan's template id, or 0 when it has none.
     * @return moodle_url The URL to store for the destination course.
     */
    public static function redirect_return_url(int $planid, int $competencyid, int $templateid): moodle_url {
        if (self::plan_overview_is_routed($templateid)) {
            return new moodle_url('/local/dimensions/view-plan.php', ['id' => $planid]);
        }

        return new moodle_url('/local/dimensions/view-competency.php', [
            'id' => $planid,
            'competencyid' => $competencyid,
            'noredirect' => 1,
        ]);
    }

    /**
     * Build the competency tracker's own return-button context.
     *
     * The footer FAB never renders on the tracker: the page has no course in context and keeps
     * core's default 'base' layout, both of which {@see hook_callbacks::before_footer_html_generation()}
     * rejects. No return-context cache is needed, because the plan id is a required parameter
     * of the page.
     *
     * Suppressed when learners of this plan are routed to competency cards
     * ({@see self::plan_overview_is_routed()}): the tracker is then their root, and the
     * overview is a page they are never sent to.
     *
     * @param int $planid The plan the tracker was opened from.
     * @param int $templateid The plan's template id, or 0 when it has none.
     * @param bool $related Whether a related-competency pill opened this page in a new tab.
     * @return array|null Keys returnurl, label and buttoncolor, or null when no button belongs here.
     */
    public static function tracker_return_context(int $planid, int $templateid, bool $related): ?array {
        if ($related || !get_config('local_dimensions', 'enablereturnbutton')) {
            return null;
        }

        if (!self::plan_overview_is_routed($templateid)) {
            return null;
        }

        return [
            'returnurl' => (new moodle_url('/local/dimensions/view-plan.php', ['id' => $planid]))->out(false),
            'label' => get_string('returntoplan', 'local_dimensions'),
            'buttoncolor' => get_config('local_dimensions', 'returnbuttoncolor') ?: '#0f6cbf',
        ];
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
     * Count visible frameworks in a course category and every category below it.
     *
     * The locked category entry lists the category's descendants too (core's 'children' scope),
     * so its headline count must cover the same subtree; the per-category counts above are
     * 'self' counts for the picker of the site entry.
     *
     * @param \context $context The course category context.
     * @return int
     */
    public static function count_frameworks_in_subtree(\context $context): int {
        return self::count_in_subtree('competency_framework', $context);
    }

    /**
     * Count visible learning plan templates in a course category and every category below it.
     *
     * @param \context $context The course category context.
     * @return int
     */
    public static function count_templates_in_subtree(\context $context): int {
        return self::count_in_subtree('competency_template', $context);
    }

    /**
     * Count visible rows of a competency table whose context is the given one or a descendant.
     *
     * @param string $table 'competency_framework' or 'competency_template'.
     * @param \context $context The root context of the subtree.
     * @return int
     */
    private static function count_in_subtree(string $table, \context $context): int {
        global $DB;
        $sql = "SELECT COUNT(t.id)
                  FROM {" . $table . "} t
                  JOIN {context} ctx ON ctx.id = t.contextid
                 WHERE t.visible = :visible
                   AND (ctx.id = :ctxid OR " . $DB->sql_like('ctx.path', ':path') . ")";
        return (int) $DB->count_records_sql($sql, [
            'visible' => 1,
            'ctxid' => $context->id,
            'path' => $DB->sql_like_escape($context->path) . '/%',
        ]);
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
     * Count the learner plans per template that still read the template live.
     *
     * Every plan that is not complete takes its name, description and due date from the
     * template: api::update_template() rewrites them with one bulk UPDATE
     * ({@see \core_competency\plan::update_multiple_from_template()}), which skips complete
     * plans. {@see self::count_plans_by_template()} counts every status, so it cannot answer
     * "how many learners' plans will this rename".
     *
     * @param int[] $templateids Template IDs.
     * @return array<int, int> templateid => count of plans that are not complete
     */
    public static function count_open_plans_by_template(array $templateids): array {
        global $DB;

        $templateids = array_values(array_unique(array_filter(array_map('intval', $templateids))));
        if (empty($templateids)) {
            return [];
        }

        $counts = [];
        foreach (array_chunk($templateids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'tpl');
            $params['complete'] = \core_competency\plan::STATUS_COMPLETE;
            $sql = "SELECT templateid, COUNT(id) AS cnt
                      FROM {competency_plan}
                     WHERE templateid $insql
                       AND status <> :complete
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
            'showhiddencats' => (bool) ($navraw['showhiddencats'] ?? false),
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
            'modalexpanded' => $dispbool($displayraw, 'modalexpanded', false),
        ];

        return ['nav' => $nav, 'display' => $display];
    }

    /**
     * Choose the hub tab to open: the requested one when the viewer may see it, else the first available.
     *
     * Core's dynamic tabs open whatever the URL fragment names, so a saved or deep-linked tab the
     * viewer cannot read would throw from require_access() and take the whole page down. Falling
     * back keeps the page up for a viewer who holds only some of the competency capabilities in the
     * context - a template manager without framework access, or the other way round. The fallback
     * order is the strip's own order. Returns the empty string when no tab is available.
     *
     * @param array $available Availability keyed by tab shortname, in strip order (bool values).
     * @param string $requested The tab the URL or the saved preference asked for.
     * @return string The shortname to open, or '' when nothing is available.
     */
    public static function pick_available_tab(array $available, string $requested): string {
        if (!empty($available[$requested])) {
            return $requested;
        }
        foreach ($available as $shortname => $isavailable) {
            if ($isavailable) {
                return (string) $shortname;
            }
        }
        return '';
    }

    /**
     * The hub's URL for a context: bare for the site, carrying pagecontextid for a course category.
     *
     * The category entry is a different page setup (locked to the category, no admin tree), so any
     * link that must land a category manager back on the hub has to carry the category; the bare
     * URL is the Site administration entry and refuses them.
     *
     * @param \context $context The context the link is for (system or course category).
     * @param array $params Further URL parameters (frameworkid, templateid, tab...).
     * @return \moodle_url
     */
    public static function hub_page_url(\context $context, array $params = []): \moodle_url {
        if ($context->contextlevel === CONTEXT_COURSECAT) {
            $params = ['pagecontextid' => $context->id] + $params;
        }
        return new \moodle_url('/local/dimensions/central.php', $params);
    }

    /**
     * The learner's stored hero fold, for the one plan or competency being viewed.
     *
     * The fold is per plan and per competency: a learner who knows one plan by heart still
     * wants the welcome card on a plan they opened today. Only the folded keys are stored, so
     * an absent key - and an absent preference - reads as open, which is the default.
     *
     * The whole stored list is handed back with the answer because a write replaces the entire
     * preference: the page knows about one hero, and writing just its own key would silently
     * unfold every other plan and competency the learner had folded.
     *
     * @param string $key This hero's own key: 'p' plus a plan id, or 'c' plus a competency id.
     * @return array Keys: key (string), slim (bool), statejson (string, the whole stored list).
     */
    public static function resolve_hero_state(string $key): array {
        $stored = json_decode((string) get_user_preferences(constants::PREF_LEARNER_HERO, ''), true);
        $stored = is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];

        return [
            'key' => $key,
            'slim' => in_array($key, $stored, true),
            'statejson' => json_encode($stored),
        ];
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
     * Search the course categories the viewer may pick in the Competency hub, in tree order.
     *
     * Built for sites with thousands of categories: one SQL search on the name (accent-insensitive
     * where the site supports it) fetches at most twice $limit rows, and only those are inspected
     * further. Visibility is core's: a hidden category is offered only when asked for and only to
     * a viewer who may see it. Competency readability is checked per hit unless the viewer reads
     * at the site, where no category can widen what the site grants; a narrower override on a
     * category cannot leak either, because the page re-checks the chosen category.
     *
     * @param string $query Search text matched against the category name; empty for the first page.
     * @param bool $includehidden Whether hidden categories the viewer may see are included.
     * @param int $limit Maximum number of hits (1 to 50).
     * @return array List of options, each: id, name (plain nested name), frameworkcount,
     *               templatecount, hasframeworks, hastemplates, hidden.
     */
    public static function central_category_search(string $query, bool $includehidden, int $limit): array {
        global $DB;
        $limit = max(1, min($limit, 50));

        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = self::sql_like_ai('name', ':q');
            $params['q'] = '%' . $DB->sql_like_escape($query) . '%';
        }
        $canviewhidden = has_capability('moodle/category:viewhiddencategories', \context_system::instance());
        if (!$includehidden || !$canviewhidden) {
            $where[] = 'visible = 1';
        }
        $select = $where ? implode(' AND ', $where) : '';

        // Over-fetch so the per-hit filters below rarely leave a short page.
        $records = $DB->get_records_select('course_categories', $select, $params, 'sortorder ASC', 'id, visible', 0, $limit * 2);

        $readsatsite = self::can_read_competency_context(\context_system::instance());
        $ids = [];
        foreach ($records as $record) {
            $category = \core_course_category::get((int) $record->id, IGNORE_MISSING, true);
            if (!$category || !\core_course_category::can_view_category($category)) {
                continue;
            }
            if (!$readsatsite && !self::can_read_competency_context(\context_coursecat::instance((int) $record->id))) {
                continue;
            }
            $ids[] = (int) $record->id;
            if (count($ids) >= $limit) {
                break;
            }
        }
        return self::central_category_options_for($ids);
    }

    /**
     * The picker option for one course category, or null when the viewer may not read it.
     *
     * The context bar renders only the selected category server-side; every other option
     * arrives through central_category_search() as the viewer types.
     *
     * @param int $categoryid The course category id.
     * @return array|null The option (see central_category_options_for()), or null.
     */
    public static function central_category_option(int $categoryid): ?array {
        if ($categoryid <= 0) {
            return null;
        }
        try {
            if (!self::can_read_competency_context(\context_coursecat::instance($categoryid))) {
                return null;
            }
        } catch (\moodle_exception $e) {
            return null;
        }
        $options = self::central_category_options_for([$categoryid]);
        return $options[0] ?? null;
    }

    /**
     * Shape picker options for a list of course category ids, in the given order.
     *
     * Names are the unescaped nested spelling, because the picker and the locked label are
     * Mustache double stashes; core's make_categories_list() returns escaped names, which would
     * render '&' as "&amp;". Counts come from two aggregate queries over the ids.
     *
     * @param array $categoryids Course category ids.
     * @return array List of options, each: id, name, frameworkcount, templatecount,
     *               hasframeworks, hastemplates, hidden.
     */
    private static function central_category_options_for(array $categoryids): array {
        $categoryids = array_values(array_unique(array_filter(array_map('intval', $categoryids))));
        if (empty($categoryids)) {
            return [];
        }
        $frameworkcounts = self::count_frameworks_by_category($categoryids);
        $templatecounts = self::count_templates_by_category($categoryids);

        $options = [];
        foreach ($categoryids as $catid) {
            $category = \core_course_category::get($catid, IGNORE_MISSING, true);
            if (!$category) {
                continue;
            }
            $frameworkcount = (int) ($frameworkcounts[$catid] ?? 0);
            $templatecount = (int) ($templatecounts[$catid] ?? 0);
            $options[] = [
                'id' => $catid,
                'name' => $category->get_nested_name(false, ' / ', ['escape' => false]),
                'frameworkcount' => $frameworkcount,
                'templatecount' => $templatecount,
                'hasframeworks' => $frameworkcount > 0,
                'hastemplates' => $templatecount > 0,
                'hidden' => !$category->visible,
            ];
        }
        return $options;
    }

    /**
     * Shape a set of sibling competency records into Structure-tree nodes.
     *
     * Used by the server-rendered first page of roots and by the browse_structure and
     * get_structure_node web services, so a server-rendered node and a lazily-fetched one are
     * identical. The node keys are declared in
     * {@see \local_dimensions\external\browse_structure::node_structure()}: a key added here
     * must be added there, or clean_returnvalue() strips it from the web-service results.
     * Batch queries cover the whole page (children, linked courses, activities and templates,
     * scale names, custom fields); depth and taxonomy are derived from each record's path.
     *
     * Names and labels are the plain spelling. Every sink escapes them once itself: the
     * structure_node template (double stashes, including the data-* attributes the detail pane
     * reads back through textContent) and the web-service consumers.
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

        // Batch: scale names. A competency without its own scale uses the framework's, and the
        // detail pane shows that effective scale.
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
                $scalenames[(int) $scale->id] = format_string($scale->name, true, ['context' => $context, 'escape' => false]);
            }
        }

        // Batch: type/tag labels and custom colours, one query.
        $cfdata = self::structure_customfield_data($ids);

        // Core's own taxonomy labels, keyed by the TAXONOMY_* constants.
        $taxonomies = competency_framework::get_taxonomies_list();

        // The select labels are admin text read raw from the option list, so they go through
        // format_string() like the names: filters apply and no tag reaches a PARAM_TEXT return
        // field, where it would fail the whole response.
        $plain = ['context' => $context, 'escape' => false];
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
                'shortname' => format_string($record->get('shortname'), true, $plain),
                'idnumber' => (string) $record->get('idnumber'),
                'taxonomy' => (string) ($taxonomies[$taxonomy] ?? $taxonomies[competency_framework::TAXONOMY_COMPETENCY]),
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
                'type' => format_string((string) ($cfdata[$id][constants::CFIELD_TYPE] ?? ''), true, $plain),
                'tag1' => format_string((string) ($cfdata[$id][constants::CFIELD_TAG1] ?? ''), true, $plain),
                'tag2' => format_string((string) ($cfdata[$id][constants::CFIELD_TAG2] ?? ''), true, $plain),
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

        // Direct SQL rather than handler::get_instances_data(), which loads every field and builds
        // a controller per instance and field: only five fields' stored rows are needed, with the
        // option list joined in. Same approach as template_metadata_cache.
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
    public static function normalise_hex_color(string $value): string {
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
        $options = self::split_select_options((string) $config['options']);
        return $options[$index - 1] ?? '';
    }

    /**
     * Compute a competency's tree depth from its path (root = 0).
     *
     * competency.path lists only the ancestors after a leading 0 (root '/0/', grandchild
     * '/0/<rootid>/<childid>/'), so the depth is the number of non-zero segments.
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
     * Role names in the plain spelling, keyed by role id, for the hub's role pickers and labels.
     *
     * Core's role_get_name() passes a custom role name through format_string() in its escaping
     * mode, which the hub's sinks (textContent, double stashes) would escape a second time. The
     * hub names roles only in system and category contexts, where no course alias applies, so the
     * name is the role's own or, for a standard role left unnamed, core's localised default.
     *
     * @param array $roleids Role ids; unknown ids are skipped.
     * @return array Map of role id => plain name.
     */
    public static function plain_role_names(array $roleids): array {
        $wanted = array_flip(array_map('intval', $roleids));
        $system = \context_system::instance();
        $names = [];
        foreach (get_all_roles() as $role) {
            $roleid = (int) $role->id;
            if (!isset($wanted[$roleid])) {
                continue;
            }
            if (trim((string) $role->name) !== '') {
                $names[$roleid] = format_string($role->name, true, ['context' => $system, 'escape' => false]);
            } else {
                $names[$roleid] = role_get_name($role, null, ROLENAME_ORIGINAL);
            }
        }
        return $names;
    }

    /**
     * Build the framework management rows for a context (Frameworks tab).
     *
     * Names are the plain spelling, because frameworks_row renders them through double stashes
     * and the tab's JS reads data-name back through textContent.
     *
     * @param \context $context The resolved page context (system or course category).
     * @param bool $includehidden Whether to include hidden frameworks (default visible-only).
     * @param string $includes Core's scope word: 'self' for the context alone, 'children' for its descendants too.
     * @return array List of ['id' => int, 'shortname' => string, 'idnumber' => string,
     *               'description' => string, 'competencycount' => int, 'visible' => bool,
     *               'deletable' => bool, 'canmanage' => bool].
     */
    public static function framework_rows(\context $context, bool $includehidden = false, string $includes = 'self'): array {
        $rows = [];
        foreach (api::list_frameworks('shortname', 'ASC', 0, 0, $context, $includes, !$includehidden) as $framework) {
            if (!competency_framework::can_read_context($framework->get_context())) {
                continue;
            }
            $id = (int) $framework->get('id');
            $competencyids = competency::get_ids_by_frameworkid($id);
            // Plain single-line text: the card clips it with an ellipsis and repeats it in a title
            // tooltip.
            $description = content_to_text(
                (string) $framework->get('description'),
                (int) $framework->get('descriptionformat')
            );
            $description = trim(preg_replace('/\s+/', ' ', $description));
            $rows[] = [
                'id' => $id,
                'shortname' => format_string(
                    $framework->get('shortname'),
                    true,
                    ['context' => $framework->get_context(), 'escape' => false]
                ),
                'idnumber' => (string) $framework->get('idnumber'),
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
     * The SCSS field is a textarea customfield, rendered as an editor that opens in the rich
     * text editor on a new instance. Pinning the value's format to FORMAT_PLAIN renders the plain
     * textarea; the format selector it still shows is hidden for the hub modals in styles.css.
     * Call from definition_after_data().
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
     * Used for the stops of the Learning plans detail-header gradient
     * ({@see \local_dimensions\output\dynamictabs\plans}). Accepts 3- or 6-digit hex
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
     * background images. Cohort links are deliberately not copied: core's
     * sync_plans_from_template_cohorts_task would create a plan for every
     * cohort member on the next cron run.
     *
     * The customfield_data rows are cloned by direct SQL rather than through
     * the customfield handler: handler reads skip fields whose type plugin is
     * disabled (e.g. customfield_picture rows from the external image mode),
     * while the metadata cache still serves their files.
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

        // Files embedded in a custom field's data are keyed by the data row id,
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
            /* customfield_data allows one row per instance and field (unique index),
               and the target may already have one (a re-run, or the template_created
               observer saving the submitted form), so replace it instead of colliding.
               Its embedded files are keyed by its id, which nothing references once the
               row is gone, so they go first. */
            $oldtarget = $DB->get_record('customfield_data', ['fieldid' => $row->fieldid, 'instanceid' => $targetid]);
            if ($oldtarget && isset($embeddedfileareas[$fieldtype])) {
                [$component, $filearea] = $embeddedfileareas[$fieldtype];
                $fs->delete_area_files((int) $oldtarget->contextid, $component, $filearea, (int) $oldtarget->id);
            }
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

        /* Required: template_scss has no TTL and caches '' on a miss, so a render
           between core's duplication and this copy would keep css_{target} empty
           until the cache is purged. */
        template_metadata_cache::invalidate_template($targetid);
        scss_manager::invalidate_cache($targetid, self::AREA_LP);

        return ['fields' => $copiedfields, 'files' => $copiedfiles];
    }
}
