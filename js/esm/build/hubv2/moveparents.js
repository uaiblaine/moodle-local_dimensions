import{outlineRows as i}from"./outlinerows";import{ROOT as a,isWithin as u}from"./tree";/**
 * The "Move to" dialogue's choices: the parents a competency may move under, as a tree of its own over
 * the workspace's store, and the search hits it may take.
 *
 * The dialogue's tree is the workspace's tree with the dialogue's own opened branches: what it loads
 * goes into the shared store (so the panes reuse it), and what it opens leaves the panes alone. The
 * framework is the root, standing for the top level. The competency being moved is drawn and cannot be
 * chosen, and it never opens, so none of its descendants is ever offered (core would make the move a
 * cycle; the canvas refuses before asking). Its current parent is marked and cannot be chosen either:
 * a move there changes nothing.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/moveparents
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const c=(e,r,n)=>{const o=e.nodes[n];return{nodes:o?{...e.nodes,[n]:{...o,haschildren:!1}}:e.nodes,branches:e.branches,expanded:r.filter(t=>t!==n&&e.branches[t]!==void 0&&!!e.nodes[t]?.haschildren)}},p=(e,r,n,o)=>r===o?!1:r===a||!u(e,r,n),m=(e,r,n,o)=>{const t=e.nodes[n]?.parentid??a;return i(c(e,r,n),o).map(s=>{if(s.kind==="more")return{...s,disabled:!1,current:!1};const d=s.id===t;return{...s,current:d,disabled:!p(e,s.id,n,t)}})},h=(e,r,n)=>e.id!==r&&!e.pathids.includes(r)&&e.id!==n;export{p as canMoveUnder,h as hitSelectable,m as parentChoices,c as pickerTree};
