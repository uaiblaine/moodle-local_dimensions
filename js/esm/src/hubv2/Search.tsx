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
 * The tree's search: a field, and its hits in a list under it. Choosing a hit reveals it on the
 * canvas (its ancestors opened, loading as needed), selects it and centres it.
 *
 * @module     local_dimensions/hubv2/Search
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useRef, useState} from 'react';
import type {KeyboardEvent, ReactNode} from 'react';
import {notifyError} from './amd';
import {track} from './pending';
import {search} from './repository';
import {fillCount} from './strings';
import type {Labels} from './strings';
import type {PathItem} from './types';

/** The wait after the last key before the search runs, in milliseconds. */
const DEBOUNCE_MS = 300;

/** The shortest text the service searches for (search_structure::MIN_QUERY_LENGTH). */
const MIN_QUERY = 2;

/** The search's props. */
export type SearchProps = {labels: Labels, frameworkid: number, onChoose: (item: PathItem) => void};

/**
 * The search.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The field and its hits.
 */
const Search = ({labels, frameworkid, onChoose}: SearchProps): ReactNode => {
    const [query, setQuery] = useState('');
    const [hits, setHits] = useState<{items: PathItem[], total: number} | null>(null);
    const [open, setOpen] = useState(false);
    const listid = useId();
    const fieldid = useId();
    const list = useRef<HTMLUListElement>(null);
    const asked = useRef(0);

    useEffect(() => {
        const text = query.trim();
        if (text.length < MIN_QUERY) {
            setHits(null);
            return undefined;
        }
        const timer = window.setTimeout(() => {
            const ask = ++asked.current;
            track('search', search(frameworkid, text))
                .then((answer) => {
                    // A later search has started: this answer is for a text no longer in the field.
                    if (ask === asked.current) {
                        setHits(answer);
                        setOpen(true);
                    }
                    return null;
                })
                .catch(notifyError);
        }, DEBOUNCE_MS);
        return () => window.clearTimeout(timer);
    }, [query, frameworkid]);

    /**
     * Arrow down moves into the hits; Escape closes them.
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    const onKeyDown = (event: KeyboardEvent): void => {
        if (event.key === 'Escape') {
            setOpen(false);
        } else if (event.key === 'ArrowDown' && open && list.current) {
            event.preventDefault();
            list.current.querySelector('button')?.focus();
        }
    };

    /**
     * Move between hits with the arrows; Escape goes back to the field.
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    const onListKeyDown = (event: KeyboardEvent<HTMLUListElement>): void => {
        const buttons = [...(list.current?.querySelectorAll('button') ?? [])];
        const index = buttons.indexOf(document.activeElement as HTMLButtonElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const next = event.key === 'ArrowDown' ? Math.min(index + 1, buttons.length - 1) : index - 1;
            (next < 0 ? document.getElementById(fieldid) : buttons[next])?.focus();
        } else if (event.key === 'Escape') {
            setOpen(false);
            document.getElementById(fieldid)?.focus();
        }
    };

    const shown = open && hits !== null;
    return (
        <div className="local-dimensions-hubv2-search">
            <label className="visually-hidden" htmlFor={fieldid}>{labels.hubv2_search}</label>
            <input
                id={fieldid}
                type="search"
                className="form-control form-control-sm"
                placeholder={labels.managecompetencies_searchplaceholder}
                value={query}
                autoComplete="off"
                aria-expanded={shown}
                aria-controls={listid}
                onChange={(event) => setQuery(event.target.value)}
                onFocus={() => setOpen(hits !== null)}
                onKeyDown={onKeyDown}
            />
            <div className="visually-hidden" aria-live="polite">
                {hits !== null ? fillCount(labels, 'hubv2_searchresults', hits.total) : ''}
            </div>
            {shown ? (
                <div className="local-dimensions-hubv2-search-results">
                    {hits.items.length === 0 ? <p className="text-muted small m-2">{labels.hubv2_searchnone}</p> : null}
                    <ul id={listid} ref={list} className="list-unstyled mb-0" onKeyDown={onListKeyDown}>
                        {hits.items.map((item) => (
                            <li key={item.id}>
                                <button
                                    type="button"
                                    className="local-dimensions-hubv2-search-hit"
                                    onClick={() => {
                                        setOpen(false);
                                        onChoose(item);
                                    }}
                                >
                                    <span className="local-dimensions-hubv2-search-name">{item.shortname}</span>
                                    {item.path ? <span className="local-dimensions-hubv2-search-path">{item.path}</span> : null}
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
};

export default Search;
