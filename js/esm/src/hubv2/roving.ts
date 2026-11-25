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
 * One pane's roving tabindex: the item that carries tabindex 0, whether the focus is on one of the
 * pane's items, and moving the focus to an item once it is drawn.
 *
 * The stop moves at once and the focus with it when the item is already in the DOM, so a key
 * repeated faster than React renders still walks one item a press: the next press reads the stop
 * (activeRef), not the element the browser last focused. An item not drawn yet (a page just loaded,
 * a node React Flow places a frame later) is focused on the next frames, under Moodle's pending
 * counter so that Behat waits for it. Where the stop goes after a change is selection.nextStop().
 *
 * @module     local_dimensions/hubv2/roving
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useRef, useState} from 'react';
import type {FocusEvent, RefObject} from 'react';
import {FRAMEWORK_ID} from './graph';
import {entryKey} from './keynav';
import type {NavItem} from './keynav';
import {notifyError} from './amd';
import {track} from './pending';
import {nextStop} from './selection';
import type {Tree} from './tree';

/** How many frames an item not drawn yet is waited for. */
const FRAMES = 30;

/** What the hook needs from its pane. */
export type RovingOptions = {
    order: NavItem[],
    tree: Tree,
    selected: number | null,
    // The pane's element: the focus is inside the pane while it is on one of its items.
    container: RefObject<HTMLElement | null>,
    // The CSS selector of an item.
    item: string,
    // An item's key, read from its element.
    keyOf: (element: HTMLElement) => string | undefined,
    // An item's element, or null while it is not drawn.
    find: (key: string) => HTMLElement | null,
    // Called just before an item takes the focus, with the caller's flag (the map pans with it).
    beforeFocus?: (key: string, flag: boolean) => void,
};

/** The hook's state and handlers. */
export type Roving = {
    active: string,
    activeRef: {current: string},
    inside: {current: boolean},
    focusKey: (key: string, flag?: boolean) => void,
    onFocus: (event: FocusEvent<HTMLElement>) => void,
    onBlur: (event: FocusEvent<HTMLElement>) => void,
};

/**
 * One pane's roving tabindex.
 *
 * @param {object} options The pane.
 * @returns {object} The stop and its handlers.
 */
export const useRoving = (options: RovingOptions): Roving => {
    const {order, tree, selected} = options;
    const [active, setActive] = useState<string>(FRAMEWORK_ID);
    const activeRef = useRef<string>(FRAMEWORK_ID);
    const inside = useRef(false);
    const previous = useRef<NavItem[]>(order);
    const latest = useRef(options);
    latest.current = options;
    const frame = useRef(0);

    const makeActive = useCallback((key: string): void => {
        if (activeRef.current !== key) {
            activeRef.current = key;
            setActive(key);
        }
    }, []);

    const focusKey = useCallback((key: string, flag: boolean = true): void => {
        makeActive(key);
        window.cancelAnimationFrame(frame.current);
        /**
         * Focus the item if it is drawn and still the stop.
         *
         * @returns {boolean} Whether there is nothing left to wait for.
         */
        const attempt = (): boolean => {
            if (activeRef.current !== key) {
                return true;
            }
            const element = latest.current.find(key);
            if (!element) {
                return false;
            }
            latest.current.beforeFocus?.(key, flag);
            element.focus({preventScroll: true});
            return true;
        };
        if (attempt()) {
            return;
        }
        track('focus', new Promise<void>((resolve) => {
            let frames = 0;
            /**
             * Try again on the next frame, a bounded number of times.
             *
             * @returns {void}
             */
            const retry = (): void => {
                if (attempt() || ++frames >= FRAMES) {
                    resolve();
                    return;
                }
                frame.current = window.requestAnimationFrame(retry);
            };
            frame.current = window.requestAnimationFrame(retry);
        })).catch(notifyError);
    }, [makeActive]);

    // After every change of the tree, the walk or the selection: the stop follows the selection while
    // the focus is elsewhere, and the focus follows its item to the nearest survivor when it is gone.
    useEffect(() => {
        const before = previous.current;
        previous.current = order;
        const stop = nextStop(before, order, tree, selected, activeRef.current, inside.current);
        if (stop.refocus) {
            const focused = document.activeElement;
            const container = latest.current.container.current;
            // A focused item that was removed leaves the focus on the body; take it back only then.
            if (!focused || focused === document.body || (container && container.contains(focused))) {
                focusKey(stop.key, true);
                return;
            }
            inside.current = false;
        }
        makeActive(stop.key);
    }, [order, tree, selected, focusKey, makeActive]);

    useEffect(() => () => window.cancelAnimationFrame(frame.current), []);

    const onFocus = useCallback((event: FocusEvent<HTMLElement>): void => {
        const element = (event.target as HTMLElement).closest<HTMLElement>(latest.current.item);
        if (!element || !event.currentTarget.contains(element)) {
            return;
        }
        inside.current = true;
        const key = latest.current.keyOf(element);
        if (key !== undefined) {
            makeActive(key);
        }
    }, [makeActive]);

    const onBlur = useCallback((event: FocusEvent<HTMLElement>): void => {
        const next = event.relatedTarget as HTMLElement | null;
        // No next element: the window lost the focus, or the focused item was removed (the effect above).
        if (next === null) {
            return;
        }
        if (event.currentTarget.contains(next) && next.closest(latest.current.item)) {
            return;
        }
        inside.current = false;
        const {tree: current, order: walk, selected: chosen} = latest.current;
        makeActive(entryKey(current, walk, chosen));
    }, [makeActive]);

    return {active, activeRef, inside, focusKey, onFocus, onBlur};
};
