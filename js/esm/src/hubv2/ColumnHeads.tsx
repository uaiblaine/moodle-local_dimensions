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
 * The map's column headings: above each column of competencies, its level and the taxonomy term the framework
 * gives that level ("Level 2: Competency").
 *
 * Quiet text in a strip along the canvas's top edge, inside the canvas, under its controls. The strip follows
 * the map sideways (its headings move and scale with the view), not up and down, and the map's fitted view
 * leaves the strip's height free above the cards, so a heading never covers a card at rest. The strip is
 * drawing for the eye: hidden from assistive technology, which reads the levels once, in the canvas's
 * description, and never a Tab stop.
 *
 * The level names are the framework's, so a manager's way to them is one control, named for what it does, above
 * the framework's own column: it opens the framework's form, where the taxonomies are edited. It is for the
 * pointer only: the root's "Edit structure" is the keyboard's way to the same form. A heading carries no control
 * of its own: one beside each term read as a button named after the term, once per column.
 *
 * @module     local_dimensions/hubv2/ColumnHeads
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from 'react';
import type {MouseEvent, ReactNode} from 'react';
import {headWords} from './columns';
import type {ColumnHead} from './columns';
import {fill} from './strings';
import type {Labels} from './strings';

/** The map's view: its pan and its zoom. */
export type Viewport = {x: number, y: number, zoom: number};

/** Hand a listener the view on every move; the returned function stops it. */
export type ViewportSubscribe = (listener: (viewport: Viewport) => void) => () => void;

/** The strip's props. */
export type ColumnHeadsProps = {
    labels: Labels,
    heads: ColumnHead[],
    // A card's width in the map's coordinates: a heading is as wide as its column's cards.
    cardwidth: number,
    // The view now, and every later one.
    read: () => Viewport,
    subscribe: ViewportSubscribe,
    // The id of the levels said in words, which the canvas names as its description.
    describedby: string,
    // Whether the viewer may edit the framework (a manager); opening its form.
    canedit: boolean,
    onEdit: () => void,
};

/**
 * The levels in words, level 1 first, as the canvas's description says them once.
 *
 * @param {object} labels The labels.
 * @param {Array} heads The headings.
 * @returns {string} The words.
 */
export const levelsSaid = (labels: Labels, heads: ColumnHead[]): string => fill(labels.hubv2_columns, [...heads]
    .sort((one, other) => one.level - other.level)
    .map((head) => headWords(labels.hubv2_fw_level, head))
    .join('; '));

/**
 * The strip of headings.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The strip, or nothing without a level drawn.
 */
const ColumnHeads = ({labels, heads, cardwidth, read, subscribe, describedby, canedit, onEdit}: ColumnHeadsProps): ReactNode => {
    const [viewport, setViewport] = useState<Viewport>(read);

    // Every move of the map moves the headings; the first frame after mount catches a fitted view that moved
    // before the subscription.
    useEffect(() => {
        const stop = subscribe(setViewport);
        const frame = window.requestAnimationFrame(() => setViewport(read()));
        return () => {
            stop();
            window.cancelAnimationFrame(frame);
        };
    }, [read, subscribe]);

    if (heads.length === 0) {
        return null;
    }
    return (
        <>
            <p id={describedby} className="visually-hidden">{levelsSaid(labels, heads)}</p>
            <div className="local-dimensions-hubv2-colheads" aria-hidden="true">
                {canedit ? (
                    // The framework's column is column 0 in both directions (columns.ts): its heading is the way to
                    // the framework's level names.
                    <div
                        className="local-dimensions-hubv2-colhead"
                        style={{left: `${viewport.x}px`, width: `${cardwidth * viewport.zoom}px`}}
                    >
                        <button
                            type="button"
                            className="local-dimensions-hubv2-iconbtn local-dimensions-hubv2-colhead-edit"
                            tabIndex={-1}
                            onClick={(event: MouseEvent<HTMLButtonElement>) => {
                                event.stopPropagation();
                                onEdit();
                            }}
                        >
                            <i className="fa fa-pen" aria-hidden="true" />
                            <span className="local-dimensions-hubv2-colhead-editlabel">{labels.hubv2_column_edit}</span>
                        </button>
                    </div>
                ) : null}
                {heads.map((head) => (
                    <div
                        key={head.level}
                        className="local-dimensions-hubv2-colhead"
                        style={{left: `${viewport.x + head.x * viewport.zoom}px`, width: `${cardwidth * viewport.zoom}px`}}
                    >
                        <span className="local-dimensions-hubv2-colhead-term">{headWords(labels.hubv2_fw_level, head)}</span>
                    </div>
                ))}
            </div>
        </>
    );
};

export default ColumnHeads;
