import{ancestors as o}from"./tree";/**
 * The framework's summary, on request: what the client can say truthfully about the tree it holds.
 *
 * The total comes from the server (core's count of the framework's competencies). Everything else is
 * counted over the competencies the workspace has loaded, and is said to be so while that is not the
 * whole framework: the tree is lazy, and a count over a part read as the whole would be false. No
 * relation is counted: the tree's nodes do not carry them (only the selected competency's are read).
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/summary
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s=(e,r)=>{const m=Object.keys(e.nodes).map(Number),l=m.reduce((u,t)=>Math.max(u,o(e,t).length+1),0),n=m.filter(u=>!!e.nodes[u].ruletype).length;return{total:r,loaded:m.length,complete:m.length>=r,levels:l,rules:n,opened:e.expanded.filter(u=>e.nodes[u]!==void 0).length}},d=(e,r)=>{const m=[{label:e.hubv2_summary_total,value:String(r.total)}];return r.complete||m.push({label:e.hubv2_summary_loaded,value:String(r.loaded)}),m.push({label:r.complete?e.hubv2_summary_levels:e.hubv2_summary_levels_loaded,value:String(r.levels)},{label:r.complete?e.hubv2_summary_rules:e.hubv2_summary_rules_loaded,value:String(r.rules)},{label:e.hubv2_summary_opened,value:String(r.opened)}),m};export{s as summaryFacts,d as summaryLines};
