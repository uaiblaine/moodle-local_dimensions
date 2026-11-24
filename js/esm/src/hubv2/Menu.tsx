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
 * A menu of the workspace: a node's actions, or the outline's display options.
 *
 * The fleet Design System's menu (menu.md): an icon before every action, a check box before every
 * on/off choice, separators between groups, the destructive item last, shortcuts at the line's end.
 * The ARIA Menu pattern: role menu, one item holding the tab stop, the keys of menukeys.ts, Escape, Tab or
 * a press outside closes it and gives the focus back to what opened it (the caller's onClose).
 *
 * It is drawn in a layer of its own on the page's body, fixed to the viewport under its anchor's real
 * bottom (menukeys.placeMenu), so neither the map nor the outline, both of which clip what overflows them,
 * can cut it. React still delivers its events through the workspace.
 *
 * @module     local_dimensions/hubv2/Menu
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Fragment, useCallback, useEffect, useLayoutEffect, useRef, useState} from 'react';
import type {CSSProperties, KeyboardEvent, ReactNode} from 'react';
import {createPortal} from 'react-dom';
import {ariaShortcut, shortcutCaps} from './commands';
import type {Shortcut} from './commands';
import {firstActive, menuKey, placeMenu} from './menukeys';
import type {Box} from './menukeys';

/** How long a pause ends a type-ahead query, in milliseconds (as the outline's). */
const TYPE_AHEAD_PAUSE = 500;

/** One item of a menu. */
export type MenuItem = {
    id: string,
    label: string,
    // A Font Awesome class; an on/off choice draws its check box instead.
    icon?: string,
    shortcut?: Shortcut,
    disabled?: boolean,
    danger?: boolean,
    // Whether a separator comes before the item.
    separator?: boolean,
    // An on/off choice: a press toggles it and keeps the menu open.
    checkbox?: boolean,
    checked?: boolean,
};

/** The menu's props. */
export type MenuProps = {
    // The menu's accessible name.
    label: string,
    items: MenuItem[],
    // The element the menu hangs from.
    anchor: HTMLElement,
    // The anchor's pane, whose bottom decides whether the menu goes above.
    pane: HTMLElement | null,
    rtl: boolean,
    mac: boolean,
    onRun: (id: string) => void,
    // The menu is closing, by a key or by a press outside it (true); the caller gives the focus back.
    onClose: (outside: boolean) => void,
};

/**
 * An element's box in viewport pixels.
 *
 * @param {HTMLElement} element The element.
 * @returns {object} Its box.
 */
const boxOf = (element: HTMLElement): Box => {
    const rect = element.getBoundingClientRect();
    return {left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom};
};

/**
 * The class of an item, written whole for the stylesheet's test.
 *
 * @param {object} item The item.
 * @returns {string} The classes.
 */
const itemClass = (item: MenuItem): string => (item.danger
    ? 'local-dimensions-hubv2-menu-item local-dimensions-hubv2-menu-item--danger'
    : 'local-dimensions-hubv2-menu-item');

/**
 * The menu.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The menu, in a portal.
 */
