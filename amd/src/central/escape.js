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
 * Conversions between the two spellings a Competency hub name can arrive in.
 *
 * The plugin's web services and templates carry names in the plain spelling (format_string()
 * with escape off), so each sink escapes exactly once. textContent and Mustache double stashes
 * do that themselves; escapeHtml() does it for the sinks that parse HTML: autocomplete labels,
 * modal titles and bodies, confirm dialogues and toasts, which is also where a string parameter
 * ends up once getString() has substituted it. Core exporters and core_competency services
 * return the escaped spelling instead, and decodeEntities() turns it back into plain text for a
 * text sink.
 *
 * @module     local_dimensions/central/escape
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {Object} Characters that change meaning in HTML text and attributes. */
const ENTITIES = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'};

/**
 * Escape a plain-text value for insertion into HTML.
 *
 * @param {*} text The plain value; null and undefined read as empty.
 * @return {String} The escaped HTML.
 */
export const escapeHtml = (text) => {
    const value = (text === null || text === undefined) ? '' : String(text);
    return value.replace(/[&<>"']/g, (char) => ENTITIES[char]);
};

/**
 * Decode an already-escaped value (core exporter output) into plain text.
 *
 * A textarea parses its content as text, so no markup in the value is ever built or run.
 *
 * @param {*} html The escaped value; null and undefined read as empty.
 * @return {String} The plain text.
 */
export const decodeEntities = (html) => {
    const area = document.createElement('textarea');
    area.innerHTML = (html === null || html === undefined) ? '' : String(html);
    return area.value;
};
