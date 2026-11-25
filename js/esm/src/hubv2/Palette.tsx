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
 * The command palette: one field driving one list, in a dialogue (Ctrl+K, Cmd+K on a Mac, or the
 * workspace's "Commands" button).
 *
 * The ARIA combobox pattern as the filter line's search draws it (amd/src/filter_line.js): the field is
 * the combobox and keeps the focus, the list is a listbox of labelled groups, the active option is named
 * by aria-activedescendant and marked aria-selected, a press on an option keeps the focus in the field.
 * Up and Down move (and wrap), Enter runs, Escape closes (Dialog.tsx). The groups and their filtering are
 * paletteitems.ts; the "Go to" hits come from the tree's search service with its debounce and minimum length,
 * as the workspace's own search asks it.
 *
 * @module     local_dimensions/hubv2/Palette
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useMemo, useRef, useState} from 'react';
import type {KeyboardEvent, ReactNode} from 'react';
import {notifyError} from './amd';
import {ariaShortcut, shortcutCaps} from './commands';
import Dialog from './Dialog';
import {flatEntries, paletteGroups, searchable, stepEntry} from './paletteitems';
import type {PaletteEntry} from './paletteitems';
import {track} from './pending';
import {search} from './repository';
import {fillCount} from './strings';
import type {Labels} from './strings';
import type {PathItem} from './types';

/** The wait after the last key before the search runs, in milliseconds (as Search.tsx). */
const DEBOUNCE_MS = 300;

/** The palette's props. */
export type PaletteProps = {
    labels: Labels,
    mac: boolean,
    frameworkid: number,
    // The title of the actions' group: the selected competency's name.
    actionstitle: string,
    actions: PaletteEntry[],
    commands: PaletteEntry[],
    // Run an entry: an action's id, or a "Go to" hit.
    onRun: (id: string, hit: PathItem | null) => void,
    onClose: () => void,
    onLostFocus: () => void,
};

/**
 * The palette.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The dialogue.
 */
