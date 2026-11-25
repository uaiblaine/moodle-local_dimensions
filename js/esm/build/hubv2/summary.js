import{ancestors as a}from"./tree";/**
 * The framework's summary, on request: what the client can say truthfully about the tree it holds.
 *
 * The total comes from the server (core's count of the framework's competencies). Everything else is
 * counted over the competencies the workspace has loaded, and is said to be so while that is not the
 * whole framework: the tree is lazy, and a count over a part read as the whole would be false. No
 * relation is counted: the tree's nodes do not carry them (only the selected competency's are read). Once the
 * framework's insights are read (insights.ts), the summary says the server's numbers instead.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/summary
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const i=(e,u)=>{const r=Object.keys(e.nodes).map(Number),m=r.reduce((n,t)=>Math.max(n,a(e,t).length+1),0),l=r.filter(n=>!!e.nodes[n].ruletype).length;return{total:u,loaded:r.length,complete:r.length>=u,levels:m,rules:l,opened:e.expanded.filter(n=>e.nodes[n]!==void 0).length}},s=(e,u,r=null)=>{const m=[{label:e.hubv2_summary_total,value:String(u.total)}];return r?(m.push({label:e.hubv2_summary_levels,value:String(r.maxdepth)},{label:e.hubv2_summary_rules,value:String(r.withrule)},{label:e.hubv2_summary_relations,value:String(r.relations)},{label:e.hubv2_summary_findings,value:String(r.findings)},{label:e.hubv2_summary_opened,value:String(u.opened)}),m):(u.complete||m.push({label:e.hubv2_summary_loaded,value:String(u.loaded)}),m.push({label:u.complete?e.hubv2_summary_levels:e.hubv2_summary_levels_loaded,value:String(u.levels)},{label:u.complete?e.hubv2_summary_rules:e.hubv2_summary_rules_loaded,value:String(u.rules)},{label:e.hubv2_summary_opened,value:String(u.opened)}),m)};export{i as summaryFacts,s as summaryLines};
