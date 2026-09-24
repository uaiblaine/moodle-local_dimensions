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

/**
 * Plugin upgrade steps.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_dimensions plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_local_dimensions_upgrade($oldversion) {
    global $DB;

    // Frankenstyle migration: rename custom field shortnames.
    // This block can be safely removed once all installations have upgraded past 2026031101.
    if ($oldversion < 2026031101) {
        $shortnamemap = [
            'customcard'      => 'local_dimensions_customcard',
            'custombgimage'   => 'local_dimensions_custombgimage',
            'custombgcolor'   => 'local_dimensions_custombgcolor',
            'customtextcolor' => 'local_dimensions_customtextcolor',
            'tag1'            => 'local_dimensions_tag1',
            'tag2'            => 'local_dimensions_tag2',
            'customscss'      => 'local_dimensions_customscss',
        ];

        // Only update fields belonging to local_dimensions custom field categories.
        $categories = $DB->get_records_sql(
            "SELECT id FROM {customfield_category} WHERE component = :component",
            ['component' => 'local_dimensions']
        );

        if ($categories) {
            $categoryids = array_keys($categories);
            [$insql, $inparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);

            foreach ($shortnamemap as $oldname => $newname) {
                $params = array_merge($inparams, ['oldname' => $oldname]);
                $DB->execute(
                    "UPDATE {customfield_field} SET shortname = :newname WHERE shortname = :oldname AND categoryid $insql",
                    array_merge($params, ['newname' => $newname])
                );
            }
        }

        upgrade_plugin_savepoint(true, 2026031101, 'local', 'dimensions');
    }

    // Provision the new "subline source" custom field for learning plan
    // templates so admins do not need to wait for an admin session refresh.
    if ($oldversion < 2026043002) {
        \local_dimensions\helper::get_subline_source_field();

        upgrade_plugin_savepoint(true, 2026043002, 'local', 'dimensions');
    }

    // Provision the new template identifier custom field (templates have no
    // native idnumber column; this customfield fills the gap so the manage
    // templates page can search and label by identifier).
    if ($oldversion < 2026050902) {
        \local_dimensions\helper::get_template_idnumber_field();
        // Existing cached payloads were built before the idnumber key existed;
        // purge so the next render rebuilds them via the extended SELECT.
        \local_dimensions\template_metadata_cache::purge_all();

        upgrade_plugin_savepoint(true, 2026050902, 'local', 'dimensions');
    }

    // No schema change; purge caches for new strings and the rebuilt template/competency edit modules.
    if ($oldversion < 2026050903) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026050903, 'local', 'dimensions');
    }

    // No schema change; purge caches for the rebuilt manage competencies page.
    if ($oldversion < 2026051001) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026051001, 'local', 'dimensions');
    }

    // No schema change; purge caches for new strings and rebuilt JS.
    if ($oldversion < 2026051002) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026051002, 'local', 'dimensions');
    }

    // Older template metadata payloads may lack timemodified; purge them so every entry is
    // rebuilt with the full payload.
    if ($oldversion < 2026051003) {
        \local_dimensions\template_metadata_cache::purge_all();

        upgrade_plugin_savepoint(true, 2026051003, 'local', 'dimensions');
    }

    // Adds the local/dimensions:editcustomscss capability (editing the SCSS custom field). The
    // upgrade syncs capabilities from db/access.php itself; this step only records the savepoint.
    if ($oldversion < 2026051101) {
        upgrade_plugin_savepoint(true, 2026051101, 'local', 'dimensions');
    }

    // Per-template enrollmentfilter / singlecourseredirect overrides: provision the two lp-area
    // select fields and purge the metadata cache so entries are rebuilt with the new keys.
    if ($oldversion < 2026051102) {
        \local_dimensions\helper::get_enrollmentfilter_field();
        \local_dimensions\helper::get_singlecourseredirect_field();
        \local_dimensions\template_metadata_cache::purge_all();

        upgrade_plugin_savepoint(true, 2026051102, 'local', 'dimensions');
    }

    // Per-competency enrollmentfilter / singlecourseredirect overrides: provision the two select
    // fields in the competency area, the first layer of the competency -> template -> global cascade.
    if ($oldversion < 2026051201) {
        \local_dimensions\helper::get_enrollmentfilter_field(\local_dimensions\helper::AREA_COMPETENCY);
        \local_dimensions\helper::get_singlecourseredirect_field(\local_dimensions\helper::AREA_COMPETENCY);

        upgrade_plugin_savepoint(true, 2026051201, 'local', 'dimensions');
    }

    // No schema change; purge caches for the rebuilt Structure tab and its new strings.
    if ($oldversion < 2026063000) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063000, 'local', 'dimensions');
    }

    // No schema change; purge caches for the Structure tab template and CSS changes.
    if ($oldversion < 2026063001) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063001, 'local', 'dimensions');
    }

    // Adds the search_structure web service, which the upgrade registers from db/services.php;
    // purge caches for the rebuilt Structure tab.
    if ($oldversion < 2026063002) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063002, 'local', 'dimensions');
    }

    // No schema change; purge caches for the rebuilt Structure tab.
    if ($oldversion < 2026063003) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063003, 'local', 'dimensions');
    }

    // No schema change: the version bump alone makes the upgrade re-register every function
    // declared in db/services.php.
    if ($oldversion < 2026063004) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063004, 'local', 'dimensions');
    }

    // Adds local_dimensions_list_related_competencies, which the upgrade registers from
    // db/services.php; purge caches for the related competencies modal.
    if ($oldversion < 2026063005) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063005, 'local', 'dimensions');
    }

    // Accent-insensitive search: provision the PostgreSQL unaccent extension where the database
    // account may create it (non-fatal), and purge caches.
    if ($oldversion < 2026070100) {
        \local_dimensions\helper::ensure_unaccent();
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026070100, 'local', 'dimensions');
    }

    if ($oldversion < 2026071000) {
        // No schema change; purge caches for the Competency hub view-state preferences.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071000, 'local', 'dimensions');
    }

    if ($oldversion < 2026071001) {
        // The Panorama accordion follows the enrollmentfilter cascade, so the summaryenrollmentfilter
        // setting is retired. The catch-all below provisions the showrelated/showrelatedlink fields.
        unset_config('summaryenrollmentfilter', 'local_dimensions');
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071001, 'local', 'dimensions');
    }

    if ($oldversion < 2026071100) {
        // Adds the enrolment methods web services, which the upgrade registers from
        // db/services.php; purge caches for the new strings.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071100, 'local', 'dimensions');
    }

    if ($oldversion < 2026071101) {
        // No schema change; purge caches for the enrolment methods tab.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071101, 'local', 'dimensions');
    }

    if ($oldversion < 2026071102) {
        // No schema change; purge caches for the enrolment methods tab.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071102, 'local', 'dimensions');
    }

    if ($oldversion < 2026071103) {
        // No schema change; purge caches for the enrolment methods tab.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071103, 'local', 'dimensions');
    }

    if ($oldversion < 2026071104) {
        // No schema change; purge caches for the enrolment methods tab.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071104, 'local', 'dimensions');
    }

    if ($oldversion < 2026071105) {
        // No schema change; purge caches for the enrolment methods tab.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071105, 'local', 'dimensions');
    }

    if ($oldversion < 2026071106) {
        // No schema change; purge caches for the enrolment methods tab.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071106, 'local', 'dimensions');
    }

    if ($oldversion < 2026071107) {
        // Adds the set_enrol_instance_status web service, which the upgrade registers from
        // db/services.php; purge caches for the enrolment methods tab.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071107, 'local', 'dimensions');
    }

    if ($oldversion < 2026071108) {
        // No schema change; purge caches for the enrolment methods tab.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071108, 'local', 'dimensions');
    }

    if ($oldversion < 2026071109) {
        // No schema change; purge caches for the enrolment methods competency search.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071109, 'local', 'dimensions');
    }

    if ($oldversion < 2026071110) {
        // No schema change; purge caches for the framework form.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071110, 'local', 'dimensions');
    }

    if ($oldversion < 2026071111) {
        // No schema change; purge caches for the framework form.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071111, 'local', 'dimensions');
    }

    if ($oldversion < 2026071112) {
        // No schema change; purge caches for the framework form.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071112, 'local', 'dimensions');
    }

    if ($oldversion < 2026071113) {
        // No schema change; purge caches for the framework form modal.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071113, 'local', 'dimensions');
    }

    if ($oldversion < 2026071114) {
        // No schema change; purge caches for the framework form.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071114, 'local', 'dimensions');
    }

    if ($oldversion < 2026071115) {
        // No schema change; purge caches for the framework form.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071115, 'local', 'dimensions');
    }

    if ($oldversion < 2026071800) {
        // Append the "enrolled and self-enrolable" option to the existing enrollmentfilter select
        // fields (lp + competency). The catch-all below skips fields that already exist and never
        // re-syncs their option lists.
        \local_dimensions\helper::sync_enrollmentfilter_option(\local_dimensions\helper::AREA_LP);
        \local_dimensions\helper::sync_enrollmentfilter_option(\local_dimensions\helper::AREA_COMPETENCY);

        upgrade_plugin_savepoint(true, 2026071800, 'local', 'dimensions');
    }

    if ($oldversion < 2026071801) {
        // The lockedcardmode/showlockeddate fields (both areas) and the "Feel"/"Look" field
        // categories are provisioned by the catch-all below; purge caches for the new strings.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071801, 'local', 'dimensions');
    }

    if ($oldversion < 2026072300) {
        // No schema change; purge caches for the learner views.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026072300, 'local', 'dimensions');
    }

    if ($oldversion < 2026072301) {
        // No schema change; purge caches for the learner views.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026072301, 'local', 'dimensions');
    }

    if ($oldversion < 2026072302) {
        // No schema change; purge caches for the rebuilt accordion detail panes.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026072302, 'local', 'dimensions');
    }

    if ($oldversion < 2026072303) {
        // No schema change; purge caches for a corrected lang string.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026072303, 'local', 'dimensions');
    }

    if ($oldversion < 2026072304) {
        // The tracker's competency-area chip group is retired, so drop its unread setting; the
        // reason is recorded in view_competency_page::export_for_template(). The tag1/tag2 custom
        // fields it named by shortname stay.
        unset_config('viewcompetency_filter_fields_competency', 'local_dimensions');
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026072304, 'local', 'dimensions');
    }

    if ($oldversion < 2026072305) {
        // No schema change. Web-service return structures are read at call time, so the extended
        // course and progress returns need no reinstall; purge caches for the learner views.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026072305, 'local', 'dimensions');
    }

    if ($oldversion < 2026072306) {
        // No schema change; purge caches for the learner views.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026072306, 'local', 'dimensions');
    }

    if ($oldversion < 2026073100) {
        /* helper::sql_like_ai() no longer creates the PostgreSQL unaccent extension at request
           time; only install and upgrade do. Sites installed before db/install.php provisioned
           it relied on the request-time path, so provision it here. */
        \local_dimensions\helper::ensure_unaccent();
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026073100, 'local', 'dimensions');
    }

    // Catch-all: re-ensure every customfield exists after any upgrade. Adding a
    // new customfield in the future only needs a version bump plus a new getter
    // wired into helper::ensure_custom_fields_exist(); no per-version savepoint
    // block is required for field provisioning. Each getter short-circuits via
    // find_field_by_shortname(), so this is idempotent.
    \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_LP);
    \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_COMPETENCY);

    return true;
}
