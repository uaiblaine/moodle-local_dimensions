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
 * The outline's inline editor: a row with a name and an ID number for a new competency, or the name
 * alone to rename one in place (owner's decision H5: fast authoring in the outline only).
 *
 * A form in all but the element: Tab and Shift+Tab move through its fields and its two buttons, Enter
 * in a field saves, Escape cancels and the workspace gives the focus back to the row. The row sits in the
 * tree's list as a presentational item holding a labelled group, so the tree's keys (which act on
 * treeitems only) leave its fields alone. What the server refuses (an ID number already taken, a
 * missing right) is said under the fields, tied to them by aria-describedby and announced, and the
 * editor stays open with what was typed.
 *
 * @module     local_dimensions/hubv2/Editor
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useRef, useState} from 'react';
import type {KeyboardEvent, ReactNode} from 'react';
import type {Labels} from './strings';

/** The editor's props. */
export type EditorProps = {
    labels: Labels,
    // A new competency (two fields) or a new name (one).
    mode: 'create' | 'rename',
    // The group's accessible name: what is being created or renamed.
    label: string,
    // The level the row stands at, for its indent.
    level: number,
    initialname: string,
    initialidnumber: string,
    // Store what was typed; resolves with the server's refusal in words, or null once stored.
    onSave: (name: string, idnumber: string) => Promise<string | null>,
    onCancel: () => void,
};

/**
 * The editor.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The row.
 */
const Editor = ({labels, mode, label, level, initialname, initialidnumber, onSave, onCancel}: EditorProps): ReactNode => {
    const [name, setName] = useState(initialname);
    const [idnumber, setIdnumber] = useState(initialidnumber);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const first = useRef<HTMLInputElement>(null);
    const alive = useRef(true);
    const id = useId();
    const errorid = `${id}-error`;

    useEffect(() => {
        first.current?.focus();
        first.current?.select();
        return () => {
            alive.current = false;
        };
    }, []);

    /**
     * Save, unless a field is empty or a save is on its way.
     *
     * @returns {void}
     */
    const save = (): void => {
        if (busy) {
            return;
        }
        if (name.trim() === '' || (mode === 'create' && idnumber.trim() === '')) {
            setError(labels['core:required']);
            return;
        }
        setBusy(true);
        onSave(name.trim(), idnumber.trim()).then((refusal) => {
            // A successful save replaces the editor; only a refusal finds it still drawn.
            if (alive.current) {
                setBusy(false);
                setError(refusal ?? '');
            }
            return null;
        }).catch(() => {
            if (alive.current) {
                setBusy(false);
            }
        });
    };

    /**
     * Enter in a field saves; Escape anywhere in the row cancels.
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            onCancel();
            return;
        }
        if (event.key === 'Enter' && event.target instanceof HTMLInputElement) {
            event.preventDefault();
            event.stopPropagation();
            save();
        }
    };

    const described = error ? errorid : undefined;
    return (
        <li role="none" className="local-dimensions-hubv2-oeditor" style={{paddingInlineStart: `${0.25 + (level - 1) * 1.25}rem`}}>
            <div role="group" aria-label={label} className="local-dimensions-hubv2-oeditor-group" onKeyDown={onKeyDown}>
                <span className="local-dimensions-hubv2-otoggle" aria-hidden="true" />
                <input
                    ref={first}
                    type="text"
                    className="form-control form-control-sm local-dimensions-hubv2-oeditor-name"
                    aria-label={labels.hubv2_editor_name}
                    placeholder={labels.hubv2_editor_name}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={described}
                    maxLength={100}
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                />
                {mode === 'create' ? (
                    <input
                        type="text"
                        className="form-control form-control-sm local-dimensions-hubv2-oeditor-id"
                        aria-label={labels['core:idnumber']}
                        placeholder={labels['core:idnumber']}
                        aria-invalid={error ? true : undefined}
                        aria-describedby={described}
                        maxLength={100}
                        value={idnumber}
                        onChange={(event) => setIdnumber(event.target.value)}
                    />
                ) : null}
                <button type="button" className="btn btn-sm btn-primary" disabled={busy} onClick={save}>
                    {labels['core:save']}
                </button>
                <button type="button" className="btn btn-sm btn-secondary" onClick={onCancel}>{labels['core:cancel']}</button>
            </div>
            <p id={errorid} className="local-dimensions-hubv2-oeditor-error" role="alert">{error}</p>
        </li>
    );
};

export default Editor;
