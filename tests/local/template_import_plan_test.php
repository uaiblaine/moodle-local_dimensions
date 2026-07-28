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

/**
 * Tests for the learning plan import verdict model and its immutable plan value object.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\template_import_verdict
 * @covers     \local_dimensions\local\template_import_plan
 */
final class template_import_plan_test extends \advanced_testcase {
    /**
     * Every constant in every set resolves to a real, non-empty label.
     *
     * A missing string would resolve to '[[key]]' and emit a debugging notice, which fails the
     * run under --fail-on-warning, so calling the label is itself half the assertion.
     *
     * @return void
     */
    public function test_every_constant_resolves_to_a_label(): void {
        $sets = [
            'verdict_label' => template_import_verdict::verdicts(),
            'verdict_help' => template_import_verdict::verdicts(),
            'reason_label' => template_import_verdict::reasons(),
            'link_status_label' => template_import_verdict::link_statuses(),
            'confidence_label' => template_import_verdict::confidences(),
            'remedy_label' => template_import_verdict::remedies(),
            'outcome_label' => template_import_verdict::outcomes(),
        ];
        foreach ($sets as $method => $values) {
            $this->assertNotEmpty($values, $method . ' declares no constants');
            foreach ($values as $value) {
                $label = (string) call_user_func([template_import_verdict::class, $method], $value);
                $this->assertNotSame('', $label, $method . '(' . $value . ') is empty');
                $this->assertStringNotContainsString('[[', $label, $method . '(' . $value . ') is unresolved');
            }
        }
    }

    /**
     * The empty reason is legal and carries no label, so a verdict that needs no reason
     * (create, insync) does not have to invent one.
     *
     * @return void
     */
    public function test_the_empty_reason_has_an_empty_label(): void {
        $this->assertSame('', template_import_verdict::reason_label(template_import_verdict::REASON_NONE));
    }

    /**
     * Every link status is classified exactly once: resolved, unresolved, or neither. The
     * analyser's structure roll-up reads these two predicates, so an unclassified status
     * would silently stop counting towards "this plan has no structure here".
     *
     * @return void
     */
    public function test_link_statuses_are_classified_consistently(): void {
        foreach (template_import_verdict::link_statuses() as $status) {
            $resolved = template_import_verdict::link_is_resolved($status);
            $unresolved = template_import_verdict::link_is_unresolved($status);
            $this->assertFalse($resolved && $unresolved, $status . ' is both resolved and unresolved');
        }
        $this->assertTrue(template_import_verdict::link_is_resolved(template_import_verdict::LINK_MATCHED));
        $this->assertTrue(template_import_verdict::link_is_resolved(template_import_verdict::LINK_MATCHEDFALLBACK));
        $this->assertTrue(template_import_verdict::link_is_resolved(template_import_verdict::LINK_ALREADYLINKED));
        $this->assertTrue(template_import_verdict::link_is_unresolved(template_import_verdict::LINK_MISSINGFRAMEWORK));
        $this->assertTrue(template_import_verdict::link_is_unresolved(template_import_verdict::LINK_MISSINGCOMPETENCY));
        $this->assertTrue(template_import_verdict::link_is_unresolved(template_import_verdict::LINK_HIDDENFRAMEWORK));
        $this->assertTrue(template_import_verdict::link_is_unresolved(template_import_verdict::LINK_AMBIGUOUS));
        // A row naming no competency at all is neither: it must not make a template "structure missing".
        $this->assertFalse(template_import_verdict::link_is_resolved(template_import_verdict::LINK_EMPTYREFERENCE));
        $this->assertFalse(template_import_verdict::link_is_unresolved(template_import_verdict::LINK_EMPTYREFERENCE));
    }

    /**
     * An empty plan declares the full scalar set, all zero. This is the contract the preview
     * web service's execute_returns() mirrors key for key: clean_returnvalue() silently strips
     * anything the structure does not declare, so the two must not drift.
     *
     * @return void
     */
    public function test_counts_declare_the_documented_scalar_set(): void {
        $plan = new template_import_plan([], [], [], \context_system::instance());
        $expected = [
            'total', 'create', 'update', 'insync', 'skip', 'conflict', 'blocked', 'orphanlink',
            'selectable', 'preselected', 'links', 'linksmatched', 'linksunresolved',
        ];
        $counts = $plan->get_counts();
        $this->assertSame($expected, array_keys($counts));
        foreach ($counts as $key => $value) {
            $this->assertIsInt($value, $key . ' is not an integer');
            $this->assertSame(0, $value, $key . ' is not zero on an empty plan');
        }
    }

