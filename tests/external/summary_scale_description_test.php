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

namespace local_dimensions\external;

/**
 * Tests the rating scale's description in the competency summary: its admin gate and its formatting.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_user_competency_summary_in_plan
 */
final class summary_scale_description_test extends \advanced_testcase {
    /**
     * With the setting on, the payload carries the scale's own description.
     *
     * @return void
     */
    public function test_execute_returns_the_scale_description_when_enabled(): void {
        $this->resetAfterTest();
        set_config('showscaledescription', 1, 'local_dimensions');
        [$competencyid, $planid, $user] = $this->set_up_plan_with_described_scale();

        $this->setUser($user);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));

        $this->assertStringContainsString(
            'Four-level skills scale',
            $payload->usercompetencysummary->competency->scaledescription
        );
    }

    /**
     * With the setting off, the field is empty and the client renders no button.
     *
     * @return void
     */
    public function test_execute_suppresses_the_scale_description_when_disabled(): void {
        $this->resetAfterTest();
        set_config('showscaledescription', 0, 'local_dimensions');
        [$competencyid, $planid, $user] = $this->set_up_plan_with_described_scale();

        $this->setUser($user);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));

        $this->assertSame('', $payload->usercompetencysummary->competency->scaledescription);
    }

    /**
     * With the setting never written, unset reads as on, so upgrading installs keep their link.
     *
     * @return void
     */
    public function test_execute_returns_the_scale_description_when_never_configured(): void {
        $this->resetAfterTest();
        unset_config('showscaledescription', 'local_dimensions');
        [$competencyid, $planid, $user] = $this->set_up_plan_with_described_scale();

        $this->setUser($user);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));

        $this->assertStringContainsString(
            'Four-level skills scale',
            $payload->usercompetencysummary->competency->scaledescription
        );
    }

    /**
     * The description is formatted once, in the competency's context, with its file URLs rewritten.
     *
     * The multilang filter is on for the site and off in the framework's category. One pass in the
     * competency's context leaves both language spans in place; a pass in the page's (system)
     * context before it would already have reduced them to one.
     *
     * @return void
     */
    public function test_the_description_is_formatted_once_in_the_competency_context(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('showscaledescription', 1, 'local_dimensions');
        filter_set_global_state('multilang', TEXTFILTER_ON);
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \context_coursecat::instance((int) $category->id);
        filter_set_local_state('multilang', $categorycontext->id, TEXTFILTER_OFF);
        [$competencyid, $planid, $user, $scaleid] = $this->set_up_plan_with_described_scale($categorycontext->id);
        $description = '<p><span lang="en" class="multilang">English</span><span lang="pt_br" class="multilang">Idioma</span>'
            . '<img src="@@PLUGINFILE@@/levels.png" alt="Levels"></p>';
        $DB->set_field('scale', 'description', $description, ['id' => $scaleid]);
        $DB->set_field('scale', 'descriptionformat', FORMAT_HTML, ['id' => $scaleid]);

        $this->setUser($user);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));
        $formatted = $payload->usercompetencysummary->competency->scaledescription;

        $this->assertStringContainsString('English', $formatted);
        $this->assertStringContainsString('Idioma', $formatted);
        $this->assertStringContainsString(
            '/pluginfile.php/' . \context_system::instance()->id . '/grade/scale/' . $scaleid . '/levels.png',
            $formatted
        );
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $formatted);
    }

    /**
     * A plain-text description is formatted in its stored format exactly once.
     *
     * The multilang test above cannot see a second pass in the competency's own context, which
     * leaves both spans in place either way. The plain-text format escapes on every pass, so a
     * second one shows as double-escaped entities, and a pass as HTML would keep the raw newline.
     *
     * @return void
     */
    public function test_a_plain_text_description_is_escaped_once(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('showscaledescription', 1, 'local_dimensions');
        [$competencyid, $planid, $user, $scaleid] = $this->set_up_plan_with_described_scale();
        $DB->set_field('scale', 'description', "Novice & expert\nLevel < 3", ['id' => $scaleid]);
        $DB->set_field('scale', 'descriptionformat', FORMAT_PLAIN, ['id' => $scaleid]);

        $this->setUser($user);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));

        $this->assertSame(
            "Novice &amp; expert<br />\nLevel &lt; 3",
            $payload->usercompetencysummary->competency->scaledescription
        );
    }

    /**
     * The competency's taxonomyterm is core's own, formatted like the other text fields of the core
     * exporter's payload; the plugin's plain spelling travels only in the taxonomy object.
     *
     * The taxonomy terms are core language strings with no character the two spellings treat
     * differently, and core caches them for the whole process, so a run cannot tell them apart. The
     * source is read instead, as bootstrap_compat_test reads templates.
     *
     * @return void
     */
    public function test_the_taxonomy_term_is_left_to_core(): void {
        global $CFG;

        $this->resetAfterTest();
        [$competencyid, $planid, $user] = $this->set_up_plan_with_described_scale();

        $this->setUser($user);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));
        $competency = $payload->usercompetencysummary->competency;

        $this->assertNotSame('', $competency->taxonomy->current->term);
        // The control: core still sends the field, and with the same term.
        $this->assertSame($competency->taxonomy->current->term, $competency->taxonomyterm);
        $source = file_get_contents($CFG->dirroot . '/local/dimensions/classes/external/get_user_competency_summary_in_plan.php');
        $this->assertDoesNotMatchRegularExpression('/->taxonomyterm\s*=/', $source);
    }

    /**
     * A plan holding one competency whose framework scale carries a description.
     *
     * @param int|null $contextid The framework's context id, the system context when null.
     * @return array The competency id, the plan id, the plan's owner and the scale id.
     */
    private function set_up_plan_with_described_scale(?int $contextid = null): array {
        global $DB;

        $this->setAdminUser();
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Emerging,Developing,Competent']);
        $DB->set_field('scale', 'description', '<p>Four-level skills scale.</p>', ['id' => $scale->id]);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework([
            'contextid' => $contextid ?? \context_system::instance()->id,
            'visible' => 1,
            'scaleid' => $scale->id,
            'scaleconfiguration' => json_encode([
                ['scaleid' => (int) $scale->id],
                ['id' => 2, 'scaledefault' => 1, 'proficient' => 1],
                ['id' => 3, 'proficient' => 1],
            ]),
        ]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');

        $template = $ccg->create_template();
        $ccg->create_template_competency([
            'templateid' => (int) $template->get('id'),
            'competencyid' => $competencyid,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan([
            'userid' => $user->id,
            'templateid' => (int) $template->get('id'),
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ]);

        return [$competencyid, (int) $plan->get('id'), $user, (int) $scale->id];
    }
}
