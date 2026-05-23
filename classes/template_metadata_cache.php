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
 * - idnumber
 * - enrollmentfilter_raw   (stored option key: inherit|all|enrolled|active)
 * - enrollmentfilter       (resolved at read time: all|enrolled|active)
 * - singlecourseredirect_raw (stored option key: inherit|yes|no)
 * - singlecourseredirect   (resolved at read time: bool)
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
     * Bulk variant of {@see get_template_metadata}: read many templates with a
     * single grouped SQL for the cache misses.
     *
     * Cache hits are honoured one-by-one; only the missing IDs hit the database,
     * via one customfield_data SELECT (chunked at 1000 to stay under DB
     * placeholder limits) plus one timemodified SELECT. Picture URLs remain
     * per-template (the file storage API has no cheap batch path) but those are
     * stored back into MUC so subsequent reads hit cache.
     *
     * Use this when rendering a list of templates (e.g. manage_templates) to
     * avoid N cache reads + N SQL misses on a cold cache.
     *
     * @param int[] $templateids Template IDs.
     * @return array<int, array> Map keyed by templateid → normalised payload.
     */
    public static function get_metadata_for_many(array $templateids): array {
        global $DB;

        $templateids = array_values(array_unique(array_filter(array_map('intval', $templateids))));
        if (empty($templateids)) {
            return [];
        }

        $cache = self::get_cache();
        $result = [];
        $missing = [];

        foreach ($templateids as $id) {
            $payload = $cache->get($id);
            if ($payload !== false && is_array($payload)) {
                $result[$id] = self::normalise_payload($payload);
            } else {
                $missing[] = $id;
            }
        }

        if (empty($missing)) {
            return $result;
        }

        $shortnames = [
            constants::CFIELD_TYPE,
            constants::CFIELD_TAG1,
            constants::CFIELD_TAG2,
            constants::CFIELD_CUSTOMBGCOLOR,
            constants::CFIELD_CUSTOMTEXTCOLOR,
            constants::CFIELD_CUSTOMCARD,
            constants::CFIELD_DISPLAYMODE,
            constants::CFIELD_TEMPLATE_IDNUMBER,
            constants::CFIELD_ENROLLMENTFILTER,
            constants::CFIELD_SINGLECOURSEREDIRECT,
        ];

        // Records keyed by [templateid][shortname]; chunked to keep IN-clause
        // size sane on databases with placeholder limits.
        $byinstance = array_fill_keys($missing, []);
        $timemodifiedmap = array_fill_keys($missing, 0);

        foreach (array_chunk($missing, 1000) as $chunk) {
            [$idsql, $idparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'tplid');
            [$snsql, $snparams] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED, 'sn');
            $params = $idparams + $snparams + [
                'component' => 'local_dimensions',
                'area' => 'lp',
            ];

            // NOTE: direct query against core {customfield_*} tables — intentional
            // for chunked bulk shape (one round-trip per 1000-template chunk).
            // The customfield API does not expose this join shape. If core
            // changes the customfield schema (already changed once between 4.x
            // and 5.x), re-validate this query before upgrading.
            $sql = "SELECT d.id AS dataid, d.instanceid, f.shortname, f.configdata,
                           d.contextid, d.value, d.intvalue
                      FROM {customfield_data} d
                      JOIN {customfield_field} f ON f.id = d.fieldid
                      JOIN {customfield_category} c ON c.id = f.categoryid
                     WHERE d.instanceid $idsql
                       AND f.shortname $snsql
                       AND c.component = :component
                       AND c.area = :area";

            foreach ($DB->get_records_sql($sql, $params) as $record) {
                $tid = (int)$record->instanceid;
                if (isset($byinstance[$tid])) {
                    $byinstance[$tid][$record->shortname] = $record;
                }
            }

            // Timemodified for the same chunk (one extra round-trip per chunk).
            [$tsql, $tparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'tplt');
            $rows = $DB->get_records_sql(
                "SELECT id, timemodified FROM {competency_template} WHERE id $tsql",
                $tparams
            );
            foreach ($rows as $row) {
                $timemodifiedmap[(int)$row->id] = (int)$row->timemodified;
            }
        }

        $usebuiltinpictures = picture_manager::is_builtin_mode();

        foreach ($missing as $id) {
            $byshortname = $byinstance[$id];
            $payload = [
                'type' => self::get_select_value($byshortname, constants::CFIELD_TYPE),
                'tag1' => self::get_select_value($byshortname, constants::CFIELD_TAG1),
                'tag2' => self::get_select_value($byshortname, constants::CFIELD_TAG2),
                'bgcolor' => self::get_color_value($byshortname, constants::CFIELD_CUSTOMBGCOLOR),
                'textcolor' => self::get_color_value($byshortname, constants::CFIELD_CUSTOMTEXTCOLOR),
                'displaymode' => self::get_displaymode_value($byshortname),
                'idnumber' => self::get_text_value($byshortname, constants::CFIELD_TEMPLATE_IDNUMBER),
                'enrollmentfilter_raw' => self::get_string_select_key(
                    $byshortname,
                    constants::CFIELD_ENROLLMENTFILTER,
                    array_keys(constants::enrollmentfilter_options()),
                    constants::ENROLLMENTFILTER_INHERIT
                ),
                'singlecourseredirect_raw' => self::get_string_select_key(
                    $byshortname,
                    constants::CFIELD_SINGLECOURSEREDIRECT,
                    array_keys(constants::singlecourseredirect_options()),
                    constants::SINGLECOURSEREDIRECT_INHERIT
                ),
                'templatecardimageurl' => null,
                'timemodified' => $timemodifiedmap[$id],
            ];

            if ($usebuiltinpictures) {
                $payload['templatecardimageurl'] = picture_manager::get_image_url('lp', $id, 'cardimage');
            }
            if (empty($payload['templatecardimageurl'])) {
                $payload['templatecardimageurl'] = self::get_external_card_image_url(
                    $byshortname,
                    constants::CFIELD_CUSTOMCARD
                );
            }

            $cache->set($id, $payload);
            $result[$id] = $payload;
        }

        self::debug('batch fetched metadata for ' . count($missing) . ' templates');
        return $result;
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
            'idnumber' => '',
            'enrollmentfilter_raw' => constants::ENROLLMENTFILTER_INHERIT,
            'singlecourseredirect_raw' => constants::SINGLECOURSEREDIRECT_INHERIT,
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
            constants::CFIELD_TEMPLATE_IDNUMBER,
            constants::CFIELD_ENROLLMENTFILTER,
            constants::CFIELD_SINGLECOURSEREDIRECT,
        ];

        [$insql, $inparams] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
        $params = [
            'instanceid' => $templateid,
            'component' => 'local_dimensions',
            'area' => 'lp',
        ] + $inparams;

        // NOTE: direct query against core {customfield_*} tables — intentional for
        // batch shape (single round-trip pulling shortname + value + intvalue +
        // configdata in one go). The customfield API does not expose this
        // join shape. If core changes the customfield schema (already changed
        // once between 4.x and 5.x), re-validate this query before upgrading.
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
        $payload['idnumber'] = self::get_text_value($byshortname, constants::CFIELD_TEMPLATE_IDNUMBER);
        $payload['enrollmentfilter_raw'] = self::get_string_select_key(
            $byshortname,
            constants::CFIELD_ENROLLMENTFILTER,
            array_keys(constants::enrollmentfilter_options()),
            constants::ENROLLMENTFILTER_INHERIT
        );
        $payload['singlecourseredirect_raw'] = self::get_string_select_key(
            $byshortname,
            constants::CFIELD_SINGLECOURSEREDIRECT,
            array_keys(constants::singlecourseredirect_options()),
            constants::SINGLECOURSEREDIRECT_INHERIT
        );

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
     * The displaymode select field stores options as plain labels
     * (e.g. "Competency Tracker\nFull Plan Overview"). The intvalue is
     * a 1-based index into the options list, which by design maps directly
     * to the DISPLAYMODE_* constant keys (1 = COMPETENCIES, 2 = PLAN).
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
        if ($selectedindex <= 0) {
            return constants::DISPLAYMODE_COMPETENCIES;
        }

        // The display mode select field stores plain labels (not "key|label").
        // The intvalue is the 1-based option index which, by design, maps
        // directly to the DISPLAYMODE_* constant keys (1 = COMPETENCIES, 2 = PLAN).
        if (array_key_exists($selectedindex, constants::display_mode_options())) {
            return $selectedindex;
        }

        return constants::DISPLAYMODE_COMPETENCIES;
    }

    /**
     * Decode a select customfield's stored option key (the part before "|").
     *
     * Select customfields store options as "key|label\nkey|label" and persist a
     * 1-based intvalue pointing at the chosen line. This helper returns the key
     * (validated against $allowed) rather than the joined "key|label" string
     * that get_select_value() returns.
     *
     * @param array $records Records keyed by shortname.
     * @param string $shortname Field shortname to decode.
     * @param string[] $allowed Allowed option keys; values outside fall back to $default.
     * @param string $default Default key when no row, invalid configdata, or out-of-range index.
     * @return string Option key from $allowed, or $default.
     */
    private static function get_string_select_key(
        array $records,
        string $shortname,
        array $allowed,
        string $default
    ): string {
        if (empty($records[$shortname])) {
            return $default;
        }

        $record = $records[$shortname];
        $selectedindex = isset($record->intvalue) ? (int)$record->intvalue : 0;
        if ($selectedindex <= 0 || empty($record->configdata)) {
            return $default;
        }

        $config = json_decode($record->configdata, true);
        if (!is_array($config) || empty($config['options'])) {
            return $default;
        }

        $options = explode("\n", $config['options']);
        $optionindex = $selectedindex - 1;
        if (!isset($options[$optionindex])) {
            return $default;
        }

        $parts = explode('|', trim($options[$optionindex]), 2);
        $key = trim((string)($parts[0] ?? ''));

        return in_array($key, $allowed, true) ? $key : $default;
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
     * Get plain text value for a shortname.
     *
     * @param array $records Records keyed by shortname.
     * @param string $shortname Field shortname.
     * @return string Value with whitespace trimmed (empty string when unset).
     */
    private static function get_text_value(array $records, string $shortname): string {
        if (empty($records[$shortname]) || !isset($records[$shortname]->value)) {
            return '';
        }
        return trim((string)$records[$shortname]->value);
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
        $rawef = (string)($payload['enrollmentfilter_raw'] ?? constants::ENROLLMENTFILTER_INHERIT);
        $rawsc = (string)($payload['singlecourseredirect_raw'] ?? constants::SINGLECOURSEREDIRECT_INHERIT);

        $allowedef = array_keys(constants::enrollmentfilter_options());
        $allowedsc = array_keys(constants::singlecourseredirect_options());
        if (!in_array($rawef, $allowedef, true)) {
            $rawef = constants::ENROLLMENTFILTER_INHERIT;
        }
        if (!in_array($rawsc, $allowedsc, true)) {
            $rawsc = constants::SINGLECOURSEREDIRECT_INHERIT;
        }

        $resolvedef = ($rawef === constants::ENROLLMENTFILTER_INHERIT)
            ? ((string)(get_config('local_dimensions', 'enrollmentfilter') ?: constants::ENROLLMENTFILTER_ALL))
            : $rawef;
        $resolvedsc = ($rawsc === constants::SINGLECOURSEREDIRECT_INHERIT)
            ? (bool) get_config('local_dimensions', 'singlecourseredirect')
            : ($rawsc === constants::SINGLECOURSEREDIRECT_YES);

        return [
            'type' => $payload['type'] ?? null,
            'tag1' => $payload['tag1'] ?? null,
            'tag2' => $payload['tag2'] ?? null,
            'bgcolor' => $payload['bgcolor'] ?? null,
            'textcolor' => $payload['textcolor'] ?? null,
            'templatecardimageurl' => $payload['templatecardimageurl'] ?? null,
            'displaymode' => (int)($payload['displaymode'] ?? constants::DISPLAYMODE_COMPETENCIES),
            'idnumber' => isset($payload['idnumber']) ? (string)$payload['idnumber'] : '',
            'enrollmentfilter_raw' => $rawef,
            'enrollmentfilter' => $resolvedef,
            'singlecourseredirect_raw' => $rawsc,
            'singlecourseredirect' => $resolvedsc,
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
