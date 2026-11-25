import{ROOT as p,ancestors as t}from"./tree";/**
 * What the workspace keeps across a reload of its pane: the branches the viewer opened and the selection.
 *
 * An action on the framework (an edit of its name or taxonomies, its scale, its visibility, a duplicate) reloads
 * the Competencies tab's pane, so the framework select, the root node and every competency's taxonomy are the
 * server's again; the workspace is mounted afresh. Before the reload the workspace holds what the viewer had
 * opened, and the new mount takes it back when it shows the same framework, opening the same branches (as
 * many children loaded as before) and selecting the same item. Another framework takes nothing.
 *
 * Node-local by nature: it lives in this page's module, for one reload. The tree is pure (tree.ts), so Jest pins
 * what is held without a DOM.
 *
 * @module     local_dimensions/hubv2/carry
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */let o=null;const s=(n,r,d)=>{const u=r.expanded.filter(e=>r.nodes[e]!==void 0).map(e=>({id:e,pathids:t(r,e)})).sort((e,l)=>e.pathids.length-l.pathids.length),a={};return Object.entries(r.branches).forEach(([e,l])=>{a[Number(e)]=l.ids.length}),{frameworkid:n,expanded:u,loaded:a,selected:d!==null&&d!==p&&r.nodes[d]?{id:d,pathids:t(r,d)}:null}},c=n=>{o=n},i=n=>{const r=o;return o=null,r&&r.frameworkid===n?r:null};export{s as carryOf,c as hold,i as take};
