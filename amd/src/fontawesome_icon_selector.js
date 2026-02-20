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
 * Provides the required functionality for an autocomplete element to select a FontAwesome icon.
 *
 * Adapted from theme_boost_union/fontawesome_icon_selector for use in local_dimensions.
 *
 * @module     local_dimensions/fontawesome_icon_selector
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {render as renderTemplate} from 'core/templates';
import {getString, getStrings} from 'core/str';

/**
 * Load the list of FontAwesome icons matching the query and render the selector labels for them.
 *
 * @param {String} selector The selector of the auto complete element.
 * @param {String} query The query string.
 * @param {Function} callback A callback function receiving an array of results.
 * @param {Function} failure A function to call in case of failure, receiving the error message.
 */
export async function transport(selector, query, callback, failure) {

    const request = {
        methodname: 'local_dimensions_get_fontawesome_icons',
        args: {
            query: query
        }
    };

    try {
        const response = await Ajax.call([request])[0];

        if (response.overflow) {
            const msg = await getString('cardicon_toomanyicons', 'local_dimensions', '>' + response.maxicons);
            callback(msg);

        } else {
            // Get all source label strings.
            const sourceStrings = await getStrings([
                {key: 'cardicon_sourcecore', component: 'local_dimensions'},
                {key: 'cardicon_sourcefasolid', component: 'local_dimensions'},
                {key: 'cardicon_sourcefabrand', component: 'local_dimensions'},
                {key: 'cardicon_sourcefablank', component: 'local_dimensions'}
            ]);

            // Format icons based on their source.
            const formattedIcons = response.icons.map(icon => {
                let formattedIcon = {
                    value: icon.name
                };

                if (icon.source === 'core') {
                    // Core: store FA class as value (not Moodle identifier like "core:i/xxx")
                    // because resolveIconClass can't map Moodle identifiers back to FA classes.
                    formattedIcon.value = icon.class;
                    formattedIcon.name = icon.name;
                    formattedIcon.class = icon.class;
                    formattedIcon.source = sourceStrings[0];
                    formattedIcon.sourcecolor = 'bg-warning text-dark';

                } else if (icon.source === 'fasolid') {
                    // Solid: just the icon class (template adds "fa fa-fw" prefix).
                    formattedIcon.name = icon.class;
                    formattedIcon.class = icon.class;
                    formattedIcon.source = sourceStrings[1];
                    formattedIcon.sourcecolor = 'bg-success';

                } else if (icon.source === 'fabrand') {
                    // Brand: add "fab" prefix (template adds "fa fa-fw" before it).
                    formattedIcon.name = icon.class;
                    formattedIcon.class = 'fab ' + icon.class;
                    formattedIcon.source = sourceStrings[2];
                    formattedIcon.sourcecolor = 'bg-success';

                } else if (icon.source === 'fablank') {
                    formattedIcon.name = icon.class;
                    formattedIcon.class = icon.class;
                    formattedIcon.source = sourceStrings[3];
                    formattedIcon.sourcecolor = 'bg-success';
                }

                return formattedIcon;
            });

            // Render all icons with the Mustache template.
            let labels = await Promise.all(
                formattedIcons.map(formattedIcon =>
                    renderTemplate('local_dimensions/form_autocomplete_fontawesome_icon', formattedIcon)
                )
            );

            // Add the rendered HTML labels to the icons.
            formattedIcons.forEach((icon, index) => {
                icon.label = labels[index];
            });

            callback(formattedIcons);
        }

    } catch (e) {
        failure(e);
    }
}

/**
 * Process the results for auto complete elements.
 *
 * @param {String} selector The selector of the auto complete element.
 * @param {Array} results An array of results returned by transport().
 * @return {Array} New array of the selector options.
 */
export function processResults(selector, results) {

    if (!Array.isArray(results)) {
        return results;

    } else {
        return results.map(result => ({value: result.value, label: result.label}));
    }
}