const Palette = (props: PaletteProps): ReactNode => {
    const {labels, mac, frameworkid, actionstitle, actions, commands, onRun, onClose, onLostFocus} = props;
    const [query, setQuery] = useState('');
    const [hits, setHits] = useState<{query: string, items: PathItem[], total: number} | null>(null);
    const [active, setActive] = useState(0);
    const field = useRef<HTMLInputElement>(null);
    const asked = useRef(0);
    const id = useId();
    const titleid = `${id}-title`;
    const listid = `${id}-list`;

    useEffect(() => {
        const text = query.trim();
        if (!searchable(text)) {
            asked.current++;
            setHits(null);
            return undefined;
        }
        const timer = window.setTimeout(() => {
            const ask = ++asked.current;
            track('palette', search(frameworkid, text))
                .then((answer) => {
                    // A later text has been typed: this answer is for one no longer in the field.
                    if (ask === asked.current) {
                        setHits({query: text, ...answer});
                    }
                    return null;
                })
                .catch(notifyError);
        }, DEBOUNCE_MS);
        return () => window.clearTimeout(timer);
    }, [query, frameworkid]);

    const gotos: PaletteEntry[] = useMemo(() => (hits ? hits.items.map((item) => ({
        id: `goto-${item.id}`,
        label: item.shortname,
        icon: 'fa-location-arrow',
        hint: [item.path, item.idnumber].filter((part) => part !== '').join(' · '),
    })) : []), [hits]);

    const groups = useMemo(() => paletteGroups({
        actions,
        commands,
        gotos,
        titles: {actions: actionstitle, hits: labels.hubv2_palette_goto, commands: labels.hubv2_palette_commands},
    }, query), [actions, commands, gotos, actionstitle, labels, query]);
    const entries = flatEntries(groups);
    const current = entries.length === 0 ? -1 : Math.min(active, entries.length - 1);

    /**
     * Run an entry.
     *
     * @param {object} entry The entry.
     * @returns {void}
     */
    const run = (entry: PaletteEntry): void => {
        const hit = entry.id.startsWith('goto-') ? hits?.items.find((item) => `goto-${item.id}` === entry.id) ?? null : null;
        onRun(entry.id, hit);
    };

    /**
     * Up and Down move the active entry; Enter runs it.
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(stepEntry(entries.length, current, event.key === 'ArrowDown'));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (current >= 0) {
                run(entries[current]);
            }
        }
    };

    /**
     * An option's id, for aria-activedescendant.
     *
     * @param {number} index The option's place.
     * @returns {string} The id.
     */
    const optionId = (index: number): string => `${id}-option-${index}`;

    let index = -1;
    return (
        <Dialog labelledby={titleid} className="local-dimensions-hubv2-dialog local-dimensions-hubv2-palette"
            initialFocus={field} returnTo="opener" onClose={onClose} onLostFocus={onLostFocus}>
            <h2 id={titleid} className="visually-hidden">{labels.hubv2_commands}</h2>
            <div className="local-dimensions-hubv2-palette-field">
                <i className="fa fa-magnifying-glass" aria-hidden="true" />
                <input
                    ref={field}
                    type="text"
                    className="local-dimensions-hubv2-palette-input"
                    role="combobox"
                    aria-label={labels.hubv2_palette_field}
                    placeholder={labels.hubv2_palette_field}
                    aria-autocomplete="list"
                    aria-expanded={entries.length > 0}
                    aria-controls={listid}
                    aria-activedescendant={current >= 0 ? optionId(current) : undefined}
                    autoComplete="off"
                    value={query}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setActive(0);
                    }}
                    onKeyDown={onKeyDown}
                />
            </div>
            <div
                id={listid}
                className="local-dimensions-hubv2-palette-list"
                role="listbox"
                aria-label={labels.hubv2_commands}
                onMouseDown={(event) => event.preventDefault()}
            >
                {groups.map((group) => (
                    <div key={group.key} role="group" aria-labelledby={`${id}-${group.key}`}>
                        <div id={`${id}-${group.key}`} className="local-dimensions-hubv2-palette-head" role="presentation">
                            {group.title}
                        </div>
                        {group.entries.map((entry) => {
                            index++;
                            const at = index;
                            const classes = at === current
                                ? 'local-dimensions-hubv2-palette-option local-dimensions-hubv2-palette-option--active'
                                : 'local-dimensions-hubv2-palette-option';
                            return (
                                <div
                                    key={entry.id}
                                    id={optionId(at)}
                                    className={classes}
                                    role="option"
                                    aria-selected={at === current}
                                    aria-keyshortcuts={entry.shortcut ? ariaShortcut(entry.shortcut, mac) : undefined}
                                    onClick={() => run(entry)}
                                >
                                    <i className={`fa ${entry.icon} local-dimensions-hubv2-menu-icon`} aria-hidden="true" />
                                    <span className="local-dimensions-hubv2-menu-label">{entry.label}</span>
                                    {entry.hint ? <span className="local-dimensions-hubv2-palette-hint">{entry.hint}</span> : null}
                                    {entry.shortcut ? (
                                        <span className="local-dimensions-hubv2-keys" aria-hidden="true">
                                            {shortcutCaps(entry.shortcut, mac).map((cap) => <kbd key={cap}>{cap}</kbd>)}
                                        </span>
                                    ) : null}
                                </div>
                            );
                        })}
                    </div>
                ))}
            </div>
            {entries.length === 0 ? <p className="local-dimensions-hubv2-palette-none">{labels.hubv2_palette_none}</p> : null}
            <div className="visually-hidden" aria-live="polite">
                {hits !== null ? fillCount(labels, 'hubv2_searchresults', hits.total) : ''}
            </div>
            <div className="local-dimensions-hubv2-palette-foot" aria-hidden="true">
                <span><kbd>↑</kbd><kbd>↓</kbd>{labels.hubv2_palette_keys_move}</span>
                <span><kbd>Enter</kbd>{labels.hubv2_palette_keys_run}</span>
                <span><kbd>Esc</kbd>{labels.hubv2_palette_keys_close}</span>
            </div>
        </Dialog>
    );
};

export default Palette;
