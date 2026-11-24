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
 * The workspace's one list of actions, and the one decision of when each is offered.
 *
 * Every action exists in three places, the panel's buttons, a node's menu and the command palette, and
 * all three render from ACTIONS through actionState(), so none of them can offer what another refuses.
 * The keyboard's shortcuts (shortcutAction) name the same ids and pass through the same decision. The
 * decision is a courtesy: every write goes through a core competency service that checks
 * moodle/competency:competencymanage in the competency's own context, whatever the client offered.
 *
 * The framework is the tree's root: its own actions (the Structures tab's, run by the same AMD functions) have
 * the `framework` scope and are offered on the root alone; creating and importing a framework, which are not
 * about the one shown, have the `structures` scope. Their forms and services check the capability again, in the
 * framework's context or in the context the tab lists.
 *
 * Pure: Jest pins the availability matrix and the key map without a DOM.
 *
 * @module     local_dimensions/hubv2/commands
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Lens} from './insights';
import type {KeyPress} from './keynav';
import type {Layout} from './selection';
import {ROOT} from './tree';
import type {StructureNode, UsageLevel} from './types';

/** Every action of the workspace. */
export type ActionId =
    'edit' | 'rename' | 'addchild' | 'quicksibling' | 'quickchild'
    | 'moveto' | 'movepick' | 'moveup' | 'movedown'
    | 'rule' | 'links' | 'relate'
    | 'delete'
    | 'moveroot' | 'movecancel'
    | 'viewoutline' | 'viewmap' | 'viewboth' | 'expandall' | 'collapseall' | 'addtop' | 'summary'
    | 'lensnone' | 'lensusage' | 'lensrules' | 'lensrelations' | 'check' | 'nextfinding' | 'prevfinding'
    | 'fwedit' | 'fwscale' | 'fwhide' | 'fwshow' | 'fwduplicate' | 'fwexport' | 'fwdelete'
    | 'fwnew' | 'fwimport';

/**
 * Where an action applies: to one competency (the panel, its menu and the palette's first group), to
 * the move in progress (the panel's move section and the palette), to the workspace (the palette's
 * last group), to the framework on the root (the root's panel, its menu and the palette's first group), or to
 * the tab's structures as a whole (the tab's Structures menu and the palette's last group).
 */
export type Scope = 'node' | 'move' | 'workspace' | 'framework' | 'structures';

/** A key and its modifiers; mod is Ctrl, or Cmd on a Mac. */
export type Shortcut = {key: string, mod?: boolean, shift?: boolean, alt?: boolean};

/** One action: what it is called, how it is drawn, and its key. */
export type ActionSpec = {
    id: ActionId,
    scope: Scope,
    // The menu draws a separator between two groups.
    group: number,
    // A local_dimensions string key, or `component:key`.
    label: string,
    // A Font Awesome class.
    icon: string,
    shortcut?: Shortcut,
    danger?: boolean,
    // Only the outline has the inline editor (owner's decision H5).
    outlineonly?: boolean,
    // Picking the new parent is offered where the map is drawn, as "Move to"'s own "Pick on the map".
    maponly?: boolean,
    // Whether, once done, it can change what the framework's insights say (usage, rules, relations, findings):
    // the workspace reads them again then (insights.ts).
    changes?: boolean,
};

/** Whether an action is offered: not at all, shown but not now, or ready. */
export type ActionState = 'hidden' | 'disabled' | 'enabled';

/** What the decision reads. */
export type ActionContext = {
    // The competency acted on, or null for the framework.
    node: StructureNode | null,
    // Whether the viewer manages competencies in the framework's context (adding at the top level).
    frameworkcanmanage: boolean,
    // The competency being moved by picking, or null.
    moving: StructureNode | null,
    // Whether the competency is the first, or the last, of its siblings (the last only when all are loaded).
    first: boolean,
    last: boolean,
    layout: Layout,
    // Whether the framework holds no competency, and whether "Expand all" is running.
    empty: boolean,
    expanding: boolean,
    // Whether the framework is visible (Hide or Show is offered); visible when not said.
    frameworkvisible?: boolean,
    // Whether the viewer may create or import a framework in the tab's context.
    cancreate?: boolean,
    // The lens shown; none when not said.
    lens?: Lens,
    // How much usage the insights tell the viewer, null before they are read.
    usagelevel?: UsageLevel | null,
    // How many findings the checker has, null before the insights are read.
    findings?: number | null,
};

