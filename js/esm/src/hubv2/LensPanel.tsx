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
 * The lenses' and the checker's controls and text: the lens switch and the "Check" button of the workspace's
 * bar, the lens's section at the top of the panel (its sentence, the legend with its counts, the list), and the
 * checker's panel mode (its heading and count, the codes present, "Check again", the findings, the codes found
 * nowhere).
 *
 * The switch is a view switch in the Design System's segmented form: buttons with aria-pressed in a labelled
 * group, as the tab's own view switch. Every list names competencies the tree may not have loaded: a name the
 * workspace does not know yet is a grey block until it does (the component asks for the names it shows, a page
 * at a time, through `need`). Every competency is a button that reveals it in both panes.
 *
 * @module     local_dimensions/hubv2/LensPanel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useState} from 'react';
import type {ReactNode, RefObject} from 'react';
import type {ActionId, ActionState} from './commands';
import {absentCodes, codeTitle, findingGroups, findingSentence, fixFor} from './findings';
import type {InsightsState, Lens, Measure} from './insights';
import {MEASURES} from './insights';
import {
    BUCKET_CLASSES, BUCKET_LABELS, LENS_LABELS, MEASURE_LABELS, pairWords, relationsLegend, ruleFacts, rulesLegend,
    unusedIds, usageLegend, usageNotice,
} from './lenses';
import {fill, fillCount} from './strings';
import type {Labels} from './strings';
import type {InsightCheck} from './types';

/** How many entries a list shows at first, and how many more each "Show more" adds. */
export const LIST_PAGE = 50;

/** A competency's name, or null while the workspace does not know it yet. */
export type NameOf = (id: number) => string | null;

/**
 * A name, or a grey block standing for it while it loads.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The name.
 */
const Name = ({name}: {name: string | null}): ReactNode => (name !== null ? <>{name}</> : (
    <span className="local-dimensions-sk-block local-dimensions-hubv2-sk-name" aria-hidden="true" />
));

/**
 * Ask for the names a list shows, once they change.
 *
 * @param {Array} ids The competencies shown.
 * @param {Function} need Asks the workspace for the names it does not know.
 * @returns {void}
 */
const useNames = (ids: number[], need: (ids: number[]) => void): void => {
    const key = ids.join(',');
    useEffect(() => {
        if (key !== '') {
            need(key.split(',').map(Number));
        }
    }, [key, need]);
};

/**
 * The "Show N more" button after a list cut at a page.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The button, or nothing when the list is whole.
 */
const ShowMore = ({labels, left, onMore}: {labels: Labels, left: number, onMore: () => void}): ReactNode => (left > 0 ? (
    <button type="button" className="btn btn-link btn-sm p-0" onClick={onMore}>
        {fill(labels.hubv2_showmore, String(Math.min(left, LIST_PAGE)))}
    </button>
) : null);

/** The lens switch's props. */
export type LensSwitchProps = {
    labels: Labels,
    lens: Lens,
    // The lenses offered: usage only when the viewer is told usage.
    offered: Lens[],
    onChange: (lens: Lens) => void,
};

/**
 * The lens switch of the workspace's bar: "Lens", then None, Usage, Rules and Relations.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The switch.
 */
export const LensSwitch = ({labels, lens, offered, onChange}: LensSwitchProps): ReactNode => {
    const id = useId();
    return (
        <div className="local-dimensions-hubv2-lensswitch">
            <span id={`${id}-label`} className="local-dimensions-hubv2-lenslabel">{labels.hubv2_lens}</span>
            <div className="local-dimensions-hubv2-seg" role="group" aria-labelledby={`${id}-label`}>
                {offered.map((value) => (
                    <button
                        key={value}
                        type="button"
                        className="local-dimensions-hubv2-seg-cell"
                        data-lens={value}
                        aria-pressed={value === lens}
                        onClick={() => onChange(value)}
                    >
                        {labels[LENS_LABELS[value]]}
                    </button>
                ))}
            </div>
        </div>
    );
};

