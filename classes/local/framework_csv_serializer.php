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
 * CSV serialization/parsing for competency frameworks (+ the plugin custom fields).
 *
 * The column contract is a SUPERSET of core admin/tool/lpimportcsv: the same 14
 * columns in the same order (so files interchange with the core tool, which
 * reads them positionally and ignores trailing columns), plus cf_* columns for
 * the plugin's competency custom fields. On parse the 14 core fields are read by
 * position (robust across languages / the core tool) and the cf_* fields by
 * header name (forward-compatible; a plain core CSV imports with empty CFs).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_competency\api;
use core_competency\competency;
use local_dimensions\helper;

/**
 * Serialize a competency framework tree to CSV and parse it back.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework_csv_serializer {
    /** @var string[] The 14 core columns, in the fixed order the core tool uses. */
    const CORE_HEADERS = [
        'parentidnumber', 'idnumber', 'shortname', 'description', 'descriptionformat',
        'scalevalues', 'scaleconfiguration', 'ruletype', 'ruleoutcome', 'ruleconfig',
        'relatedidnumbers', 'exportid', 'isframework', 'taxonomies',
    ];

    /**
     * The plugin custom-field columns, in export order. cf_customscss is emitted only
     * when the custom SCSS feature is enabled. Kept in sync with helper's CF mapping.
     *
     * @var string[]
     */
    const CF_HEADERS = [
        'cf_bgcolor', 'cf_textcolor', 'cf_tag1', 'cf_tag2', 'cf_type',
        'cf_enrollmentfilter', 'cf_singlecourseredirect', 'cf_customscss',
    ];

    /**
     * The full header row for an export.
     *
     * @param bool $includescss Whether to emit the cf_customscss column.
     * @return string[]
     */
    public static function headers(bool $includescss): array {
        $cf = self::CF_HEADERS;
        if (!$includescss) {
            $cf = array_values(array_diff($cf, ['cf_customscss']));
        }
        return array_merge(self::CORE_HEADERS, $cf);
    }

    /**
     * Serialize one framework and its whole competency tree to CSV.
     *
     * @param int $frameworkid Framework id.
     * @param bool $includescss Whether to emit the cf_customscss column.
     * @return array{filename: string, content: string}
     */
    public static function export_framework(int $frameworkid, bool $includescss): array {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $framework = api::read_framework($frameworkid);
        $cfheaders = self::headers($includescss);
        $blankcf = array_fill_keys(array_slice($cfheaders, count(self::CORE_HEADERS)), '');

        $writer = new \csv_export_writer();
        $writer->add_data($cfheaders);

        // Framework row: isframework = 1, empty parent/rule/related/exportid, blank CFs.
        $scale = $framework->get_scale();
        $writer->add_data(array_merge([
            '',
            (string) $framework->get('idnumber'),
            (string) $framework->get('shortname'),
            (string) $framework->get('description'),
            (string) $framework->get('descriptionformat'),
            $scale ? $scale->compact_items() : '',
            (string) $framework->get('scaleconfiguration'),
            '', '', '', '', '',
            '1',
            implode(',', $framework->get('taxonomies')),
        ], array_values($blankcf)));

        // Competency rows, indexed by id so a parent resolves to its idnumber.
        $competencies = api::list_competencies(['competencyframeworkid' => $frameworkid]);
        $idtoidnumber = [];
        foreach ($competencies as $competency) {
            $idtoidnumber[(int) $competency->get('id')] = (string) $competency->get('idnumber');
        }

        foreach ($competencies as $competency) {
            $parentid = (int) $competency->get('parentid');
            $hasownscale = $competency->get('scaleid') !== null && (int) $competency->get('scaleid') > 0;
            $ruleconfig = $competency->get('ruleconfig');
            $core = [
                $parentid && isset($idtoidnumber[$parentid]) ? $idtoidnumber[$parentid] : '',
                (string) $competency->get('idnumber'),
                (string) $competency->get('shortname'),
                (string) $competency->get('description'),
                (string) $competency->get('descriptionformat'),
                $hasownscale ? self::scale_values($competency) : '',
                $hasownscale ? (string) $competency->get('scaleconfiguration') : '',
                (string) $competency->get('ruletype'),
                (string) (int) $competency->get('ruleoutcome'),
                $ruleconfig === null ? 'null' : (string) $ruleconfig,
                self::related_idnumbers($competency, $idtoidnumber),
                (string) (int) $competency->get('id'),
                '',
                '',
            ];
            $cf = helper::export_competency_customfields((int) $competency->get('id'));
            $row = $core;
            foreach (array_slice($cfheaders, count(self::CORE_HEADERS)) as $token) {
                $row[] = (string) ($cf[$token] ?? '');
            }
            $writer->add_data($row);
        }

        $name = \clean_param($framework->get('shortname') . '-' . $framework->get('idnumber'), PARAM_FILE);
        return [
            'filename' => ($name !== '' ? $name : 'framework') . '.csv',
            'content' => $writer->print_csv_data(true),
        ];
    }

    /**
     * Compact scale-item string for a competency's own scale.
     *
     * @param competency $competency Competency.
     * @return string
     */
    private static function scale_values(competency $competency): string {
        $scale = \grade_scale::fetch(['id' => (int) $competency->get('scaleid')]);
        if (!$scale) {
            return '';
        }
        $scale->load_items();
        return $scale->compact_items();
    }

    /**
     * Comma-joined related-competency idnumbers (literal commas escaped as %2C), matching core.
     *
     * @param competency $competency Competency.
     * @param array $idtoidnumber Map of competency id => idnumber.
     * @return string
     */
    private static function related_idnumbers(competency $competency, array $idtoidnumber): string {
        $related = $competency->get_related_competencies();
        $idnumbers = [];
        foreach ($related as $rel) {
            $relid = (int) $rel->get('id');
            $idnumber = $idtoidnumber[$relid] ?? (string) $rel->get('idnumber');
            $idnumbers[] = str_replace(',', '%2C', $idnumber);
        }
        return implode(',', $idnumbers);
    }

    /**
     * Parse CSV text into a framework record and a flat map of competency records.
     *
     * The 14 core fields are read by position; the cf_* fields by header name (so a
     * plain core CSV, which lacks them, still parses with empty custom fields).
     *
     * @param string $text Raw CSV text.
     * @param string $encoding Character encoding.
     * @param string $delimiter Delimiter name (comma, semicolon, tab, …).
     * @return array{framework: \stdClass|null, competencies: array<string, \stdClass>, error: string}
     */
    public static function parse(string $text, string $encoding = 'utf-8', string $delimiter = 'comma'): array {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $iid = \csv_import_reader::get_new_iid('local_dimensions_framework');
        $reader = new \csv_import_reader($iid, 'local_dimensions_framework');
        if (!$reader->load_csv_content($text, $encoding, $delimiter) || !$reader->init()) {
            $error = $reader->get_error();
            $reader->cleanup();
            return ['framework' => null, 'competencies' => [], 'error' => $error ?: get_string('csvemptyfile', 'error')];
        }

        $columns = $reader->get_columns();
        // Map cf_* header token => column index (core fields are read positionally 0-13).
        $cfindex = [];
        foreach ((array) $columns as $index => $name) {
            $token = trim((string) $name);
            if (strpos($token, 'cf_') === 0) {
                $cfindex[$token] = $index;
            }
        }

        $framework = null;
        $competencies = [];
        while ($row = $reader->next()) {
            $get = static function (int $index) use ($row): string {
                return isset($row[$index]) ? (string) $row[$index] : '';
            };
            $cf = [];
            foreach ($cfindex as $token => $index) {
                $cf[$token] = isset($row[$index]) ? (string) $row[$index] : '';
            }

            $isframework = trim($get(12));
            if ($isframework !== '' && $isframework !== '0') {
                // isframework column (position 12) is truthy → the framework row.
                $framework = (object) [
                    'idnumber' => shorten_text(clean_param($get(1), PARAM_TEXT), 100),
                    'shortname' => shorten_text(clean_param($get(2), PARAM_TEXT), 100),
                    'description' => clean_param($get(3), PARAM_RAW),
                    'descriptionformat' => clean_param($get(4), PARAM_INT),
                    'scalevalues' => $get(5),
                    'scaleconfiguration' => $get(6),
                    'taxonomies' => clean_param($get(13), PARAM_TEXT),
                    'children' => [],
                ];
            } else {
                $idnumber = shorten_text(clean_param($get(1), PARAM_TEXT), 100);
                if ($idnumber === '') {
                    continue;
                }
                $competencies[$idnumber] = (object) [
                    'parentidnumber' => clean_param($get(0), PARAM_TEXT),
                    'idnumber' => $idnumber,
                    'shortname' => shorten_text(clean_param($get(2), PARAM_TEXT), 100),
                    'description' => clean_param($get(3), PARAM_RAW),
                    'descriptionformat' => clean_param($get(4), PARAM_INT),
                    'scalevalues' => $get(5),
                    'scaleconfiguration' => $get(6),
                    'ruletype' => clean_param($get(7), PARAM_RAW),
                    'ruleoutcome' => clean_param($get(8), PARAM_INT),
                    'ruleconfig' => $get(9),
                    'relatedidnumbers' => $get(10),
                    'exportid' => clean_param($get(11), PARAM_RAW),
                    'cf' => $cf,
                    'children' => [],
                ];
            }
        }
        $reader->close();
        $reader->cleanup();

        return ['framework' => $framework, 'competencies' => $competencies, 'error' => ''];
    }
}
