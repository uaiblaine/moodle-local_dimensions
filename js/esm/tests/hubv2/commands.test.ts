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
 * Tests for the action registry: who is offered which action, when, and which key names it.
 *
 * The refusals come first: a reader (a competency without canmanage) is offered no action that writes,
 * in any place, and no key reaches one; each refusal sits beside the same case for a manager, which is
 * offered the action, so the fixture is known to reach the decision.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    ACTIONS, actionState, actionsFor, ariaShortcut, shortcutAction, shortcutCaps,
} from '../../src/hubv2/commands';
import type {ActionContext, ActionId} from '../../src/hubv2/commands';
import {ROOT} from '../../src/hubv2/tree';
import type {StructureNode} from '../../src/hubv2/types';

/**
 * A competency with the fields the decision reads.
 *
 * @param {object} extra Fields to override.
 * @returns {object} The node.
 */
const node = (extra: Partial<StructureNode> = {}): StructureNode => ({
    id: 5, parentid: 1, shortname: 'C5', idnumber: '', taxonomy: '', scale: '', description: '',
    coursecount: 0, activitycount: 0, templatecount: 0, depth: 0, indent: 0, haschildren: true, canmanage: true,
    ruletype: null, ruleoutcome: 0, ruleconfig: null, rulelabel: '', type: '', tag1: '', tag2: '', bgcolor: '', textcolor: '',
    ...extra,
});

/**
 * A context: a manager on a middle competency with children, in the Both view, nothing moving.
 *
 * @param {object} extra Fields to override.
 * @returns {object} The context.
 */
const context = (extra: Partial<ActionContext> = {}): ActionContext => ({
    node: node(), frameworkcanmanage: true, moving: null, first: false, last: false, layout: 'both', empty: false,
    expanding: false, ...extra,
});

/** Every action that acts on one competency: each one writes, or opens a dialogue that writes. */
const NODE_ACTIONS = ACTIONS.filter((spec) => spec.scope === 'node').map((spec) => spec.id);

/**
 * The ids of the offered actions of some scopes.
 *
 * @param {Array} scopes The scopes.
 * @param {object} given The context.
 * @returns {Array} The ids.
 */
const offered = (scopes: ('node' | 'move' | 'workspace' | 'framework' | 'structures')[], given: ActionContext): ActionId[] =>
    actionsFor(scopes, given).map((view) => view.spec.id);

describe('a reader', () => {
    it('is offered no action on a competency, in the panel, the menu or the palette', () => {
        const reader = context({node: node({canmanage: false}), frameworkcanmanage: false});
        expect(offered(['node'], reader)).toEqual([]);
        NODE_ACTIONS.forEach((id) => expect(actionState(id, reader)).toBe('hidden'));
        // Control: the same competency for a manager offers every one of them.
        expect(offered(['node'], context())).toEqual(NODE_ACTIONS);
    });

    it('cannot add at the top level, and keeps the view commands', () => {
        const reader = context({node: null, frameworkcanmanage: false});
        expect(actionState('addtop', reader)).toBe('hidden');
        expect(actionState('addtop', context({node: null}))).toBe('enabled');
        expect(offered(['workspace'], reader)).toEqual([
            'viewoutline', 'viewmap', 'expandall', 'collapseall', 'summary',
            'lensusage', 'lensrules', 'lensrelations', 'check', 'nextfinding', 'prevfinding',
        ]);
    });

    it('reaches no write through a key: every key command is refused by the same decision', () => {
        const reader = context({node: node({canmanage: false})});
        const keys = [
            {key: 'ArrowUp', altKey: true}, {key: 'ArrowDown', altKey: true}, {key: 'Delete'}, {key: 'F2'},
            {key: 'Enter', ctrlKey: true}, {key: 'Enter', metaKey: true, shiftKey: true}, {key: 'Insert'},
        ];
        keys.forEach((key) => {
            const command = shortcutAction(key, 'outline') as ActionId;
            expect(NODE_ACTIONS).toContain(command);
            expect(actionState(command, reader)).toBe('hidden');
            expect(actionState(command, context())).toBe('enabled');
        });
    });

    it('is offered nothing on a competency the manager of the framework does not manage', () => {
        // Each competency carries its own right, read in its own context by the server.
        const given = context({node: node({canmanage: false}), frameworkcanmanage: true});
        expect(offered(['node'], given)).toEqual([]);
    });
});