    /**
     * Counts roll up the items and their links.
     *
     * @return void
     */
    public function test_counts_roll_up_items_and_links(): void {
        $plan = new template_import_plan($this->make_items(), [], [], \context_system::instance());
        $counts = $plan->get_counts();

        $this->assertSame(4, $counts['total']);
        $this->assertSame(1, $counts['create']);
        $this->assertSame(1, $counts['update']);
        $this->assertSame(1, $counts['blocked']);
        $this->assertSame(1, $counts['orphanlink']);
        $this->assertSame(0, $counts['insync']);
        $this->assertSame(2, $counts['selectable']);
        $this->assertSame(1, $counts['preselected']);
        $this->assertSame(3, $counts['links']);
        $this->assertSame(2, $counts['linksmatched']);
        $this->assertSame(1, $counts['linksunresolved']);
    }

    /**
     * Items are addressed by their stable item key, and an unknown key is null rather than fatal:
     * the apply path looks up every selection the browser sends against a freshly built plan.
     *
     * @return void
     */
    public function test_get_item_addresses_items_by_key(): void {
        $plan = new template_import_plan($this->make_items(), [], [], \context_system::instance());

        $this->assertSame('t0', $plan->get_item('t0')['itemkey']);
        $this->assertNull($plan->get_item('t99'));
        $this->assertSame(['t0', 't1', 't2', 't3'], array_keys($plan->get_items()));
    }

    /**
     * Notices, missing structures and the target context come back as they went in.
     *
     * @return void
     */
    public function test_the_plan_carries_its_file_level_findings(): void {
        $notices = [['key' => 'imagesnotcarried', 'message' => 'Images are not carried.']];
        $missing = [['idnumber' => 'FW-9', 'shortname' => 'Clinical skills']];
        $context = \context_system::instance();
        $plan = new template_import_plan([], $notices, $missing, $context);

        $this->assertSame($notices, $plan->get_notices());
        $this->assertSame($missing, $plan->get_missing_structures());
        $this->assertSame($context->id, $plan->get_target_context()->id);
    }

    /**
     * Both language files declare exactly the same keys, each in ascending byte order. CI's
     * validate step enforces the ordering and the plugin's own rule is that the two files stay
     * in sync; this feature adds keys in several passes, so the check is worth automating.
     *
     * @return void
     */
    public function test_language_files_stay_in_sync_and_sorted(): void {
        $en = $this->lang_keys('en');
        $ptbr = $this->lang_keys('pt_br');

        $this->assertSame($en, $ptbr, 'The English and Brazilian Portuguese key sets differ');

        $sorted = $en;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $en, 'lang/en/local_dimensions.php is not in ascending byte order');
        $sortedptbr = $ptbr;
        sort($sortedptbr, SORT_STRING);
        $this->assertSame($sortedptbr, $ptbr, 'lang/pt_br/local_dimensions.php is not in ascending byte order');
    }

    /**
     * The string keys a language file declares, in file order.
     *
     * Read as text rather than included: the point is to check the order the keys appear in,
     * which an include() would throw away.
     *
     * @param string $lang The language directory name.
     * @return array
     */
    private function lang_keys(string $lang): array {
        $root = \core_component::get_component_directory('local_dimensions');
        $source = file_get_contents($root . '/lang/' . $lang . '/local_dimensions.php');
        preg_match_all('/^\$string\[\'([a-z0-9_]+)\'\]/m', (string) $source, $matches);
        return $matches[1];
    }

    /**
     * Four items exercising the counted dimensions: a preselected create with two matched links,
     * a selectable-but-unticked update, a blocked row with an unresolved link, and an orphan link.
     *
     * @return array
     */
    private function make_items(): array {
        return [
            't0' => [
                'itemkey' => 't0',
                'verdict' => template_import_verdict::VERDICT_CREATE,
                'selectable' => true,
                'preselected' => true,
                'links' => [
                    't0l0' => ['itemkey' => 't0l0', 'status' => template_import_verdict::LINK_MATCHED],
                    't0l1' => ['itemkey' => 't0l1', 'status' => template_import_verdict::LINK_MATCHEDFALLBACK],
                ],
            ],
            't1' => [
                'itemkey' => 't1',
                'verdict' => template_import_verdict::VERDICT_UPDATE,
                'selectable' => true,
                'preselected' => false,
                'links' => [],
            ],
            't2' => [
                'itemkey' => 't2',
                'verdict' => template_import_verdict::VERDICT_BLOCKED,
                'selectable' => false,
                'preselected' => false,
                'links' => [
                    't2l0' => ['itemkey' => 't2l0', 'status' => template_import_verdict::LINK_MISSINGCOMPETENCY],
                ],
            ],
            't3' => [
                'itemkey' => 't3',
                'verdict' => template_import_verdict::VERDICT_ORPHANLINK,
                'selectable' => false,
                'preselected' => false,
                'links' => [],
            ],
        ];
    }
}
