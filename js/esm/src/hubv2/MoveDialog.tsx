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
 * "Move to": the searchable way to give a competency a new parent, beside picking it on the map.
 *
 * A dialogue titled with the competency's name: a search field, then either the possible parents as a
 * single-select tree (an APG Tree View over the workspace's store, moveparents.ts; one Tab stop, the
 * keys of keynav.ts, its branches loaded as they are opened) or, for a text of two characters or more,
 * the search service's hits as a single-select list, each with its path. The framework's row stands for
 * the top level. The competency, its descendants and its current parent cannot be chosen.
 *
 * Once a parent is chosen, the dialogue reads both parents afresh and says, before its buttons, which of
 * them has a rule that core resets (move.ruleNotes, the same sentences the pick-on-the-map flow shows).
 * The commit button names the target and calls the workspace's one move (Hub.tsx moveUnder), which the
 * dialogue itself is the confirmation of. "Pick on the map" starts the pick-on-the-map flow instead.
 * It is opened by another layer (a menu, the palette), already closed: on close the focus goes to
 * the selected item of the pane that last had it (focusreturn.ts).
 *
 * The bulk dock opens it with several competencies, the topmost picked (bulk.topmost): its title counts them, its
 * body names the first five, none of them or their descendants can be chosen, a parent they all have is refused, and
 * once a parent is chosen it says how many already under it are skipped. Its rule notes name the parents of the
 * ones that move. It then returns the focus to the dock's button, and "Pick on the map" is not offered.
 *
 * @module     local_dimensions/hubv2/MoveDialog
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useMemo, useRef, useState} from 'react';
import type {KeyboardEvent, ReactNode} from 'react';
import {notifyError} from './amd';
import Dialog from './Dialog';
import {FRAMEWORK_ID} from './graph';
import {keyAction, navOrder} from './keynav';
import {ruleNotes} from './move';
import type {MoveParent} from './move';
import {movePlan, preview} from './bulk';
import {hitSelectable, parentChoices, pickerTree, sharedParent} from './moveparents';
import type {ParentChoice} from './moveparents';
import {searchable} from './paletteitems';
import {track} from './pending';
import {search} from './repository';
import {fill, fillCount, fillObject} from './strings';
import type {Labels} from './strings';
import {ROOT} from './tree';
import type {Tree} from './tree';
import type {PathItem, StructureNode} from './types';

/** The wait after the last key before the search runs, in milliseconds (as Search.tsx). */
const DEBOUNCE_MS = 300;

/** The dialogue's props. */
export type MoveDialogProps = {
    labels: Labels,
    tree: Tree,
    // The competencies to move: one from a node's menu, the topmost picked from the bulk dock.
    subjects: StructureNode[],
    frameworkid: number,
    frameworkname: string,
    rtl: boolean,
    pagesize: number,
    // Whether the map is drawn: "Pick on the map" is offered only then.
    canpick: boolean,
    // Load a parent's first page, or its next one, into the workspace's store.
    loadBranch: (id: number, more: boolean) => Promise<void>,
    // A parent as it is now on the server, null for the top level or one gone.
    freshParent: (id: number) => Promise<MoveParent | null>,
    onPick: () => void,
    onCommit: (target: number) => void,
    onClose: () => void,
    onLostFocus: () => void,
    // Told the parent chosen, null for none, so the workspace marks what the move touches (an impact preview).
    onTarget?: (target: number | null) => void,
};

/** The chosen parent: ROOT for the top level. */
type Target = {id: number, name: string};

/**
 * The chevron of a parent row: down when open, towards the children's side when closed.
 *
 * @param {boolean} expanded Whether the row is open.
 * @param {boolean} rtl Whether the page is written right to left.
 * @returns {string} The Font Awesome class.
 */
const chevron = (expanded: boolean, rtl: boolean): string => {
    if (expanded) {
        return 'fa-chevron-down';
    }
    return rtl ? 'fa-chevron-left' : 'fa-chevron-right';
};

