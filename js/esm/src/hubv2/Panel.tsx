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
 * The panel beside the outline and the map: the selected competency's details and actions, or the
 * framework's when nothing is selected, and the controls of the move mode while it lasts.
 *
 * Every related competency is listed in words, each a button that reveals it in both panes; the map
 * draws, besides, a dotted edge to those it shows. A relation is never said by the drawing alone.
 *
 * Its buttons are the action registry's (commands.ts), the same list and the same decision the node's
 * menu and the command palette draw from: "Edit" and "Add child", then "More actions", which opens the
 * node's own menu under itself (the destructive action lives there, last). With nothing selected the root is
 * selected, and the panel shows the framework: its name, ID number, context, visibility (a status label), scale
 * with the values the framework counts as proficient, the taxonomy term of each level, the competencies' total
 * and the summary (summary.ts), which the palette's command brings into view; then the framework's own actions
 * ("Edit structure", then "More actions", the root's menu) and "Add competency". During a move it shows what the
 * move needs.
 *
 * A lens shown puts its section first (its sentence, legend and list, LensPanel.tsx), above whatever is selected;
 * the checker, while open, is the panel's whole content until its close button or a selection closes it.
 *
 * Under a selected competency's own content come the sections other plugins add (sections.ts), one block each
 * in the order received: a heading, a definition list of plain rows (a tone drawn as a status label around its
 * text, never colour alone), a muted note and one link to a page of this site in the same tab. No section draws
 * nothing at all; a failed read says so in one muted line and leaves the rest of the panel as it is.
 *
 * @module     local_dimensions/hubv2/Panel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactNode, RefObject} from 'react';
import type {ActionId, ActionView} from './commands';
import {isHexColour} from './html';
import type {SummaryLine} from './summary';
import {headingId, toneClass} from './sections';
import type {SectionsState} from './sections';
import {fill, fillCount} from './strings';
import type {Labels} from './strings';
import type {Framework, PanelSection, PathItem, StructureNode, Usage} from './types';

/** What the panel's buttons do: run an action of the registry, open the node's menu, reveal a relation. */
export type PanelActions = {
    run: (id: ActionId) => void,
    menu: (anchor: HTMLElement) => void,
    reveal: (item: PathItem) => void,
};

/** The actions the panel draws as buttons; every other one is in "More actions". */
const PANEL_BUTTONS: ActionId[] = ['edit', 'addchild'];

/** The framework's action the root's panel draws as its button; the rest are in "More actions". */
const FRAMEWORK_BUTTONS: ActionId[] = ['fwedit'];

/** The panel's props. */
export type PanelProps = {
    labels: Labels,
    framework: Framework,
    count: number,
    node: StructureNode | null,
    path: string[],
    usage: Usage | null,
    related: PathItem[],
    moving: StructureNode | null,
    // The registry's actions offered here: on the selected competency, on the move, at the top level.
    nodeactions: ActionView[],
    // Whether the node's menu is open from the panel's "More actions".
    menuopen: boolean,
    moveactions: ActionView[],
    topactions: ActionView[],
    // The registry's actions on the framework, offered on the root.
    frameworkactions: ActionView[],
    summary: SummaryLine[],
    // The summary's heading, which the palette's "Framework summary" focuses.
    summaryRef: RefObject<HTMLHeadingElement | null>,
    actions: PanelActions,
    // The lens's section (LensPanel.tsx LensSection), first in the panel while a lens is shown.
    lens?: ReactNode,
    // The checker (LensPanel.tsx Checker): while it is open it is the panel's whole content.
    checker?: ReactNode,
    // The selected competency's companion sections (sections.ts); absent or idle draws nothing.
    sections?: SectionsState,
};

/**
 * A label and its value, in the panel's two-column list.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The pair.
 */
const Pair = ({label, children}: {label: string, children: ReactNode}): ReactNode => (
    <>
        <dt className="local-dimensions-hubv2-kv-label">{label}</dt>
        <dd className="local-dimensions-hubv2-kv-value">{children}</dd>
    </>
);

