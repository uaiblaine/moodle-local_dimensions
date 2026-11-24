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
 * The outline: the workspace's tree as an APG Tree View, beside the map, on the same store.
 *
 * A flat list of treeitems (outlinerows.ts says why) with one Tab stop; the workspace owns the keyboard
 * (keynav.ts, the same key map as the map's, plus type-ahead here), the selection and every action,
 * and this component only draws the rows and reports clicks. A row's click selects it, or picks the
 * new parent in the move mode; its chevron only opens or closes. Each row is named by the competency
 * alone and described by its meta in words (children, rule, related competencies), so the state a
 * screen reader already reads from aria-expanded and aria-level is not said twice.
 *
 * @module     local_dimensions/hubv2/Outline
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef} from 'react';
import type {FocusEvent, KeyboardEvent, MouseEvent, ReactNode, RefObject} from 'react';
import {isHexColour} from './html';
import {rowMeta, scrollToShow} from './outlinerows';
import type {OutlineRow} from './outlinerows';
import {fill, fillCount} from './strings';
import type {Labels} from './strings';
import type {Framework} from './types';

/** The mark's colours by depth, when the competency has no colour of its own; deeper ones take the last. */
const DEPTH_MARKS = [
    'local-dimensions-hubv2-mark-depth1',
    'local-dimensions-hubv2-mark-depth2',
    'local-dimensions-hubv2-mark-depth3',
    'local-dimensions-hubv2-mark-depth4',
];

/** The outline's id, which the splitter controls, and the prefix of its rows' ids; one workspace per page. */
const ID = 'local-dimensions-hubv2-outline';

/** How many grey rows stand for a branch being loaded. */
const SKELETON_ROWS = 3;

/** What the outline reports to the workspace. */
export type OutlineActions = {
    // A row clicked: select it, or pick it as the new parent in the move mode.
    activate: (row: OutlineRow) => void,
    toggle: (id: number) => void,
    loadMore: (parent: number) => void,
    expandAll: () => void,
    collapseAll: () => void,
    add: () => void,
    onKeyDown: (event: KeyboardEvent<HTMLUListElement>) => void,
    onFocus: (event: FocusEvent<HTMLUListElement>) => void,
    onBlur: (event: FocusEvent<HTMLUListElement>) => void,
};

/** The outline's props. */
export type OutlineProps = {
    labels: Labels,
    framework: Framework,
    count: number,
    rows: OutlineRow[],
    selected: number | null,
    moving: number | null,
    // The selected competency's related competencies, once read; the only relations the tree knows.
    relatedcount: number | null,
    // The row that carries tabindex 0.
    active: string,
    // The parents whose first page is loading.
    loading: number[],
    expanding: boolean,
    notice: string,
    rtl: boolean,
    // The size of a page of children, which a "load more" row loads at most.
    pagesize: number,
    listRef: RefObject<HTMLUListElement | null>,
    actions: OutlineActions,
};

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
 * The quiet meta at a row's end (outlinerows.rowMeta), shown short and said in words.
 *
 * On screen a count is the quiet counter, a rule is its icon alone and the related competencies an
 * icon and a number, all hidden from assistive technology; the row's description says each in full.
 *
 * @param {object} labels The labels.
 * @param {object} row The row.
 * @param {number} count The framework's competency count.
 * @param {number|null} related The row's related competencies, when known.
 * @returns {object} The parts to show and the description to say.
 */
const meta = (labels: Labels, row: OutlineRow, count: number, related: number | null): {shown: ReactNode[], said: string} => {
    const shown: ReactNode[] = [];
    const said: string[] = [];
    rowMeta(row, count, related).forEach((part) => {
        if (part.kind === 'count') {
            shown.push(<span key="count" className="local-dimensions-hubv2-ometa-count">{part.value}</span>);
            said.push(fillCount(labels, row.kind === 'framework' ? 'hubv2_competencies' : 'hubv2_children', part.value));
        } else if (part.kind === 'rule') {
            shown.push(<i key="rule" className="fa fa-diagram-project local-dimensions-hubv2-ometa-rule" />);
            said.push(fill(labels.hubv2_a11y_rule, part.label));
        } else {
            shown.push(
                <span key="related" className="local-dimensions-hubv2-ometa-related">
                    <i className="fa fa-share-nodes" />{part.value}
                </span>
            );
            said.push(fillCount(labels, 'hubv2_outline_related', part.value));
        }
    });
    return {shown, said: said.join(', ')};
};

/**
 * One row.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The treeitem.
 */
