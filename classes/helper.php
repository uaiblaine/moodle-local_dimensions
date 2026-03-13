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
    protected static function find_field_by_shortname(string $shortname, string $area = self::AREA_LP): ?field_controller {
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
            $optionstext[] = $key . '|' . (string) $langstring;
        }

        return self::create_custom_field(
            $shortname,
            'select',
            self::AREA_LP,
            new \lang_string('displaymode', 'local_dimensions'),
            [
                'options' => join("\n", $optionstext),
                'defaultvalue' => (string) constants::DISPLAYMODE_COMPETENCIES,
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
        }

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

    /**
     * Ensure all custom fields exist for both LP and competency areas.
     *
     * Uses a session flag so the check runs only once per user session,
     * avoiding repeated DB queries on every page load.
     *
     */
    public static function ensure_all_fields(): void {
        global $SESSION;

        if (!empty($SESSION->local_dimensions_fields_ensured)) {
            return;
        }

        // Only admins should create custom fields.
        if (!has_capability('moodle/site:config', \context_system::instance())) {
            return;
        }

        self::ensure_custom_fields_exist(self::AREA_LP);
        self::ensure_custom_fields_exist(self::AREA_COMPETENCY);

        $SESSION->local_dimensions_fields_ensured = true;
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
        $configuredlevels = array_filter(explode(',', (string) $framework->get('taxonomies')));
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
     * Store the return URL and valid course IDs in session cache.
     *
     * @param moodle_url $url The URL to store as return destination.
     * @param array $validcourseids Array of course IDs where the button should appear.
     */
    public static function set_return_context(moodle_url $url, array $validcourseids = []): void {
        $cache = cache::make('local_dimensions', 'returncontext');
        $cache->set('data', [
            'url' => $url->out(false),
            'courses' => $validcourseids,
        ]);
    }

    /**
     * Get the stored return context from session cache.
     *
     * @return array|null Array with 'url' and 'courses' keys, or null if not set.
     */
    public static function get_return_context(): ?array {
        $cache = cache::make('local_dimensions', 'returncontext');
        $data = $cache->get('data');
        if (empty($data) || empty($data['url'])) {
            return null;
        }
        return $data;
    }

    /**
     * Clear the return context from session cache.
     */
    public static function clear_return_context(): void {
        $cache = cache::make('local_dimensions', 'returncontext');
        $cache->delete('data');
    }
}
