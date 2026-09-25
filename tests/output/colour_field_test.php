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

namespace local_dimensions\output;

use core_competency\plan;
use local_dimensions\constants;
use local_dimensions\customfield\competency_handler;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Both learner heroes read their admin colours through one hex-only rule.
 *
 * The colours land in a style attribute, where Mustache escaping does not protect, so anything
 * but a hex colour must be dropped. The plan hero (template area) and the competency hero
 * (competency area) share the reader, and a second copy of the rule is how the two drift apart.
 *
 * The class-level covers tags stay in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes, and the plugin still supports 4.5.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\output\customfield_reader
 * @covers     \local_dimensions\output\view_plan_summary_page
 * @covers     \local_dimensions\output\view_competency_page
 */
final class colour_field_test extends \advanced_testcase {
    /** @var string A value that is not a colour and would extend the style attribute. */
    private const HOSTILE = 'red; background-image: url(x)';

    /**
     * The plan hero takes a template's hex colour and drops anything else.
     *
     * @return void
     */
    public function test_the_plan_hero_reads_only_hex_colours(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $templateid = (int) $ccg->create_template()->get('id');
        lp_handler::create()->instance_form_save((object) [
            'id' => $templateid,
            'customfield_' . constants::CFIELD_CUSTOMBGCOLOR => 'a1b',
            'customfield_' . constants::CFIELD_CUSTOMTEXTCOLOR => self::HOSTILE,
        ], true);
        $plan = $ccg->create_plan([
            'userid' => $this->getDataGenerator()->create_user()->id,
            'templateid' => $templateid,
            'status' => plan::STATUS_ACTIVE,
        ]);

        $hero = (new view_plan_summary_page($plan))->export_for_template($PAGE->get_renderer('core'))['hero'];

        $this->assertSame('#a1b', $hero['bgcolor']);
        $this->assertTrue($hero['hasbgcolor']);
        $this->assertNull($hero['textcolor']);
        $this->assertFalse($hero['hastextcolor']);
    }

    /**
     * The competency hero applies the same rule to the competency's own colours.
     *
     * @return void
     */
    public function test_the_competency_hero_reads_only_hex_colours(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $competencyid = (int) $ccg->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        competency_handler::create()->instance_form_save((object) [
            'id' => $competencyid,
            'customfield_' . constants::CFIELD_CUSTOMBGCOLOR => self::HOSTILE,
            'customfield_' . constants::CFIELD_CUSTOMTEXTCOLOR => '#A1B2C3',
        ], true);
        $competency = $DB->get_record('competency', ['id' => $competencyid], '*', MUST_EXIST);

        $page = new view_competency_page($competency, [], (int) $this->getDataGenerator()->create_user()->id);
        $hero = $page->export_for_template($PAGE->get_renderer('core'))['hero'];

        $this->assertNull($hero['bgcolor']);
        $this->assertFalse($hero['hasbgcolor']);
        $this->assertSame('#A1B2C3', $hero['textcolor']);
        $this->assertTrue($hero['hastextcolor']);
    }

    /**
     * The hex rule is written once, in the shared reader.
     *
     * @return void
     */
    public function test_the_hex_rule_has_one_copy(): void {
        global $CFG;
        $copies = [];
        foreach (glob($CFG->dirroot . '/local/dimensions/classes/output/*.php') as $path) {
            $count = substr_count((string) file_get_contents($path), '[A-Fa-f0-9]{6}|[A-Fa-f0-9]{3}');
            if ($count > 0) {
                $copies[basename($path)] = $count;
            }
        }

        $this->assertSame(['customfield_reader.php' => 1], $copies);
    }
}