describe('a manager', () => {
    it('cannot move the first competency up nor the last one down, and sees why', () => {
        expect(actionState('moveup', context({first: true}))).toBe('disabled');
        expect(actionState('movedown', context({first: true}))).toBe('enabled');
        expect(actionState('movedown', context({last: true}))).toBe('disabled');
        expect(actionState('moveup', context({last: true}))).toBe('enabled');
        // An only child is both.
        const only = context({first: true, last: true});
        expect([actionState('moveup', only), actionState('movedown', only)]).toEqual(['disabled', 'disabled']);
    });

    it('cannot set a rule on a competency without children', () => {
        expect(actionState('rule', context({node: node({haschildren: false})}))).toBe('disabled');
        expect(actionState('rule', context())).toBe('enabled');
    });

    it('has the inline editor in the views that draw the outline only', () => {
        ['rename', 'quicksibling', 'quickchild'].forEach((id) => {
            expect(actionState(id as ActionId, context({layout: 'map'}))).toBe('hidden');
            expect(actionState(id as ActionId, context({layout: 'outline'}))).toBe('enabled');
            expect(actionState(id as ActionId, context({layout: 'both'}))).toBe('enabled');
        });
        expect(actionState('edit', context({layout: 'map'}))).toBe('enabled');
    });

    it('picks the new parent on the map only where the map is drawn', () => {
        expect(actionState('movepick', context({layout: 'outline'}))).toBe('hidden');
        expect(actionState('movepick', context({layout: 'map'}))).toBe('enabled');
        expect(actionState('movepick', context({layout: 'both'}))).toBe('enabled');
        // "Move to" stays in every view.
        expect(actionState('moveto', context({layout: 'outline'}))).toBe('enabled');
    });

    it('acts on a root competency as on any other', () => {
        expect(offered(['node'], context({node: node({parentid: ROOT})}))).toEqual(NODE_ACTIONS);
    });

    it('is offered only the move\'s own controls while a move is being picked', () => {
        const moving = node({id: 9, parentid: 1});
        const picking = context({moving});
        expect(offered(['node'], picking)).toEqual([]);
        expect(offered(['move'], picking)).toEqual(['moveroot', 'movecancel']);
        expect(actionState('addtop', picking)).toBe('hidden');
        // A top-level competency has no "top level" to go to.
        expect(offered(['move'], context({moving: node({id: 9, parentid: ROOT})}))).toEqual(['movecancel']);
        // Control: no move, no move controls.
        expect(offered(['move'], context())).toEqual([]);
    });
});

describe('the workspace\'s commands', () => {
    it('offer the views other than the current one', () => {
        expect(offered(['workspace'], context({layout: 'map'})).filter((id) => id.startsWith('view')))
            .toEqual(['viewoutline', 'viewboth']);
        expect(offered(['workspace'], context({layout: 'outline'})).filter((id) => id.startsWith('view')))
            .toEqual(['viewmap', 'viewboth']);
    });

    it('offer no tree command for an empty framework, and hold "Expand all" while it runs', () => {
        expect(actionState('expandall', context({empty: true}))).toBe('hidden');
        expect(actionState('collapseall', context({empty: true}))).toBe('hidden');
        expect(actionState('expandall', context({expanding: true}))).toBe('disabled');
        expect(actionState('expandall', context())).toBe('enabled');
    });
});