/**
 * The usage lists, behind the "See usage" disclosure.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The lists.
 */
const UsageLists = ({labels, usage}: {labels: Labels, usage: Usage | null}): ReactNode => {
    if (!usage) {
        return <p className="text-muted small mb-0">{labels.hubv2_loading}</p>;
    }
    const empty = usage.templates.length + usage.courses.length + usage.activities.length === 0;
    if (empty) {
        return <p className="text-muted small mb-0">{labels.hubv2_usage_none}</p>;
    }
    return (
        <div className="local-dimensions-hubv2-usagelists small">
            {usage.templates.length > 0 ? (
                <>
                    <h4 className="h6 mt-2">{labels.hubv2_usage_templates}</h4>
                    <ul className="list-unstyled mb-0">{usage.templates.map((t) => <li key={`t${t.id}`}>{t.name}</li>)}</ul>
                </>
            ) : null}
            {usage.courses.length > 0 ? (
                <>
                    <h4 className="h6 mt-2">{labels.hubv2_usage_courses}</h4>
                    <ul className="list-unstyled mb-0">
                        {usage.courses.map((c) => <li key={`c${c.id}`}><a href={c.url}>{c.name}</a></li>)}
                    </ul>
                </>
            ) : null}
            {usage.activities.length > 0 ? (
                <>
                    <h4 className="h6 mt-2">{labels.hubv2_usage_activities}</h4>
                    <ul className="list-unstyled mb-0">
                        {usage.activities.map((a) => (
                            <li key={`a${a.cmid}`}>
                                {a.url ? <a href={a.url}>{a.name}</a> : a.name}
                                <span className="text-muted"> · {a.courseshortname}</span>
                            </li>
                        ))}
                    </ul>
                </>
            ) : null}
        </div>
    );
};

/**
 * The Bootstrap classes of an action's button: the first of the list is the primary one, a destructive
 * one is drawn as such.
 *
 * @param {object} view The action.
 * @param {boolean} first Whether it is the list's first.
 * @returns {string} The classes.
 */
const buttonClass = (view: ActionView, first: boolean): string => {
    if (view.spec.danger) {
        return 'btn btn-sm btn-outline-danger';
    }
    return first ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-secondary';
};

/**
 * The "More actions" button, which opens the selection's menu under itself.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The button.
 */
const MoreButton = ({labels, open, menu}: {labels: Labels, open: boolean, menu: (anchor: HTMLElement) => void}): ReactNode => (
    <button
        type="button"
        className="btn btn-sm btn-outline-secondary"
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={(event) => menu(event.currentTarget)}
    >
        {labels.hubv2_moreactions}<i className="fa fa-chevron-down ms-1" aria-hidden="true" />
    </button>
);

/**
 * The classes of the framework's visibility label: the success tone while visible, the neutral one while hidden.
 *
 * @param {boolean} visible Whether the framework is visible.
 * @returns {string} The classes.
 */
const visibilityClass = (visible: boolean): string => (visible
    ? 'local-dimensions-hubv2-status local-dimensions-hubv2-status--success'
    : 'local-dimensions-hubv2-status');

/**
 * The framework's facts on the root's panel: its context, visibility, scale (each value with its marks) and the
 * taxonomy term of each level it names.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The section.
 */
