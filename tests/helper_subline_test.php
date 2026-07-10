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

use local_dimensions\customfield\competency_handler;
use local_dimensions\customfield\lp_handler;

/**
 * Tests for the accordion subline-source resolution and the competency select-label reader.
 *
 * Locks the fix for the "Accordion subtitle" bug: the tag1/tag2 sources must
 * resolve and surface the competency's select-option label (which the legacy
 * hex-only reader always returned as empty).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper::get_template_subline_source
 * @covers     \local_dimensions\helper::read_competency_select_label
 */
final class helper_subline_test extends \advanced_testcase {
    /**
     * Store a subline-source option key on a learning-plan template.
     *
     * Select custom fields persist a 1-based option index, so the key's position
     * in constants::subline_source_options() (plus one) is what gets saved.
     *
     * @param int $templateid Learning plan template id.
     * @param string $sourcekey One of the constants::SUBLINE_* option keys.
     * @return void
     */
    private function set_template_subline_source(int $templateid, string $sourcekey): void {
        $keys = array_keys(constants::subline_source_options());
        $pos = array_search($sourcekey, $keys, true);
        $data = (object) [
            'id' => $templateid,
            'customfield_' . constants::CFIELD_SUBLINE_SOURCE => ($pos === false) ? 0 : $pos + 1,
        ];
        lp_handler::create()->instance_form_save($data, true);
    }

    /**
     * Every stored subline source round-trips to its own key; an unset field falls back to STATUS.
     *
     * @return void
     */
    public function test_get_template_subline_source_resolves_each_option(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $templateid = (int) $ccg->create_template()->get('id');

        // A template with no stored value keeps the legacy status behaviour.
        $this->assertSame(
            constants::SUBLINE_STATUS,
            helper::get_template_subline_source($templateid)
        );

        foreach (array_keys(constants::subline_source_options()) as $sourcekey) {
            $this->set_template_subline_source($templateid, $sourcekey);
            $this->assertSame(
                $sourcekey,
                helper::get_template_subline_source($templateid),
                "Subline source '{$sourcekey}' did not round-trip"
            );
        }
    }

    /**
     * A competency tag1/tag2 select resolves to its option label; an unset field returns an empty string.
     *
     * @return void
     */
    public function test_read_competency_select_label_returns_tag_label(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $competencyid = (int) $ccg->create_competency([
            'competencyframeworkid' => $framework->get('id'),
        ])->get('id');

        // Nothing stored yet: the reader returns an empty string (hassublinetext stays false).
        $this->assertSame('', helper::read_competency_select_label($competencyid, constants::CFIELD_TAG1));

        // Store tag1 and tag2 through the shared CSV form-data path (label to stored index).
        $data = (object) (['id' => $competencyid] + helper::customfields_to_formdata([
            'cf_tag1' => '1st Year',
            'cf_tag2' => 'Advanced',
        ]));
        competency_handler::create()->instance_form_save($data, true);

        $this->assertSame(
            '1st Year',
            helper::read_competency_select_label($competencyid, constants::CFIELD_TAG1)
        );
        $this->assertSame(
            'Advanced',
            helper::read_competency_select_label($competencyid, constants::CFIELD_TAG2)
        );
    }
}
