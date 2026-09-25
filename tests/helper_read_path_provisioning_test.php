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
 * Tests that the template setting resolvers read their fields and never create them.
 *
 * Fields are created only under the provisioning lock; a resolver that created a missing field
 * would do it on a learner's page view, with no lock, racing every other request.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper::get_template_subline_source
 * @covers     \local_dimensions\helper::get_template_enrollmentfilter
 * @covers     \local_dimensions\helper::get_template_singlecourseredirect
 * @covers     \local_dimensions\helper::get_template_lockedcardmode
 * @covers     \local_dimensions\helper::get_template_showlockeddate
 * @covers     \local_dimensions\helper::resolve_showrelated_for_template
 * @covers     \local_dimensions\helper::resolve_showrelatedlink_for_template
 */
final class helper_read_path_provisioning_test extends \advanced_testcase {
    /**
     * Drop the handlers' cached field lists, which would outlive the rollback of the rows they hold.
     *
     * @return void
     */
    protected function tearDown(): void {
        lp_handler::create()->reset_configuration_cache();
        competency_handler::create()->reset_configuration_cache();
        parent::tearDown();
    }

    /**
     * Count the lp-area fields with a shortname.
     *
     * The same shortname may exist in the competency area, so the count is scoped by category.
     *
     * @param string $shortname Custom-field shortname.
     * @return int
     */
    private function count_lp_fields(string $shortname): int {
        global $DB;
        return $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component AND c.area = :area AND f.shortname = :shortname",
            ['component' => 'local_dimensions', 'area' => helper::AREA_LP, 'shortname' => $shortname]
        );
    }

    /**
     * Each resolver, with the option it stores to prove it reads the field, and what it returns.
     *
     * @return array Keyed by shortname: [resolver name, option key to store, value read back for it,
     *     value the fallback gives with the global settings the test sets].
     */
    private function resolvers(): array {
        return [
            constants::CFIELD_SUBLINE_SOURCE => [
                'get_template_subline_source',
                constants::SUBLINE_TAG1,
                constants::SUBLINE_TAG1,
                constants::SUBLINE_STATUS,
            ],
            constants::CFIELD_ENROLLMENTFILTER => [
                'get_template_enrollmentfilter',
                constants::ENROLLMENTFILTER_ENROLLED,
                constants::ENROLLMENTFILTER_ENROLLED,
                constants::ENROLLMENTFILTER_ALL,
            ],
            constants::CFIELD_SINGLECOURSEREDIRECT => [
                'get_template_singlecourseredirect',
                constants::SINGLECOURSEREDIRECT_YES,
                true,
                false,
            ],
            constants::CFIELD_LOCKEDCARDMODE => [
                'get_template_lockedcardmode',
                constants::LOCKEDCARDMODE_LEARNMORE,
                constants::LOCKEDCARDMODE_LEARNMORE,
                constants::LOCKEDCARDMODE_BLOCKED,
            ],
            constants::CFIELD_SHOWLOCKEDDATE => [
                'get_template_showlockeddate',
                constants::SHOWLOCKEDDATE_NO,
                false,
                true,
            ],
            constants::CFIELD_SHOWRELATED => [
                'resolve_showrelated_for_template',
                constants::SHOWRELATED_YES,
                true,
                false,
            ],
            constants::CFIELD_SHOWRELATEDLINK => [
                'resolve_showrelatedlink_for_template',
                constants::SHOWRELATED_YES,
                true,
                false,
            ],
        ];
    }

    /**
     * The option keys of a field, in the order its options are stored.
     *
     * @param string $shortname Custom-field shortname.
     * @return string[]
     */
    private function option_keys(string $shortname): array {
        return array_keys(match ($shortname) {
            constants::CFIELD_SUBLINE_SOURCE => constants::subline_source_options(),
            constants::CFIELD_ENROLLMENTFILTER => constants::enrollmentfilter_options(),
            constants::CFIELD_SINGLECOURSEREDIRECT => constants::singlecourseredirect_options(),
            constants::CFIELD_LOCKEDCARDMODE => constants::lockedcardmode_options(),
            constants::CFIELD_SHOWLOCKEDDATE => constants::showlockeddate_options(),
            constants::CFIELD_SHOWRELATED => constants::showrelated_options(),
            constants::CFIELD_SHOWRELATEDLINK => constants::showrelatedlink_options(),
        });
    }

    /**
     * With a field missing, each resolver falls back and leaves it missing; once provisioning
     * restores it, the same resolver reads the value stored in it.
     *
     * @return void
     */
    public function test_resolvers_never_create_a_missing_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ALL, 'local_dimensions');
        set_config('singlecourseredirect', 0, 'local_dimensions');
        set_config('lockedcardmode', constants::LOCKEDCARDMODE_BLOCKED, 'local_dimensions');
        set_config('showlockeddate', 1, 'local_dimensions');
        set_config('showrelated', 0, 'local_dimensions');
        set_config('showrelatedlink', 0, 'local_dimensions');

        $templateid = (int) $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_template()->get('id');

        // Remove the seven fields, as an administrator deleting them in the field admin UI would.
        $handler = lp_handler::create();
        foreach (array_keys($this->resolvers()) as $shortname) {
            $field = helper::find_field_by_shortname($shortname, helper::AREA_LP);
            $this->assertNotNull($field, "Provisioning did not create {$shortname}");
            $handler->delete_field_configuration($field);
        }
        $handler->reset_configuration_cache();

        // A learner opening their plan reaches every resolver.
        $this->setUser($this->getDataGenerator()->create_user());
        foreach ($this->resolvers() as $shortname => [$resolver, , , $fallback]) {
            $this->assertSame(0, $this->count_lp_fields($shortname), "{$shortname} was not removed");
            $this->assertSame($fallback, helper::$resolver($templateid), "{$resolver} did not fall back");
            $this->assertSame(0, $this->count_lp_fields($shortname), "{$resolver} created {$shortname}");
        }

        // Control: provisioning brings the fields back, and each resolver reads what is stored.
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        foreach ($this->resolvers() as $shortname => [$resolver, $key, $expected]) {
            $this->assertSame(1, $this->count_lp_fields($shortname), "Provisioning did not restore {$shortname}");
            $position = array_search($key, $this->option_keys($shortname), true);
            lp_handler::create()->instance_form_save(
                (object) ['id' => $templateid, 'customfield_' . $shortname => $position + 1],
                false
            );
            $this->assertSame($expected, helper::$resolver($templateid), "{$resolver} ignored its field");
        }
    }
}