describe('shortcutAction', () => {
    it('names the menu with Shift+F10 and the context-menu key, in both panes', () => {
        expect(shortcutAction({key: 'F10', shiftKey: true}, 'map')).toBe('menu');
        expect(shortcutAction({key: 'ContextMenu'}, 'outline')).toBe('menu');
        expect(shortcutAction({key: 'F10'}, 'outline')).toBeNull();
    });

    it('names the palette with Ctrl+K and Cmd+K only', () => {
        expect(shortcutAction({key: 'k', ctrlKey: true}, 'outline')).toBe('palette');
        expect(shortcutAction({key: 'K', metaKey: true}, 'map')).toBe('palette');
        expect(shortcutAction({key: 'k'}, 'outline')).toBeNull();
        expect(shortcutAction({key: 'k', ctrlKey: true, shiftKey: true}, 'outline')).toBeNull();
    });

    it('reorders with Alt and an arrow, and leaves the arrows alone otherwise', () => {
        expect(shortcutAction({key: 'ArrowUp', altKey: true}, 'map')).toBe('moveup');
        expect(shortcutAction({key: 'ArrowDown', altKey: true}, 'outline')).toBe('movedown');
        expect(shortcutAction({key: 'ArrowDown'}, 'outline')).toBeNull();
        expect(shortcutAction({key: 'ArrowDown', altKey: true, shiftKey: true}, 'outline')).toBeNull();
    });

    it('gives the authoring keys to the outline alone', () => {
        expect(shortcutAction({key: 'F2'}, 'outline')).toBe('rename');
        expect(shortcutAction({key: 'Enter', ctrlKey: true}, 'outline')).toBe('quicksibling');
        expect(shortcutAction({key: 'Enter', metaKey: true}, 'outline')).toBe('quicksibling');
        expect(shortcutAction({key: 'Enter', ctrlKey: true, shiftKey: true}, 'outline')).toBe('quickchild');
        expect(shortcutAction({key: 'Insert'}, 'outline')).toBe('quicksibling');
        ['F2', 'Insert'].forEach((key) => expect(shortcutAction({key}, 'map')).toBeNull());
        expect(shortcutAction({key: 'Enter', ctrlKey: true}, 'map')).toBeNull();
    });

    it('leaves Enter and Tab to the tree: neither is an authoring key', () => {
        expect(shortcutAction({key: 'Enter'}, 'outline')).toBeNull();
        expect(shortcutAction({key: 'Tab'}, 'outline')).toBeNull();
        expect(shortcutAction({key: 'Tab', shiftKey: true}, 'outline')).toBeNull();
    });

    it('names every shortcut the registry shows', () => {
        ACTIONS.filter((spec) => spec.shortcut).forEach((spec) => {
            const shortcut = spec.shortcut!;
            const key = {key: shortcut.key, ctrlKey: !!shortcut.mod, shiftKey: !!shortcut.shift, altKey: !!shortcut.alt};
            expect(shortcutAction(key, 'outline')).toBe(spec.id);
        });
    });
});

describe('the shortcuts\' caps', () => {
    it('follow the platform', () => {
        expect(shortcutCaps({key: 'Enter', mod: true, shift: true}, false)).toEqual(['Ctrl', 'Shift', 'Enter']);
        expect(shortcutCaps({key: 'Enter', mod: true, shift: true}, true)).toEqual(['⌘', '⇧', 'Enter']);
        expect(shortcutCaps({key: 'ArrowUp', alt: true}, false)).toEqual(['Alt', '↑']);
        expect(shortcutCaps({key: 'Delete'}, false)).toEqual(['Del']);
        expect(ariaShortcut({key: 'K', mod: true}, true)).toBe('Meta+K');
        expect(ariaShortcut({key: 'ArrowDown', alt: true}, false)).toBe('Alt+ArrowDown');
    });
});