const Row = (props: {
    row: OutlineRow,
    labels: Labels,
    count: number,
    selected: boolean,
    moving: boolean,
    related: number | null,
    active: boolean,
    rtl: boolean,
    pagesize: number,
    actions: OutlineActions,
}): ReactNode => {
    const {row, labels, count, selected, moving, related, active, rtl, pagesize, actions} = props;
    // The level's indent, written on the start side, so a right-to-left page indents from the right.
    const indent = {paddingInlineStart: `${0.25 + (row.level - 1) * 1.25}rem`};
    const common = {
        'role': 'treeitem',
        'data-key': row.key,
        'aria-level': row.level,
        'aria-setsize': row.setsize,
        'aria-posinset': row.posinset,
        'tabIndex': active ? 0 : -1,
        'style': indent,
    };

    if (row.kind === 'more') {
        const label = fill(labels.hubv2_loadmore, String(Math.min(row.remaining, pagesize)));
        const remaining = fillCount(labels, 'hubv2_outline_remaining', row.remaining);
        // Named on the row itself: a name read from its content would run the button's words into the count's.
        return (
            <li {...common} className="local-dimensions-hubv2-orow local-dimensions-hubv2-orow-more"
                aria-label={`${label}, ${remaining}`} onClick={() => actions.loadMore(row.id)}>
                <span className="local-dimensions-hubv2-otoggle" aria-hidden="true" />
                <button type="button" className="local-dimensions-hubv2-omore" tabIndex={-1} aria-hidden="true"
                    onClick={(event: MouseEvent) => {
                        event.stopPropagation();
                        actions.loadMore(row.id);
                    }}>
                    {label}
                </button>
                <span className="local-dimensions-hubv2-ometa" aria-hidden="true">{remaining}</span>
            </li>
        );
    }

    const {shown, said} = meta(labels, row, count, related);
    const node = row.node;
    let markclass = 'local-dimensions-hubv2-mark local-dimensions-hubv2-mark-framework';
    let markstyle;
    if (node) {
        markstyle = isHexColour(node.bgcolor) ? {backgroundColor: node.bgcolor} : undefined;
        markclass = markstyle
            ? 'local-dimensions-hubv2-mark'
            : `local-dimensions-hubv2-mark ${DEPTH_MARKS[Math.min(row.level - 1, DEPTH_MARKS.length) - 1]}`;
    }
    const classes = ['local-dimensions-hubv2-orow'];
    if (selected) {
        classes.push('local-dimensions-hubv2-orow-selected');
    }
    if (moving) {
        classes.push('local-dimensions-hubv2-orow-moving');
    }
    /**
     * Open or close the row, without selecting it.
     *
     * @param {object} event The click.
     * @returns {void}
     */
    const onChevron = (event: MouseEvent): void => {
        event.stopPropagation();
        actions.toggle(row.id);
    };
    return (
        <li
            {...common}
            className={classes.join(' ')}
            aria-expanded={row.expandable ? row.expanded : undefined}
            // APG single-select tree: aria-selected on the selected item only.
            aria-selected={selected ? true : undefined}
            aria-labelledby={`${ID}-${row.key}-name`}
            aria-describedby={said ? `${ID}-${row.key}-desc` : undefined}
            onClick={() => actions.activate(row)}
        >
            {row.expandable && row.kind === 'competency' ? (
                <span className="local-dimensions-hubv2-otoggle local-dimensions-hubv2-otoggle-on" aria-hidden="true"
                    onClick={onChevron}>
                    <i className={`fa ${chevron(row.expanded, rtl)}`} />
                </span>
            ) : <span className="local-dimensions-hubv2-otoggle" aria-hidden="true" />}
            <span className={markclass} style={markstyle} aria-hidden="true" />
            {/* The browser's title shows a name the row truncates: the Design System's tooltip is for controls
                and glyphs, and the row's overflow would clip its balloon. */}
            <span id={`${ID}-${row.key}-name`} className="local-dimensions-hubv2-oname" title={row.name}>{row.name}</span>
            {shown.length > 0 ? <span className="local-dimensions-hubv2-ometa" aria-hidden="true">{shown}</span> : null}
            {said ? <span id={`${ID}-${row.key}-desc`} className="visually-hidden">{said}</span> : null}
        </li>
    );
};

/**
 * Grey rows standing for a branch being loaded, at its children's indent.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The rows.
 */
