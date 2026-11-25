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
 * What the command palette lists for a text: its three groups and the order the arrows walk.
 *
 * The actions on the selected competency and the workspace's commands come from the registry
 * (commands.ts) and are filtered here by their labels; the "Go to" hits are the search service's
 * answer for the text, listed as they come. With no text the palette lists the actions and the
 * commands; a text shorter than the search's minimum filters them and asks the server nothing.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/paletteitems
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Shortcut} from './commands';

/** The shortest text the search service answers (search_structure::MIN_QUERY_LENGTH, as Search.tsx reads it). */
export const MIN_QUERY = 2;

/** One entry of the palette. */
export type PaletteEntry = {
    // Unique in the palette: an action's id, or `goto-<competency id>`.
    id: string,
    label: string,
    icon: string,
    // A quiet note at the end of the line: a competency's path and ID number.
    hint?: string,
    shortcut?: Shortcut,
};

/** One group: the actions on the selection, "Go to", and the structure and view commands. */
export type PaletteGroup = {key: 'actions' | 'goto' | 'commands', title: string, entries: PaletteEntry[]};

/**
 * A text folded for matching: lower case, without accents, so "regua" finds "Régua".
 *
 * @param {string} text The text.
 * @returns {string} The folded text.
 */
export const fold = (text: string): string => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLocaleLowerCase();

/**
 * Whether a text is long enough to ask the search service.
 *
 * @param {string} query The text in the field.
 * @returns {boolean} Whether it is.
 */
export const searchable = (query: string): boolean => query.trim().length >= MIN_QUERY;

/**
 * The palette's groups for a text, each with at least one entry.
 *
 * @param {object} input The entries and the groups' titles.
 * @param {string} query The text in the field.
 * @returns {Array} The groups, in the order shown.
 */
export const paletteGroups = (
    input: {
        actions: PaletteEntry[],
        commands: PaletteEntry[],
        gotos: PaletteEntry[],
        titles: {actions: string, hits: string, commands: string},
    },
    query: string,
): PaletteGroup[] => {
    const wanted = fold(query.trim());
    /**
     * Whether an entry's label contains the text.
     *
     * @param {object} entry The entry.
     * @returns {boolean} Whether it does.
     */
    const matches = (entry: PaletteEntry): boolean => wanted === '' || fold(entry.label).includes(wanted);
    const groups: PaletteGroup[] = [
        {key: 'actions', title: input.titles.actions, entries: input.actions.filter(matches)},
        {key: 'goto', title: input.titles.hits, entries: searchable(query) ? input.gotos : []},
        {key: 'commands', title: input.titles.commands, entries: input.commands.filter(matches)},
    ];
    return groups.filter((group) => group.entries.length > 0);
};

/**
 * Every entry of the groups, in the order the arrows walk them.
 *
 * @param {Array} groups The groups.
 * @returns {Array} The entries.
 */
export const flatEntries = (groups: PaletteGroup[]): PaletteEntry[] => groups.flatMap((group) => group.entries);

/**
 * The entry Up or Down moves to, wrapping at the ends; none when the list is empty.
 *
 * @param {number} count How many entries there are.
 * @param {number} active The active entry, -1 for none.
 * @param {boolean} down Down when true.
 * @returns {number} The new active entry, -1 for none.
 */
export const stepEntry = (count: number, active: number, down: boolean): number => {
    if (count === 0) {
        return -1;
    }
    if (active < 0) {
        return down ? 0 : count - 1;
    }
    return (active + (down ? 1 : -1) + count) % count;
};
