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
 * Tests for the enrolment/display settings cascade resolvers.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper::resolve_enrollmentfilter_for_view
 * @covers     \local_dimensions\helper::resolve_singlecourseredirect_for_view
 * @covers     \local_dimensions\helper::resolve_lockedcardmode_for_view
 * @covers     \local_dimensions\helper::resolve_showlockeddate_for_view
 * @covers     \local_dimensions\helper::resolve_showrelated_for_template
 * @covers     \local_dimensions\helper::resolve_showrelatedlink_for_template
 */
final class helper_cascade_test extends \advanced_testcase {
    /**
     * Set an lp select field to an option key by its 1-based index.
     *
     * @param int $templateid Template id.
     * @param string $shortname Custom-field shortname.
     * @param array $keys Ordered option keys.
     * @param string $key Chosen key.
     * @return void
     */
    private function set_lp(int $templateid, string $shortname, array $keys, string $key): void {
        $pos = array_search($key, $keys, true);
        $data = (object) ['id' => $templateid, 'customfield_' . $shortname => $pos === false ? 0 : $pos + 1];
        lp_handler::create()->instance_form_save($data, true);
    }

    /**
     * enrollmentfilter resolves competency -> plan -> global, and templateid=0 skips the plan.
     *
     * @return void
     */
    public function test_enrollmentfilter_cascade(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ALL, 'local_dimensions');

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $comp = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $compid = (int) $comp->get('id');
        $templateid = (int) $ccg->create_template()->get('id');
        $efkeys = array_keys(constants::enrollmentfilter_options());

        // Both inherit -> global (all).
        $this->assertSame(
            constants::ENROLLMENTFILTER_ALL,
            helper::resolve_enrollmentfilter_for_view($compid, $templateid)
        );

        // Plan = enrolled, competency inherits -> plan.
        $this->set_lp($templateid, constants::CFIELD_ENROLLMENTFILTER, $efkeys, constants::ENROLLMENTFILTER_ENROLLED);
        $this->assertSame(
            constants::ENROLLMENTFILTER_ENROLLED,
            helper::resolve_enrollmentfilter_for_view($compid, $templateid)
        );

        // Competency = active -> competency wins.
        $cdata = (object) ['id' => $compid];
        $cdata->{'customfield_' . constants::CFIELD_ENROLLMENTFILTER} =
            array_search(constants::ENROLLMENTFILTER_ACTIVE, $efkeys, true) + 1;
        competency_handler::create()->instance_form_save($cdata, true);
        $this->assertSame(
            constants::ENROLLMENTFILTER_ACTIVE,
            helper::resolve_enrollmentfilter_for_view($compid, $templateid)
        );

        // A templateid of 0 skips the plan; competency still wins.
        $this->assertSame(
            constants::ENROLLMENTFILTER_ACTIVE,
            helper::resolve_enrollmentfilter_for_view($compid, 0)
        );

        // Competency = enrolledorself -> resolves to the new aggregate value.
        $cdata2 = (object) ['id' => $compid];
        $cdata2->{'customfield_' . constants::CFIELD_ENROLLMENTFILTER} =
            array_search(constants::ENROLLMENTFILTER_ENROLLEDORSELF, $efkeys, true) + 1;
        competency_handler::create()->instance_form_save($cdata2, true);
        $this->assertSame(
            constants::ENROLLMENTFILTER_ENROLLEDORSELF,
            helper::resolve_enrollmentfilter_for_view($compid, $templateid)
        );
    }

    /**
     * lockedcardmode resolves competency -> plan -> global, and templateid=0 skips the plan.
     *
     * @return void
     */
    public function test_lockedcardmode_cascade(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        set_config('lockedcardmode', constants::LOCKEDCARDMODE_BLOCKED, 'local_dimensions');

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $comp = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $compid = (int) $comp->get('id');
        $templateid = (int) $ccg->create_template()->get('id');
        $keys = array_keys(constants::lockedcardmode_options());

        // Both inherit -> global (blocked).
        $this->assertSame(
            constants::LOCKEDCARDMODE_BLOCKED,
            helper::resolve_lockedcardmode_for_view($compid, $templateid)
        );

        // Plan = learnmore, competency inherits -> plan.
        $this->set_lp($templateid, constants::CFIELD_LOCKEDCARDMODE, $keys, constants::LOCKEDCARDMODE_LEARNMORE);
        $this->assertSame(
            constants::LOCKEDCARDMODE_LEARNMORE,
            helper::resolve_lockedcardmode_for_view($compid, $templateid)
        );

        // Competency = blocked -> competency wins over the plan's learnmore.
        $cdata = (object) ['id' => $compid];
        $cdata->{'customfield_' . constants::CFIELD_LOCKEDCARDMODE} =
            array_search(constants::LOCKEDCARDMODE_BLOCKED, $keys, true) + 1;
        competency_handler::create()->instance_form_save($cdata, true);
        $this->assertSame(
            constants::LOCKEDCARDMODE_BLOCKED,
            helper::resolve_lockedcardmode_for_view($compid, $templateid)
        );

        // A templateid of 0 skips the plan; competency still wins.
        $this->assertSame(
            constants::LOCKEDCARDMODE_BLOCKED,
            helper::resolve_lockedcardmode_for_view($compid, 0)
        );
    }

    /**
     * showlockeddate resolves competency -> plan -> global, and templateid=0 skips the plan.
     *
     * @return void
     */
    public function test_showlockeddate_cascade(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        set_config('showlockeddate', 1, 'local_dimensions');

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $comp = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $compid = (int) $comp->get('id');
        $templateid = (int) $ccg->create_template()->get('id');
        $keys = array_keys(constants::showlockeddate_options());

        // Both inherit -> global (true).
        $this->assertTrue(helper::resolve_showlockeddate_for_view($compid, $templateid));

        // Plan = no, competency inherits -> plan (false).
        $this->set_lp($templateid, constants::CFIELD_SHOWLOCKEDDATE, $keys, constants::SHOWLOCKEDDATE_NO);
        $this->assertFalse(helper::resolve_showlockeddate_for_view($compid, $templateid));

        // Competency = yes -> competency wins over the plan's no.
        $cdata = (object) ['id' => $compid];
        $cdata->{'customfield_' . constants::CFIELD_SHOWLOCKEDDATE} =
            array_search(constants::SHOWLOCKEDDATE_YES, $keys, true) + 1;
        competency_handler::create()->instance_form_save($cdata, true);
        $this->assertTrue(helper::resolve_showlockeddate_for_view($compid, $templateid));

        // A templateid of 0 skips the plan; competency still wins.
        $this->assertTrue(helper::resolve_showlockeddate_for_view($compid, 0));
    }

    /**
     * showrelated resolves plan -> global (2-level, no competency layer).
     *
     * @return void
     */
    public function test_showrelated_cascade(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        set_config('showrelated', 0, 'local_dimensions');

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $templateid = (int) $ccg->create_template()->get('id');
        $keys = array_keys(constants::showrelated_options());

        // Inherit -> global (off).
        $this->assertFalse(helper::resolve_showrelated_for_template($templateid));
        // Plan = yes -> on.
        $this->set_lp($templateid, constants::CFIELD_SHOWRELATED, $keys, constants::SHOWRELATED_YES);
        $this->assertTrue(helper::resolve_showrelated_for_template($templateid));
    }
}
