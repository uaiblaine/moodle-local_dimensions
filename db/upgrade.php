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

    return true;
}
