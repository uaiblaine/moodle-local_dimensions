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
 * CSV serialization/parsing for learning plan templates (+ the plugin custom fields).
 *
 * The file has TWO row types, discriminated by a leading rowtype column: one `template`
 * row per template followed by its `link` rows, in sortorder. Every column is read by
 * HEADER NAME using stable lowercase-ASCII tokens, unlike framework_csv_serializer whose
 * 14 positional columns exist only to interchange with core's tool_lpimportcsv - core has
 * no learning plan template import or export at all, so there is no positional contract to
 * honour here. Name indexing means a hand-authored file may omit any column: an ABSENT
 * column leaves the field untouched, a present-but-empty cell clears it.
 *
 * A one-directional ingest shim reads a five-column admin/tool/lptmanager export. Our own
 * output is deliberately NOT readable by that plugin (its format cannot carry a second
 * framework, a custom field, visible, duedate or a context).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_competency\api;
use core_competency\competency;
use core_competency\template;
use local_dimensions\helper;

/**
 * Serialize learning plan templates to CSV and parse them back.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_csv_serializer {
    /** @var string The rowtype value marking a template row. */
    const ROWTYPE_TEMPLATE = 'template';

    /** @var string The rowtype value marking a competency-link row. */
    const ROWTYPE_LINK = 'link';

    /** @var string[] The core columns, in export order. Read by name, never by position. */
    const HEADERS_CORE = [
        'rowtype', 'template_idnumber', 'shortname', 'description', 'descriptionformat',
        'visible', 'duedate', 'sourcecontext',
        'framework_idnumber', 'framework_shortname', 'competency_idnumber', 'competency_shortname', 'sortorder',
    ];

    /**
     * The plugin custom-field columns, in export order. cf_customscss is emitted only when
     * the custom SCSS feature is enabled (kept last so the array_diff in headers() drops it
     * cleanly). The first four are lp-only; the rest match framework_csv_serializer::CF_HEADERS
     * token for token, so the two formats keep one encoding.
     *
     * @var string[]
     */
    const HEADERS_CF = [
        'cf_displaymode', 'cf_subline_source', 'cf_showrelated', 'cf_showrelatedlink',
        'cf_bgcolor', 'cf_textcolor', 'cf_tag1', 'cf_tag2', 'cf_type',
        'cf_enrollmentfilter', 'cf_singlecourseredirect', 'cf_lockedcardmode', 'cf_showlockeddate',
        'cf_customscss',
    ];

    /**
     * The full header row for an export.
     *
     * @param bool $includescss Whether to emit the cf_customscss column.
     * @return string[]
     */
    public static function headers(bool $includescss): array {
        $cf = self::HEADERS_CF;
        if (!$includescss) {
            $cf = array_values(array_diff($cf, ['cf_customscss']));
        }
        return array_merge(self::HEADERS_CORE, $cf);
    }

    /**
     * Serialize one or more templates and their competency links to CSV.
     *
     * @param array $templateids Template ids, in the order they should appear.
     * @param bool $includescss Whether to emit the cf_customscss column.
     * @return array{filename: string, content: string, frameworks: array} The frameworks entry
     *     lists every distinct framework the links reference, as id/idnumber/shortname maps, so
     *     the caller can offer them as companion structure downloads.
     */
    public static function export_templates(array $templateids, bool $includescss): array {
        $headers = self::headers($includescss);
        $cftokens = array_slice($headers, count(self::HEADERS_CORE));
        $blanktail = array_fill(0, count($cftokens), '');

        $rows = [$headers];
        $frameworks = [];
        $exported = 0;
        $lastshortname = '';

        foreach ($templateids as $rawid) {
            $templateid = (int) $rawid;
            if ($templateid <= 0) {
                continue;
            }
            $tpl = template::get_record(['id' => $templateid]);
            if (!$tpl) {
                continue;
            }
            $exported++;
            $lastshortname = (string) $tpl->get('shortname');
            $cf = helper::export_template_customfields($templateid);

            $row = [
                self::ROWTYPE_TEMPLATE,
                (string) ($cf['template_idnumber'] ?? ''),
                $lastshortname,
                (string) $tpl->get('description'),
                (string) (int) $tpl->get('descriptionformat'),
                (string) (int) $tpl->get('visible'),
                self::format_duedate((int) $tpl->get('duedate')),
                self::describe_context($tpl),
                '', '', '', '', '',
            ];
            foreach ($cftokens as $token) {
                $row[] = (string) ($cf[$token] ?? '');
            }
            $rows[] = $row;

            $parentkey = (string) ($cf['template_idnumber'] ?? '');
            $sortorder = 0;
            foreach (api::list_competencies_in_template($templateid) as $competency) {
                $framework = self::framework_of($competency, $frameworks);
                $rows[] = array_merge([
                    self::ROWTYPE_LINK,
                    $parentkey,
                    $parentkey === '' ? $lastshortname : '',
                    '', '', '', '', '',
                    (string) ($framework['idnumber'] ?? ''),
                    (string) ($framework['shortname'] ?? ''),
                    (string) $competency->get('idnumber'),
                    (string) $competency->get('shortname'),
                    (string) $sortorder,
                ], $blanktail);
                $sortorder++;
            }
        }

        $content = '';
        foreach ($rows as $row) {
            $content .= self::encode_row($row) . "\n";
        }

        return [
            'filename' => self::build_filename($exported, $lastshortname),
            'content' => $content,
            'frameworks' => array_values($frameworks),
        ];
    }

    /**
     * The export filename: the template's own name for a single export, a generic one otherwise.
     *
     * @param int $exported How many templates the file carries.
     * @param string $shortname The last exported template's shortname.
     * @return string
     */
    private static function build_filename(int $exported, string $shortname): string {
        if ($exported === 1) {
            $name = \clean_param($shortname, PARAM_FILE);
            if ($name !== '') {
                return $name . '.csv';
            }
        }
        return 'learningplantemplates.csv';
    }

    /**
     * The framework a competency belongs to, memoised into the caller's collector.
     *
     * @param competency $competency The competency being exported.
     * @param array $frameworks Collector keyed by framework id (appended by reference).
     * @return array{id: int, idnumber: string, shortname: string} Empty strings when unreadable.
     */
    private static function framework_of(competency $competency, array &$frameworks): array {
        $frameworkid = (int) $competency->get('competencyframeworkid');
        if (isset($frameworks[$frameworkid])) {
            return $frameworks[$frameworkid];
        }
        $framework = $competency->get_framework();
        $entry = [
            'id' => $frameworkid,
            'idnumber' => $framework ? (string) $framework->get('idnumber') : '',
            'shortname' => $framework ? (string) $framework->get('shortname') : '',
        ];
        $frameworks[$frameworkid] = $entry;
        return $entry;
    }

    /**
     * Describe a template's context for the preview, without ever being read back as a target.
     *
     * Carries the category NAME as well as its idnumber, because most categories have no
     * idnumber and a bare empty cell would be indistinguishable from the system context.
     *
     * @param template $tpl The template being exported.
     * @return string 'system', or 'Category name (idnumber)' / 'Category name'.
     */
    private static function describe_context(template $tpl): string {
        $context = $tpl->get_context();
        if ($context->contextlevel != CONTEXT_COURSECAT) {
            return 'system';
        }
        $category = \core_course_category::get($context->instanceid, IGNORE_MISSING, true);
        if (!$category) {
            return '';
        }
        $idnumber = (string) $category->idnumber;
        $name = (string) $category->name;
        return $idnumber === '' ? $name : $name . ' (' . $idnumber . ')';
    }

    /**
     * Serialize a duedate timestamp as a UTC date, or an empty cell when there is none.
     *
     * UTC is pinned deliberately: the form builds the timestamp in the acting user's timezone,
     * so an unpinned round trip would reconstruct a different integer for the same wall-clock
     * string, template::validate_duedate()'s "unchanged" short-circuit would miss, and a
     * re-import of an untouched file would block on a past duedate.
     *
     * @param int $duedate Unix timestamp, or 0 for none.
     * @return string 'YYYY-MM-DD', or 'YYYY-MM-DD HH:MM' when the time is not UTC midnight.
     */
    private static function format_duedate(int $duedate): string {
        if ($duedate <= 0) {
            return '';
        }
        $date = new \DateTimeImmutable('@' . $duedate);
        $date = $date->setTimezone(new \DateTimeZone('UTC'));
        return $date->format('H:i') === '00:00' ? $date->format('Y-m-d') : $date->format('Y-m-d H:i');
    }

    /**
     * Parse a UTC duedate cell back to a timestamp.
     *
     * @param string $value The raw cell: a UTC date, a UTC date and time, or a bare timestamp.
     * @return int|null The timestamp, 0 for an empty cell, or null when unparseable.
     */
    public static function parse_duedate(string $value): ?int {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $utc = new \DateTimeZone('UTC');
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value, $utc);
            if ($date && $date->format($format) === $value) {
                return $date->getTimestamp();
            }
        }
        return null;
    }

    /**
     * Encode one row as an RFC 4180 CSV line (every field quoted, internal quotes doubled).
     *
     * Built in memory rather than via csv_export_writer, whose fixed per-user temp path is
     * double-unlinked when two exports share a request - a PHP warning that fails
     * phpunit --fail-on-warning. See framework_csv_serializer::encode_row().
     *
     * @param array $row Cell values.
     * @return string
     */
    private static function encode_row(array $row): string {
        return implode(',', array_map(static function ($cell): string {
            return '"' . str_replace('"', '""', (string) $cell) . '"';
        }, $row));
    }

    /**
     * Parse CSV text into template records and competency-link records.
     *
     * Nothing is resolved against the database here and nothing is validated beyond the
     * shape of the file: template_import_analyser owns every verdict. In particular
     * shortname is NOT passed through shorten_text(), so the analyser can report an
     * over-long name rather than silently truncating it.
     *
     * @param string $text Raw CSV text.
     * @param string $encoding Character encoding.
     * @param string $delimiter Delimiter name (comma, semicolon, tab, ...).
     * @return array{templates: array, links: array, error: string, legacy: bool} Templates are
     *     keyed 0..n in file order and carry a cf map; links carry their parent key and sortorder.
     */
    public static function parse(string $text, string $encoding = 'utf-8', string $delimiter = 'comma'): array {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $empty = ['templates' => [], 'links' => [], 'error' => '', 'legacy' => false];

        $iid = \csv_import_reader::get_new_iid('local_dimensions_template');
        $reader = new \csv_import_reader($iid, 'local_dimensions_template');
        if (!$reader->load_csv_content($text, $encoding, $delimiter) || !$reader->init()) {
            $error = $reader->get_error();
            $reader->cleanup();
            $empty['error'] = $error ?: get_string('csvemptyfile', 'error');
            return $empty;
        }

        $columns = array_map(static function ($name): string {
            return strtolower(trim((string) $name));
        }, (array) $reader->get_columns());

        // A framework CSV has the same shape as a legacy template CSV to a loose test, so it
        // is refused by name before the shim can misread its columns as template fields.
        if (self::looks_like_framework_csv($columns)) {
            $reader->close();
            $reader->cleanup();
            $empty['error'] = get_string('central_plans_import_frameworkcsv', 'local_dimensions');
            return $empty;
        }

        $index = array_flip($columns);
        $legacy = !isset($index['rowtype']);
        if ($legacy && count($columns) !== 5) {
            $reader->close();
            $reader->cleanup();
            $empty['error'] = get_string('central_plans_import_unknownformat', 'local_dimensions');
            return $empty;
        }

        $result = $legacy ? self::parse_legacy($reader) : self::parse_native($reader, $index);
        $reader->close();
        $reader->cleanup();
        return $result;
    }

    /**
     * Whether the header row is this plugin's own framework CSV rather than a template CSV.
     *
     * @param array $columns Lower-cased header tokens.
     * @return bool
     */
    private static function looks_like_framework_csv(array $columns): bool {
        $core = framework_csv_serializer::CORE_HEADERS;
        return count(array_intersect($columns, $core)) >= 6 && !in_array('rowtype', $columns, true);
    }

    /**
     * Read the native two-row-type file.
     *
     * @param \csv_import_reader $reader An initialised reader positioned at the first data row.
     * @param array $index Map of lower-cased header token => column index.
     * @return array{templates: array, links: array, error: string, legacy: bool}
     */
    private static function parse_native(\csv_import_reader $reader, array $index): array {
        $templates = [];
        $links = [];
        $rownumber = 1;

        while ($row = $reader->next()) {
            $rownumber++;
            $get = static function (string $token) use ($row, $index): string {
                if (!isset($index[$token])) {
                    return '';
                }
                $position = $index[$token];
                return isset($row[$position]) ? trim((string) $row[$position]) : '';
            };
            $has = static function (string $token) use ($index): bool {
                return isset($index[$token]);
            };

            $rowtype = strtolower($get('rowtype'));
            if ($rowtype === self::ROWTYPE_TEMPLATE) {
                $cf = [];
                foreach (self::HEADERS_CF as $token) {
                    if ($has($token)) {
                        $cf[$token] = $get($token);
                    }
                }
                if ($has('template_idnumber')) {
                    $cf['template_idnumber'] = $get('template_idnumber');
                }
                $templates[] = (object) [
                    'rownumber' => $rownumber,
                    'templateidnumber' => $get('template_idnumber'),
                    'shortname' => \clean_param($get('shortname'), PARAM_TEXT),
                    'description' => $has('description') ? \clean_param($get('description'), PARAM_RAW) : null,
                    'descriptionformat' => $has('descriptionformat') ? $get('descriptionformat') : null,
                    'visible' => $has('visible') ? $get('visible') : null,
                    'duedate' => $has('duedate') ? $get('duedate') : null,
                    'sourcecontext' => $get('sourcecontext'),
                    'cf' => $cf,
                    'links' => [],
                ];
            } else if ($rowtype === self::ROWTYPE_LINK) {
                $links[] = (object) [
                    'rownumber' => $rownumber,
                    'parentidnumber' => $get('template_idnumber'),
                    'parentshortname' => \clean_param($get('shortname'), PARAM_TEXT),
                    'frameworkidnumber' => $get('framework_idnumber'),
                    'frameworkshortname' => $get('framework_shortname'),
                    'competencyidnumber' => $get('competency_idnumber'),
                    'competencyshortname' => $get('competency_shortname'),
                    'sortorder' => $get('sortorder'),
                ];
            } else {
                $links[] = (object) [
                    'rownumber' => $rownumber,
                    'unknownrowtype' => $rowtype,
                    'parentidnumber' => '',
                    'parentshortname' => '',
                    'frameworkidnumber' => '',
                    'frameworkshortname' => '',
                    'competencyidnumber' => '',
                    'competencyshortname' => '',
                    'sortorder' => '',
                ];
            }
        }

        return ['templates' => $templates, 'links' => $links, 'error' => '', 'legacy' => false];
    }

    /**
     * Read a five-column admin/tool/lptmanager export.
     *
     * That format is positional (its own header row carries localised get_string() values, so
     * the names cannot be trusted) and carries no ordering metadata, so link sortorder is the
     * ordinal of each comma-split idnumber. Nothing else about it is honoured on export.
     *
     * @param \csv_import_reader $reader An initialised reader positioned at the first data row.
     * @return array{templates: array, links: array, error: string, legacy: bool}
     */
    private static function parse_legacy(\csv_import_reader $reader): array {
        $templates = [];
        $links = [];
        $rownumber = 1;

        while ($row = $reader->next()) {
            $rownumber++;
            $cell = static function (int $position) use ($row): string {
                return isset($row[$position]) ? trim((string) $row[$position]) : '';
            };
            $shortname = \clean_param($cell(0), PARAM_TEXT);
            if ($shortname === '') {
                continue;
            }
            $templates[] = (object) [
                'rownumber' => $rownumber,
                'templateidnumber' => '',
                'shortname' => $shortname,
                'description' => \clean_param($cell(1), PARAM_RAW),
                'descriptionformat' => $cell(2),
                'visible' => null,
                'duedate' => null,
                'sourcecontext' => '',
                'cf' => [],
                'links' => [],
            ];
            $sortorder = 0;
            foreach (explode(',', $cell(4)) as $idnumber) {
                $idnumber = trim($idnumber);
                if ($idnumber === '') {
                    continue;
                }
                $links[] = (object) [
                    'rownumber' => $rownumber,
                    'parentidnumber' => '',
                    'parentshortname' => $shortname,
                    'frameworkidnumber' => $cell(3),
                    'frameworkshortname' => '',
                    'competencyidnumber' => $idnumber,
                    'competencyshortname' => '',
                    'sortorder' => (string) $sortorder,
                ];
                $sortorder++;
            }
        }

        return ['templates' => $templates, 'links' => $links, 'error' => '', 'legacy' => true];
    }
}
