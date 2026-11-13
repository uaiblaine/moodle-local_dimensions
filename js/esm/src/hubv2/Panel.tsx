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
 * The panel beside the canvas: the selected competency's details and actions, or the framework's
 * when nothing is selected, and the controls of the move mode while it lasts.
 *
 * @module     local_dimensions/hubv2/Panel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactNode} from 'react';
import {isHexColour} from './html';
import {fill, fillCount} from './strings';
import type {Labels} from './strings';
import type {Framework, PathItem, StructureNode, Usage} from './types';

/** What the panel's buttons do. */
export type PanelActions = {
    edit: (node: StructureNode) => void,
    addChild: (node: StructureNode | null) => void,
    rule: (node: StructureNode) => void,
    links: (node: StructureNode) => void,
    relate: (node: StructureNode) => void,
    move: (node: StructureNode) => void,
    moveRoot: () => void,
    cancelMove: () => void,
    moveOne: (node: StructureNode, up: boolean) => void,
    remove: (node: StructureNode) => void,
    reveal: (item: PathItem) => void,
};

/** The panel's props. */
export type PanelProps = {
    labels: Labels,
    framework: Framework,
    count: number,
    node: StructureNode | null,
    path: string[],
    usage: Usage | null,
    related: PathItem[],
    onCanvas: (id: number) => boolean,
    moving: StructureNode | null,
    canUp: boolean,
    canDown: boolean,
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
 * The move mode's controls: what to do next, top level, cancel.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The section.
 */
const MoveSection = ({labels, moving, actions}: {labels: Labels, moving: StructureNode, actions: PanelActions}): ReactNode => (
    <section className="local-dimensions-hubv2-sec local-dimensions-hubv2-movesec">
        <p className="mb-2">{fill(labels.hubv2_movepick, moving.shortname)}</p>
        <div className="d-flex flex-wrap gap-2">
            {moving.parentid !== 0 ? (
                <button type="button" className="btn btn-sm btn-primary" onClick={actions.moveRoot}>{labels.hubv2_moveroot}</button>
            ) : null}
            <button type="button" className="btn btn-sm btn-secondary" onClick={actions.cancelMove}>
                {labels.hubv2_movecancel}
            </button>
        </div>
    </section>
);

/**
 * The panel.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The panel.
 */
const Panel = (props: PanelProps): ReactNode => {
    const {labels, framework, count, node, path, usage, related, onCanvas, moving, canUp, canDown, actions} = props;

    if (!node) {
        return (
            <aside className="local-dimensions-hubv2-panel" aria-label={framework.shortname}>
                {moving ? <MoveSection labels={labels} moving={moving} actions={actions} /> : null}
                <section className="local-dimensions-hubv2-sec">
                    <p className="local-dimensions-hubv2-kicker">
                        {labels.central_browseframeworks_framework}
                        {framework.idnumber ? <> · {framework.idnumber}</> : null}
                    </p>
                    <h3 className="h5 mb-1">{framework.shortname}</h3>
                    <p className="text-muted mb-0">{fillCount(labels, 'hubv2_competencies', count)}</p>
                </section>
                <section className="local-dimensions-hubv2-sec">
                    <p className="text-muted mb-0">{labels.hubv2_noselection}</p>
                </section>
                {framework.canmanage && !moving ? (
                    <section className="local-dimensions-hubv2-sec">
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => actions.addChild(null)}>
                            <i className="fa fa-plus me-1" aria-hidden="true" />{labels.managecompetencies_addcompetency}
                        </button>
                    </section>
                ) : null}
            </aside>
        );
    }

    // Related competencies on the canvas are drawn as dotted edges; the others are listed here.
    const offcanvas = related.filter((item) => !onCanvas(item.id));
    return (
        <aside className="local-dimensions-hubv2-panel" aria-label={node.shortname}>
            {moving ? <MoveSection labels={labels} moving={moving} actions={actions} /> : null}
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
                {offcanvas.length > 0 ? (
                    <>
                        <p className="small text-muted mb-1">{labels.hubv2_related_offcanvas}</p>
                        <ul className="list-unstyled small mb-0">
                            {offcanvas.map((item) => (
                                <li key={item.id}>
                                    <button type="button" className="btn btn-link btn-sm p-0 text-start"
                                        onClick={() => actions.reveal(item)}>
                                        {item.shortname}
                                    </button>
                                    {item.path ? <span className="text-muted"> · {item.path}</span> : null}
                                </li>
                            ))}
                        </ul>
                    </>
                ) : null}
            </section>

            {node.canmanage && !moving ? (
                <section className="local-dimensions-hubv2-sec">
                    <h4 className="local-dimensions-hubv2-sectitle">{labels.hubv2_actions}</h4>
                    <div className="local-dimensions-hubv2-actions d-flex flex-wrap gap-2">
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => actions.edit(node)}>
                            <i className="fa fa-pen me-1" aria-hidden="true" />{labels.hubv2_edit}
                        </button>
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => actions.addChild(node)}>
                            <i className="fa fa-plus me-1" aria-hidden="true" />{labels.managecompetencies_addchild}
                        </button>
                        <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary"
                            disabled={!node.haschildren}
                            title={node.haschildren ? undefined : labels.central_rule_nochildren}
                            onClick={() => actions.rule(node)}
                        >
                            <i className="fa fa-diagram-project me-1" aria-hidden="true" />{labels.hubv2_rule}
                        </button>
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => actions.links(node)}>
                            <i className="fa fa-link me-1" aria-hidden="true" />{labels.central_links_button}
                        </button>
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => actions.relate(node)}>
                            <i className="fa fa-share-nodes me-1" aria-hidden="true" />{labels.hubv2_relate}
                        </button>
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => actions.move(node)}>
                            <i className="fa fa-arrows-up-down-left-right me-1" aria-hidden="true" />{labels.hubv2_move}
                        </button>
                        <button type="button" className="btn btn-sm btn-outline-secondary" disabled={!canUp}
                            onClick={() => actions.moveOne(node, true)}>
                            <i className="fa fa-arrow-up me-1" aria-hidden="true" />{labels.hubv2_moveup}
                        </button>
                        <button type="button" className="btn btn-sm btn-outline-secondary" disabled={!canDown}
                            onClick={() => actions.moveOne(node, false)}>
                            <i className="fa fa-arrow-down me-1" aria-hidden="true" />{labels.hubv2_movedown}
                        </button>
                        <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => actions.remove(node)}>
                            <i className="fa fa-trash me-1" aria-hidden="true" />{labels.hubv2_delete}
                        </button>
                    </div>
                </section>
            ) : null}
        </aside>
    );
};

export default Panel;
