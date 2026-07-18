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

    // Structure tab v3 polish: "show hidden frameworks" now filters the framework dropdown
    // client-side (no reload, no toggle flash), the search box sits below the display toggles,
    // and search results render as an overlay. Purge caches so the rebuilt JS/Mustache/CSS are served fresh.
    if ($oldversion < 2026063003) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063003, 'local', 'dimensions');
    }

    // Force a web-service descriptions re-sync. An upgrade previously ran against a stale
    // db/services.php (an old copy had overwritten the working tree), so Moodle pruned the
    // functions missing from that file (browse_structure, search_structure, ...). This bump
    // re-registers every function now declared in db/services.php via external_update_descriptions.
    if ($oldversion < 2026063004) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063004, 'local', 'dimensions');
    }

    // Structure tab v3 (Fase 3): the "Related competencies" modal adds a read WS
    // (local_dimensions_list_related_competencies). The bump registers it via
    // external_update_descriptions; purge so the rebuilt JS/Mustache/strings are served fresh.
    if ($oldversion < 2026063005) {
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026063005, 'local', 'dimensions');
    }

    // Search + tree polish batch: accent-insensitive search (provision the PostgreSQL unaccent
    // extension where possible - non-fatal), plus rebuilt JS/CSS/templates. Purge so the new
    // bundles/strings are served fresh.
    if ($oldversion < 2026070100) {
        \local_dimensions\helper::ensure_unaccent();
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026070100, 'local', 'dimensions');
    }

    if ($oldversion < 2026071000) {
        // Persist the Competency hub view state via user preferences plus a full privacy
        // provider; purge so the new AMD bundles, the preference callback and the new strings
        // are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071000, 'local', 'dimensions');
    }

    if ($oldversion < 2026071001) {
        // The Panorama accordion now uses the enrollmentfilter cascade, so the separate
        // summaryenrollmentfilter setting is retired. The catch-all below provisions the two new
        // per-plan showrelated/showrelatedlink customfields. Purge so the rebuilt WS signature,
        // AMD bundles and strings are served fresh.
        unset_config('summaryenrollmentfilter', 'local_dimensions');
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071001, 'local', 'dimensions');
    }

    if ($oldversion < 2026071100) {
        // Enrolment methods tab (participants modal): four new web services
        // (list_enrol_competencies, list_enrol_courses, queue_enrol_action,
        // get_enrol_queue_status) registered via external_update_descriptions; purge so the
        // new strings are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071100, 'local', 'dimensions');
    }

    if ($oldversion < 2026071101) {
        // Enrolment methods tab front-end: the 4th participants-modal tab (templates, AMD
        // bundle, strings, styles). Purge so the rebuilt JS/Mustache/strings are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071101, 'local', 'dimensions');
    }

    if ($oldversion < 2026071102) {
        // Enrolment methods tab polish after first manual testing: refresh button, active
        // state + assigned role surfaced in rows/details, striped larger details modal,
        // accordion animation and reworked strings. Purge so the rebuilt JS/Mustache/strings
        // are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071102, 'local', 'dimensions');
    }

    if ($oldversion < 2026071103) {
        // Enrolment methods tab: "Manage enrol plugins" modal-header shortcut (site admins)
        // and a warning state when both enrol plugins are disabled sitewide. Purge so the
        // rebuilt JS/Mustache/strings are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071103, 'local', 'dimensions');
    }

    if ($oldversion < 2026071104) {
        // Enrolment methods tab: the disabled/empty alerts toggle via el.hidden again (the
        // d-flex utility on the alert beat the hidden attribute, so both always showed).
        // Purge so the rebuilt Mustache is served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071104, 'local', 'dimensions');
    }

    if ($oldversion < 2026071105) {
        // Enrolment methods tab a11y pass: the accordion body becomes a labelled table
        // (course / category / role / status / actions headers) and the neutral badges pair
        // bg-secondary with text-dark for contrast. Purge so the rebuilt JS/Mustache/styles
        // are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071105, 'local', 'dimensions');
    }

    if ($oldversion < 2026071106) {
        // Enrolment methods tab: course rows are DOM-built (a bare tr Mustache partial
        // cannot pass the template HTML validation), retiring enrol_row.mustache. Purge so
        // the rebuilt AMD bundle is served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071106, 'local', 'dimensions');
    }

    if ($oldversion < 2026071107) {
        // Enrolment methods tab: per-row enable/disable toggle backed by the new
        // set_enrol_instance_status web service (registered via external_update_descriptions)
        // and border cleanup on the accordion tables. Purge so the rebuilt AMD bundle and
        // strings are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071107, 'local', 'dimensions');
    }

    if ($oldversion < 2026071108) {
        // Enrolment methods tab: toast feedback on the enable/disable toggle, the config bar
        // distributes horizontally and the selected method segment wears the primary colour.
        // Purge so the rebuilt AMD bundle, Mustache and strings are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071108, 'local', 'dimensions');
    }

    if ($oldversion < 2026071109) {
        // Enrolment methods tab: server-side competency search (case- and accent-insensitive
        // query on list_enrol_competencies) with a debounced search box. Purge so the rebuilt
        // AMD bundle, Mustache and strings are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071109, 'local', 'dimensions');
    }

    if ($oldversion < 2026071110) {
        // Framework form (Structures tab): native scale parity on edit (readonly + constant
        // instead of a static swap, proficiency config always editable) and the "Open scales
        // page" header shortcut on the create/edit modal. Purge so the rebuilt AMD bundle,
        // Mustache and strings are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071110, 'local', 'dimensions');
    }

    if ($oldversion < 2026071111) {
        // Framework form: a frozen scale select is also disabled (readonly alone does not
        // lock a select visually); the constant keeps supplying scaleid server-side.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071111, 'local', 'dimensions');
    }

    if ($oldversion < 2026071112) {
        // Framework form: no required rule on the frozen (disabled) scale select — the rule
        // validates submitted values, where a disabled field never appears, so saving always
        // failed with "Enter a value"; the form constant remains the server-side guarantee.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071112, 'local', 'dimensions');
    }

    if ($oldversion < 2026071113) {
        // Framework form modal: the standardised close-button chip now also covers modals
        // tagged with the shared header class (a ModalForm body carries no plugin classes,
        // so the :has() selector alone never matched it). Purge for the rebuilt CSS/AMD.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071113, 'local', 'dimensions');
    }

    if ($oldversion < 2026071114) {
        // Framework form: toast confirmation after saving the create/edit modal. Purge for
        // the rebuilt AMD bundle and the new strings.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071114, 'local', 'dimensions');
    }

    if ($oldversion < 2026071115) {
        // Framework form: set_data no longer string-casts the taxonomies accessor (the
        // persistent already returns the per-level array) — under developer debugging the
        // warning escalated to an exception and the edit modal never opened.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071115, 'local', 'dimensions');
    }

    if ($oldversion < 2026071800) {
        // Append the new "enrolled and self-enrolable" option to the existing
        // enrollmentfilter select fields (lp + competency). The provisioning
        // catch-all below short-circuits on the existing field and never re-syncs
        // its option list, so this reconcile is required on upgraded sites.
        \local_dimensions\helper::sync_enrollmentfilter_option(\local_dimensions\helper::AREA_LP);
        \local_dimensions\helper::sync_enrollmentfilter_option(\local_dimensions\helper::AREA_COMPETENCY);

        upgrade_plugin_savepoint(true, 2026071800, 'local', 'dimensions');
    }

    if ($oldversion < 2026071801) {
        // Bring lockedcardmode + showlockeddate to the plan/competency level: two new
        // cascade select customfields (both areas), plus a reorganization of every
        // plugin customfield into the localized "Feel"/"Look" categories. Both the
        // provisioning and the categorization run through the catch-all's
        // ensure_custom_fields_exist() below; purge so the new strings are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071801, 'local', 'dimensions');
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