/** The "Check" button's props. */
export type CheckButtonProps = {
    labels: Labels,
    // The findings' count once read, else null.
    findings: number | null,
    open: boolean,
    buttonRef: RefObject<HTMLButtonElement | null>,
    onToggle: () => void,
};

/**
 * The "Check" button of the workspace's bar, with the loud counter of findings above zero; its count is in its
 * name, the counter itself is hidden from assistive technology (the Design System's counter).
 *
 * @param {object} props The props.
 * @returns {ReactNode} The button.
 */
export const CheckButton = ({labels, findings, open, buttonRef, onToggle}: CheckButtonProps): ReactNode => (
    <button
        ref={buttonRef}
        type="button"
        className="btn btn-sm btn-outline-secondary local-dimensions-hubv2-checkbtn"
        aria-pressed={open}
        onClick={onToggle}
    >
        <i className="fa fa-list-check me-1" aria-hidden="true" />{labels.hubv2_checker}
        {findings !== null && findings > 0 ? (
            <>
                <span className="local-dimensions-hubv2-badge" aria-hidden="true">{findings}</span>
                <span className="visually-hidden">, {fillCount(labels, 'hubv2_findings', findings)}</span>
            </>
        ) : null}
    </button>
);

/**
 * The insights' loading and failure states, shared by the lens's section and the checker.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The skeleton, the failure with its retry, or nothing once read.
 */
const InsightsStatus = ({labels, state, onRetry}: {labels: Labels, state: InsightsState, onRetry: () => void}): ReactNode => {
    if (state.status === 'failed') {
        return (
            <div className="local-dimensions-hubv2-insights-failed" role="alert">
                <p className="mb-2">{labels.hubv2_insights_failed}</p>
                <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onRetry}>
                    {labels.hubv2_insights_retry}
                </button>
            </div>
        );
    }
    if (state.index === null) {
        return (
            <div className="local-dimensions-hubv2-insights-loading" aria-busy="true">
                <div className="local-dimensions-sk" aria-hidden="true">
                    <span className="local-dimensions-sk-block local-dimensions-hubv2-sk-line" />
                    <span className="local-dimensions-sk-block local-dimensions-hubv2-sk-line" />
                    <span className="local-dimensions-sk-block local-dimensions-hubv2-sk-line" />
                </div>
                <span className="visually-hidden" role="status">{labels.hubv2_insights_loading}</span>
            </div>
        );
    }
    return null;
};

/** One line of a legend: a swatch in the mark's shape, the words, the count. */
type LegendItem = {key: string, swatch: string, label: string, count: number};

/**
 * A legend: the marks a lens draws, each with its words and how many competencies carry it.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The legend.
 */
const Legend = ({labels, items}: {labels: Labels, items: LegendItem[]}): ReactNode => (
    <>
        <h5 className="local-dimensions-hubv2-subtitle">{labels.hubv2_lens_legend}</h5>
        <ul className="local-dimensions-hubv2-legend list-unstyled mb-0">
            {items.map((item) => (
                <li key={item.key}>
                    <span className={`local-dimensions-hubv2-swatchbox ${item.swatch}`} aria-hidden="true" />
                    <span className="local-dimensions-hubv2-legend-label">{item.label}</span>
                    <span className="local-dimensions-hubv2-legend-count">{item.count}</span>
                </li>
            ))}
        </ul>
    </>
);

/** What the lens's section needs from the workspace. */
export type LensSectionProps = {
    labels: Labels,
    lens: Lens,
    state: InsightsState,
    measure: Measure,
    // The competencies the tree has loaded, in its order: the unused ones the answer cannot name.
    loaded: number[],
    nameOf: NameOf,
    need: (ids: number[]) => void,
    reveal: (id: number) => void,
    // The registry's decision on the Rule action for a competency, and running it.
    ruleState: (id: number) => ActionState,
    runOn: (action: ActionId, id: number) => void,
    onMeasure: (measure: Measure) => void,
    onRetry: () => void,
};

/**
 * The usage lens's part of the panel: the measure switch, the legend and the unused competencies.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The section's body.
 */
