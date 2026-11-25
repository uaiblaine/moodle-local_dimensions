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
 * The splitter between the outline and the map in the Both layout: an APG Window Splitter, moved by
 * the arrow keys, Home and End, or a drag; a double click goes back to the default width.
 *
 * The widths and the keys are outlinewidth.ts's; the width lives in the workspace, which hands it to the
 * stylesheet. Below the stacked breakpoint the stylesheet does not draw it.
 *
 * @module     local_dimensions/hubv2/Splitter
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useRef} from 'react';
import type {KeyboardEvent, PointerEvent, ReactNode} from 'react';
import {OUTLINE_WIDTH, dragWidth, keyWidth, upperBound} from './outlinewidth';

/** The splitter's props. */
export type SplitterProps = {
    label: string,
    // The id of the outline the splitter sizes.
    controls: string,
    width: number,
    // The width of the outline, the splitter and the map together, read when it is needed.
    available: () => number,
    rtl: boolean,
    // A new width; final when the key or the drop that made it ends.
    onWidth: (width: number, final: boolean) => void,
    onReset: () => void,
};

/**
 * The splitter.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The separator.
 */
const Splitter = (props: SplitterProps): ReactNode => {
    const {label, controls, width, available, rtl, onWidth, onReset} = props;
    const drag = useRef<{startx: number, start: number, last: number} | null>(null);

    /**
     * Move it by the keyboard.
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }
        const next = keyWidth(event.key, width, available(), rtl);
        if (next === null) {
            return;
        }
        event.preventDefault();
        onWidth(next, true);
    };

    /**
     * Start a drag from the grab point, so the splitter never jumps.
     *
     * @param {object} event The pointer event.
     * @returns {void}
     */
    const onPointerDown = (event: PointerEvent<HTMLDivElement>): void => {
        event.preventDefault();
        drag.current = {startx: event.clientX, start: width, last: width};
        event.currentTarget.setPointerCapture(event.pointerId);
    };

    /**
     * Follow the pointer.
     *
     * @param {object} event The pointer event.
     * @returns {void}
     */
    const onPointerMove = (event: PointerEvent<HTMLDivElement>): void => {
        const state = drag.current;
        if (!state) {
            return;
        }
        state.last = dragWidth(state.start, state.startx, event.clientX, available(), rtl);
        onWidth(state.last, false);
    };

    /**
     * End a drag at the width it last showed: a cancelled pointer's coordinates are no drop point.
     *
     * @returns {void}
     */
    const onPointerEnd = (): void => {
        const state = drag.current;
        if (!state) {
            return;
        }
        drag.current = null;
        onWidth(state.last, true);
    };

    return (
        <div
            className="local-dimensions-hubv2-splitter"
            role="separator"
            aria-orientation="vertical"
            aria-label={label}
            aria-controls={controls}
            aria-valuenow={width}
            aria-valuemin={OUTLINE_WIDTH.minimum}
            aria-valuemax={upperBound(available())}
            tabIndex={0}
            onKeyDown={onKeyDown}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerEnd}
            onPointerCancel={onPointerEnd}
            onLostPointerCapture={onPointerEnd}
            onDoubleClick={onReset}
        >
            <span className="local-dimensions-hubv2-splitter-grip" aria-hidden="true" />
        </div>
    );
};

export default Splitter;
