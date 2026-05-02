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
 * Helpers for the custom-field driven chip filter UI.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

/**
 * Static helpers that read custom-field values for the chip filter UI.
 */
class chip_filters {
    /**
     * Parse a CSV admin setting into a list of safe shortnames.
     *
     * @param string|null $configvalue The raw setting value.
     * @return string[] Sanitised, deduplicated shortnames.
     */
    public static function parse_shortnames(?string $configvalue): array {
        if (empty($configvalue)) {
            return [];
        }
        $parts = preg_split('/[\s,]+/', trim((string) $configvalue));
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            // Custom field shortnames must be lowercase ASCII (Moodle convention).
            if ($part !== '' && preg_match('/^[a-z0-9_]+$/', $part)) {
                $out[$part] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * Read selected custom-field values for a single competency.
     *
     * @param int $competencyid
     * @param string[] $shortnames
     * @return array<string, string> Map of shortname => stringified value (empty when missing).
     */
    public static function get_competency_values(int $competencyid, array $shortnames): array {
        if (empty($shortnames)) {
            return [];
        }
        return self::read_values('competency', $competencyid, $shortnames);
    }

    /**
     * Read selected custom-field values for many courses.
     *
     * Uses the local_dimensions/course_customfields cache to avoid querying
     * the same course twice across requests.
     *
     * @param int[] $courseids
     * @param string[] $shortnames
     * @return array<int, array<string, string>> Map of courseid => map of shortname => value.
     */
    public static function get_course_values(array $courseids, array $shortnames): array {
        $result = [];
        if (empty($shortnames) || empty($courseids)) {
            return $result;
        }

        $cache = \cache::make('local_dimensions', 'course_customfields');
        $missing = [];
        foreach ($courseids as $cid) {
            $cid = (int) $cid;
            $cached = $cache->get($cid);
            if ($cached !== false && is_array($cached)) {
                $result[$cid] = array_intersect_key($cached, array_flip($shortnames));
                // Ensure every requested shortname is present (empty when missing).
                foreach ($shortnames as $sn) {
                    if (!array_key_exists($sn, $result[$cid])) {
                        $result[$cid][$sn] = '';
                    }
                }
            } else {
                $missing[] = $cid;
            }
        }

        if (!empty($missing)) {
            $loaded = self::load_course_values_batch($missing);
            foreach ($missing as $cid) {
                $values = $loaded[$cid] ?? [];
                $cache->set($cid, $values);
                $picked = [];
                foreach ($shortnames as $sn) {
                    $picked[$sn] = $values[$sn] ?? '';
                }
                $result[$cid] = $picked;
            }
        }

        return $result;
    }

    /**
     * Read all course custom field values in a single batched query.
     *
     * @param int[] $courseids
     * @return array<int, array<string, string>>
     */
    protected static function load_course_values_batch(array $courseids): array {
        $out = [];
        foreach ($courseids as $cid) {
            $out[(int) $cid] = [];
        }

        try {
            $handler = \core_course\customfield\course_handler::create();
        } catch (\Throwable $e) {
            return $out;
        }

        // Use the per-instance API (works on every supported Moodle release).
        // Performance trade-off: small N+1 contained by the chip cache above.
        foreach ($courseids as $cid) {
            $cid = (int) $cid;
            try {
                $datas = $handler->get_instance_data($cid, true);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($datas as $data) {
                $field = $data->get_field();
                $shortname = $field->get('shortname');
                $out[$cid][$shortname] = self::stringify_value($data->get_value());
            }
        }

        return $out;
    }

    /**
     * Read selected custom-field values for any local_dimensions area (lp/competency).
     *
     * @param string $area "lp" or "competency"
     * @param int $instanceid
     * @param string[] $shortnames
     * @return array<string, string>
     */
    protected static function read_values(string $area, int $instanceid, array $shortnames): array {
        global $DB;

        if (empty($shortnames)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED, 'sn');

        $sql = "SELECT f.shortname, d.value
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
             LEFT JOIN {customfield_data} d ON d.fieldid = f.id AND d.instanceid = :instanceid
                 WHERE c.component = :component
                   AND c.area = :area
                   AND f.shortname $insql";

        $params = $inparams + [
            'component' => 'local_dimensions',
            'area' => $area,
            'instanceid' => $instanceid,
        ];

        $rows = $DB->get_records_sql($sql, $params);
        $values = [];
        foreach ($shortnames as $sn) {
            $values[$sn] = '';
        }
        foreach ($rows as $row) {
            $values[$row->shortname] = self::stringify_value($row->value);
        }
        return $values;
    }

    /**
     * Coerce a stored custom-field value into a display-safe string.
     *
     * @param mixed $value
     * @return string
     */
    protected static function stringify_value($value): string {
        if ($value === null || $value === false) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            return '';
        }
        return trim((string) $value);
    }

    /**
     * Resolve human-readable labels (custom field "name") for the given
     * shortnames in a given area. Falls back to the shortname when the
     * field cannot be located or has an empty name.
     *
     * @param string $area "lp", "competency" (local_dimensions areas) or "course" (core_course area).
     * @param string[] $shortnames
     * @return array<string, string> Map of shortname => display label.
     */
    public static function get_field_labels(string $area, array $shortnames): array {
        global $DB;

        $labels = [];
        foreach ($shortnames as $sn) {
            $labels[$sn] = $sn;
        }
        if (empty($shortnames)) {
            return $labels;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED, 'sn');

        if ($area === 'course') {
            // Core course custom fields live in component=core_course, area=course.
            $component = 'core_course';
            $cfarea = 'course';
        } else {
            $component = 'local_dimensions';
            $cfarea = $area;
        }

        $sql = "SELECT f.shortname, f.name
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE c.component = :component
                   AND c.area = :area
                   AND f.shortname $insql";
        $params = $inparams + ['component' => $component, 'area' => $cfarea];

        $rows = $DB->get_records_sql($sql, $params);
        foreach ($rows as $row) {
            if (!empty($row->name)) {
                $labels[$row->shortname] = format_string($row->name);
            }
        }
        return $labels;
    }

    /**
     * Build a "filterfields" template payload from per-instance values.
     *
     * @param string[] $shortnames Configured shortnames in display order.
     * @param array<int, array<string, string>> $instancevalues Map of instanceid => {shortname => value}.
     * @param array<string, string> $labels Optional shortname => human label override.
     * @return array<int, array{shortname:string,label:string,values:array<int,array{value:string}>}>
     */
    public static function build_filterfields_payload(
        array $shortnames,
        array $instancevalues,
        array $labels = []
    ): array {
        $out = [];
        foreach ($shortnames as $shortname) {
            $unique = [];
            foreach ($instancevalues as $values) {
                $value = $values[$shortname] ?? '';
                if ($value === '') {
                    continue;
                }
                $unique[$value] = true;
            }
            if (empty($unique)) {
                continue;
            }
            $valueslist = array_keys($unique);
            sort($valueslist, SORT_NATURAL | SORT_FLAG_CASE);
            $out[] = [
                'shortname' => $shortname,
                'groupid' => preg_replace('/[^a-z0-9_-]/i', '-', $shortname),
                'label' => $labels[$shortname] ?? $shortname,
                'values' => array_map(static function ($v) {
                    return ['value' => $v];
                }, $valueslist),
            ];
        }
        return $out;
    }
}