const SkeletonRows = ({level, parent}: {level: number, parent: string}): ReactNode => (
    <>
        {Array.from({length: SKELETON_ROWS}, (_, index) => (
            <li key={`${parent}-sk${index}`} role="none" aria-hidden="true"
                className="local-dimensions-hubv2-orow local-dimensions-hubv2-orow-sk local-dimensions-sk"
                style={{paddingInlineStart: `${0.25 + (level - 1) * 1.25}rem`}}>
                <span className="local-dimensions-hubv2-otoggle" />
                <span className="local-dimensions-sk-block local-dimensions-hubv2-sk-mark" />
                <span className="local-dimensions-sk-block local-dimensions-hubv2-sk-line" />
            </li>
        ))}
    </>
);

/**
 * The outline.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The section.
 */
const Outline = (props: OutlineProps): ReactNode => {
    const {labels, framework, count, rows, selected, moving, relatedcount, active, loading, expanding, notice, rtl, pagesize,
        listRef, actions} = props;
    const scrolled = useRef<string | null>(null);

    // Keep the Tab stop's row in sight inside the list, never scrolling the page: the stop follows a
    // selection made on the map or by a search, and moves with the keyboard.
    useEffect(() => {
        const list = listRef.current;
        if (!list || scrolled.current === active) {
            return;
        }
        scrolled.current = active;
        const row = list.querySelector<HTMLElement>(`[data-key="${active}"]`);
        if (!row) {
            return;
        }
        // The list is positioned (styles.css), so a row's offsetTop is measured from the list's top.
        const next = scrollToShow(row.offsetTop, row.offsetHeight, list.scrollTop, list.clientHeight);
        if (next !== null) {
            list.scrollTop = next;
        }
    }, [active, rows, listRef]);

    const empty = count === 0;
    const selectedkey = selected === null ? 'f' : `c${selected}`;
    const movingkey = moving === null ? null : `c${moving}`;
    const items: ReactNode[] = [];
    rows.forEach((row) => {
        items.push(
            <Row
                key={row.key}
                row={row}
                labels={labels}
                count={count}
                selected={row.key === selectedkey}
                moving={row.key === movingkey}
                related={row.key === selectedkey ? relatedcount : null}
                active={row.key === active}
                rtl={rtl}
                pagesize={pagesize}
                actions={actions}
            />
        );
        if (row.kind === 'competency' && !row.expanded && loading.includes(row.id)) {
            items.push(<SkeletonRows key={`${row.key}-loading`} level={row.level + 1} parent={row.key} />);
        }
    });

    return (
        <section id={ID} className="local-dimensions-hubv2-outline" aria-labelledby={`${ID}-title`}>
            <div className="local-dimensions-hubv2-ohead">
                <h3 id={`${ID}-title`} className="local-dimensions-hubv2-otitle">{labels.hubv2_outline}</h3>
                <button
                    type="button"
                    className="local-dimensions-hubv2-iconbtn local-dimensions-tip local-dimensions-tip-bottom"
                    aria-label={labels.managecompetencies_expandall}
                    data-dim-tip={labels.managecompetencies_expandall}
                    disabled={expanding || empty}
                    onClick={actions.expandAll}
                >
                    <i className="fa fa-angles-down" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    className="local-dimensions-hubv2-iconbtn local-dimensions-tip local-dimensions-tip-bottom"
                    aria-label={labels.managecompetencies_collapseall}
                    data-dim-tip={labels.managecompetencies_collapseall}
                    disabled={empty}
                    onClick={actions.collapseAll}
                >
                    <i className="fa fa-angles-up" aria-hidden="true" />
                </button>
            </div>
            {notice ? <p className="local-dimensions-hubv2-onotice">{notice}</p> : null}
            <ul
                ref={listRef}
                className="local-dimensions-hubv2-tree"
                role="tree"
                aria-label={framework.shortname}
                aria-busy={loading.length > 0 || expanding ? true : undefined}
                onKeyDown={actions.onKeyDown}
                onFocus={actions.onFocus}
                onBlur={actions.onBlur}
            >
                {items}
            </ul>
            {loading.length > 0 ? <span className="visually-hidden" role="status">{labels.hubv2_loading}</span> : null}
            {empty ? (
                <div className="local-dimensions-hubv2-oempty">
                    <span className="local-dimensions-hubv2-oempty-icon" aria-hidden="true"><i className="fa fa-sitemap" /></span>
                    <p className="local-dimensions-hubv2-oempty-title">{labels.nocompetencies}</p>
                    {framework.canmanage && moving === null ? (
                        <button type="button" className="btn btn-primary btn-sm" onClick={actions.add}>
                            <i className="fa fa-plus me-1" aria-hidden="true" />{labels.managecompetencies_addcompetency}
                        </button>
                    ) : null}
                </div>
            ) : null}
        </section>
    );
};

export {ID as OUTLINE_ID};

export default Outline;
