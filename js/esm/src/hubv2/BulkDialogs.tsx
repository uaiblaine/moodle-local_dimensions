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
 * The bulk dock's two dialogues of its own: "Set appearance…" and "Delete…" ("Move under…" is MoveDialog.tsx with
 * several subjects).
 *
 * Each is the workspace's Dialog (labelled by its title, modal, Tab kept inside, Escape and the veil close it), its
 * title naming the action and the count and taking the focus, its body naming the first five competencies and "and N
 * more", its foot Cancel and the commit, which is the confirmation, names the count and acts once; the focus goes
 * back to the dock's button on close (the Design System's data report, "Bulk dock", D135).
 *
 * Set appearance: each field is left as it is until a value or "Clear" is chosen, and only those are sent
 * (bulk.appearancePayload). A colour is typed as a hex value beside a swatch, refused in place when it is not one
 * (contrast.normalise, the server's rule); the competency form's colour swatch and contrast panel are AMD modules
 * bound to that form's own fields, so they are not reused here. A chip previews both colours, and with both set the
 * dialogue says their contrast ratio, warning under 4.5:1.
 *
 * Delete: the workspace asks core's refusal reasons first (local_dimensions_competency_delete_check, one per topmost
 * competency); the dialogue then says how many core will delete, what goes with them, which it will refuse and why.
 * Its commit deletes only those the check allowed.
 *
 * @module     local_dimensions/hubv2/BulkDialogs
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useId, useRef, useState} from 'react';
import type {ReactNode} from 'react';
import {APPEARANCE_FIELDS, BULK_MAX, COLOUR_FIELDS, appearancePayload, preview} from './bulk';
import type {AppearanceName, Choice, DeletionSummary, FieldEntry} from './bulk';
import {MIN_CONTRAST, contrastRatio, normalise, ratioText} from './contrast';
import Dialog from './Dialog';
import {fill, fillCount} from './strings';
import type {Labels} from './strings';
import type {AppearanceOptions} from './types';

/** The label string of each appearance field: the competency form's own field names. */
const FIELD_LABELS: Record<AppearanceName, string> = {
    bgcolor: 'custombgcolor',
    textcolor: 'customtextcolor',
    tag1: 'tag1',
    tag2: 'tag2',
    type: 'type',
};

/** Every field left as it is. */
const KEEP_ALL = Object.fromEntries(APPEARANCE_FIELDS.map((name) => [name, {mode: 'keep'}])) as Record<AppearanceName, Choice>;

/**
 * The first five names and "and N more".
 *
 * @param {object} props The props.
 * @returns {ReactNode} The list.
 */
const Names = (props: {labels: Labels, names: string[]}): ReactNode => {
    const {labels, names} = props;
    const {shown, more} = preview(names);
    return (
        <ul className="local-dimensions-hubv2-bulknames">
            {shown.map((name, index) => <li key={`${index}-${name}`}>{name}</li>)}
            {more > 0 ? <li>{fill(labels.hubv2_bulk_more, String(more))}</li> : null}
        </ul>
    );
};

/** What both dialogues share. */
type BulkDialogProps = {
    labels: Labels,
    // The names of the competencies acted on, in order.
    names: string[],
    onClose: () => void,
    onLostFocus: () => void,
};

/**
 * The frame both dialogues share: the title, focused at the opening, its close button, the body and the foot.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The dialogue.
 */
