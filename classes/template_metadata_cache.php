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
 * Cache helper for template metadata used by plan cards.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

/**
 * Cache helper for template metadata used by plan cards.
 *
 * Payload keys:
 * - type
 * - tag1
 * - tag2
 * - bgcolor
 * - textcolor
 * - templatecardimageurl
 * - displaymode
 * - timemodified
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_metadata_cache {
    /** @var \cache|null Cache instance. */
    private static $cache = null;

    /**
     * Get the cache instance.
     *
     * @return \cache
     */
    private static function get_cache(): \cache {
        if (self::$cache === null) {
            self::$cache = \cache::make('local_dimensions', 'template_metadata');
        }
        return self::$cache;
    }

    /**
     * Get metadata for a template using lazy MUC cache.
     *
     * @param int $templateid Template ID.
     * @return array<string, mixed>
     */
    public static function get_template_metadata(int $templateid): array {
        $cache = self::get_cache();
        $payload = $cache->get($templateid);

        if ($payload !== false && is_array($payload)) {
            self::debug('cache hit for template ' . $templateid);
            return self::normalise_payload($payload);
        }

        self::debug('cache miss for template ' . $templateid);
        $payload = self::fetch_template_metadata($templateid);
        $cache->set($templateid, $payload);
        return $payload;
    }

    /**
     * Invalidate cached metadata for one template.
     *
     * @param int $templateid Template ID.
     */
    public static function invalidate_template(int $templateid): void {
        self::get_cache()->delete($templateid);
        self::debug('cache invalidated for template ' . $templateid);
    }

    /**
     * Purge all cached template metadata.
     */
    public static function purge_all(): void {
        self::get_cache()->purge();
        self::debug('cache purged');
    }

    /**
     * Build metadata payload from database/custom fields.
     *
     * @param int $templateid Template ID.
     * @return array<string, mixed>
     */
    private static function fetch_template_metadata(int $templateid): array {
        global $DB;

        $payload = [
            'type' => null,
            'tag1' => null,
            'tag2' => null,
            'bgcolor' => null,
            'textcolor' => null,
            'templatecardimageurl' => null,
            'displaymode' => constants::DISPLAYMODE_COMPETENCIES,
            'timemodified' => 0,
        ];

        $timemodified = $DB->get_field('competency_template', 'timemodified', ['id' => $templateid]);
        $payload['timemodified'] = $timemodified ? (int)$timemodified : 0;

        $shortnames = [
            constants::CFIELD_TYPE,
            constants::CFIELD_TAG1,
            constants::CFIELD_TAG2,
            constants::CFIELD_CUSTOMBGCOLOR,
            constants::CFIELD_CUSTOMTEXTCOLOR,
            constants::CFIELD_CUSTOMCARD,
            constants::CFIELD_DISPLAYMODE,
        ];

        [$insql, $inparams] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
        $params = [
            'instanceid' => $templateid,
            'component' => 'local_dimensions',
            'area' => 'lp',
        ] + $inparams;

        $sql = "SELECT f.shortname,
                       f.configdata,
                       d.id AS dataid,
                       d.contextid,
                       d.value,
                       d.intvalue
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
             LEFT JOIN {customfield_data} d ON d.fieldid = f.id AND d.instanceid = :instanceid
                 WHERE c.component = :component
                   AND c.area = :area
                   AND f.shortname $insql";

        $records = $DB->get_records_sql($sql, $params);
        $byshortname = [];
        foreach ($records as $record) {
            $byshortname[$record->shortname] = $record;
        }

        $payload['type'] = self::get_select_value($byshortname, constants::CFIELD_TYPE);
        $payload['tag1'] = self::get_select_value($byshortname, constants::CFIELD_TAG1);
        $payload['tag2'] = self::get_select_value($byshortname, constants::CFIELD_TAG2);
        $payload['bgcolor'] = self::get_color_value($byshortname, constants::CFIELD_CUSTOMBGCOLOR);
        $payload['textcolor'] = self::get_color_value($byshortname, constants::CFIELD_CUSTOMTEXTCOLOR);
        $payload['displaymode'] = self::get_displaymode_value($byshortname);

        // Built-in mode first, then legacy external customfield_picture fallback.
        if (picture_manager::is_builtin_mode()) {
            $payload['templatecardimageurl'] = picture_manager::get_image_url('lp', $templateid, 'cardimage');
        }

        if (empty($payload['templatecardimageurl'])) {
            $payload['templatecardimageurl'] = self::get_external_card_image_url($byshortname, constants::CFIELD_CUSTOMCARD);
        }

        return $payload;
    }

    /**
     * Get select field label for a shortname.
     *
     * @param array $records Records keyed by shortname.
     * @param string $shortname Field shortname.
     * @return string|null
     */
    private static function get_select_value(array $records, string $shortname): ?string {
        if (empty($records[$shortname])) {
            return null;
        }

        $record = $records[$shortname];
        $selectedindex = isset($record->intvalue) ? (int)$record->intvalue : 0;
        if ($selectedindex <= 0 || empty($record->configdata)) {
            return null;
        }

        $config = json_decode($record->configdata, true);
        if (!is_array($config) || empty($config['options'])) {
            return null;
        }

        $options = explode("\n", $config['options']);
        $optionindex = $selectedindex - 1;
        if (!isset($options[$optionindex])) {
            return null;
        }

        $value = trim($options[$optionindex]);
        return $value !== '' ? $value : null;
    }

    /**
     * Get display mode int value from the select field.
     *
     * The displaymode select field stores options as "1|Label\n2|Label".
     * The intvalue is the 1-based index into the options list, and the
     * option key (before the pipe) is the actual display mode constant.
     *
     * @param array $records Records keyed by shortname.
     * @return int Display mode constant.
     */
    private static function get_displaymode_value(array $records): int {
        $shortname = constants::CFIELD_DISPLAYMODE;
        if (empty($records[$shortname])) {
            return constants::DISPLAYMODE_COMPETENCIES;
        }

        $record = $records[$shortname];
        $selectedindex = isset($record->intvalue) ? (int)$record->intvalue : 0;
        if ($selectedindex <= 0 || empty($record->configdata)) {
            return constants::DISPLAYMODE_COMPETENCIES;
        }

        $config = json_decode($record->configdata, true);
        if (!is_array($config) || empty($config['options'])) {
            return constants::DISPLAYMODE_COMPETENCIES;
        }

        $options = explode("\n", $config['options']);
        $optionindex = $selectedindex - 1;
        if (!isset($options[$optionindex])) {
            return constants::DISPLAYMODE_COMPETENCIES;
        }

        // Options are stored as "key|label"; extract the key.
        $option = trim($options[$optionindex]);
        $parts = explode('|', $option, 2);
        $value = (int)$parts[0];

        if (array_key_exists($value, constants::display_mode_options())) {
            return $value;
        }

        return constants::DISPLAYMODE_COMPETENCIES;
    }

    /**
     * Get normalized hex color for a shortname.
     *
     * @param array $records Records keyed by shortname.
     * @param string $shortname Field shortname.
     * @return string|null
     */
    private static function get_color_value(array $records, string $shortname): ?string {
        if (empty($records[$shortname]) || empty($records[$shortname]->value)) {
            return null;
        }

        $value = trim((string)$records[$shortname]->value);
        if (!preg_match('/^#?([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
            return null;
        }

        if ($value[0] !== '#') {
            $value = '#' . $value;
        }

        return $value;
    }

    /**
     * Get card image URL from legacy customfield_picture storage.
     *
     * @param array $records Records keyed by shortname.
     * @param string $shortname Field shortname.
     * @return string|null
     */
    private static function get_external_card_image_url(array $records, string $shortname): ?string {
        if (empty($records[$shortname])) {
            return null;
        }

        $record = $records[$shortname];
        if (empty($record->dataid) || empty($record->contextid)) {
            return null;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            (int)$record->contextid,
            'customfield_picture',
            'file',
            (int)$record->dataid,
            '',
            false
        );

        if (empty($files)) {
            return null;
        }

        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out();
    }

    /**
     * Ensure payload has the expected keys.
     *
     * @param array $payload Raw payload.
     * @return array<string, mixed>
     */
    private static function normalise_payload(array $payload): array {
        return [
            'type' => $payload['type'] ?? null,
            'tag1' => $payload['tag1'] ?? null,
            'tag2' => $payload['tag2'] ?? null,
            'bgcolor' => $payload['bgcolor'] ?? null,
            'textcolor' => $payload['textcolor'] ?? null,
            'templatecardimageurl' => $payload['templatecardimageurl'] ?? null,
            'displaymode' => (int)($payload['displaymode'] ?? constants::DISPLAYMODE_COMPETENCIES),
            'timemodified' => isset($payload['timemodified']) ? (int)$payload['timemodified'] : 0,
        ];
    }

    /**
     * Optional DEBUG_DEVELOPER logging for cache operations.
     *
     * @param string $message Debug message.
     */
    private static function debug(string $message): void {
        if (get_config('local_dimensions', 'debugtemplatemetadatacache')) {
            debugging('local_dimensions template_metadata_cache: ' . $message, DEBUG_DEVELOPER);
        }
    }
}