/**
 * The class of a choice row, written whole for the stylesheet's test.
 *
 * @param {boolean} chosen Whether it is the chosen parent.
 * @returns {string} The classes.
 */
const choiceClass = (chosen: boolean): string => (chosen
    ? 'local-dimensions-hubv2-pick local-dimensions-hubv2-pick--chosen'
    : 'local-dimensions-hubv2-pick');

/**
 * The dialogue's title: the competency's name, or how many the bulk dock moves.
 *
 * @param {object} labels The labels.
 * @param {Array} subjects The competencies to move.
 * @returns {string} The title.
 */
const titleOf = (labels: Labels, subjects: StructureNode[]): string => (subjects.length > 1
    ? fillCount(labels, 'hubv2_bulk_move_title', subjects.length)
    : fill(labels.hubv2_moveto_title, subjects[0].shortname));

/**
 * The commit button's words: the target named once chosen, with the number the bulk dock moves.
 *
 * @param {object} labels The labels.
 * @param {object|null} target The chosen parent.
 * @param {boolean} bulk Whether several competencies move.
 * @param {number} count How many will move.
 * @returns {string} The words.
 */
const commitLabelOf = (labels: Labels, target: Target | null, bulk: boolean, count: number): string => {
    if (!target) {
        return labels.hubv2_move;
    }
    if (bulk) {
        return target.id === ROOT
            ? fillCount(labels, 'hubv2_bulk_move_commitroot', count)
            : fillObject(labels.hubv2_bulk_move_commit, {count: String(count), name: target.name});
    }
    return target.id === ROOT ? labels.hubv2_moveto_commitroot : fill(labels.hubv2_moveto_commit, target.name);
};

/**
 * What the bulk dock's move says above the picker: the first five names, and once a parent is chosen how many
 * already under it are skipped. Nothing for one competency.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The lines, or nothing.
 */
const BulkLines = (props: {labels: Labels, subjects: StructureNode[], skipped: number}): ReactNode => {
    const {labels, subjects, skipped} = props;
    if (subjects.length < 2) {
        return null;
    }
    const names = preview(subjects.map((subject) => subject.shortname));
    return (
        <>
            <ul className="local-dimensions-hubv2-bulknames">
                {names.shown.map((name, index) => <li key={`${index}-${name}`}>{name}</li>)}
                {names.more > 0 ? <li>{fill(labels.hubv2_bulk_more, String(names.more))}</li> : null}
            </ul>
            {skipped > 0 ? (
                <p className="local-dimensions-hubv2-dialog-label">{fillCount(labels, 'hubv2_bulk_move_skipped', skipped)}</p>
            ) : null}
        </>
    );
};

/**
 * The dialogue.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The dialogue.
 */