describe('the framework on the root', () => {
    const FRAMEWORK_ACTIONS: ActionId[] = ['fwedit', 'fwscale', 'fwhide', 'fwshow', 'fwduplicate', 'fwexport', 'fwdelete'];

    it('offers a reader none of its actions, and nothing to create', () => {
        const reader = context({node: null, frameworkcanmanage: false, frameworkvisible: true, cancreate: false});
        expect(offered(['framework', 'structures'], reader)).toEqual([]);
        FRAMEWORK_ACTIONS.forEach((id) => expect(actionState(id, reader)).toBe('hidden'));
        expect(actionState('fwnew', reader)).toBe('hidden');
        expect(actionState('fwimport', reader)).toBe('hidden');
        // Control: a manager of the same framework is offered them, Hide for a visible one.
        const manager = context({node: null, frameworkvisible: true, cancreate: true});
        expect(offered(['framework'], manager)).toEqual(['fwedit', 'fwscale', 'fwhide', 'fwduplicate', 'fwexport', 'fwdelete']);
        expect(offered(['structures'], manager)).toEqual(['fwnew', 'fwimport']);
    });

    it('offers Hide for a visible framework and Show for a hidden one, never both', () => {
        const visible = context({node: null, frameworkvisible: true});
        expect(actionState('fwhide', visible)).toBe('enabled');
        expect(actionState('fwshow', visible)).toBe('hidden');
        const hidden = context({node: null, frameworkvisible: false});
        expect(actionState('fwhide', hidden)).toBe('hidden');
        expect(actionState('fwshow', hidden)).toBe('enabled');
        expect(offered(['framework'], hidden)).toEqual(['fwedit', 'fwscale', 'fwshow', 'fwduplicate', 'fwexport', 'fwdelete']);
        // Not said: visible, as a framework is by default.
        expect(actionState('fwhide', context({node: null}))).toBe('enabled');
    });

    it('is acted on from the root alone: a selected competency hides the framework\'s actions', () => {
        expect(offered(['framework'], context())).toEqual([]);
        expect(offered(['framework'], context({node: null}))).not.toEqual([]);
    });

    it('offers nothing on the framework, and nothing to create, while a move is being picked', () => {
        const moving = context({node: null, moving: node({id: 9}), cancreate: true});
        expect(offered(['framework', 'structures'], moving)).toEqual([]);
    });

    it('lets a manager of the tab\'s context create where the framework is not theirs to manage', () => {
        const creator = context({node: null, frameworkcanmanage: false, cancreate: true});
        expect(offered(['framework'], creator)).toEqual([]);
        expect(offered(['structures'], creator)).toEqual(['fwnew', 'fwimport']);
    });

    it('draws the delete last, as the destructive one, after a separator group', () => {
        const views = actionsFor(['framework'], context({node: null}));
        const last = views[views.length - 1];
        expect(last.spec.id).toBe('fwdelete');
        expect(last.spec.danger).toBe(true);
        expect(last.spec.group).toBeGreaterThan(views[views.length - 2].spec.group);
    });
});

describe('the lenses and the checker in the registry', () => {
    it('offer the lenses other than the one shown, usage only when the viewer is told usage', () => {
        const lensids = (given: ActionContext): ActionId[] => offered(['workspace'], given).filter((id) => id.startsWith('lens'));
        expect(lensids(context())).toEqual(['lensusage', 'lensrules', 'lensrelations']);
        expect(lensids(context({lens: 'rules'}))).toEqual(['lensnone', 'lensusage', 'lensrelations']);
        expect(lensids(context({usagelevel: 'none'}))).toEqual(['lensrules', 'lensrelations']);
        expect(lensids(context({usagelevel: 'presence'}))).toContain('lensusage');
    });

    it('offer "Check structure" always, and the walk unless the checker found nothing', () => {
        expect(actionState('check', context())).toBe('enabled');
        expect(actionState('nextfinding', context({findings: null}))).toBe('enabled');
        expect(actionState('prevfinding', context({findings: 3}))).toBe('enabled');
        expect(actionState('nextfinding', context({findings: 0}))).toBe('hidden');
        expect(actionState('prevfinding', context({findings: 0}))).toBe('hidden');
    });

    it('name F8 and Shift+F8 in both panes, and leave F8 with Ctrl or Alt to the browser', () => {
        expect(shortcutAction({key: 'F8'}, 'outline')).toBe('nextfinding');
        expect(shortcutAction({key: 'F8', shiftKey: true}, 'map')).toBe('prevfinding');
        expect(shortcutAction({key: 'F8', ctrlKey: true}, 'outline')).toBeNull();
        expect(shortcutAction({key: 'F8', altKey: true}, 'map')).toBeNull();
    });

    it('mark as changing the insights exactly the actions that write a competency, a rule, a link, a relation, or in bulk', () => {
        expect(ACTIONS.filter((spec) => spec.changes).map((spec) => spec.id)).toEqual([
            'edit', 'addchild', 'quicksibling', 'quickchild', 'moveto', 'movepick', 'rule', 'links', 'relate', 'delete',
            'moveroot', 'addtop', 'bulkappearance', 'bulkmove', 'bulkdelete',
        ]);
        // The control: a rename and a reorder change nothing the insights say.
        expect(ACTIONS.find((spec) => spec.id === 'rename')?.changes).toBeUndefined();
        expect(ACTIONS.find((spec) => spec.id === 'moveup')?.changes).toBeUndefined();
    });
});
