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

use core_reportbuilder\local\models\{column, filter, user_filter};
use core_user\reportbuilder\datasource\users;
use local_dimensions\constants;
use local_dimensions\helper;
use local_dimensions\reportbuilder\datasource\learning_plans;

/**
 * Tests for the repairs db/upgrade.php runs on every upgrade.
 *
 * Each repair must leave a site unchanged once applied, because it runs again on every later
 * upgrade, from any date-numbered release, in whatever language the upgrade runs.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\legacy_repair
 */
final class legacy_repair_test extends \advanced_testcase {
    /**
     * Provision both areas, as a site coming from a date-numbered release, and return the plugin's category id per area.
     *
     * The test site was installed by db/install.php, which records the once-only repairs as applied;
     * a site coming from a date-numbered release has no such record.
     *
     * @return array Category id keyed by area.
     */
    private function provision(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        unset_config(legacy_repair::APPLIED_SETTING, 'local_dimensions');
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $ids = [];
        foreach ([helper::AREA_LP, helper::AREA_COMPETENCY] as $area) {
            $ids[$area] = (int) $DB->get_field_sql(
                "SELECT MIN(id) FROM {customfield_category} WHERE component = ? AND area = ?",
                ['local_dimensions', $area]
            );
        }
        return $ids;
    }

    /**
     * Shortnames of the plugin's fields in one area.
     *
     * @param string $area The handler area.
     * @return array Shortnames, sorted.
     */
    private function shortnames(string $area): array {
        global $DB;
        $names = $DB->get_fieldset_sql(
            "SELECT f.shortname
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = ? AND c.area = ?
           ORDER BY f.shortname",
            ['local_dimensions', $area]
        );
        return array_values($names);
    }

    /**
     * Give a provisioned field its pre-2026031101 shortname back.
     *
     * @param string $area The handler area.
     * @param string $newname The current shortname.
     * @param string $oldname The shortname to restore.
     * @return void
     */
    private function unprefix(string $area, string $newname, string $oldname): void {
        global $DB;
        $field = helper::find_field_by_shortname($newname, $area);
        $this->assertNotNull($field);
        $DB->set_field('customfield_field', 'shortname', $oldname, ['id' => $field->get('id')]);
    }

    /**
     * A field with a legacy shortname gets the prefixed one, in both areas, and once only.
     *
     * @return void
     */
    public function test_legacy_shortnames_are_renamed_once(): void {
        $this->provision();
        $expectedlp = $this->shortnames(helper::AREA_LP);
        $expectedcompetency = $this->shortnames(helper::AREA_COMPETENCY);
        $this->unprefix(helper::AREA_LP, constants::CFIELD_CUSTOMBGCOLOR, 'custombgcolor');
        $this->unprefix(helper::AREA_COMPETENCY, constants::CFIELD_CUSTOMTEXTCOLOR, 'customtextcolor');
        $this->assertNotEquals($expectedlp, $this->shortnames(helper::AREA_LP));

        $this->assertSame(2, legacy_repair::rename_legacy_shortnames());
        $this->assertSame($expectedlp, $this->shortnames(helper::AREA_LP));
        $this->assertSame($expectedcompetency, $this->shortnames(helper::AREA_COMPETENCY));

        $this->assertSame(0, legacy_repair::rename_legacy_shortnames());
        $this->assertSame($expectedlp, $this->shortnames(helper::AREA_LP));
    }

