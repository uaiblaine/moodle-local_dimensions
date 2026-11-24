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
 * The workspace's modal dialogue: the command palette's and "Move to"'s frame.
 *
 * Labelled by its title, modal, the focus kept inside (Tab and Shift+Tab wrap), Escape or a press on
 * the veil around it closes it, and the focus goes back to the element that had it when the dialogue
 * opened (the fleet Design System's dialog variant, panel-popover-sheet.md). When that element has left
 * the page meanwhile (a view switched from the palette), or when the dialogue was opened by another
 * layer that has closed (returnTo 'item'), onLostFocus decides where the focus goes (focusreturn.ts).
 *
 * Drawn on the page's body, over everything, as core's dialogues are; React still delivers its events
 * through the workspace.
 *
 * @module     local_dimensions/hubv2/Dialog
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef} from 'react';
import type {KeyboardEvent, MouseEvent, ReactNode, RefObject} from 'react';
import {createPortal} from 'react-dom';

/** What can take the focus inside the dialogue, in the page's order. */
const FOCUSABLE = 'button:not([disabled]), input:not([disabled]), [tabindex="0"], a[href]';

/** The dialogue's props. */
export type DialogProps = {
    // The id of the element whose text names the dialogue.
    labelledby: string,
    // The class of the dialogue's box, written whole by the caller.
    className: string,
    // The element that takes the focus when the dialogue opens.
    initialFocus: RefObject<HTMLElement | null>,
    // Where the focus goes on close: the element that had it at the opening, or straight to onLostFocus.
    returnTo: 'opener' | 'item',
    onClose: () => void,
    onLostFocus: () => void,
    children: ReactNode,
};

/**
 * The dialogue.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The dialogue, in a portal.
 */
const Dialog = ({labelledby, className, initialFocus, returnTo, onClose, onLostFocus, children}: DialogProps): ReactNode => {
    const box = useRef<HTMLDivElement>(null);
    const latest = useRef({onLostFocus, returnTo});
    latest.current = {onLostFocus, returnTo};

    // The focus enters at the caller's element and returns, on close, to where it was.
    useEffect(() => {
        const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        initialFocus.current?.focus();
        return () => {
            if (latest.current.returnTo === 'opener' && opener && opener !== document.body && opener.isConnected) {
                opener.focus({preventScroll: true});
            } else {
                latest.current.onLostFocus();
            }
        };
    }, [initialFocus]);

    /**
     * Escape closes; Tab and Shift+Tab wrap inside the dialogue.
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            onClose();
            return;
        }
        if (event.key !== 'Tab' || !box.current) {
            return;
        }
        const focusable = [...box.current.querySelectorAll<HTMLElement>(FOCUSABLE)].filter((element) => !element.hidden);
        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    /**
     * A press on the veil itself, not on the dialogue, closes it.
     *
     * @param {object} event The press.
     * @returns {void}
     */
    const onVeil = (event: MouseEvent<HTMLDivElement>): void => {
        if (event.target === event.currentTarget) {
            onClose();
        }
    };

    return createPortal(
        <div className="local-dimensions-hubv2-scrim" onMouseDown={onVeil}>
            <div ref={box} className={className} role="dialog" aria-modal="true" aria-labelledby={labelledby}
                onKeyDown={onKeyDown}>
                {children}
            </div>
        </div>,
        document.body
    );
};

export default Dialog;
