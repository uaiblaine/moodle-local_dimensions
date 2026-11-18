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

/** The framework the canvas shows. */
export type Framework = {id: number, shortname: string, idnumber: string, competencycount: number, canmanage: boolean};

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

/** The component's props, built by classes/output/hubv2_page.php props(). */
export type HubProps = {
    framework: Framework,
    roots: NodePage,
    pagesize: number,
    rulesmodules: {type: string, name: string}[],
    courseoutcomes: {value: number, label: string}[],
    moduleoutcomes: {value: number, label: string}[],
    listurl: string,
};