    /**
     * A legacy shortname stays when its area already has the prefixed field.
     *
     * The other area is checked on its own: the prefixed name there does not block a rename here.
     *
     * @return void
     */
    public function test_rename_skips_an_area_that_already_has_the_new_name(): void {
        global $DB;
        $categories = $this->provision();
        // A hand-made field named like a pre-2026031101 one, beside the provisioned field.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $handmade = $generator->create_field([
            'categoryid' => $categories[helper::AREA_LP],
            'shortname' => 'customtextcolor',
            'type' => 'text',
            'name' => 'Text colour',
        ]);
        // The competency area has only the legacy name.
        $this->unprefix(helper::AREA_COMPETENCY, constants::CFIELD_CUSTOMTEXTCOLOR, 'customtextcolor');

        $this->assertSame(1, legacy_repair::rename_legacy_shortnames());
        $this->assertSame(
            'customtextcolor',
            $DB->get_field('customfield_field', 'shortname', ['id' => $handmade->get('id')])
        );
        $this->assertContains(constants::CFIELD_CUSTOMTEXTCOLOR, $this->shortnames(helper::AREA_COMPETENCY));
        $this->assertCount(1, array_keys($this->shortnames(helper::AREA_LP), constants::CFIELD_CUSTOMTEXTCOLOR, true));
    }

    /**
     * The retired settings are removed and the rest of the plugin's settings stay.
     *
     * @return void
     */
    public function test_retired_settings_are_removed(): void {
        $this->provision();
        foreach (legacy_repair::RETIRED_SETTINGS as $name) {
            set_config($name, '1', 'local_dimensions');
        }
        set_config('singlecourseredirect', '1', 'local_dimensions');

        legacy_repair::run();

        foreach (legacy_repair::RETIRED_SETTINGS as $name) {
            $this->assertFalse(get_config('local_dimensions', $name), $name);
        }
        $this->assertSame('1', get_config('local_dimensions', 'singlecourseredirect'));
    }

    /**
     * Two runs leave the fields exactly as one run does.
     *
     * @return void
     */
    public function test_run_twice_changes_nothing_more(): void {
        global $DB;
        $this->provision();
        $this->unprefix(helper::AREA_LP, constants::CFIELD_CUSTOMBGCOLOR, 'custombgcolor');

        legacy_repair::run();
        $this->assertContains(constants::CFIELD_CUSTOMBGCOLOR, $this->shortnames(helper::AREA_LP));
        $this->assertNotContains('custombgcolor', $this->shortnames(helper::AREA_LP));
        $once = $DB->get_records('customfield_field', null, 'id', 'id, shortname, configdata');
        legacy_repair::run();
        $this->assertEquals($once, $DB->get_records('customfield_field', null, 'id', 'id, shortname, configdata'));
    }

    /**
     * db/upgrade.php applies the repairs, whatever version the site comes from.
     *
     * A site switching from a date-numbered release resets its version row below the branch's
     * first number, so the upgrade function sees an old version and must repair from it.
     *
     * @return void
     */
    public function test_upgrade_applies_the_repairs(): void {
        global $CFG;
        $this->provision();
        $this->unprefix(helper::AREA_COMPETENCY, constants::CFIELD_CUSTOMBGCOLOR, 'custombgcolor');
        set_config('summaryenrollmentfilter', '1', 'local_dimensions');

        require_once($CFG->dirroot . '/local/dimensions/db/upgrade.php');
        $this->assertTrue(xmldb_local_dimensions_upgrade(2026041999));

        $this->assertContains(constants::CFIELD_CUSTOMBGCOLOR, $this->shortnames(helper::AREA_COMPETENCY));
        $this->assertNotContains('custombgcolor', $this->shortnames(helper::AREA_COMPETENCY));
        $this->assertFalse(get_config('local_dimensions', 'summaryenrollmentfilter'));
    }

    /**
     * db/install.php records the once-only repairs, so they never run on a new site's data.
     *
     * Calls the install function itself: the test site was installed before any test runs.
     *
     * @return void
     */
    public function test_a_new_install_records_the_once_only_repairs(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        unset_config(legacy_repair::APPLIED_SETTING, 'local_dimensions');

        require_once($CFG->dirroot . '/local/dimensions/db/install.php');
        $this->assertTrue(xmldb_local_dimensions_install());

        $this->assertSame(
            implode(',', array_keys(legacy_repair::ONCE)),
            get_config('local_dimensions', legacy_repair::APPLIED_SETTING)
        );
    }

    /**
     * A once-only repair runs once: an option list an administrator edits afterwards stays as edited.
     *
     * The control: on the first run the same four-line list does get its fifth option.
     *
     * @return void
     */
    public function test_a_later_edit_is_not_undone(): void {
        global $DB;
        $this->provision();
        $field = helper::find_field_by_shortname(constants::CFIELD_ENROLLMENTFILTER, helper::AREA_LP);
        $fieldid = (int) $field->get('id');
        $config = json_decode($DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $five = $config['options'];
        $lines = explode("\n", $five);
        array_pop($lines);
        $config['options'] = implode("\n", $lines);
        $four = json_encode($config);

        $DB->set_field('customfield_field', 'configdata', $four, ['id' => $fieldid]);
        legacy_repair::run();
        $after = json_decode($DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $this->assertSame($five, $after['options']);

        // The administrator trims the list again; the next upgrade leaves it.
        $DB->set_field('customfield_field', 'configdata', $four, ['id' => $fieldid]);
        legacy_repair::run();
        $this->assertSame($four, $DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]));
    }

    /**
     * Saved learning-plan reports follow the template customfields to `template_extras`.
     *
     * Columns, filters, conditions, condition values and user filter values move; elements of
     * other entities and reports of other sources stay as they were; a second run changes nothing.
     *
     * @return void
     */
    public function test_saved_reports_follow_the_template_customfields(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $old = 'template:customfield_' . constants::CFIELD_TAG1;
        $new = 'template_extras:customfield_' . constants::CFIELD_TAG1;

        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $plans = $generator->create_report(['name' => 'Plans', 'source' => learning_plans::class, 'default' => 0]);
        $users = $generator->create_report(['name' => 'Users', 'source' => users::class, 'default' => 0]);

        foreach ([$plans, $users] as $report) {
            $reportid = (int) $report->get('id');
            foreach ([$old, 'template:shortname'] as $order => $identifier) {
                $record = ['reportid' => $reportid, 'uniqueidentifier' => $identifier, 'columnorder' => $order + 1];
                (new column(0, (object) $record))->create();
            }
            foreach ([0, 1] as $iscondition) {
                $record = ['reportid' => $reportid, 'uniqueidentifier' => $old, 'iscondition' => $iscondition, 'filterorder' => 1];
                (new filter(0, (object) $record))->create();
            }
            $conditiondata = json_encode(["{$old}_operator" => 1, 'plan:status_operator' => 2]);
            $DB->set_field('reportbuilder_report', 'conditiondata', $conditiondata, ['id' => $reportid]);
            $record = ['reportid' => $reportid, 'filterdata' => json_encode(["{$old}_value" => 'x'])];
            (new user_filter(0, (object) $record))->create();
        }

        $this->assertSame(5, legacy_repair::move_template_customfield_report_elements());

        $planid = (int) $plans->get('id');
        $columns = $DB->get_fieldset('reportbuilder_column', 'uniqueidentifier', ['reportid' => $planid]);
        $this->assertEqualsCanonicalizing([$new, 'template:shortname'], $columns);
        $filters = $DB->get_fieldset('reportbuilder_filter', 'uniqueidentifier', ['reportid' => $planid]);
        $this->assertSame([$new, $new], $filters);
        $conditiondata = $DB->get_field('reportbuilder_report', 'conditiondata', ['id' => $planid]);
        $this->assertSame(["{$new}_operator" => 1, 'plan:status_operator' => 2], json_decode($conditiondata, true));
        $filterdata = $DB->get_field('reportbuilder_user_filter', 'filterdata', ['reportid' => $planid]);
        $this->assertSame(["{$new}_value" => 'x'], json_decode($filterdata, true));

        // The other source's report is untouched.
        $usersid = (int) $users->get('id');
        $moved = 'reportid = ? AND ' . $DB->sql_like('uniqueidentifier', '?');
        $this->assertSame(0, $DB->count_records_select('reportbuilder_column', $moved, [$usersid, 'template_extras:%']));
        $this->assertSame(0, $DB->count_records_select('reportbuilder_filter', $moved, [$usersid, 'template_extras:%']));
        $conditiondata = $DB->get_field('reportbuilder_report', 'conditiondata', ['id' => $usersid]);
        $this->assertStringContainsString($old, $conditiondata);

        $this->assertSame(0, legacy_repair::move_template_customfield_report_elements());
    }
}