/** Every action, in the menu's order. */
export const ACTIONS: ActionSpec[] = [
    {id: 'edit', scope: 'node', group: 1, label: 'hubv2_edit', icon: 'fa-pen', changes: true},
    {id: 'rename', scope: 'node', group: 1, label: 'hubv2_cmd_rename', icon: 'fa-i-cursor', shortcut: {key: 'F2'},
        outlineonly: true},
    {id: 'addchild', scope: 'node', group: 1, label: 'managecompetencies_addchild', icon: 'fa-plus', changes: true},
    {id: 'quicksibling', scope: 'node', group: 1, label: 'hubv2_cmd_quicksibling', icon: 'fa-turn-down',
        shortcut: {key: 'Enter', mod: true}, outlineonly: true, changes: true},
    {id: 'quickchild', scope: 'node', group: 1, label: 'hubv2_cmd_quickchild', icon: 'fa-indent',
        shortcut: {key: 'Enter', mod: true, shift: true}, outlineonly: true, changes: true},
    {id: 'moveto', scope: 'node', group: 2, label: 'hubv2_cmd_moveto', icon: 'fa-folder-tree', changes: true},
    {id: 'movepick', scope: 'node', group: 2, label: 'hubv2_cmd_movepick', icon: 'fa-arrows-up-down-left-right',
        maponly: true, changes: true},
    {id: 'moveup', scope: 'node', group: 2, label: 'hubv2_moveup', icon: 'fa-arrow-up', shortcut: {key: 'ArrowUp', alt: true}},
    {id: 'movedown', scope: 'node', group: 2, label: 'hubv2_movedown', icon: 'fa-arrow-down',
        shortcut: {key: 'ArrowDown', alt: true}},
    {id: 'rule', scope: 'node', group: 3, label: 'hubv2_cmd_rule', icon: 'fa-diagram-project', changes: true},
    {id: 'links', scope: 'node', group: 3, label: 'hubv2_cmd_links', icon: 'fa-link', changes: true},
    {id: 'relate', scope: 'node', group: 3, label: 'hubv2_cmd_relate', icon: 'fa-share-nodes', changes: true},
    {id: 'delete', scope: 'node', group: 4, label: 'hubv2_delete', icon: 'fa-trash', shortcut: {key: 'Delete'}, danger: true,
        changes: true},
    {id: 'fwedit', scope: 'framework', group: 1, label: 'central_frameworks_edit', icon: 'fa-pen'},
    {id: 'fwscale', scope: 'framework', group: 1, label: 'central_frameworks_configurescale', icon: 'fa-scale-balanced'},
    {id: 'fwhide', scope: 'framework', group: 2, label: 'hubv2_fw_hide', icon: 'fa-eye-slash'},
    {id: 'fwshow', scope: 'framework', group: 2, label: 'hubv2_fw_show', icon: 'fa-eye'},
    {id: 'fwduplicate', scope: 'framework', group: 2, label: 'central_frameworks_duplicate', icon: 'fa-copy'},
    {id: 'fwexport', scope: 'framework', group: 2, label: 'hubv2_fw_export', icon: 'fa-download'},
    {id: 'fwdelete', scope: 'framework', group: 3, label: 'hubv2_fw_delete', icon: 'fa-trash', danger: true},
    {id: 'moveroot', scope: 'move', group: 1, label: 'hubv2_moveroot', icon: 'fa-turn-up', changes: true},
    {id: 'movecancel', scope: 'move', group: 1, label: 'hubv2_movecancel', icon: 'fa-xmark'},
    {id: 'viewoutline', scope: 'workspace', group: 1, label: 'hubv2_cmd_viewoutline', icon: 'fa-list'},
    {id: 'viewmap', scope: 'workspace', group: 1, label: 'hubv2_cmd_viewmap', icon: 'fa-sitemap'},
    {id: 'viewboth', scope: 'workspace', group: 1, label: 'hubv2_cmd_viewboth', icon: 'fa-table-columns'},
    {id: 'expandall', scope: 'workspace', group: 2, label: 'managecompetencies_expandall', icon: 'fa-angles-down'},
    {id: 'collapseall', scope: 'workspace', group: 2, label: 'managecompetencies_collapseall', icon: 'fa-angles-up'},
    {id: 'addtop', scope: 'workspace', group: 3, label: 'managecompetencies_addcompetency', icon: 'fa-plus', changes: true},
    {id: 'summary', scope: 'workspace', group: 3, label: 'hubv2_cmd_summary', icon: 'fa-chart-simple'},
    {id: 'lensnone', scope: 'workspace', group: 4, label: 'hubv2_cmd_lensnone', icon: 'fa-eye-slash'},
    {id: 'lensusage', scope: 'workspace', group: 4, label: 'hubv2_cmd_lensusage', icon: 'fa-chart-column'},
    {id: 'lensrules', scope: 'workspace', group: 4, label: 'hubv2_cmd_lensrules', icon: 'fa-diagram-project'},
    {id: 'lensrelations', scope: 'workspace', group: 4, label: 'hubv2_cmd_lensrelations', icon: 'fa-share-nodes'},
    {id: 'check', scope: 'workspace', group: 5, label: 'hubv2_cmd_check', icon: 'fa-list-check'},
    {id: 'nextfinding', scope: 'workspace', group: 5, label: 'hubv2_cmd_nextfinding', icon: 'fa-arrow-down',
        shortcut: {key: 'F8'}},
    {id: 'prevfinding', scope: 'workspace', group: 5, label: 'hubv2_cmd_prevfinding', icon: 'fa-arrow-up',
        shortcut: {key: 'F8', shift: true}},
    {id: 'fwnew', scope: 'structures', group: 1, label: 'central_frameworks_new', icon: 'fa-plus'},
    {id: 'fwimport', scope: 'structures', group: 1, label: 'central_frameworks_import_title', icon: 'fa-upload'},
];