const FrameworkFacts = ({labels, framework}: {labels: Labels, framework: Framework}): ReactNode => (
    <>
        <section className="local-dimensions-hubv2-sec">
            <dl className="local-dimensions-hubv2-kv mb-0">
                <Pair label={labels.hubv2_fw_context}>{framework.contextname}</Pair>
                <Pair label={labels.hubv2_fw_visibility}>
                    <span data-region="framework-visibility" className={visibilityClass(framework.visible)}>
                        <i className={`fa ${framework.visible ? 'fa-eye' : 'fa-eye-slash'}`} aria-hidden="true" />
                        {labels[framework.visible ? 'tool_lp:visible' : 'tool_lp:hidden']}
                    </span>
                </Pair>
                <Pair label={labels.hubv2_scale}>{framework.scale.name || '-'}</Pair>
            </dl>
            {framework.scale.values.length > 0 ? (
                <ul className="local-dimensions-hubv2-scalevalues list-unstyled mb-0">
                    {framework.scale.values.map((value) => (
                        <li key={value.id}>
                            <span className="local-dimensions-hubv2-scalevalue">{value.name}</span>
                            {value.proficient ? (
                                <span className="local-dimensions-hubv2-status local-dimensions-hubv2-status--success">
                                    {labels.central_frameworks_scaleproficient}
                                </span>
                            ) : null}
                            {value.isdefault ? (
                                <span className="local-dimensions-hubv2-status">{labels.central_frameworks_scaledefault}</span>
                            ) : null}
                        </li>
                    ))}
                </ul>
            ) : null}
        </section>
        {framework.taxonomies.length > 0 ? (
            <section className="local-dimensions-hubv2-sec">
                <h4 className="local-dimensions-hubv2-sectitle">{labels['tool_lp:taxonomies']}</h4>
                <dl className="local-dimensions-hubv2-kv mb-0">
                    {framework.taxonomies.map((term, index) => (
                        <Pair key={index} label={fill(labels.hubv2_fw_level, String(index + 1))}>{term}</Pair>
                    ))}
                </dl>
            </section>
        ) : null}
    </>
);

/**
 * The buttons of a list of actions, each with its icon; a disabled one says why when it can.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The buttons.
 */
const ActionButtons = ({labels, views, run, secondary = false}: {
    labels: Labels,
    views: ActionView[],
    run: (id: ActionId) => void,
    // Whether the surface's primary action is elsewhere, so none of these takes the solid fill.
    secondary?: boolean,
}): ReactNode => (
    <div className="local-dimensions-hubv2-actions d-flex flex-wrap gap-2">
        {views.map((view, index) => (
            <button
                key={view.spec.id}
                type="button"
                className={buttonClass(view, index === 0 && !secondary)}
                disabled={view.state === 'disabled'}
                title={view.state === 'disabled' && view.spec.id === 'rule' ? labels.central_rule_nochildren : undefined}
                onClick={() => run(view.spec.id)}
            >
                <i className={`fa ${view.spec.icon} me-1`} aria-hidden="true" />{labels[view.spec.label]}
            </button>
        ))}
    </div>
);

/**
 * The move mode's controls: what to do next, and the move's own actions (top level, cancel).
 *
 * @param {object} props The props.
 * @returns {ReactNode} The section.
 */
const MoveSection = ({labels, moving, views, run}: {
    labels: Labels,
    moving: StructureNode,
    views: ActionView[],
    run: (id: ActionId) => void,
}): ReactNode => (
    <section className="local-dimensions-hubv2-sec local-dimensions-hubv2-movesec">
        <p className="mb-2">{fill(labels.hubv2_movepick, moving.shortname)}</p>
        <ActionButtons labels={labels} views={views} run={run} />
    </section>
);

/**
 * One section another plugin adds: its heading, its rows, its note and its link, every text plain.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The section.
 */
const CompanionSection = ({section}: {section: PanelSection}): ReactNode => (
    <section className="local-dimensions-hubv2-sec" data-region="companion-section" data-key={section.key}
        aria-labelledby={headingId(section.key)}>
        <h4 id={headingId(section.key)} className="local-dimensions-hubv2-sectitle">{section.title}</h4>
        {section.rows.length > 0 ? (
            <dl className="local-dimensions-hubv2-kv mb-0">
                {section.rows.map((row, index) => (
                    <Pair key={index} label={row.label}>
                        {row.tone ? <span className={toneClass(row.tone)}>{row.value}</span> : row.value}
                    </Pair>
                ))}
            </dl>
        ) : null}
        {section.note ? <p className="text-muted small mb-0">{section.note}</p> : null}
        {section.linkurl ? (
            <a className="btn btn-link btn-sm p-0 text-start" href={section.linkurl}>{section.linklabel}</a>
        ) : null}
    </section>
);