const UsageBody = (props: LensSectionProps): ReactNode => {
    const {labels, state, measure, loaded, nameOf, need, reveal, onMeasure} = props;
    const [shown, setShown] = useState(LIST_PAGE);
    const index = state.index;
    const unused = index ? unusedIds(index, measure, loaded) : {ids: [], complete: true};
    const visible = unused.ids.slice(0, shown);
    useNames(visible, need);
    const measureid = useId();
    if (!index) {
        return null;
    }
    const level = index.answer.usagelevel;
    const notice = usageNotice(labels, level);
    if (level === 'none') {
        return <p className="local-dimensions-hubv2-lens-notice mb-0">{notice}</p>;
    }
    const legend = usageLegend(index, measure).map((line) => ({
        key: line.bucket,
        swatch: BUCKET_CLASSES[line.bucket],
        label: labels[BUCKET_LABELS[line.bucket]],
        count: line.count,
    }));
    return (
        <>
            <p className="text-muted small mb-2">{labels.hubv2_lens_usage_desc}</p>
            {notice ? <p className="local-dimensions-hubv2-lens-notice small mb-2">{notice}</p> : null}
            <span id={measureid} className="visually-hidden">{labels.hubv2_lens_measure}</span>
            <div className="local-dimensions-hubv2-seg local-dimensions-hubv2-seg-wrap mb-2" role="group"
                aria-labelledby={measureid}>
                {MEASURES.map((value) => (
                    <button key={value} type="button" className="local-dimensions-hubv2-seg-cell" aria-pressed={value === measure}
                        onClick={() => onMeasure(value)}>
                        {labels[MEASURE_LABELS[value]]}
                    </button>
                ))}
            </div>
            <Legend labels={labels} items={legend} />
            <h5 className="local-dimensions-hubv2-subtitle">{fill(labels.hubv2_lens_unusedlist, String(unused.ids.length))}</h5>
            {unused.ids.length === 0 ? <p className="text-muted small mb-0">{labels.hubv2_lens_unused_empty}</p> : null}
            {!unused.complete ? <p className="text-muted small mb-1">{labels.hubv2_lens_unused_partial}</p> : null}
            <ul className="local-dimensions-hubv2-lenslist list-unstyled mb-0">
                {visible.map((id) => (
                    <li key={id}>
                        <button type="button" className="btn btn-link btn-sm p-0 text-start" onClick={() => reveal(id)}
                            aria-busy={nameOf(id) === null ? true : undefined}>
                            <Name name={nameOf(id)} />
                        </button>
                    </li>
                ))}
            </ul>
            <ShowMore labels={labels} left={unused.ids.length - visible.length} onMore={() => setShown(shown + LIST_PAGE)} />
        </>
    );
};

/**
 * The rules lens's part of the panel: the legend, then one block per rule.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The section's body.
 */