const Menu = ({label, items, anchor, pane, rtl, mac, onRun, onClose}: MenuProps): ReactNode => {
    const [active, setActive] = useState(() => firstActive(items.map((item) => ({label: item.label, disabled: !!item.disabled}))));
    // Placed before the first paint (the layout effect below); never hidden meanwhile, since a hidden item
    // cannot take the focus the menu gives its first item on opening.
    const [place, setPlace] = useState<CSSProperties>({left: 0, top: 0});
    const box = useRef<HTMLDivElement>(null);
    const buttons = useRef<(HTMLButtonElement | null)[]>([]);
    const typed = useRef({text: '', at: 0});
    const latest = useRef({onClose, anchor, pane, rtl});
    latest.current = {onClose, anchor, pane, rtl};

    /**
     * Put the menu under its anchor, or above it; it closes when its anchor has left the page.
     *
     * @returns {void}
     */
    const position = useCallback((): void => {
        const {anchor: from, pane: around, rtl: backwards} = latest.current;
        const element = box.current;
        if (!element) {
            return;
        }
        if (!from.isConnected) {
            latest.current.onClose(false);
            return;
        }
        const placement = placeMenu(
            boxOf(from),
            {width: element.offsetWidth, height: element.offsetHeight},
            {width: window.innerWidth, height: window.innerHeight},
            around ? boxOf(around) : null,
            backwards
        );
        setPlace({left: placement.left, top: placement.top});
    }, []);

    useLayoutEffect(() => {
        position();
    }, [position, items.length]);

    // The view moving under the menu (a scroll, a resize) moves the menu with its anchor.
    useEffect(() => {
        window.addEventListener('resize', position);
        window.addEventListener('scroll', position, true);
        return () => {
            window.removeEventListener('resize', position);
            window.removeEventListener('scroll', position, true);
        };
    }, [position]);

    // A press outside the menu closes it; a press on its anchor is the anchor's own toggle.
    useEffect(() => {
        /**
         * Close on a press outside.
         *
         * @param {object} event The pointer event.
         * @returns {void}
         */
        const onPointer = (event: PointerEvent): void => {
            const target = event.target as Node | null;
            if (target && (box.current?.contains(target) || latest.current.anchor.contains(target))) {
                return;
            }
            latest.current.onClose(true);
        };
        document.addEventListener('pointerdown', onPointer, true);
        return () => document.removeEventListener('pointerdown', onPointer, true);
    }, []);

    // The active item holds the focus.
    useEffect(() => {
        if (active >= 0) {
            buttons.current[active]?.focus({preventScroll: true});
        }
    }, [active]);

    /**
     * The menu's keys (menukeys.menuKey).
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        let query = '';
        if (event.key.length === 1 && event.key !== ' ') {
            const previous = typed.current;
            query = event.timeStamp - previous.at > TYPE_AHEAD_PAUSE ? event.key : previous.text + event.key;
            typed.current = {text: query, at: event.timeStamp};
        }
        const step = menuKey(items.map((item) => ({label: item.label, disabled: !!item.disabled})), active, event, query);
        if (!step.handled) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        if (step.close) {
            onClose(false);
            return;
        }
        if (step.run !== null) {
            onRun(items[step.run].id);
            return;
        }
        setActive(step.active);
    };

    return createPortal(
        <div
            ref={box}
            className="local-dimensions-hubv2-menu"
            role="menu"
            aria-label={label}
            style={place}
            onKeyDown={onKeyDown}
        >
            {items.map((item, index) => (
                <Fragment key={item.id}>
                    {item.separator ? <div className="local-dimensions-hubv2-menu-sep" role="separator" /> : null}
                    <button
                        ref={(element) => {
                            buttons.current[index] = element;
                        }}
                        type="button"
                        className={itemClass(item)}
                        role={item.checkbox ? 'menuitemcheckbox' : 'menuitem'}
                        tabIndex={index === active ? 0 : -1}
                        aria-checked={item.checkbox ? !!item.checked : undefined}
                        aria-disabled={item.disabled ? true : undefined}
                        aria-keyshortcuts={item.shortcut ? ariaShortcut(item.shortcut, mac) : undefined}
                        onClick={() => {
                            setActive(index);
                            if (!item.disabled) {
                                onRun(item.id);
                            }
                        }}
                    >
                        {item.checkbox ? (
                            <span className="local-dimensions-hubv2-menu-box" aria-hidden="true">
                                <i className="fa fa-check" />
                            </span>
                        ) : <i className={`fa ${item.icon ?? 'fa-circle'} local-dimensions-hubv2-menu-icon`} aria-hidden="true" />}
                        <span className="local-dimensions-hubv2-menu-label">{item.label}</span>
                        {item.shortcut ? (
                            <span className="local-dimensions-hubv2-keys" aria-hidden="true">
                                {shortcutCaps(item.shortcut, mac).map((cap) => <kbd key={cap}>{cap}</kbd>)}
                            </span>
                        ) : null}
                    </button>
                </Fragment>
            ))}
        </div>,
        document.body
    );
};

export default Menu;