/**
 * The companion sections of the selection, in the order received; a failed read is one muted line.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The sections, the line, or nothing.
 */
export const CompanionSections = ({labels, state}: {labels: Labels, state?: SectionsState}): ReactNode => {
    if (!state) {
        return null;
    }
    if (state.status === 'failed') {
        return (
            <p className="local-dimensions-hubv2-sec text-muted small mb-0" data-region="companion-sections-failed">
                {labels.hubv2_sections_failed}
            </p>
        );
    }
    return <>{state.sections.map((section) => <CompanionSection key={section.key} section={section} />)}</>;
};

/**
 * The panel.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The panel.
 */
const Panel = (props: PanelProps): ReactNode => {
    const {labels, framework, count, node, path, usage, related, moving, nodeactions, menuopen, moveactions, topactions,
        frameworkactions, summary, summaryRef, actions, lens, checker, sections} = props;
    const movesection = moving ? <MoveSection labels={labels} moving={moving} views={moveactions} run={actions.run} /> : null;

    if (checker) {
        return (
            <aside className="local-dimensions-hubv2-panel" aria-label={labels.hubv2_checker_title}>
                {movesection}
                {checker}
            </aside>
        );
    }

    if (!node) {
        return (
            <aside className="local-dimensions-hubv2-panel" aria-label={framework.shortname}>
                {movesection}
                {lens}
                <section className="local-dimensions-hubv2-sec">
                    <p className="local-dimensions-hubv2-kicker">
                        {labels.central_browseframeworks_framework}
                        {framework.idnumber ? <> · {framework.idnumber}</> : null}
                    </p>
                    <h3 className="h5 mb-1">{framework.shortname}</h3>
                    <p className="text-muted mb-0">{fillCount(labels, 'hubv2_competencies', count)}</p>
                </section>
                <FrameworkFacts labels={labels} framework={framework} />
                <section className="local-dimensions-hubv2-sec">
                    <h4 ref={summaryRef} tabIndex={-1} className="local-dimensions-hubv2-sectitle">{labels.hubv2_summary_title}</h4>
                    <dl className="local-dimensions-hubv2-kv mb-0">
                        {summary.map((line) => <Pair key={line.label} label={line.label}>{line.value}</Pair>)}
                    </dl>
                    <p className="text-muted small mb-0">{labels.hubv2_noselection}</p>
                </section>
                {frameworkactions.length > 0 ? (
                    <section className="local-dimensions-hubv2-sec">
                        <h4 className="local-dimensions-hubv2-sectitle">{labels.hubv2_actions}</h4>
                        <div className="local-dimensions-hubv2-actions d-flex flex-wrap gap-2">
                            <ActionButtons labels={labels}
                                views={frameworkactions.filter((view) => FRAMEWORK_BUTTONS.includes(view.spec.id))}
                                run={actions.run} />
                            <MoreButton labels={labels} open={menuopen} menu={actions.menu} />
                        </div>
                    </section>
                ) : null}
                {topactions.length > 0 ? (
                    <section className="local-dimensions-hubv2-sec">
                        <ActionButtons labels={labels} views={topactions} run={actions.run}
                            secondary={frameworkactions.length > 0} />
                    </section>
                ) : null}
            </aside>
        );
    }

    return (
        <aside className="local-dimensions-hubv2-panel" aria-label={node.shortname}>
            {movesection}
            {lens}
            <section className="local-dimensions-hubv2-sec">
                <p className="local-dimensions-hubv2-kicker">
                    {node.taxonomy}
                    {node.idnumber ? (
                        <> · <span className="visually-hidden">{labels['core:idnumber']} </span>{node.idnumber}</>
                    ) : null}
                </p>
                <h3 className="h5 mb-1">{node.shortname}</h3>
                <p className="text-muted small mb-0">
                    <span className="visually-hidden">{labels.hubv2_path}: </span>
                    {path.length > 0 ? path.join(' / ') : labels.hubv2_rootlevel}
                </p>
            </section>

            <section className="local-dimensions-hubv2-sec">
                <h4 className="local-dimensions-hubv2-sectitle">{labels.hubv2_usage}</h4>
                <ul className="local-dimensions-hubv2-usage list-unstyled mb-2">
                    <li><b>{node.templatecount}</b><span>{labels.hubv2_usage_templates}</span></li>
                    <li><b>{node.coursecount}</b><span>{labels.hubv2_usage_courses}</span></li>
                    <li><b>{node.activitycount}</b><span>{labels.hubv2_usage_activities}</span></li>
                </ul>
                <details className="local-dimensions-hubv2-details">
                    <summary>{labels.hubv2_seeusage}</summary>
                    <UsageLists labels={labels} usage={usage} />
                </details>
            </section>

            <section className="local-dimensions-hubv2-sec">
                <h4 className="local-dimensions-hubv2-sectitle">{labels.hubv2_assessment}</h4>
                <dl className="local-dimensions-hubv2-kv mb-0">
                    <Pair label={labels.hubv2_scale}>{node.scale}</Pair>
                    <Pair label={labels.hubv2_rule}>{node.ruletype ? node.rulelabel : labels.managecompetencies_norule}</Pair>
                </dl>
            </section>

            <section className="local-dimensions-hubv2-sec">
                <h4 className="local-dimensions-hubv2-sectitle">{labels.hubv2_appearance}</h4>
                <dl className="local-dimensions-hubv2-kv mb-0">
                    <Pair label={labels.hubv2_colour}>
                        {isHexColour(node.bgcolor) ? (
                            <>
                                <span className="local-dimensions-hubv2-swatch" aria-hidden="true"
                                    style={{backgroundColor: node.bgcolor}} />
                                {node.bgcolor}
                            </>
                        ) : labels.hubv2_nocolour}
                    </Pair>
                    <Pair label={labels.hubv2_tag1}>{node.tag1 || '-'}</Pair>
                    <Pair label={labels.hubv2_tag2}>{node.tag2 || '-'}</Pair>
                    <Pair label={labels.hubv2_type}>{node.type || '-'}</Pair>
                </dl>
            </section>

            {node.description ? (
                <section className="local-dimensions-hubv2-sec">
                    <h4 className="local-dimensions-hubv2-sectitle">{labels.hubv2_description}</h4>
                    {/* The one HTML sink of the canvas: helper::structure_nodes() ran the description through
                        format_text() in the framework's context, so it is trusted server HTML. */}
                    <div className="local-dimensions-hubv2-description" dangerouslySetInnerHTML={{__html: node.description}} />
                </section>
            ) : null}

            <section className="local-dimensions-hubv2-sec">
                <h4 className="local-dimensions-hubv2-sectitle">{labels.central_related_button} ({related.length})</h4>
                {related.length === 0 ? <p className="text-muted small mb-0">{labels.central_related_empty}</p> : null}
                {related.length > 0 ? (
                    <ul className="list-unstyled small mb-0">
                        {related.map((item) => (
                            <li key={item.id}>
                                <button type="button" className="btn btn-link btn-sm p-0 text-start"
                                    onClick={() => actions.reveal(item)}>
                                    {item.shortname}
                                </button>
                                {item.path ? <span className="text-muted"> · {item.path}</span> : null}
                            </li>
                        ))}
                    </ul>
                ) : null}
            </section>

            {nodeactions.length > 0 ? (
                <section className="local-dimensions-hubv2-sec">
                    <h4 className="local-dimensions-hubv2-sectitle">{labels.hubv2_actions}</h4>
                    <div className="local-dimensions-hubv2-actions d-flex flex-wrap gap-2">
                        <ActionButtons labels={labels} views={nodeactions.filter((view) => PANEL_BUTTONS.includes(view.spec.id))}
                            run={actions.run} />
                        <MoreButton labels={labels} open={menuopen} menu={actions.menu} />
                    </div>
                </section>
            ) : null}

            <CompanionSections labels={labels} state={sections} />
        </aside>
    );
};

export default Panel;
