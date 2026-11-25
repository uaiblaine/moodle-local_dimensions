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
 * The shapes the canvas receives from the page and from the hub's web services.
 *
 * @module     local_dimensions/hubv2/types
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * One competency of the tree: helper::structure_nodes(), declared field for field in
 * classes/external/browse_structure.php node_structure(). Names are the plain spelling;
 * description is formatted, trusted server HTML.
 */
export type StructureNode = {
    id: number,
    parentid: number,
    shortname: string,
    idnumber: string,
    taxonomy: string,
    scale: string,
    description: string,
    coursecount: number,
    activitycount: number,
    templatecount: number,
    depth: number,
    indent: number,
    haschildren: boolean,
    canmanage: boolean,
    ruletype: string | null,
    ruleoutcome: number,
    ruleconfig: string | null,
    rulelabel: string,
    type: string,
    tag1: string,
    tag2: string,
    bgcolor: string,
    textcolor: string,
};

/** One page of a parent's direct children (local_dimensions_browse_structure). */
export type NodePage = {items: StructureNode[], total: number};

/** A competency named by id with its ancestors (search_structure, list_related_competencies). */
export type PathItem = {id: number, shortname: string, idnumber: string, path: string, pathids: number[]};

/** One value of the framework's scale, with the framework's marks on it (hub_map::framework_facts()). */
export type ScaleValue = {id: number, name: string, isdefault: boolean, proficient: boolean};

/**
 * The framework the canvas shows, and what its root node says of it (hub_map::framework_facts()). Names are the
 * plain spelling. `taxonomies` holds the term of each level the framework names, level 1 first; a deeper level
 * is `taxonomyfallback`, as core's competency_framework::get_taxonomy() reads it.
 */
export type Framework = {
    id: number,
    shortname: string,
    idnumber: string,
    competencycount: number,
    canmanage: boolean,
    visible: boolean,
    contextname: string,
    scale: {name: string, values: ScaleValue[]},
    taxonomies: string[],
    taxonomyfallback: string,
    // Whether core would delete it when the page was built; the delete asks core again.
    deletable: boolean,
};

/** The commands not about one framework: creating or importing one in the tab's context, and the scales page. */
export type Structures = {contextid: number, cancreate: boolean, canscalespage: boolean};

/** Where a competency is used (local_dimensions_competency_usage). */
export type Usage = {
    courses: {id: number, name: string, shortname: string, url: string}[],
    activities: {cmid: number, name: string, coursename: string, courseshortname: string, url: string}[],
    templates: {id: number, name: string, visible: boolean}[],
};

/** What deleting a competency involves (local_dimensions_competency_delete_check). */
export type DeleteCheck = {
    candelete: boolean,
    descendants: number,
    parenthasrule: boolean,
    /** True only for a site-level manager; otherwise the counts are presence only (0 or 1). */
    exact: boolean,
    templates: number,
    plans: number,
    courses: number,
    users: number,
    badges: boolean,
    blockedcompetencies: number,
};

/**
 * How much usage the viewer is told (get_framework_insights::USAGE_*): counts, presence (0 or 1 per kind), or
 * nothing.
 */
export type UsageLevel = 'exact' | 'presence' | 'none';

/** One competency's use, by kind; only competencies with some use are listed. */
export type InsightUsage = {competencyid: number, courses: number, modules: number, templates: number, plans: number};

/** One rule: its parent, type, label, outcome, the points it asks and the children it lists (points rules only). */
export type InsightRule = {
    competencyid: number,
    // 'all', 'points', or another rule class's short name.
    type: string,
    // The rule's localised label, plain text.
    label: string,
    // 'none', 'evidence', 'complete' or 'recommend'.
    outcome: string,
    required: number,
    available: number,
    requiredchildren: number,
    children: {id: number, points: number, required: boolean}[],
    canfire: boolean,
};

/** One finding of the checker: its code (strings hubv2_check_<code> and _desc), the competency (0: the framework). */
export type InsightCheck = {code: string, competencyid: number, params: {name: string, value: string}[]};

/**
 * What local_dimensions_get_framework_insights answers for one framework, field for field its
 * execute_returns(). Each list holds only the competencies with something to say. `pairs` lists each
 * related pair of the framework once, the lower id first.
 */
export type Insights = {
    frameworkid: number,
    exact: boolean,
    usagelevel: UsageLevel,
    usage: InsightUsage[],
    rules: InsightRule[],
    relations: {competencyid: number, count: number}[],
    pairs: {a: number, b: number}[],
    checks: InsightCheck[],
    summary: {competencies: number, maxdepth: number, withrule: number, relations: number, findings: number},
};

/** What local_dimensions_set_competencies_appearance answers: the ids that changed, and every one's effective values. */
export type AppearanceAnswer = {
    changed: number[],
    competencies: {id: number, fields: {name: string, value: string}[]}[],
};

/** The options of the appearance fields that are selects, plain labels; position + 1 is what the service takes. */
export type AppearanceOptions = {tag1: string[], tag2: string[], type: string[]};

/** One row of a companion section: a plain label and value, and a tone of the hook's or '' for none. */
export type SectionRow = {label: string, value: string, tone: string};

/**
 * A section another plugin adds to the panel (local_dimensions_get_competency_panel_sections, field for field its
 * execute_returns()). Every text is plain; linkurl is a page of this site, or '' with linklabel '' for no link.
 */
export type PanelSection = {
    key: string,
    component: string,
    title: string,
    rows: SectionRow[],
    note: string,
    linklabel: string,
    linkurl: string,
};

/** A competency the canvas reveals at mount: the deep link, checked by the server against the framework shown. */
export type RevealTarget = {id: number, pathids: number[]};

/** The component's props, built by classes/output/hub_map.php props(). */
export type HubProps = {
    framework: Framework,
    roots: NodePage,
    pagesize: number,
    rulesmodules: {type: string, name: string}[],
    courseoutcomes: {value: number, label: string}[],
    moduleoutcomes: {value: number, label: string}[],
    /** Whether the page's language is written right to left: the tree then grows leftwards. */
    rtl: boolean,
    reveal: RevealTarget | null,
    /** The workspace's layout when it mounts: the tab's view (structure::VIEWS), the classic list aside. */
    layout: 'outline' | 'map' | 'both',
    structures: Structures,
    /** The lens when it mounts (hub_map::LENSES): the address's, else the saved one, else none. */
    lens: 'none' | 'usage' | 'rules' | 'relations',
    /** The bulk dock's appearance choices (hub_map::appearance_options()); null for a reader, absent from an older page. */
    appearance?: AppearanceOptions | null,
    /** Whether a plugin listens for the panel's companion sections (hub_map::has_panel_sections()); absent from an older page. */
    sections?: boolean,
};