const Frame = (props: {title: string, onClose: () => void, onLostFocus: () => void, body: ReactNode, foot: ReactNode,
    labels: Labels}): ReactNode => {
    const {title, onClose, onLostFocus, body, foot, labels} = props;
    const heading = useRef<HTMLHeadingElement>(null);
    const id = useId();
    return (
        <Dialog labelledby={`${id}-title`} className="local-dimensions-hubv2-dialog local-dimensions-hubv2-bulkdialog"
            initialFocus={heading} returnTo="opener" onClose={onClose} onLostFocus={onLostFocus}>
            <div className="local-dimensions-hubv2-dialog-head">
                <h2 id={`${id}-title`} ref={heading} tabIndex={-1} className="local-dimensions-hubv2-dialog-title">{title}</h2>
                <button type="button" className="local-dimensions-hubv2-iconbtn" aria-label={labels['core:closebuttontitle']}
                    onClick={onClose}>
                    <i className="fa fa-xmark" aria-hidden="true" />
                </button>
            </div>
            <div className="local-dimensions-hubv2-dialog-body">{body}</div>
            <div className="local-dimensions-hubv2-dialog-foot">{foot}</div>
        </Dialog>
    );
};

/** The appearance dialogue's props. */
export type AppearanceDialogProps = BulkDialogProps & {
    options: AppearanceOptions,
    // Send the fields; resolves with the server's refusal in words, or null once applied.
    onCommit: (fields: FieldEntry[]) => Promise<string | null>,
};

/**
 * One colour field: leave it, set it (a hex value beside a swatch, refused in place when not a colour) or clear it.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The field.
 */
const ColourField = (props: {labels: Labels, name: AppearanceName, choice: Choice, onChange: (choice: Choice) => void}):
    ReactNode => {
    const {labels, name, choice, onChange} = props;
    const id = useId();
    const typed = choice.mode === 'set' ? choice.value : '';
    const colour = normalise(typed);
    const invalid = choice.mode === 'set' && typed.trim() !== '' && colour === '';
    return (
        <div className="local-dimensions-hubv2-bulkfield">
            <label htmlFor={`${id}-mode`} className="local-dimensions-hubv2-bulkfield-label">{labels[FIELD_LABELS[name]]}</label>
            <select id={`${id}-mode`} className="form-select form-select-sm" value={choice.mode}
                onChange={(event) => {
                    const mode = event.target.value;
                    onChange(mode === 'set' ? {mode: 'set', value: typed} : {mode: mode === 'clear' ? 'clear' : 'keep'});
                }}>
                <option value="keep">{labels.hubv2_bulk_keep}</option>
                <option value="set">{labels.hubv2_bulk_setcolour}</option>
                <option value="clear">{labels.hubv2_bulk_clearvalue}</option>
            </select>
            {choice.mode === 'set' ? (
                <div className="local-dimensions-hubv2-bulkcolour">
                    <label htmlFor={`${id}-value`} className="visually-hidden">{labels[FIELD_LABELS[name]]}</label>
                    <input id={`${id}-value`} type="text" className="form-control form-control-sm" value={typed}
                        placeholder="#0f6cbf" autoComplete="off" spellCheck={false} maxLength={7}
                        aria-invalid={invalid ? true : undefined}
                        aria-describedby={`${id}-help`}
                        onChange={(event) => onChange({mode: 'set', value: event.target.value})} />
                    <span className="local-dimensions-hubv2-swatch" aria-hidden="true"
                        style={colour ? {backgroundColor: colour} : undefined} />
                    <p id={`${id}-help`} className={invalid
                        ? 'local-dimensions-hubv2-bulkfield-help local-dimensions-hubv2-bulkfield-error'
                        : 'local-dimensions-hubv2-bulkfield-help'}>
                        {invalid ? labels.hubv2_bulk_colour_invalid : labels.hubv2_bulk_colour_help}
                    </p>
                </div>
            ) : null}
        </div>
    );
};

/**
 * One select field: leave it, clear it, or one of its options.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The field.
 */
