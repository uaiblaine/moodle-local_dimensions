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

namespace local_dimensions;

use core_competency\competency;

/**
 * Tests for the rule outcome sentence shown in a competency's Rules tab.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper::get_rule_outcome_text
 */
final class helper_rule_outcome_text_test extends \advanced_testcase {
    /**
     * Each rule type and outcome reads its own string; any other outcome reads nothing.
     *
     * @return void
     */
    public function test_each_rule_type_and_outcome_reads_its_own_string(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $term = helper::get_competency_taxonomy_data($competency, $framework)['current']['term'];

        $expected = [
            'points' => [
                competency::OUTCOME_EVIDENCE => get_string('rules_points_outcome_attach', 'local_dimensions', $term),
                competency::OUTCOME_COMPLETE => get_string('rules_points_outcome_complete', 'local_dimensions', $term),
                competency::OUTCOME_RECOMMEND => get_string('rules_points_outcome_recommend', 'local_dimensions', $term),
            ],
            'all' => [
                competency::OUTCOME_EVIDENCE => get_string('rules_all_outcome_attach', 'local_dimensions', $term),
                competency::OUTCOME_COMPLETE => get_string('rules_all_outcome_complete', 'local_dimensions', $term),
                competency::OUTCOME_RECOMMEND => get_string('rules_all_outcome_recommend', 'local_dimensions', $term),
            ],
        ];

        $seen = [];
        foreach ($expected as $ruletype => $outcomes) {
            foreach ($outcomes as $outcome => $text) {
                $actual = helper::get_rule_outcome_text($ruletype, $outcome, $competency, $framework);
                $this->assertSame($text, $actual, "{$ruletype} / {$outcome}");
                $seen[] = $actual;
            }
            $this->assertSame('', helper::get_rule_outcome_text($ruletype, competency::OUTCOME_NONE, $competency, $framework));
            $this->assertSame('', helper::get_rule_outcome_text($ruletype, 4, $competency, $framework));
        }
        // The six strings differ, so a swapped arm cannot pass unnoticed.
        $this->assertCount(6, array_unique($seen));

        // Any rule type other than points reads the "all" strings.
        $this->assertSame(
            $expected['all'][competency::OUTCOME_COMPLETE],
            helper::get_rule_outcome_text('', competency::OUTCOME_COMPLETE, $competency, $framework)
        );
    }
}