const RulesBody = (props: LensSectionProps): ReactNode => {
    const {labels, state, nameOf, need, reveal, ruleState, runOn} = props;
    const [shown, setShown] = useState(LIST_PAGE);
    const rules = state.index ? state.index.answer.rules : [];
    const visible = rules.slice(0, shown);
    useNames(visible.flatMap((rule) => [rule.competencyid, ...rule.children.filter((c) => c.required).map((c) => c.id)]), need);
    if (!state.index) {
        return null;
    }
    const counts = rulesLegend(state.index);
    const legend: LegendItem[] = [
        {key: 'fire', swatch: 'local-dimensions-hubv2-lens-ruled', label: labels.hubv2_lens_rule_with, count: counts.fire},
        {key: 'cannotfire', swatch: 'local-dimensions-hubv2-lens-cannotfire', label: labels.hubv2_lens_rule_cannotfire,
            count: counts.cannotfire},
        {key: 'without', swatch: 'local-dimensions-hubv2-lens-quiet', label: labels.hubv2_lens_rule_without, count: counts.without},
    ];
    return (
        <>
            <p className="text-muted small mb-2">{labels.hubv2_lens_rules_desc}</p>
            <Legend labels={labels} items={legend} />
            {rules.length === 0 ? <p className="text-muted small mb-0">{labels.hubv2_lens_rules_empty}</p> : null}
            {visible.map((rule) => {
                const facts = ruleFacts(labels, rule);
                const state = ruleState(rule.competencyid);
                return (
                    <div key={rule.competencyid} className="local-dimensions-hubv2-ruleblock">
                        <h5 className="local-dimensions-hubv2-subtitle">
                            <button type="button" className="btn btn-link btn-sm p-0 text-start"
                                onClick={() => reveal(rule.competencyid)}>
                                <Name name={nameOf(rule.competencyid)} />
                            </button>
                        </h5>
                        {!facts.canfire ? (
                            <p className="local-dimensions-hubv2-status local-dimensions-hubv2-status--warning mb-1">
                                <i className="fa fa-triangle-exclamation" aria-hidden="true" />{labels.hubv2_lens_rule_cannotfire}
                            </p>
                        ) : null}
                        <dl className="local-dimensions-hubv2-kv mb-1">
                            <dt className="local-dimensions-hubv2-kv-label">{labels.hubv2_lens_rule_type}</dt>
                            <dd className="local-dimensions-hubv2-kv-value">{facts.type}</dd>
                            <dt className="local-dimensions-hubv2-kv-label">{labels.hubv2_lens_rule_asks}</dt>
                            <dd className="local-dimensions-hubv2-kv-value">{facts.asks}</dd>
                            {facts.available ? (
                                <>
                                    <dt className="local-dimensions-hubv2-kv-label">{labels.hubv2_lens_rule_available}</dt>
                                    <dd className="local-dimensions-hubv2-kv-value">{facts.available}</dd>
                                </>
                            ) : null}
                            {facts.required.length > 0 ? (
                                <>
                                    <dt className="local-dimensions-hubv2-kv-label">{labels.hubv2_lens_rule_requiredchildren}</dt>
                                    <dd className="local-dimensions-hubv2-kv-value">
                                        {facts.required.map((id, index) => (
                                            <span key={id}>{index > 0 ? ', ' : null}<Name name={nameOf(id)} /></span>
                                        ))}
                                    </dd>
                                </>
                            ) : null}
                            <dt className="local-dimensions-hubv2-kv-label">{labels['tool_lp:outcome']}</dt>
                            <dd className="local-dimensions-hubv2-kv-value">{facts.outcome}</dd>
                        </dl>
                        {state !== 'hidden' ? (
                            <button type="button" className="btn btn-sm btn-outline-secondary" disabled={state === 'disabled'}
                                title={state === 'disabled' ? labels.central_rule_nochildren : undefined}
                                onClick={() => runOn('rule', rule.competencyid)}>
                                <i className="fa fa-diagram-project me-1" aria-hidden="true" />{labels.hubv2_cmd_rule}
                            </button>
                        ) : null}
                    </div>
                );
            })}
            <ShowMore labels={labels} left={rules.length - visible.length} onMore={() => setShown(shown + LIST_PAGE)} />
        </>
    );
};

/**
 * The relations lens's part of the panel: the legend, then each related pair as two names.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The section's body.
 */