const OptionField = (props: {labels: Labels, name: AppearanceName, options: string[], choice: Choice,
    onChange: (choice: Choice) => void}): ReactNode => {
    const {labels, name, options, choice, onChange} = props;
    const id = useId();
    const value = choice.mode === 'set' ? `o${choice.value}` : choice.mode;
    return (
        <div className="local-dimensions-hubv2-bulkfield">
            <label htmlFor={id} className="local-dimensions-hubv2-bulkfield-label">{labels[FIELD_LABELS[name]]}</label>
            <select id={id} className="form-select form-select-sm" value={value}
                onChange={(event) => {
                    const picked = event.target.value;
                    if (picked.startsWith('o')) {
                        onChange({mode: 'set', value: picked.slice(1)});
                    } else {
                        onChange({mode: picked === 'clear' ? 'clear' : 'keep'});
                    }
                }}>
                <option value="keep">{labels.hubv2_bulk_keep}</option>
                <option value="clear">{labels.hubv2_bulk_clearvalue}</option>
                {options.map((option, index) => <option key={`${index}-${option}`} value={`o${index + 1}`}>{option}</option>)}
            </select>
        </div>
    );
};

/**
 * The preview chip and, with both colours set, their contrast ratio.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The preview.
 */
const Preview = (props: {labels: Labels, background: string, text: string}): ReactNode => {
    const {labels, background, text} = props;
    const ratio = background && text ? contrastRatio(background, text) : null;
    const style = {backgroundColor: background || undefined, color: text || undefined};
    return (
        <div className="local-dimensions-hubv2-bulkpreview">
            <span className="local-dimensions-hubv2-bulkchip" style={style}>{labels.hubv2_bulk_preview}</span>
            {ratio !== null ? (
                <p className={ratio < MIN_CONTRAST
                    ? 'local-dimensions-hubv2-bulkcontrast local-dimensions-hubv2-bulkcontrast--low'
                    : 'local-dimensions-hubv2-bulkcontrast'}>
                    {fill(labels.hubv2_bulk_contrast, ratioText(ratio))}
                    {ratio < MIN_CONTRAST ? ` ${labels.hubv2_bulk_contrast_low}` : ''}
                </p>
            ) : null}
        </div>
    );
};

/**
 * "Set appearance…".
 *
 * @param {object} props The props.
 * @returns {ReactNode} The dialogue.
 */
export const AppearanceDialog = (props: AppearanceDialogProps): ReactNode => {
    const {labels, names, options, onCommit, onClose, onLostFocus} = props;
    const [choices, setChoices] = useState(KEEP_ALL);
    const [busy, setBusy] = useState(false);
    const [refusal, setRefusal] = useState('');
    const payload = appearancePayload(choices);
    const toomany = names.length > BULK_MAX;
    const ready = payload !== null && payload.length > 0 && !toomany && !busy;
    /**
     * Take one field's new choice.
     *
     * @param {string} name The field.
     * @param {object} choice Its choice.
     * @returns {void}
     */
    const choose = (name: AppearanceName, choice: Choice): void => setChoices((current) => ({...current, [name]: choice}));
    /**
     * Send the fields, once.
     *
     * @returns {void}
     */
    const commit = (): void => {
        if (!ready || payload === null) {
            return;
        }
        setBusy(true);
        setRefusal('');
        onCommit(payload).then((answer) => {
            if (answer !== null) {
                setRefusal(answer);
                setBusy(false);
            }
            return null;
        }).catch(() => setBusy(false));
    };
    /**
     * A colour as the preview takes it: the one set, or none.
     *
     * @param {string} name The field.
     * @returns {string} The colour, or ''.
     */
    const colourOf = (name: AppearanceName): string => {
        const choice = choices[name];
        return choice.mode === 'set' ? normalise(choice.value) : '';
    };
    const body = (
        <>
            <Names labels={labels} names={names} />
            {toomany ? <div className="alert alert-warning mb-0">{fill(labels.hubv2_bulk_toomany, String(BULK_MAX))}</div> : null}
            <p className="local-dimensions-hubv2-dialog-label">{labels.hubv2_bulk_appearance_intro}</p>
            {APPEARANCE_FIELDS.map((name) => (COLOUR_FIELDS.includes(name)
                ? <ColourField key={name} labels={labels} name={name} choice={choices[name]}
                    onChange={(choice) => choose(name, choice)} />
                : <OptionField key={name} labels={labels} name={name} options={options[name as 'tag1' | 'tag2' | 'type']}
                    choice={choices[name]} onChange={(choice) => choose(name, choice)} />))}
            <Preview labels={labels} background={colourOf('bgcolor')} text={colourOf('textcolor')} />
            {refusal ? <div className="alert alert-danger mb-0" role="alert">{refusal}</div> : null}
        </>
    );
    const foot = (
        <>
            <button type="button" className="btn btn-sm btn-secondary" onClick={onClose}>{labels['core:cancel']}</button>
            <button type="button" className="btn btn-sm btn-primary" disabled={!ready} onClick={commit}>
                {fillCount(labels, 'hubv2_bulk_appearance_commit', names.length)}
            </button>
        </>
    );
    return <Frame labels={labels} title={fillCount(labels, 'hubv2_bulk_appearance_title', names.length)}
        onClose={onClose} onLostFocus={onLostFocus} body={body} foot={foot} />;
};