const MoveDialog = (props: MoveDialogProps): ReactNode => {
    const {labels, tree, subjects, frameworkid, frameworkname, rtl, loadBranch, freshParent, onPick, onCommit, onClose,
        onLostFocus, onTarget} = props;
    const bulk = subjects.length > 1;
    const canpick = props.canpick && !bulk;
    const ids = useMemo(() => subjects.map((subject) => subject.id), [subjects]);
    const currentparent = sharedParent(tree, ids);
    const [open, setOpen] = useState<number[]>([]);
    const [active, setActive] = useState<string>(FRAMEWORK_ID);
    const [target, setTarget] = useState<Target | null>(null);
    const [notes, setNotes] = useState<string[]>([]);
    const [query, setQuery] = useState('');
    const [hits, setHits] = useState<{items: PathItem[], total: number} | null>(null);
    const [activehit, setActivehit] = useState(0);
    const field = useRef<HTMLInputElement>(null);
    const treeRef = useRef<HTMLUListElement>(null);
    const hitsRef = useRef<HTMLUListElement>(null);
    const asked = useRef(0);
    const noted = useRef(0);
    const id = useId();
    const titleid = `${id}-title`;
    const topname = fill(labels.hubv2_moveto_top, frameworkname);

    // The workspace marks the old and the new parent while a new one is chosen.
    const targetid = target ? target.id : null;
    useEffect(() => {
        onTarget?.(targetid);
    }, [targetid, onTarget]);

    const choices = useMemo(() => parentChoices(tree, open, ids, topname), [tree, open, ids, topname]);
    const order = useMemo(() => navOrder(pickerTree(tree, open, ids)), [tree, open, ids]);
    // Which of the subjects a chosen parent would move, and which it already holds.
    const plan = target ? movePlan(tree, ids, target.id) : {move: ids, skipped: []};
    // The parents the moved ones leave, each once, as a key the notes' effect can compare.
    const leaving = [...new Set(plan.move.map((subject) => tree.nodes[subject]?.parentid ?? ROOT))].join(',');
    const searching = searchable(query);

    useEffect(() => {
        const text = query.trim();
        if (!searchable(text)) {
            asked.current++;
            setHits(null);
            return undefined;
        }
        const timer = window.setTimeout(() => {
            const ask = ++asked.current;
            track('moveto', search(frameworkid, text))
                .then((answer) => {
                    if (ask === asked.current) {
                        setHits(answer);
                        setActivehit(0);
                    }
                    return null;
                })
                .catch(notifyError);
        }, DEBOUNCE_MS);
        return () => window.clearTimeout(timer);
    }, [query, frameworkid]);

    // Once a parent is chosen, the parents are read afresh: whether each has a rule is what the note says. Several
    // subjects may leave several parents; each is named once.
    useEffect(() => {
        const ask = ++noted.current;
        if (!target) {
            setNotes([]);
            return;
        }
        const parents = leaving === '' ? [] : leaving.split(',').map(Number);
        track('moveto', Promise.all([Promise.all(parents.map(freshParent)), freshParent(target.id)]))
            .then(([oldparents, newparent]) => {
                if (ask === noted.current) {
                    const notes = oldparents.flatMap((oldparent) => ruleNotes(labels, oldparent, null));
                    setNotes([...new Set([...notes, ...ruleNotes(labels, null, newparent)])]);
                }
                return null;
            })
            .catch(notifyError);
    }, [target, leaving, freshParent, labels]);

    /**
     * Focus a row of the tree once it is drawn.
     *
     * @param {string} key The row's key.
     * @returns {void}
     */
    const focusRow = (key: string): void => {
        setActive(key);
        window.requestAnimationFrame(() => {
            treeRef.current?.querySelector<HTMLElement>(`[data-key="${key}"]`)?.focus();
        });
    };

    /**
     * Open a branch of the dialogue's tree, loading it first when the store does not hold it.
     *
     * @param {number} parent The competency.
     * @returns {void}
     */
    const openBranch = (parent: number): void => {
        const loaded = tree.branches[parent] !== undefined ? Promise.resolve() : loadBranch(parent, false);
        track('moveto', loaded.then(() => setOpen((current) => (current.includes(parent) ? current : [...current, parent]))))
            .catch(notifyError);
    };

    /**
     * Choose a row as the new parent, when it can be one.
     *
     * @param {object} row The row.
     * @returns {void}
     */
    const chooseRow = (row: ParentChoice): void => {
        if (row.kind === 'more') {
            track('moveto', loadBranch(row.id, true)).catch(notifyError);
            return;
        }
        setActive(row.key);
        if (!row.disabled) {
            setTarget({id: row.id, name: row.kind === 'framework' ? topname : row.name});
        }
    };

    /**
     * The tree's keys (keynav.keyAction over the dialogue's own walk).
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    const onTreeKey = (event: KeyboardEvent<HTMLUListElement>): void => {
        const action = keyAction(order, active, event, rtl);
        if (!action) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        switch (action.type) {
            case 'focus':
                focusRow(action.key);
                break;
            case 'expand':
                openBranch(action.id);
                break;
            case 'collapse':
                setOpen((current) => current.filter((other) => other !== action.id));
                break;
            case 'select': {
                const row = choices.find((choice) => choice.key === active);
                if (row) {
                    chooseRow(row);
                }
                break;
            }
            case 'loadmore':
                track('moveto', loadBranch(action.parent, true)).catch(notifyError);
                break;
            default:
                break;
        }
    };

    /**
     * The hits' keys: Up and Down move, Home and End go to the ends, Enter and Space choose.
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    const onHitsKey = (event: KeyboardEvent<HTMLUListElement>): void => {
        const items = hits?.items ?? [];
        const moves: Record<string, number> = {
            ArrowDown: Math.min(activehit + 1, items.length - 1),
            ArrowUp: Math.max(activehit - 1, 0),
            Home: 0,
            End: items.length - 1,
        };
        if (event.key in moves) {
            event.preventDefault();
            event.stopPropagation();
            const next = moves[event.key];
            setActivehit(next);
            window.requestAnimationFrame(() => hitsRef.current?.querySelectorAll<HTMLElement>('[role="option"]')[next]?.focus());
        } else if ((event.key === 'Enter' || event.key === ' ') && items[activehit]) {
            event.preventDefault();
            event.stopPropagation();
            chooseHit(items[activehit]);
        }
    };

    /**
     * Choose a hit as the new parent, when it can be one.
     *
     * @param {object} item The hit.
     * @returns {void}
     */
    const chooseHit = (item: PathItem): void => {
        if (hitSelectable(item, ids, currentparent)) {
            setTarget({id: item.id, name: item.shortname});
        }
    };

    const commitlabel = commitLabelOf(labels, target, bulk, plan.move.length);
    const activekey = choices.some((choice) => choice.key === active) ? active : FRAMEWORK_ID;

    return (
        <Dialog labelledby={titleid} className="local-dimensions-hubv2-dialog local-dimensions-hubv2-movedialog"
            initialFocus={field} returnTo="item" onClose={onClose} onLostFocus={onLostFocus}>
            <div className="local-dimensions-hubv2-dialog-head">
                <h2 id={titleid} className="local-dimensions-hubv2-dialog-title">
                    {titleOf(labels, subjects)}
                </h2>
                <button type="button" className="local-dimensions-hubv2-iconbtn" aria-label={labels['core:closebuttontitle']}
                    onClick={onClose}>
                    <i className="fa fa-xmark" aria-hidden="true" />
                </button>
            </div>
            <div className="local-dimensions-hubv2-dialog-body">
                <BulkLines labels={labels} subjects={subjects} skipped={target ? plan.skipped.length : 0} />
                <label className="visually-hidden" htmlFor={`${id}-search`}>{labels.hubv2_moveto_search}</label>
                <input
                    ref={field}
                    id={`${id}-search`}
                    type="search"
                    className="form-control form-control-sm"
                    placeholder={labels.hubv2_moveto_search}
                    autoComplete="off"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    onKeyDown={(event) => {
                        // Enter in the field searches; it never commits the move.
                        if (event.key === 'Enter') {
                            event.preventDefault();
                        }
                    }}
                />
                <p id={`${id}-parent`} className="local-dimensions-hubv2-dialog-label">{labels.hubv2_moveto_parent}</p>
                {searching ? (
                    <>
                        <div className="visually-hidden" aria-live="polite">
                            {hits !== null ? fillCount(labels, 'hubv2_searchresults', hits.total) : ''}
                        </div>
                        {hits !== null && hits.items.length === 0 ? (
                            <p className="text-muted small mb-0">{labels.hubv2_searchnone}</p>
                        ) : null}
                        <ul ref={hitsRef} className="local-dimensions-hubv2-picklist" role="listbox"
                            aria-labelledby={`${id}-parent`} onKeyDown={onHitsKey}>
                            {(hits?.items ?? []).map((item, index) => {
                                const selectable = hitSelectable(item, ids, currentparent);
                                return (
                                    <li
                                        key={item.id}
                                        className={choiceClass(target?.id === item.id)}
                                        role="option"
                                        tabIndex={index === activehit ? 0 : -1}
                                        aria-selected={target?.id === item.id}
                                        aria-disabled={selectable ? undefined : true}
                                        onClick={() => {
                                            setActivehit(index);
                                            chooseHit(item);
                                        }}
                                    >
                                        <span className="local-dimensions-hubv2-pick-name">{item.shortname}</span>
                                        {item.path ? <span className="local-dimensions-hubv2-pick-note">{item.path}</span> : null}
                                    </li>
                                );
                            })}
                        </ul>
                    </>
                ) : (
                    <ul ref={treeRef} className="local-dimensions-hubv2-picklist" role="tree" aria-labelledby={`${id}-parent`}
                        onKeyDown={onTreeKey}>
                        {choices.map((row) => (
                            <li
                                key={row.key}
                                data-key={row.key}
                                className={choiceClass(target !== null && row.kind !== 'more' && target.id === row.id)}
                                role="treeitem"
                                aria-level={row.level}
                                aria-setsize={row.setsize}
                                aria-posinset={row.posinset}
                                aria-expanded={row.expandable ? row.expanded : undefined}
                                aria-selected={row.kind === 'more' ? undefined : target !== null && target.id === row.id}
                                aria-disabled={row.disabled ? true : undefined}
                                tabIndex={row.key === activekey ? 0 : -1}
                                style={{paddingInlineStart: `${0.25 + (row.level - 1) * 1.25}rem`}}
                                onClick={() => chooseRow(row)}
                            >
                                {row.expandable ? (
                                    <span
                                        className="local-dimensions-hubv2-otoggle local-dimensions-hubv2-otoggle-on"
                                        aria-hidden="true"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            if (row.expanded) {
                                                setOpen((current) => current.filter((other) => other !== row.id));
                                            } else {
                                                openBranch(row.id);
                                            }
                                        }}>
                                        <i className={`fa ${chevron(row.expanded, rtl)}`} />
                                    </span>
                                ) : <span className="local-dimensions-hubv2-otoggle" aria-hidden="true" />}
                                <span className="local-dimensions-hubv2-pick-name">
                                    {row.kind === 'more' ? fill(labels.hubv2_loadmore, String(row.remaining)) : row.name}
                                </span>
                                {row.current ? (
                                    <span className="local-dimensions-hubv2-pick-note">{labels.hubv2_moveto_current}</span>
                                ) : null}
                                {row.kind === 'competency' && ids.includes(row.id) ? (
                                    <span className="local-dimensions-hubv2-pick-note">
                                        {bulk ? labels.hubv2_bulk_moveto_picked : labels.hubv2_moveto_self}
                                    </span>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
                {notes.length > 0 ? (
                    <div className="local-dimensions-hubv2-dialog-note">
                        <i className="fa fa-circle-info" aria-hidden="true" />
                        <div>{notes.map((note) => <p key={note} className="mb-0">{note}</p>)}</div>
                    </div>
                ) : null}
            </div>
            <div className="local-dimensions-hubv2-dialog-foot">
                {canpick ? (
                    <button type="button" className="btn btn-sm btn-outline-secondary me-auto" onClick={onPick}>
                        {labels.hubv2_moveto_pick}
                    </button>
                ) : null}
                <button type="button" className="btn btn-sm btn-secondary" onClick={onClose}>{labels['core:cancel']}</button>
                <button type="button" className="btn btn-sm btn-primary" disabled={!target || plan.move.length === 0}
                    onClick={() => {
                        if (target) {
                            onCommit(target.id);
                        }
                    }}>
                    {commitlabel}
                </button>
            </div>
        </Dialog>
    );
};

export default MoveDialog;