/** The views the palette switches to, by action. */
export const VIEW_ACTIONS: Partial<Record<ActionId, Layout>> = {viewoutline: 'outline', viewmap: 'map', viewboth: 'both'};

/** The lenses the palette switches to, by action. */
export const LENS_ACTIONS: Partial<Record<ActionId, Lens>> = {
    lensnone: 'none', lensusage: 'usage', lensrules: 'rules', lensrelations: 'relations',
};

/**
 * An action's specification.
 *
 * @param {string} id The action.
 * @returns {object} Its specification.
 */
export const specOf = (id: ActionId): ActionSpec => ACTIONS.find((spec) => spec.id === id) as ActionSpec;

/**
 * Whether an action acting on one competency is offered, and whether now.
 *
 * Hidden without a competency, without the right to manage it, or while a move is being picked (the
 * panel shows the move's own controls then); the inline editor's actions only while the outline is
 * drawn, picking the new parent only while the map is. Shown but disabled where the action exists and
 * cannot run now: a rule without children, up for the first, down for the last.
 *
 * @param {object} spec The action.
 * @param {object} context What the decision reads.
 * @returns {string} The state.
 */
const nodeState = (spec: ActionSpec, context: ActionContext): ActionState => {
    const {node} = context;
    if (!node || !node.canmanage || context.moving) {
        return 'hidden';
    }
    if ((spec.outlineonly && context.layout === 'map') || (spec.maponly && context.layout === 'outline')) {
        return 'hidden';
    }
    switch (spec.id) {
        case 'rule':
            return node.haschildren ? 'enabled' : 'disabled';
        case 'moveup':
            return context.first ? 'disabled' : 'enabled';
        case 'movedown':
            return context.last ? 'disabled' : 'enabled';
        default:
            return 'enabled';
    }
};

/**
 * Whether switching to a lens is offered: not to the one shown, nor to usage when the viewer is told none.
 *
 * @param {string} lens The lens.
 * @param {object} context What the decision reads.
 * @returns {string} The state.
 */
const lensState = (lens: Lens, context: ActionContext): ActionState => {
    if (lens === (context.lens ?? 'none') || (lens === 'usage' && context.usagelevel === 'none')) {
        return 'hidden';
    }
    return 'enabled';
};

/**
 * Whether an action of the workspace is offered: a view other than the current one, a lens other than the
 * current one (usage only when the viewer is told usage), the tree's own commands when there is a tree, adding
 * at the top level for a manager outside the move mode, walking the findings unless there are none.
 *
 * @param {object} spec The action.
 * @param {object} context What the decision reads.
 * @returns {string} The state.
 */