/** The delete dialogue's props. */
export type DeleteDialogProps = BulkDialogProps & {
    // Null while core's reasons are being read.
    summary: DeletionSummary | null,
    onCommit: () => void,
};

/**
 * "Delete…": what core will delete, what goes with it, what core will refuse and why, then the one commit.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The dialogue.
 */
export const DeleteDialog = (props: DeleteDialogProps): ReactNode => {
    const {labels, names, summary, onCommit, onClose, onLostFocus} = props;
    const [busy, setBusy] = useState(false);
    const allowed = summary ? summary.allowed.length : 0;
    let verdict: ReactNode = <p className="mb-0" role="status">{labels.hubv2_bulk_delete_checking}</p>;
    if (summary) {
        verdict = (
            <>
                {allowed > 0 ? (
                    <div className="alert alert-danger mb-0">
                        <p className="mb-0">{fillCount(labels, 'hubv2_bulk_delete_can', allowed)}</p>
                        {summary.descendants > 0
                            ? <p className="mb-0">{fillCount(labels, 'hubv2_bulk_delete_with', summary.descendants)}</p>
                            : null}
                        {summary.parentrule ? <p className="mb-0">{labels.hubv2_delete_parentrule}</p> : null}
                    </div>
                ) : <p className="mb-0">{labels.hubv2_bulk_delete_none}</p>}
                {summary.refused.length > 0 ? (
                    <>
                        <p className="local-dimensions-hubv2-dialog-label">
                            {fillCount(labels, 'hubv2_bulk_delete_refused', summary.refused.length)}
                        </p>
                        <ul className="local-dimensions-hubv2-bulkrefused">
                            {summary.refused.map((item) => (
                                <li key={item.id}>
                                    <span className="local-dimensions-hubv2-bulkrefused-name">{item.name}</span>
                                    {item.reasons.length > 0 ? `: ${item.reasons.join('; ')}` : ''}
                                </li>
                            ))}
                        </ul>
                    </>
                ) : null}
            </>
        );
    }
    const foot = (
        <>
            <button type="button" className="btn btn-sm btn-secondary" onClick={onClose}>{labels['core:cancel']}</button>
            <button type="button" className="btn btn-sm btn-danger" disabled={allowed === 0 || busy}
                onClick={() => {
                    if (!busy) {
                        setBusy(true);
                        onCommit();
                    }
                }}>
                {fillCount(labels, 'hubv2_bulk_delete_commit', allowed)}
            </button>
        </>
    );
    return <Frame labels={labels} title={fillCount(labels, 'hubv2_bulk_delete_title', names.length)}
        onClose={onClose} onLostFocus={onLostFocus} body={<><Names labels={labels} names={names} />{verdict}</>} foot={foot} />;
};
