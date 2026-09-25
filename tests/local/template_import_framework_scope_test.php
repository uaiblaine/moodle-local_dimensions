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

namespace local_dimensions\local;

use core_competency\template;
use core_competency\template_competency;
use local_dimensions\helper;

/**
 * Which competency structures a learning plan CSV can resolve and link, by the context it is imported into.
 *
 * The fixture holds three structures with competencies: one at the system context, one in the target
 * category and one in a sibling category. From the target, the system structure is a parent and the
 * sibling's is in neither direction, so only the first two may be resolved; from the system context
 * all three are readable, which is the control proving the sibling rows are resolvable at all.
 *
 * The covers tags stay in this docblock because moodle-cs for Moodle 4.5 cannot see PHPUnit
 * attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\template_import_analyser
 * @covers     \local_dimensions\local\template_csv_importer
 */
final class template_import_framework_scope_test extends \advanced_testcase {
    /**
     * A structure ID number resolves only among the structures readable from the target.
     *
     * @return void
     */
    public function test_a_structure_idnumber_resolves_only_from_the_target(): void {
        $site = $this->prepare_site();
        $csv = $this->scoped_csv();

        $plan = $this->analyse($csv, $site['target']);
        $links = array_values($plan->get_item('t0')['links']);

        $this->assertSame(template_import_verdict::LINK_MISSINGFRAMEWORK, $links[0]['status'], 'Sibling by ID number');
        $this->assertSame(0, $links[0]['competencyid']);
        $this->assertFalse($links[0]['selectable']);
        $this->assertContains(
            ['idnumber' => 'FW-SIB', 'shortname' => 'Sibling structure'],
            $plan->get_missing_structures()
        );
        $this->assertContains('frameworkunreadable', array_column($plan->get_notices(), 'key'));
        // The name lookup already stopped at the target's structures.
        $this->assertSame(template_import_verdict::LINK_MISSINGFRAMEWORK, $links[1]['status'], 'Sibling by name');

        // The structure in the target category and the one at the system context above it resolve.
        $this->assertSame(template_import_verdict::LINK_MATCHED, $links[2]['status'], 'Target by ID number');
        $this->assertSame($site['competencies']['target'], $links[2]['competencyid']);
        $this->assertSame(template_import_verdict::LINK_MATCHED, $links[3]['status'], 'System by ID number');
        $this->assertSame($site['competencies']['system'], $links[3]['competencyid']);

        // The control: from the system context the sibling category is a child, so both of its rows resolve.
        $fromsystem = $this->analyse($csv, \context_system::instance());
        $links = array_values($fromsystem->get_item('t0')['links']);
        $this->assertSame(template_import_verdict::LINK_MATCHED, $links[0]['status'], 'Sibling by ID number, from the system');
        $this->assertSame($site['competencies']['sibling'], $links[0]['competencyid']);
        $this->assertSame(template_import_verdict::LINK_MATCHEDFALLBACK, $links[1]['status'], 'Sibling by name, from the system');
        $this->assertNotContains('frameworkunreadable', array_column($fromsystem->get_notices(), 'key'));
    }

    /**
     * An import into the target category links the target's competencies and never the sibling's.
     *
     * Every link row is ticked, not only the preselected ones, so the refusal has to come from the
     * projection and not from the browser leaving the row unticked.
     *
     * @return void
     */
    public function test_the_import_never_links_a_sibling_structure(): void {
        $site = $this->prepare_site();
        $csv = $this->scoped_csv();

        $results = $this->apply($csv, $site['target']);

        $this->assertSame(template_import_verdict::OUTCOME_CREATED, $results[0]['outcome'], json_encode($results[0]));
        $template = template::get_record(['shortname' => 'Scoped']);
        $this->assertNotFalse($template);
        $this->assertSame((int) $site['target']->id, (int) $template->get('contextid'));
        $linked = array_map(
            static fn(template_competency $link): int => (int) $link->get('competencyid'),
            template_competency::get_records(['templateid' => (int) $template->get('id')])
        );
        sort($linked);
        $expected = [$site['competencies']['target'], $site['competencies']['system']];
        sort($expected);
        $this->assertSame($expected, $linked);
    }

