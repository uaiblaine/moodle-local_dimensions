import{outlineRows as p}from"./outlinerows";import{ROOT as c,isWithin as b}from"./tree";/**
 * The "Move to" dialogue's choices: the parents a competency may move under, as a tree of its own over
 * the workspace's store, and the search hits it may take.
 *
 * The dialogue's tree is the workspace's tree with the dialogue's own opened branches: what it loads
 * goes into the shared store (so the panes reuse it), and what it opens leaves the panes alone. The
 * framework is the root, standing for the top level. The competency being moved is drawn and cannot be
 * chosen, and it never opens, so none of its descendants is ever offered (core would make the move a
 * cycle; the canvas refuses before asking). Its current parent is marked and cannot be chosen either:
 * a move there changes nothing. The bulk dock moves several competencies at once: each of them is closed and
 * cannot be chosen, and a parent is marked current, and refused, only when it is every one's parent already (a
 * competency already under the chosen parent is skipped, bulk.movePlan).
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/moveparents
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const a=e=>Array.isArray(e)?e:[e],d=(e,n,t)=>{const r=a(t),o={...e.nodes};return r.forEach(s=>{o[s]&&(o[s]={...o[s],haschildren:!1})}),{nodes:o,branches:e.branches,expanded:n.filter(s=>!r.includes(s)&&e.branches[s]!==void 0&&!!e.nodes[s]?.haschildren)}},T=(e,n,t,r)=>n===r?!1:n===c||!b(e,n,t),h=(e,n)=>{const t=new Set(n.map(r=>e.nodes[r]?.parentid??c));return t.size===1?[...t][0]:null},x=(e,n,t,r)=>{const o=a(t),s=h(e,o);return p(d(e,n,o),r).map(u=>{if(u.kind==="more")return{...u,disabled:!1,current:!1};const i=u.id===s,m=u.id!==c&&o.some(l=>b(e,u.id,l));return{...u,current:i,disabled:i||m}})},O=(e,n,t)=>a(n).every(r=>e.id!==r&&!e.pathids.includes(r))&&e.id!==t;export{T as canMoveUnder,O as hitSelectable,x as parentChoices,d as pickerTree,h as sharedParent};