const workspaceState = (spec: ActionSpec, context: ActionContext): ActionState => {
    const view = VIEW_ACTIONS[spec.id];
    if (view) {
        return view === context.layout ? 'hidden' : 'enabled';
    }
    const lens = LENS_ACTIONS[spec.id];
    if (lens) {
        return lensState(lens, context);
    }
    switch (spec.id) {
        case 'expandall':
            if (context.empty) {
                return 'hidden';
            }
            return context.expanding ? 'disabled' : 'enabled';
        case 'collapseall':
            return context.empty ? 'hidden' : 'enabled';
        case 'addtop':
            return context.frameworkcanmanage && !context.moving ? 'enabled' : 'hidden';
        case 'nextfinding':
        case 'prevfinding':
            // Before the insights are read the walk reads them first; with none found there is nowhere to go.
            return context.findings === 0 ? 'hidden' : 'enabled';
        default:
            return 'enabled';
    }
};

/**
 * Whether an action on the framework is offered: on the root alone (nothing selected), for whoever manages
 * competencies in the framework's context, outside the move mode; Hide while it is visible, Show while it is not.
 *
 * @param {object} spec The action.
 * @param {object} context What the decision reads.
 * @returns {string} The state.
 */
const frameworkState = (spec: ActionSpec, context: ActionContext): ActionState => {
    if (context.node || !context.frameworkcanmanage || context.moving) {
        return 'hidden';
    }
    const visible = context.frameworkvisible !== false;
    if (spec.id === 'fwhide') {
        return visible ? 'enabled' : 'hidden';
    }
    if (spec.id === 'fwshow') {
        return visible ? 'hidden' : 'enabled';
    }
    return 'enabled';
};

/**
 * Whether an action is offered, and whether now: the one decision the panel, the menu, the palette
 * and the keys read.
 *
 * @param {string} id The action.
 * @param {object} context What the decision reads.
 * @returns {string} The state.
 */
export const actionState = (id: ActionId, context: ActionContext): ActionState => {
    const spec = specOf(id);
    if (spec.scope === 'node') {
        return nodeState(spec, context);
    }
    if (spec.scope === 'framework') {
        return frameworkState(spec, context);
    }
    if (spec.scope === 'structures') {
        return context.cancreate && !context.moving ? 'enabled' : 'hidden';
    }
    if (spec.scope === 'move') {
        if (!context.moving) {
            return 'hidden';
        }
        // Core's own move to the top level for one already there would change nothing.
        return spec.id === 'moveroot' && context.moving.parentid === ROOT ? 'hidden' : 'enabled';
    }
    return workspaceState(spec, context);
};

/** An action as a place draws it. */
export type ActionView = {spec: ActionSpec, state: ActionState};

/**
 * The actions of some scopes that are offered, in the menu's order.
 *
 * @param {Array} scopes The scopes.
 * @param {object} context What the decision reads.
 * @returns {Array} The actions, each with its state; none hidden.
 */
export const actionsFor = (scopes: Scope[], context: ActionContext): ActionView[] =>
    ACTIONS.filter((spec) => scopes.includes(spec.scope))
        .map((spec) => ({spec, state: actionState(spec.id, context)}))
        .filter((view) => view.state !== 'hidden');

/** What a key asks of the workspace, besides walking the tree. */
export type KeyCommand = ActionId | 'menu' | 'palette';

/**
 * Whether a key is pressed with Ctrl, or Cmd on a Mac (the command key of that platform).
 *
 * @param {object} key The key.
 * @returns {boolean} Whether it is.
 */
const withMod = (key: KeyPress): boolean => !!(key.ctrlKey || key.metaKey);

/**
 * Whether a key is pressed with no modifier at all.
 *
 * @param {object} key The key.
 * @returns {boolean} Whether it is.
 */
const bare = (key: KeyPress): boolean => !key.altKey && !key.ctrlKey && !key.metaKey && !key.shiftKey;

/**
 * The checker's walk: F8 to the next finding, Shift+F8 to the previous one; with Ctrl, Cmd or Alt the key is the
 * browser's.
 *
 * @param {object} key The key and its modifiers.
 * @returns {string|null} The command.
 */
const findingCommand = (key: KeyPress): KeyCommand | null => {
    if (key.key !== 'F8' || key.altKey || withMod(key)) {
        return null;
    }
    return key.shiftKey ? 'prevfinding' : 'nextfinding';
};

