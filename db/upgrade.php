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

    // Force lang + AMD revision bumps so newly added strings (edittemplate_*)
    // and rebuilt JS modules (edit_template, edit_competency) are served fresh
    // to admin sessions still holding the previous revision.
    if ($oldversion < 2026050903) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026050903, 'local', 'dimensions');
    }

    // Manage competencies aside gained icons + a delete-competency button;
    // bump the JS revision so browsers fetch the rebuilt manage_competencies.min.js
    // instead of serving the cached version that lacked the new aside markup.
    if ($oldversion < 2026051001) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026051001, 'local', 'dimensions');
    }

    // Wave 1 of post-revamp improvements: admin_externalpage gating,
    // aria-labelledby in form sections, empty-state no-permission hint,
    // native action-selector delete dialog. New strings added; bump JS rev.
    if ($oldversion < 2026051002) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026051002, 'local', 'dimensions');
    }

    // Wave 2: SQL-side hidden filtering, batch template_metadata_cache fetch,
    // README/CHANGELOG documentation. Purge MUC so the next manage_templates
    // render seeds the cache via the new batch path with the correct payload
    // shape (timemodified is now stored alongside the customfield-derived
    // values regardless of how the entry was hydrated).
    if ($oldversion < 2026051003) {
        \local_dimensions\template_metadata_cache::purge_all();

        upgrade_plugin_savepoint(true, 2026051003, 'local', 'dimensions');
    }

    // Security/quality review fixes: new local/dimensions:editcustomscss capability
    // gates editing of the SCSS field on competencies and templates. Capabilities
    // are reloaded automatically by the upgrade pipeline; the savepoint here only
    // marks the version transition.
    if ($oldversion < 2026051101) {
        upgrade_plugin_savepoint(true, 2026051101, 'local', 'dimensions');
    }

    // Per-template overrides for enrollmentfilter / singlecourseredirect. Provision
    // the two new lp-area select customfields and purge metadata cache so that
    // existing entries are rebuilt with the new payload keys.
    if ($oldversion < 2026051102) {
        \local_dimensions\helper::get_enrollmentfilter_field();
        \local_dimensions\helper::get_singlecourseredirect_field();
        \local_dimensions\template_metadata_cache::purge_all();

        upgrade_plugin_savepoint(true, 2026051102, 'local', 'dimensions');
    }

    // Per-competency overrides for enrollmentfilter / singlecourseredirect.
    // Provisions the two select customfields in the competency area so the
    // cascade competency -> template -> global has a place to read the
    // competency layer from. view-competency.php now consults this field first.
    if ($oldversion < 2026051201) {
        \local_dimensions\helper::get_enrollmentfilter_field(\local_dimensions\helper::AREA_COMPETENCY);
        \local_dimensions\helper::get_singlecourseredirect_field(\local_dimensions\helper::AREA_COMPETENCY);

        upgrade_plugin_savepoint(true, 2026051201, 'local', 'dimensions');
    }

    // Structure tab v3 (Fase 1): nodes now carry activitycount + rulelabel, the tree gained
    // display-option toggles (taxonomy/idnumber/rule badges) and expand/collapse-all, and the
    // detail pane shows labeled fields. Purge caches so the rebuilt structure.min.js and the new
    // managecompetencies_* strings are served fresh to sessions holding the previous revision.
    if ($oldversion < 2026063000) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063000, 'local', 'dimensions');
    }

    // Structure tab v3 polish: the tree drops the folder/circle node icons (leaf nodes show a
    // bullet, branches keep the chevron) and the tree container scrolls internally (max-height)
    // so "expand all" no longer scrolls the page. Purge caches so the updated Mustache + CSS are
    // served fresh.
    if ($oldversion < 2026063001) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063001, 'local', 'dimensions');
    }

    // Structure tab v3 (Fase 2): adds the search_structure web service (in-tree search with
    // reveal-walk) and a resizable tree/detail split. Purge caches so the new WS is registered
    // and the rebuilt structure.min.js + Mustache + CSS are served fresh.
    if ($oldversion < 2026063002) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063002, 'local', 'dimensions');
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
