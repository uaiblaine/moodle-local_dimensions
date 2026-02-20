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
 * Settings and admin pages for local_dimensions plugin.
 *
 * This file adds local_dimensions pages to the 'competencies' admin category
 * instead of 'localplugins' to integrate with the native competency navigation.
 *
 * @package   local_dimensions
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig && get_config('core_competency', 'enabled')) {
    // Parent category for our pages (under competencies).
    $parentname = 'competencies';

    // Create a subcategory for Local Dimensions under competencies.
    $ADMIN->add($parentname, new admin_category(
        'local_dimensions',
        get_string('pluginsettings', 'local_dimensions')
    ));

    // Settings page for Local Dimensions.
    $settings = new admin_settingpage(
        'local_dimensions_settings',
        get_string('pluginsettings', 'local_dimensions')
    );

    // -----------------------------------------------------------------------
    // 1. General Settings.
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_dimensions/generalsettingsheading',
        get_string('generalsettingsheading', 'local_dimensions'),
        get_string('generalsettingsheading_desc', 'local_dimensions')
    ));

    // Enable return button.
    $settings->add(new admin_setting_configcheckbox(
        'local_dimensions/enablereturnbutton',
        get_string('enablereturnbutton', 'local_dimensions'),
        get_string('enablereturnbutton_desc', 'local_dimensions'),
        1
    ));

    // Return button color.
    $settings->add(new admin_setting_configcolourpicker(
        'local_dimensions/returnbuttoncolor',
        get_string('returnbuttoncolor', 'local_dimensions'),
        get_string('returnbuttoncolor_desc', 'local_dimensions'),
        '#667eea'
    ));

    // Image handler mode.
    $imagehandleroptions = [
        'builtin' => get_string('imagehandler_builtin', 'local_dimensions'),
    ];
    // Only offer external option if the plugin is installed.
    if (\local_dimensions\picture_manager::is_external_plugin_available()) {
        $imagehandleroptions['customfield_picture'] = get_string('imagehandler_external', 'local_dimensions');
    }
    $settings->add(new admin_setting_configselect(
        'local_dimensions/imagehandler',
        get_string('imagehandler', 'local_dimensions'),
        get_string('imagehandler_desc', 'local_dimensions'),
        'builtin',
        $imagehandleroptions
    ));

    // Enable custom SCSS for all modes (Plan Summary and Competency View).
    $settings->add(new admin_setting_configcheckbox(
        'local_dimensions/enablecustomscss',
        get_string('enablecustomscss', 'local_dimensions'),
        get_string('enablecustomscss_desc', 'local_dimensions'),
        0
    ));

    // -----------------------------------------------------------------------
    // 2. Competency View Mode.
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_dimensions/competencyviewheading',
        get_string('competencyviewheading', 'local_dimensions'),
        get_string('competencyviewheading_desc', 'local_dimensions')
    ));

    // Percentage display mode.
    $settings->add(new admin_setting_configselect(
        'local_dimensions/percentagedisplaymode',
        get_string('percentagedisplaymode', 'local_dimensions'),
        get_string('percentagedisplaymode_desc', 'local_dimensions'),
        'hover',
        [
            'fixed' => get_string('percentagemode_fixed', 'local_dimensions'),
            'hover' => get_string('percentagemode_hover', 'local_dimensions'),
            'hidden' => get_string('percentagemode_hidden', 'local_dimensions'),
        ]
    ));

    // Locked card display mode.
    $settings->add(new admin_setting_configselect(
        'local_dimensions/lockedcardmode',
        get_string('lockedcardmode', 'local_dimensions'),
        get_string('lockedcardmode_desc', 'local_dimensions'),
        'blocked',
        [
            'blocked' => get_string('lockedcardmode_blocked', 'local_dimensions'),
            'learnmore' => get_string('lockedcardmode_learnmore', 'local_dimensions'),
        ]
    ));

    // Learn More button color.
    $settings->add(new admin_setting_configcolourpicker(
        'local_dimensions/learnmorebuttoncolor',
        get_string('learnmorebuttoncolor', 'local_dimensions'),
        get_string('learnmorebuttoncolor_desc', 'local_dimensions'),
        '#667eea'
    ));

    // Show availability date on locked cards.
    $settings->add(new admin_setting_configcheckbox(
        'local_dimensions/showlockeddate',
        get_string('showlockeddate', 'local_dimensions'),
        get_string('showlockeddate_desc', 'local_dimensions'),
        1
    ));

    // Locked card icon (icon picker with AJAX search).
    require_once(__DIR__ . '/classes/admin/setting_iconpicker.php');
    $settings->add(new \local_dimensions\admin\setting_iconpicker(
        'local_dimensions/cardicon',
        get_string('cardicon', 'local_dimensions'),
        get_string('cardicon_desc', 'local_dimensions'),
        '',
        PARAM_TEXT
    ));

    // Enrollment filter mode.
    $settings->add(new admin_setting_configselect(
        'local_dimensions/enrollmentfilter',
        get_string('enrollmentfilter', 'local_dimensions'),
        get_string('enrollmentfilter_desc', 'local_dimensions'),
        'all',
        [
            'all' => get_string('enrollmentfilter_all', 'local_dimensions'),
            'enrolled' => get_string('enrollmentfilter_enrolled', 'local_dimensions'),
            'active' => get_string('enrollmentfilter_active', 'local_dimensions'),
        ]
    ));

    // Single course redirect (only applies when enrollment filter is 'active').
    $settings->add(new admin_setting_configcheckbox(
        'local_dimensions/singlecourseredirect',
        get_string('singlecourseredirect', 'local_dimensions'),
        get_string('singlecourseredirect_desc', 'local_dimensions'),
        0
    ));

    // -----------------------------------------------------------------------
    // 3. Plan Summary Mode - Expanded Content Display.
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_dimensions/plansummaryheading',
        get_string('plansummaryheading', 'local_dimensions'),
        get_string('plansummaryheading_desc', 'local_dimensions')
    ));

    // Enrollment filter for plan summary accordion.
    $settings->add(new admin_setting_configselect(
        'local_dimensions/summaryenrollmentfilter',
        get_string('summaryenrollmentfilter', 'local_dimensions'),
        get_string('summaryenrollmentfilter_desc', 'local_dimensions'),
        'all',
        [
            'all' => get_string('enrollmentfilter_all', 'local_dimensions'),
            'enrolled' => get_string('enrollmentfilter_enrolled', 'local_dimensions'),
            'active' => get_string('enrollmentfilter_active', 'local_dimensions'),
        ]
    ));

    // Show competency description.
    $settings->add(new admin_setting_configcheckbox(
        'local_dimensions/showdescription',
        get_string('showdescription', 'local_dimensions'),
        get_string('showdescription_desc', 'local_dimensions'),
        1
    ));

    // Show competency path/hierarchy.
    $settings->add(new admin_setting_configcheckbox(
        'local_dimensions/showpath',
        get_string('showpath', 'local_dimensions'),
        get_string('showpath_desc', 'local_dimensions'),
        0
    ));

    // Show related competencies.
    $settings->add(new admin_setting_configcheckbox(
        'local_dimensions/showrelated',
        get_string('showrelated', 'local_dimensions'),
        get_string('showrelated_desc', 'local_dimensions'),
        0
    ));

    // Show evidence cards.
    $settings->add(new admin_setting_configcheckbox(
        'local_dimensions/showevidence',
        get_string('showevidence', 'local_dimensions'),
        get_string('showevidence_desc', 'local_dimensions'),
        1
    ));

    // Show comments section.
    $settings->add(new admin_setting_configcheckbox(
        'local_dimensions/showcomments',
        get_string('showcomments', 'local_dimensions'),
        get_string('showcomments_desc', 'local_dimensions'),
        0
    ));



    $ADMIN->add('local_dimensions', $settings);

    // Competency Custom Fields configuration.
    $ADMIN->add('local_dimensions', new admin_externalpage(
        'local_dimensions_customfield',
        get_string('customfields', 'local_dimensions'),
        new moodle_url('/local/dimensions/customfield.php'),
        'moodle/competency:competencymanage'
    ));

    // Manage competencies with custom fields.
    $ADMIN->add('local_dimensions', new admin_externalpage(
        'local_dimensions_manage',
        get_string('managecompetencies', 'local_dimensions'),
        new moodle_url('/local/dimensions/manage_competencies.php'),
        'moodle/competency:competencymanage'
    ));

    // Learning Plan Template custom fields configuration.
    $ADMIN->add('local_dimensions', new admin_externalpage(
        'local_dimensions_customfield_template',
        get_string('templatecustomfields', 'local_dimensions'),
        new moodle_url('/local/dimensions/customfield_template.php'),
        'moodle/competency:templatemanage'
    ));

    // Manage templates with custom fields.
    $ADMIN->add('local_dimensions', new admin_externalpage(
        'local_dimensions_manage_templates',
        get_string('managetemplates', 'local_dimensions'),
        new moodle_url('/local/dimensions/manage_templates.php'),
        'moodle/competency:templatemanage'
    ));
}