    /**
     * The file under test: one template whose links name each structure.
     *
     * Rows in order: the sibling structure by ID number, the sibling structure by name only, the target
     * category's structure and the system structure.
     *
     * @return string The CSV text.
     */
    private function scoped_csv(): string {
        return $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-SCOPE', 'shortname' => 'Scoped'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-SCOPE', 'framework_idnumber' => 'FW-SIB',
             'framework_shortname' => 'Sibling structure', 'competency_idnumber' => 'C-SIB', 'sortorder' => '0'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-SCOPE', 'framework_idnumber' => '',
             'framework_shortname' => 'Sibling structure', 'competency_idnumber' => 'C-SIB', 'sortorder' => '1'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-SCOPE', 'framework_idnumber' => 'FW-TGT',
             'framework_shortname' => 'Target structure', 'competency_idnumber' => 'C-TGT', 'sortorder' => '2'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-SCOPE', 'framework_idnumber' => 'FW-SYS',
             'framework_shortname' => 'System structure', 'competency_idnumber' => 'C-SYS', 'sortorder' => '3'],
        ]);
    }

    /**
     * Two sibling categories and a structure with one competency at the system context and in each.
     *
     * @return array Keys target (the target category's context) and competencies (ids keyed system,
     *     target and sibling).
     */
    private function prepare_site(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $dg = $this->getDataGenerator();
        $target = \context_coursecat::instance((int) $dg->create_category(['name' => 'Target school'])->id);
        $sibling = \context_coursecat::instance((int) $dg->create_category(['name' => 'Sibling school'])->id);

        return [
            'target' => $target,
            'competencies' => [
                'system' => $this->create_structure(\context_system::instance(), 'FW-SYS', 'System structure', 'C-SYS'),
                'target' => $this->create_structure($target, 'FW-TGT', 'Target structure', 'C-TGT'),
                'sibling' => $this->create_structure($sibling, 'FW-SIB', 'Sibling structure', 'C-SIB'),
            ],
        ];
    }

    /**
     * Create a structure holding one competency.
     *
     * @param \context $context The context the structure lives in.
     * @param string $idnumber The structure ID number.
     * @param string $shortname The structure name.
     * @param string $competencyidnumber The competency ID number, also used as its name.
     * @return int The competency id.
     */
    private function create_structure(\context $context, string $idnumber, string $shortname, string $competencyidnumber): int {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework([
            'contextid' => $context->id,
            'idnumber' => $idnumber,
            'shortname' => $shortname,
        ]);

        return (int) $ccg->create_competency([
            'competencyframeworkid' => (int) $framework->get('id'),
            'idnumber' => $competencyidnumber,
            'shortname' => $competencyidnumber,
        ])->get('id');
    }

    /**
     * Parse a CSV and analyse it against a context.
     *
     * @param string $csv The CSV text.
     * @param \context $target The target context.
     * @return template_import_plan
     */
    private function analyse(string $csv, \context $target): template_import_plan {
        $parsed = template_csv_serializer::parse($csv);
        $this->assertSame('', $parsed['error']);
        return (new template_import_analyser($parsed, $target, false))->analyse();
    }

    /**
     * Import the first template of a CSV into a context, with every link row ticked.
     *
     * The verdict, fingerprint and remedy are the ones a preview of the same file offers, as the
     * browser would send them back.
     *
     * @param string $csv The CSV text.
     * @param \context $target The target context.
     * @return array The per-item results.
     */
    private function apply(string $csv, \context $target): array {
        $item = $this->analyse($csv, $target)->get_item('t0');
        $this->assertSame(template_import_verdict::VERDICT_CREATE, $item['verdict']);
        $remedy = template_import_verdict::REMEDY_NONE;
        foreach ($item['remedies'] as $offered) {
            if (!empty($offered['selected'])) {
                $remedy = $offered['remedy'];
            }
        }

        $importer = new template_csv_importer(template_csv_serializer::parse($csv), $target, false);
        return $importer->apply([[
            'itemkey' => 't0',
            'verdict' => $item['verdict'],
            'fingerprint' => $item['fingerprint'],
            'remedy' => $remedy,
            'links' => array_keys($item['links']),
        ]]);
    }

    /**
     * Build a CSV whose header is the real export header, from partial row maps.
     *
     * @param array $rows Each an array of column token => cell value; absent tokens are empty.
     * @return string
     */
    private function csv(array $rows): string {
        $headers = template_csv_serializer::headers(false);
        $lines = [$this->encode($headers)];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $header) {
                $cells[] = (string) ($row[$header] ?? '');
            }
            $lines[] = $this->encode($cells);
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Encode one CSV row, quoting every cell.
     *
     * @param array $cells The cell values.
     * @return string
     */
    private function encode(array $cells): string {
        return implode(',', array_map(static function ($cell): string {
            return '"' . str_replace('"', '""', (string) $cell) . '"';
        }, $cells));
    }
}
