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
 * Competency hub tab orchestration: shared helpers to re-render a dynamic tab pane
 * from the server without a page reload. The pane dataset is the single source of
 * truth for a tab's arguments.
 *
 * @module     local_dimensions/central/tabs
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import {getContent} from 'core/local/repository/dynamic_tabs';
import $ from 'jquery';

/**
 * The getContent arguments for a tab, read from the pane dataset (mirrors core
 * dynamic_tabs: every data-* attribute except the tab class/content markers).
 *
 * @param {HTMLElement} pane The tab pane ([data-tab-content]).
 * @return {Object}
 */
export const paneArgs = (pane) => {
    const args = {...pane.dataset};
    delete args.tabClass;
    delete args.tabContent;
    return args;
};

/**
 * Reload a tab pane from the server and re-attach its JS, without reloading the page.
 *
 * @param {HTMLElement} pane The tab pane ([data-tab-content]).
 * @param {Object} [args] Override arguments; defaults to the pane dataset.
 * @return {Promise<void>}
 */
export const reloadPane = async(pane, args) => {
    const useargs = args || paneArgs(pane);
    const response = await getContent(pane.dataset.tabClass, JSON.stringify(useargs));
    const responseJs = $.parseHTML(response.javascript, null, true).map((node) => node.innerHTML).join('\n');
    const {html, js} = await Templates.renderForPromise(response.template, JSON.parse(response.content));
    await Templates.replaceNodeContents(pane, html, js + responseJs);
};