const RelationsBody = (props: LensSectionProps): ReactNode => {
    const {labels, state, nameOf, need, reveal} = props;
    const [shown, setShown] = useState(LIST_PAGE);
    const pairs = state.index ? state.index.answer.pairs : [];
    const visible = pairs.slice(0, shown);
    useNames(visible.flatMap((pair) => [pair.a, pair.b]), need);
    if (!state.index) {
        return null;
    }
    const counts = relationsLegend(state.index);
    const legend: LegendItem[] = [
        {key: 'related', swatch: 'local-dimensions-hubv2-lens-related', label: labels.hubv2_lens_related, count: counts.related},
        {key: 'unrelated', swatch: 'local-dimensions-hubv2-lens-quiet', label: labels.hubv2_lens_unrelated,
            count: counts.unrelated},
    ];
    return (
        <>
            <p className="text-muted small mb-2">{labels.hubv2_lens_relations_desc}</p>
            <Legend labels={labels} items={legend} />
            <h5 className="local-dimensions-hubv2-subtitle">{fill(labels.hubv2_lens_pairs, String(pairs.length))}</h5>
            {pairs.length === 0 ? <p className="text-muted small mb-0">{labels.hubv2_lens_pairs_empty}</p> : null}
            <ul className="local-dimensions-hubv2-lenslist list-unstyled mb-0">
                {visible.map((pair) => {
                    const a = nameOf(pair.a);
                    const b = nameOf(pair.b);
                    return (
                        <li key={`${pair.a}-${pair.b}`} aria-label={a !== null && b !== null ? pairWords(labels, a, b) : undefined}>
                            <button type="button" className="btn btn-link btn-sm p-0 text-start" onClick={() => reveal(pair.a)}>
                                <Name name={a} />
                            </button>
                            <i className="fa fa-arrows-left-right mx-2 local-dimensions-hubv2-pairsep" aria-hidden="true" />
                            <button type="button" className="btn btn-link btn-sm p-0 text-start" onClick={() => reveal(pair.b)}>
                                <Name name={b} />
                            </button>
                        </li>
                    );
                })}
            </ul>
            <ShowMore labels={labels} left={pairs.length - visible.length} onMore={() => setShown(shown + LIST_PAGE)} />
        </>
    );
};

/**
 * The lens's section at the top of the panel: its name, then its body once the insights are read.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The section, or nothing without a lens.
 */
export const LensSection = (props: LensSectionProps): ReactNode => {
    const {labels, lens, state, onRetry} = props;
    if (lens === 'none') {
        return null;
    }
    let body: ReactNode = null;
    if (lens === 'usage') {
        body = <UsageBody {...props} />;
    } else if (lens === 'rules') {
        body = <RulesBody {...props} />;
    } else {
        body = <RelationsBody {...props} />;
    }
    return (
        <section className="local-dimensions-hubv2-sec local-dimensions-hubv2-lenssec" data-region="lens">
            <p className="local-dimensions-hubv2-kicker">{labels.hubv2_lens}</p>
            <h4 className="local-dimensions-hubv2-sectitle">{labels[LENS_LABELS[lens]]}</h4>
            <InsightsStatus labels={labels} state={state} onRetry={onRetry} />
            {body}
        </section>
    );
};

/** What the checker's panel needs from the workspace. */
export type CheckerProps = {
    labels: Labels,
    state: InsightsState,
    // The findings in the list's order (findings.orderFindings).
    findings: InsightCheck[],
    // The finding chosen last, by the list or by F8.
    current: number | null,
    frameworkname: string,
    canmanage: boolean,
    nameOf: NameOf,
    need: (ids: number[]) => void,
    // The registry's decision on an action for a competency.
    actionFor: (action: ActionId, id: number) => ActionState,
    onReveal: (index: number) => void,
    onFix: (action: ActionId, id: number) => void,
    onAgain: () => void,
    onClose: () => void,
    headingRef: RefObject<HTMLHeadingElement | null>,
};

/**
 * The checker's panel mode: the findings of the framework's structure, each with what fixes it.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The panel's content.
 */
