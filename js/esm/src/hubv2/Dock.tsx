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
 * The bulk dock: the count of the competencies picked and the actions over them (the fleet Design System's data
 * report, "Bulk dock", D133 to D135).
 *
 * Drawn by the workspace after its panes while something is picked, and only for a manager (the registry's `bulk`
 * scope); sticky 12 px above the viewport's bottom while the panes scroll, never fixed. Its count comes first, in a
 * polite live region, saying how many picks sit in closed branches when some do; then the actions as core's buttons,
 * the main one the only solid fill, "Clear selection", and the destructive one last, outlined. It takes no focus
 * when it appears and follows the panes in the Tab order. An action needing more input opens a dialogue
 * (BulkDialogs.tsx); the dock never grows fields.
 *
 * @module     local_dimensions/hubv2/Dock
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {MouseEvent, ReactNode} from 'react';
import type {ActionId, ActionView} from './commands';
import {dockCount} from './bulk';
import type {Labels} from './strings';

/** The dock's props. */
export type DockProps = {
    labels: Labels,
    // How many competencies are picked, and how many of them sit in closed branches.
    count: number,
    hidden: number,
    // The bulk actions the registry offers, in its order.
    actions: ActionView[],
    onRun: (id: ActionId, button: HTMLButtonElement) => void,
    onClear: () => void,
};

/**
 * A bulk action's button classes: the first one the solid main action, a destructive one outlined in danger, the
 * rest outlined, written whole for the stylesheet's test.
 *
 * @param {object} view The action.
 * @param {number} index Its place in the dock.
 * @returns {string} The classes.
 */
const buttonClass = (view: ActionView, index: number): string => {
    if (view.spec.danger) {
        return 'btn btn-outline-danger';
    }
    return index === 0 ? 'btn btn-primary' : 'btn btn-outline-secondary';
};

/**
 * The dock.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The dock, or nothing when no action is offered.
 */
const Dock = (props: DockProps): ReactNode => {
    const {labels, count, hidden, actions, onRun, onClear} = props;
    if (actions.length === 0) {
        return null;
    }
    const plain = actions.filter((view) => !view.spec.danger);
    const danger = actions.filter((view) => view.spec.danger);
    /**
     * One action's button.
     *
     * @param {object} view The action.
     * @param {number} index Its place among the actions.
     * @returns {ReactNode} The button.
     */
    const button = (view: ActionView, index: number): ReactNode => (
        <button
            key={view.spec.id}
            type="button"
            className={buttonClass(view, index)}
            aria-haspopup="dialog"
            disabled={view.state === 'disabled'}
            onClick={(event: MouseEvent<HTMLButtonElement>) => onRun(view.spec.id, event.currentTarget)}
        >
            <i className={`fa ${view.spec.icon} me-1`} aria-hidden="true" />{labels[view.spec.label]}
        </button>
    );
    return (
        <div className="local-dimensions-hubv2-dock" role="region" aria-label={labels.hubv2_bulk_dock}
            data-region="bulk-dock">
            <span className="local-dimensions-hubv2-dockcount" aria-live="polite">
                {dockCount(labels, count, hidden)}
            </span>
            <div className="local-dimensions-hubv2-dockactions">
                {plain.map(button)}
                <button type="button" className="btn btn-outline-secondary" onClick={onClear}>{labels.hubv2_bulk_clear}</button>
                {danger.map((view) => button(view, plain.length))}
            </div>
        </div>
    );
};

export default Dock;
