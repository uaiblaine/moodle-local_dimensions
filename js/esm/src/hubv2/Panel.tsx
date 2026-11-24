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
 * node's own menu under itself (the destructive action lives there, last). With nothing selected it shows
 * the framework's summary (summary.ts), which the palette's command brings into view, and "Add competency".
 * During a move it shows what the move needs.
 *
 * @module     local_dimensions/hubv2/Panel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactNode, RefObject} from 'react';
import type {ActionId, ActionView} from './commands';
import {isHexColour} from './html';
import type {SummaryLine} from './summary';
import {fill, fillCount} from './strings';
import type {Labels} from './strings';
import type {Framework, PathItem, StructureNode, Usage} from './types';

/** What the panel's buttons do: run an action of the registry, open the node's menu, reveal a relation. */
export type PanelActions = {
    run: (id: ActionId) => void,
    menu: (anchor: HTMLElement) => void,
    reveal: (item: PathItem) => void,
};

/** The actions the panel draws as buttons; every other one is in "More actions". */
const PANEL_BUTTONS: ActionId[] = ['edit', 'addchild'];

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
    summary: SummaryLine[],
    // The summary's heading, which the palette's "Framework summary" focuses.
    summaryRef: RefObject<HTMLHeadingElement | null>,
    actions: PanelActions,
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
 * The buttons of a list of actions, each with its icon; a disabled one says why when it can.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The buttons.
 */
const ActionButtons = ({labels, views, run}: {labels: Labels, views: ActionView[], run: (id: ActionId) => void}): ReactNode => (
    <div className="local-dimensions-hubv2-actions d-flex flex-wrap gap-2">
        {views.map((view, index) => (
            <button
                key={view.spec.id}
                type="button"
                className={buttonClass(view, index === 0)}
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
 * The panel.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The panel.
 */
const Panel = (props: PanelProps): ReactNode => {
    const {labels, framework, count, node, path, usage, related, moving, nodeactions, menuopen, moveactions, topactions,
        summary, summaryRef, actions} = props;
    const movesection = moving ? <MoveSection labels={labels} moving={moving} views={moveactions} run={actions.run} /> : null;

    if (!node) {
        return (
            <aside className="local-dimensions-hubv2-panel" aria-label={framework.shortname}>
                {movesection}
                <section className="local-dimensions-hubv2-sec">
                    <p className="local-dimensions-hubv2-kicker">
                        {labels.central_browseframeworks_framework}
                        {framework.idnumber ? <> · {framework.idnumber}</> : null}
                    </p>
                    <h3 className="h5 mb-1">{framework.shortname}</h3>
                    <p className="text-muted mb-0">{fillCount(labels, 'hubv2_competencies', count)}</p>
                </section>
                <section className="local-dimensions-hubv2-sec">
                    <h4 ref={summaryRef} tabIndex={-1} className="local-dimensions-hubv2-sectitle">{labels.hubv2_summary_title}</h4>
                    <dl className="local-dimensions-hubv2-kv mb-0">
                        {summary.map((line) => <Pair key={line.label} label={line.label}>{line.value}</Pair>)}
                    </dl>
                    <p className="text-muted small mb-0">{labels.hubv2_noselection}</p>
                </section>
                {topactions.length > 0 ? (
                    <section className="local-dimensions-hubv2-sec">
                        <ActionButtons labels={labels} views={topactions} run={actions.run} />
                    </section>
                ) : null}
            </aside>
        );
    }

    return (
        <aside className="local-dimensions-hubv2-panel" aria-label={node.shortname}>
            {movesection}
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
                        <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary"
                            aria-haspopup="menu"
                            aria-expanded={menuopen}
                            onClick={(event) => actions.menu(event.currentTarget)}
                        >
                            {labels.hubv2_moreactions}<i className="fa fa-chevron-down ms-1" aria-hidden="true" />
                        </button>
                    </div>
                </section>
            ) : null}
        </aside>
    );
};

export default Panel;
