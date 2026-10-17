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

use local_dimensions\constants;
use local_dimensions\customfield\competency_handler;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;
use local_dimensions\reportbuilder\datasource\learning_plans;

/**
 * Brings a site from any date-numbered release of the plugin to the current data shape.
 *
 * Releases before the branch-per-version split were numbered by date (up to 2026101301); each
 * branch now numbers itself inside its Moodle version's namespace, below those dates. A site
 * switching branches sets its version row below the branch's first number, so numbered upgrade
 * steps would either run again or never run. These repairs replace them: db/upgrade.php and
 * db/install.php call {@see self::run()}. A repair that rewrites data an administrator may edit
 * afterwards runs once per site, recorded in the plugin setting {@see self::APPLIED_SETTING}; the
 * record, not the data's current shape, decides, so a later edit is never undone. The plugin owns
 * no tables, so there is no schema to bring up step by step.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class legacy_repair {
    /**
     * Custom field shortnames used before the frankenstyle prefix (release 2026031101).
     *
     * @var array
     */
    const RENAMED_SHORTNAMES = [
        'customcard' => constants::CFIELD_CUSTOMCARD,
        'custombgimage' => constants::CFIELD_CUSTOMBGIMAGE,
        'custombgcolor' => constants::CFIELD_CUSTOMBGCOLOR,
        'customtextcolor' => constants::CFIELD_CUSTOMTEXTCOLOR,
        'tag1' => constants::CFIELD_TAG1,
        'tag2' => constants::CFIELD_TAG2,
        'customscss' => constants::CFIELD_CUSTOMSCSS,
    ];

    /**
     * Plugin setting listing the once-only repairs already applied, comma-separated.
     *
     * @var string
     */
    const APPLIED_SETTING = 'legacyrepairsapplied';

    /**
     * Once-only repairs, in the order they run: id => method of this class.
     *
     * @var array
     */
    const ONCE = [
        'shortnames' => 'rename_legacy_shortnames',
        'enrollmentfilter' => 'sync_enrollmentfilter_options',
        'rbtemplateextras' => 'move_template_customfield_report_elements',
    ];

    /**
     * Settings the plugin no longer reads; nothing writes them any more.
     *
     * @var array
     */
    const RETIRED_SETTINGS = [
        'summaryenrollmentfilter',
        'viewcompetency_filter_fields_competency',
    ];

    /**
     * Apply the repairs this site has not had yet, then the ones that are safe every time.
     *
     * Runs before db/upgrade.php's provisioning catch-all: a renamed field must exist under its
     * new shortname before the catch-all looks for it, or the catch-all creates an empty twin.
     * On a fresh install (db/install.php) the once-only repairs find nothing to change and are
     * recorded as applied, so they never touch fields an administrator edits later.
     *
     * @return void
     */
    public static function run(): void {
        $applied = array_filter(explode(',', (string) get_config('local_dimensions', self::APPLIED_SETTING)));
        foreach (self::ONCE as $id => $method) {
            if (in_array($id, $applied, true)) {
                continue;
            }
            self::$method();
            $applied[] = $id;
            set_config(self::APPLIED_SETTING, implode(',', $applied), 'local_dimensions');
        }
        foreach (self::RETIRED_SETTINGS as $name) {
            unset_config($name, 'local_dimensions');
        }
        helper::ensure_unaccent();
    }

    /**
     * Append the fifth enrolment-filter option to fields provisioned before it existed.
     *
     * @return void
     */
    public static function sync_enrollmentfilter_options(): void {
        helper::sync_enrollmentfilter_option(helper::AREA_LP);
        helper::sync_enrollmentfilter_option(helper::AREA_COMPETENCY);
    }

    /**
     * Give the plugin's fields that still carry a pre-2026031101 shortname their prefixed name.
     *
     * A field keeps its old name when its area already has a field with the new one: renaming it
     * would leave two fields with one shortname in an area. That happens only when someone created
     * a field by hand, and the provisioned field is the one the plugin reads.
     *
     * @return int The number of fields renamed.
     */
    public static function rename_legacy_shortnames(): int {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(array_keys(self::RENAMED_SHORTNAMES), SQL_PARAMS_NAMED);
        $params['component'] = 'local_dimensions';
        $legacy = $DB->get_records_sql(
            "SELECT f.id, f.shortname, c.area
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component
                AND f.shortname $insql
           ORDER BY f.id",
            $params
        );

        $renamed = 0;
        foreach ($legacy as $field) {
            $newname = self::RENAMED_SHORTNAMES[$field->shortname];
            $taken = $DB->record_exists_sql(
                "SELECT 1
                   FROM {customfield_field} f
                   JOIN {customfield_category} c ON c.id = f.categoryid
                  WHERE c.component = :component
                    AND c.area = :area
                    AND f.shortname = :shortname",
                ['component' => 'local_dimensions', 'area' => $field->area, 'shortname' => $newname]
            );
            if ($taken) {
                continue;
            }
            $DB->set_field('customfield_field', 'shortname', $newname, ['id' => $field->id]);
            $renamed++;
        }

        if ($renamed) {
            lp_handler::create()->reset_configuration_cache();
            competency_handler::create()->reset_configuration_cache();
        }
        return $renamed;
    }

    /**
     * Point saved learning-plan reports at the entity that now holds the template customfields.
     *
     * The `lp` customfields moved from the `template` report entity to `template_extras`, which
     * changes every element's unique identifier from `template:customfield_*` to
     * `template_extras:customfield_*`. Columns, filters and conditions, the stored condition
     * values and the stored user filter values follow, or the report builder drops them as
     * unknown elements. On a site without the `reportbuilder_user_filter` table (before 5.0)
     * the user filter values live in user preferences and are left for the user to set again.
     *
     * @return int The number of report rows changed.
     */
    public static function move_template_customfield_report_elements(): int {
        global $DB;

        $from = 'template:customfield_';
        $to = 'template_extras:customfield_';

        [$insql, $params] = $DB->get_in_or_equal(
            [learning_plans::class, '\\' . learning_plans::class],
            SQL_PARAMS_NAMED
        );
        $reports = $DB->get_records_select_menu('reportbuilder_report', "source $insql", $params, '', 'id, conditiondata');
        if (!$reports) {
            return 0;
        }

        $changed = 0;
        [$reportsql, $reportparams] = $DB->get_in_or_equal(array_keys($reports), SQL_PARAMS_NAMED);
        $likesql = $DB->sql_like('uniqueidentifier', ':prefix');
        $reportparams['prefix'] = $DB->sql_like_escape($from) . '%';
        foreach (['reportbuilder_column', 'reportbuilder_filter'] as $table) {
            $rows = $DB->get_records_select_menu(
                $table,
                "reportid $reportsql AND $likesql",
                $reportparams,
                '',
                'id, uniqueidentifier'
            );
            foreach ($rows as $id => $identifier) {
                $DB->set_field($table, 'uniqueidentifier', $to . substr($identifier, strlen($from)), ['id' => $id]);
                $changed++;
            }
        }

        foreach ($reports as $id => $json) {
            if (($moved = self::rename_value_keys((string) $json, $from, $to)) !== null) {
                $DB->set_field('reportbuilder_report', 'conditiondata', $moved, ['id' => $id]);
                $changed++;
            }
        }

        if ($DB->get_manager()->table_exists('reportbuilder_user_filter')) {
            $userfilters = $DB->get_records_select_menu(
                'reportbuilder_user_filter',
                "reportid $reportsql",
                $reportparams,
                '',
                'id, filterdata'
            );
            foreach ($userfilters as $id => $json) {
                if (($moved = self::rename_value_keys((string) $json, $from, $to)) !== null) {
                    $DB->set_field('reportbuilder_user_filter', 'filterdata', $moved, ['id' => $id]);
                    $changed++;
                }
            }
        }

        return $changed;
    }

    /**
     * Rename the keys of a stored filter/condition value set that start with a prefix.
     *
     * @param string $json Stored JSON object, keyed `entity:name_suffix`.
     * @param string $from Old key prefix.
     * @param string $to New key prefix.
     * @return string|null The re-encoded JSON, or null when nothing changed.
     */
    private static function rename_value_keys(string $json, string $from, string $to): ?string {
        $values = json_decode($json, true);
        if (!is_array($values)) {
            return null;
        }
        $moved = [];
        $touched = false;
        foreach ($values as $key => $value) {
            if (str_starts_with((string) $key, $from)) {
                $key = $to . substr((string) $key, strlen($from));
                $touched = true;
            }
            $moved[$key] = $value;
        }
        return $touched ? json_encode($moved) : null;
    }
}
