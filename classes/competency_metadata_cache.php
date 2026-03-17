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
 * Cache helper for competency metadata used by competency cards.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

/**
 * Cache helper for competency metadata used by competency cards.
 *
 * Payload keys:
 * - tag1
 * - tag2
 * - bgcolor
 * - textcolor
 * - cardimageurl
 * - timemodified
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_metadata_cache {
    /** @var \cache|null Cache instance. */
    private static $cache = null;

    /**
     * Get the cache instance.
     *
     * @return \cache
     */
    private static function get_cache(): \cache {
        if (self::$cache === null) {
            self::$cache = \cache::make('local_dimensions', 'competency_metadata');
        }
        return self::$cache;
    }

    /**
     * Get metadata for a competency using lazy MUC cache.
     *
     * @param int $competencyid Competency ID.
     * @return array<string, mixed>
     */
    public static function get_competency_metadata(int $competencyid): array {
        $cache = self::get_cache();
        $payload = $cache->get($competencyid);

        if ($payload !== false && is_array($payload)) {
            self::debug('cache hit for competency ' . $competencyid);
            return self::normalise_payload($payload);
        }

        self::debug('cache miss for competency ' . $competencyid);
        $payload = self::fetch_competency_metadata($competencyid);
        $cache->set($competencyid, $payload);
        return $payload;
    }

    /**
     * Get metadata for multiple competencies in bulk.
     *
     * Uses MUC get_many/set_many for efficiency. Missing entries are
     * fetched from the database in a single bulk query.
     *
     * @param array<int> $competencyids Competency IDs.
     * @return array<int, array<string, mixed>> Keyed by competency ID.
     */
    public static function get_many(array $competencyids): array {
        if (empty($competencyids)) {
            return [];
        }

        $competencyids = array_unique(array_map('intval', $competencyids));
        $cache = self::get_cache();
        $cached = $cache->get_many($competencyids);

        $results = [];
        $missing = [];

        foreach ($competencyids as $id) {
            if (isset($cached[$id]) && $cached[$id] !== false && is_array($cached[$id])) {
                $results[$id] = self::normalise_payload($cached[$id]);
            } else {
                $missing[] = $id;
            }
        }

        if (!empty($missing)) {
            self::debug('bulk cache miss for ' . count($missing) . ' competencies');
            $fetched = self::fetch_many($missing);
            $tostore = [];
            foreach ($missing as $id) {
                $payload = $fetched[$id] ?? self::empty_payload();
                $tostore[$id] = $payload;
                $results[$id] = $payload;
            }
            $cache->set_many($tostore);
        }

        return $results;
    }

    /**
     * Invalidate cached metadata for one competency.
     *
     * @param int $competencyid Competency ID.
     */
    public static function invalidate_competency(int $competencyid): void {
        self::get_cache()->delete($competencyid);
        self::debug('cache invalidated for competency ' . $competencyid);
    }

    /**
     * Purge all cached competency metadata.
     */
    public static function purge_all(): void {
        self::get_cache()->purge();
        self::debug('cache purged');
    }

    /**
     * Build metadata payload for a single competency from database/custom fields.
     *
     * @param int $competencyid Competency ID.
     * @return array<string, mixed>
     */
    private static function fetch_competency_metadata(int $competencyid): array {
        global $DB;

        $payload = self::empty_payload();

        $timemodified = $DB->get_field('competency', 'timemodified', ['id' => $competencyid]);
        $payload['timemodified'] = $timemodified ? (int)$timemodified : 0;

        $shortnames = self::get_shortnames();

        [$insql, $inparams] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
        $params = [
            'instanceid' => $competencyid,
            'component' => 'local_dimensions',
            'area' => 'competency',
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

        return self::build_payload_from_records($competencyid, $byshortname, $payload);
    }

    /**
     * Fetch metadata for multiple competencies in a single bulk query.
     *
     * @param array<int> $competencyids Competency IDs.
     * @return array<int, array<string, mixed>> Keyed by competency ID.
     */
    private static function fetch_many(array $competencyids): array {
        global $DB;

        $results = [];

        // Fetch timemodified for all competencies.
        [$idsql, $idparams] = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED, 'cid');
        $timerecords = $DB->get_records_sql(
            "SELECT id, timemodified FROM {competency} WHERE id $idsql",
            $idparams
        );

        // Fetch custom field data for all competencies in one query.
        $shortnames = self::get_shortnames();
        [$shortsql, $shortparams] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED, 'sn');
        [$instsql, $instparams] = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED, 'inst');

        $sql = "SELECT d.id AS dataid,
                       d.instanceid,
                       f.shortname,
                       f.configdata,
                       d.contextid,
                       d.value,
                       d.intvalue
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
                  JOIN {customfield_data} d ON d.fieldid = f.id
                 WHERE c.component = :component
                   AND c.area = :area
                   AND f.shortname $shortsql
                   AND d.instanceid $instsql";

        $params = [
            'component' => 'local_dimensions',
            'area' => 'competency',
        ] + $shortparams + $instparams;

        $records = $DB->get_records_sql($sql, $params);

        // Group records by instanceid.
        $grouped = [];
        foreach ($records as $record) {
            $grouped[(int)$record->instanceid][$record->shortname] = $record;
        }

        // Build payload for each competency.
        foreach ($competencyids as $id) {
            $payload = self::empty_payload();
            $payload['timemodified'] = isset($timerecords[$id]) ? (int)$timerecords[$id]->timemodified : 0;
            $byshortname = $grouped[$id] ?? [];
            $results[$id] = self::build_payload_from_records($id, $byshortname, $payload);
        }

        return $results;
    }

    /**
     * Build the payload array from custom field records.
     *
     * @param int $competencyid Competency ID.
     * @param array<string, object> $byshortname Records keyed by shortname.
     * @param array<string, mixed> $payload Base payload to populate.
     * @return array<string, mixed>
     */
    private static function build_payload_from_records(int $competencyid, array $byshortname, array $payload): array {
        $payload['tag1'] = self::get_select_value($byshortname, constants::CFIELD_TAG1);
        $payload['tag2'] = self::get_select_value($byshortname, constants::CFIELD_TAG2);
        $payload['bgcolor'] = self::get_color_value($byshortname, constants::CFIELD_CUSTOMBGCOLOR);
        $payload['textcolor'] = self::get_color_value($byshortname, constants::CFIELD_CUSTOMTEXTCOLOR);

        // Built-in mode first, then legacy external customfield_picture fallback.
        if (picture_manager::is_builtin_mode()) {
            $payload['cardimageurl'] = picture_manager::get_image_url('competency', $competencyid, 'cardimage');
        }

        if (empty($payload['cardimageurl'])) {
            $payload['cardimageurl'] = self::get_external_card_image_url($byshortname, constants::CFIELD_CUSTOMCARD);
        }

        return $payload;
    }

    /**
     * Get the list of custom field shortnames to fetch.
     *
     * @return array<string>
     */
    private static function get_shortnames(): array {
        return [
            constants::CFIELD_TAG1,
            constants::CFIELD_TAG2,
            constants::CFIELD_CUSTOMBGCOLOR,
            constants::CFIELD_CUSTOMTEXTCOLOR,
            constants::CFIELD_CUSTOMCARD,
        ];
    }

    /**
     * Return an empty payload with all expected keys.
     *
     * @return array<string, mixed>
     */
    private static function empty_payload(): array {
        return [
            'tag1' => null,
            'tag2' => null,
            'bgcolor' => null,
            'textcolor' => null,
            'cardimageurl' => null,
            'timemodified' => 0,
        ];
    }

    /**
     * Get select field label for a shortname.
     *
     * @param array<string, object> $records Records keyed by shortname.
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
     * Get normalized hex color for a shortname.
     *
     * @param array<string, object> $records Records keyed by shortname.
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
     * @param array<string, object> $records Records keyed by shortname.
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
     * @param array<string, mixed> $payload Raw payload.
     * @return array<string, mixed>
     */
    private static function normalise_payload(array $payload): array {
        return [
            'tag1' => $payload['tag1'] ?? null,
            'tag2' => $payload['tag2'] ?? null,
            'bgcolor' => $payload['bgcolor'] ?? null,
            'textcolor' => $payload['textcolor'] ?? null,
            'cardimageurl' => $payload['cardimageurl'] ?? null,
            'timemodified' => isset($payload['timemodified']) ? (int)$payload['timemodified'] : 0,
        ];
    }

    /**
     * Optional DEBUG_DEVELOPER logging for cache operations.
     *
     * @param string $message Debug message.
     */
    private static function debug(string $message): void {
        if (get_config('local_dimensions', 'debugcompetencymetadatacache')) {
            debugging('local_dimensions competency_metadata_cache: ' . $message, DEBUG_DEVELOPER);
        }
    }
}