export const Checker = (props: CheckerProps): ReactNode => {
    const {labels, state, findings, current, frameworkname, canmanage, nameOf, need, actionFor, onReveal, onFix, onAgain,
        onClose, headingRef} = props;
    const [shown, setShown] = useState(LIST_PAGE);
    // The chosen finding is always shown, past the first page if need be.
    const limit = Math.max(shown, current !== null ? current + 1 : 0);
    const visible = findings.slice(0, limit);
    useNames(visible.map((check) => check.competencyid).filter((id) => id > 0), need);
    const index = state.index;
    const absent = index ? absentCodes(index.answer.checks, index.answer.usagelevel === 'exact') : [];
    return (
        <section className="local-dimensions-hubv2-sec local-dimensions-hubv2-checker" data-region="checker">
            <div className="local-dimensions-hubv2-checker-head">
                <p className="local-dimensions-hubv2-kicker mb-0">{labels.hubv2_checker_title}</p>
                <button type="button" className="local-dimensions-hubv2-iconbtn local-dimensions-tip local-dimensions-tip-bottom"
                    aria-label={labels.hubv2_checker_close} data-dim-tip={labels.hubv2_checker_close} onClick={onClose}>
                    <i className="fa fa-xmark" aria-hidden="true" />
                </button>
            </div>
            <h4 ref={headingRef} tabIndex={-1} className="h5 mb-1">
                {index ? fillCount(labels, 'hubv2_findings', index.answer.checks.length) : labels.hubv2_checker_title}
            </h4>
            <InsightsStatus labels={labels} state={state} onRetry={onAgain} />
            {index ? (
                <>
                    <p className="text-muted small mb-2">{labels.hubv2_checker_intro}</p>
                    {findings.length > 0 ? (
                        <ul className="local-dimensions-hubv2-codes list-unstyled mb-2">
                            {findingGroups(findings).map((group) => (
                                <li key={group.code} className="local-dimensions-hubv2-status">
                                    {codeTitle(labels, group.code)}
                                    <span className="local-dimensions-hubv2-count">{group.count}</span>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                    <button type="button" className="btn btn-sm btn-outline-secondary mb-2" onClick={onAgain}
                        disabled={state.status === 'loading'}>
                        <i className="fa fa-rotate me-1" aria-hidden="true" />{labels.hubv2_checker_again}
                    </button>
                    {findings.length === 0 ? (
                        <p className="local-dimensions-hubv2-empty--compact mb-0">{labels.hubv2_checker_none}</p>
                    ) : null}
                    <ol className="local-dimensions-hubv2-findings list-unstyled mb-0">
                        {visible.map((check, position) => {
                            const fix = fixFor(check, canmanage);
                            const fixstate = fix ? actionFor(fix, check.competencyid) : 'hidden';
                            const chosen = position === current;
                            const name = check.competencyid > 0 ? nameOf(check.competencyid) : frameworkname;
                            const sentence = findingSentence(labels, check);
                            return (
                                <li key={`${check.code}-${check.competencyid}`}
                                    className={chosen ? 'local-dimensions-hubv2-finding local-dimensions-hubv2-finding--chosen'
                                        : 'local-dimensions-hubv2-finding'}
                                    aria-current={chosen ? 'true' : undefined}>
                                    <p className="local-dimensions-hubv2-finding-title mb-0">
                                        <i className="fa fa-triangle-exclamation me-1" aria-hidden="true" />
                                        {codeTitle(labels, check.code)}
                                    </p>
                                    <p className="local-dimensions-hubv2-finding-name mb-1"><Name name={name} /></p>
                                    {sentence ? <p className="small mb-1">{sentence}</p> : null}
                                    <div className="d-flex flex-wrap gap-2">
                                        {fix && fixstate !== 'hidden' ? (
                                            <button type="button" className="btn btn-sm btn-outline-secondary"
                                                disabled={fixstate === 'disabled'}
                                                title={fixstate === 'disabled' ? labels.central_rule_nochildren : undefined}
                                                onClick={() => onFix(fix, check.competencyid)}>
                                                {fix === 'rule' ? labels.hubv2_cmd_rule : null}
                                                {fix === 'edit' ? labels.hubv2_edit : null}
                                                {fix === 'links' ? labels.hubv2_cmd_links : null}
                                            </button>
                                        ) : null}
                                        <button type="button" className="btn btn-sm btn-outline-secondary" aria-pressed={chosen}
                                            aria-label={name !== null ? fill(labels.hubv2_checker_revealname, name) : undefined}
                                            onClick={() => onReveal(position)}>
                                            {labels.hubv2_checker_reveal}
                                        </button>
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                    <ShowMore labels={labels} left={findings.length - visible.length} onMore={() => setShown(limit + LIST_PAGE)} />
                    {absent.length > 0 ? (
                        <p className="text-muted small mt-2 mb-0">
                            {fill(labels.hubv2_checker_clean, absent.map((code) => codeTitle(labels, code)).join(', '))}
                        </p>
                    ) : null}
                </>
            ) : null}
        </section>
    );
};
