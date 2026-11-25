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
 * A menu's keyboard and its place on the screen, for Menu.tsx.
 *
 * The keys are the APG Menu pattern's and the fleet Design System's (menu.md): Up and Down move through
 * every item and wrap, Home and End go to the ends, a printable character moves to the next label it
 * starts (keynav.typeAhead), Enter and Space run the item, Escape and Tab close the menu. A disabled
 * item is reached like the others and runs nothing.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/menukeys
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {typeAhead} from './keynav';
import type {KeyPress} from './keynav';

/** One item as the keyboard sees it. */
export type MenuKeyItem = {label: string, disabled: boolean};

/** What a key does to an open menu. */
export type MenuStep = {
    // The item that holds the focus after the key.
    active: number,
    // The item to run, or null.
    run: number | null,
    close: boolean,
    // Whether the key was the menu's: the caller then stops the browser's default.
    handled: boolean,
};

/**
 * The item a menu opens on: the first that can run, else the first.
 *
 * @param {Array} items The items.
 * @returns {number} Its place, -1 for an empty menu.
 */
export const firstActive = (items: MenuKeyItem[]): number => {
    if (items.length === 0) {
        return -1;
    }
    const index = items.findIndex((item) => !item.disabled);
    return index < 0 ? 0 : index;
};

/**
 * What a key does to an open menu.
 *
 * @param {Array} items The items, in order.
 * @param {number} active The item that holds the focus.
 * @param {object} key The key and its modifiers.
 * @param {string} query What was typed since the last pause, this key included, for a printable key.
 * @returns {object} The step.
 */
export const menuKey = (items: MenuKeyItem[], active: number, key: KeyPress, query: string = ''): MenuStep => {
    const stay = {active, run: null, close: false, handled: true};
    const count = items.length;
    if (key.key === 'Escape' || key.key === 'Tab') {
        return {...stay, close: true};
    }
    if (count === 0 || key.altKey || key.ctrlKey || key.metaKey) {
        return {...stay, handled: false};
    }
    switch (key.key) {
        case 'ArrowDown':
            return {...stay, active: (active + 1) % count};
        case 'ArrowUp':
            return {...stay, active: (active - 1 + count) % count};
        case 'Home':
            return {...stay, active: 0};
        case 'End':
            return {...stay, active: count - 1};
        case 'Enter':
        case ' ':
            return items[active] && !items[active].disabled ? {...stay, run: active} : stay;
        default:
            break;
    }
    if (key.key.length === 1 && query !== '') {
        const names = items.map((item, index) => ({key: String(index), name: item.label}));
        const found = typeAhead(names, String(active), query);
        return found === null ? stay : {...stay, active: Number(found)};
    }
    return {...stay, handled: false};
};

/** A box on the screen, in viewport pixels. */
export type Box = {left: number, top: number, right: number, bottom: number};

/** Where a menu is drawn, in viewport pixels, and whether it went above its anchor. */
export type Placement = {left: number, top: number, above: boolean};

/**
 * Where a menu goes: under its anchor's real bottom with its end on the anchor's end (the start, right
 * to left), above the anchor when below would leave the viewport or the anchor's pane, and held inside
 * the viewport either way. The menu is drawn in a layer of its own, so the pane never clips it; the
 * pane's box only decides the side.
 *
 * @param {object} anchor The anchor's box.
 * @param {object} size The menu's size.
 * @param {object} view The viewport's size.
 * @param {object|null} pane The anchor's pane's box, or null.
 * @param {boolean} rtl Whether the page is written right to left.
 * @param {number} gap The space between the anchor and the menu.
 * @param {number} margin How far from the viewport's edges the menu stays.
 * @returns {object} The placement.
 */
export const placeMenu = (
    anchor: Box,
    size: {width: number, height: number},
    view: {width: number, height: number},
    pane: Box | null,
    rtl: boolean,
    gap: number = 6,
    margin: number = 8,
): Placement => {
    const wanted = rtl ? anchor.left : anchor.right - size.width;
    const left = Math.max(margin, Math.min(wanted, view.width - margin - size.width));
    const floor = Math.min(view.height, pane ? pane.bottom : view.height) - margin;
    const ceiling = Math.max(0, pane ? pane.top : 0) + margin;
    const below = anchor.bottom + gap;
    const above = anchor.top - gap - size.height;
    if (below + size.height <= floor) {
        return {left, top: below, above: false};
    }
    if (above >= ceiling) {
        return {left, top: above, above: true};
    }
    // Neither side fits: the roomier one, kept on the screen.
    const roomBelow = view.height - below;
    const roomAbove = anchor.top - gap;
    const top = roomBelow >= roomAbove ? below : above;
    return {left, top: Math.max(margin, Math.min(top, view.height - margin - size.height)), above: roomBelow < roomAbove};
};