/**
 * The commands both panes share: the menu (Shift+F10, the context-menu key), the palette (Ctrl+K,
 * Cmd+K), reordering (Alt+Up, Alt+Down, as core's reorder lists do), Delete, which still confirms, and the
 * checker's walk (F8 and Shift+F8, the workspace root's, wherever the focus is in it).
 *
 * @param {object} key The key and its modifiers.
 * @returns {string|null} The command.
 */
const sharedCommand = (key: KeyPress): KeyCommand | null => {
    if ((key.key === 'F10' && key.shiftKey && !key.altKey && !withMod(key)) || (key.key === 'ContextMenu' && bare(key))) {
        return 'menu';
    }
    if (withMod(key) && !key.altKey && !key.shiftKey && key.key.toLowerCase() === 'k') {
        return 'palette';
    }
    const finding = findingCommand(key);
    if (finding) {
        return finding;
    }
    const arrows: Record<string, ActionId> = {ArrowUp: 'moveup', ArrowDown: 'movedown'};
    if (key.altKey && !withMod(key) && !key.shiftKey && arrows[key.key]) {
        return arrows[key.key];
    }
    return key.key === 'Delete' && bare(key) ? 'delete' : null;
};

/**
 * The inline editor's keys, the outline's alone: F2 renames, Ctrl+Enter (Cmd+Enter) adds a sibling
 * below, with Shift a first child, and Insert is the sibling's alias.
 *
 * @param {object} key The key and its modifiers.
 * @returns {string|null} The command.
 */
const authoringCommand = (key: KeyPress): KeyCommand | null => {
    if (bare(key)) {
        const plain: Record<string, ActionId> = {F2: 'rename', Insert: 'quicksibling'};
        return plain[key.key] ?? null;
    }
    if (key.key === 'Enter' && withMod(key) && !key.altKey) {
        return key.shiftKey ? 'quickchild' : 'quicksibling';
    }
    return null;
};

/**
 * The command a key names on an item of a pane, or null for a key that is not a command.
 *
 * Enter alone selects and Tab leaves the tree, so neither is an authoring key, whatever a mind map
 * editor would make of them.
 *
 * @param {object} key The key and its modifiers.
 * @param {string} pane The pane the item is in.
 * @returns {string|null} The command.
 */
export const shortcutAction = (key: KeyPress, pane: 'outline' | 'map'): KeyCommand | null =>
    sharedCommand(key) ?? (pane === 'outline' ? authoringCommand(key) : null);

/** The key caps of the arrows, which read better as arrows. */
const KEY_CAPS: Record<string, string> = {ArrowUp: '↑', ArrowDown: '↓', ArrowLeft: '←', ArrowRight: '→', Delete: 'Del'};

/**
 * The caps a shortcut is drawn with: the modifiers in the platform's order and words, then the key.
 *
 * @param {object} shortcut The shortcut.
 * @param {boolean} mac Whether the viewer is on a Mac.
 * @returns {Array} The caps.
 */
export const shortcutCaps = (shortcut: Shortcut, mac: boolean): string[] => {
    const caps: string[] = [];
    if (shortcut.mod) {
        caps.push(mac ? '⌘' : 'Ctrl');
    }
    if (shortcut.alt) {
        caps.push(mac ? '⌥' : 'Alt');
    }
    if (shortcut.shift) {
        caps.push(mac ? '⇧' : 'Shift');
    }
    caps.push(KEY_CAPS[shortcut.key] ?? shortcut.key);
    return caps;
};

/**
 * A shortcut as aria-keyshortcuts spells it.
 *
 * @param {object} shortcut The shortcut.
 * @param {boolean} mac Whether the viewer is on a Mac.
 * @returns {string} The value.
 */
export const ariaShortcut = (shortcut: Shortcut, mac: boolean): string => {
    const parts: string[] = [];
    if (shortcut.mod) {
        parts.push(mac ? 'Meta' : 'Control');
    }
    if (shortcut.alt) {
        parts.push('Alt');
    }
    if (shortcut.shift) {
        parts.push('Shift');
    }
    parts.push(shortcut.key);
    return parts.join('+');
};

/** The palette's own shortcut, shown on its button. */
export const PALETTE_SHORTCUT: Shortcut = {key: 'K', mod: true};

/**
 * Whether the viewer is on a Mac, where Cmd stands for Ctrl.
 *
 * @returns {boolean} Whether it is.
 */
export const isMac = (): boolean => /Mac|iPhone|iPad/.test(window.navigator.platform || window.navigator.userAgent);
