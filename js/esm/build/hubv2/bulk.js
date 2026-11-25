import{normalise as m}from"./contrast";import{blockedReasons as l}from"./deletion";import{fill as i,fillCount as s,fillObject as c}from"./strings";import{ROOT as p,ancestors as d,isWithin as b}from"./tree";/**
 * What the bulk dock's three actions decide before they ask the server: which competencies an action acts on, in
 * which order, what the appearance dialogue sends, what the delete confirmation says, and the words of each outcome.
 *
 * A move and a delete act on the picked competencies none of whose ancestors is picked too (topmost): core moves and
 * deletes a competency with its descendants, so a descendant would move twice or be deleted with its ancestor. The
 * order is the tree's (depth first, the server's sort), so the same set always runs in the same order. The server
 * decides every write again; these decisions only keep the dock from asking what it knows core refuses.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/bulk
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const E=200,a=5,f=["bgcolor","textcolor","tag1","tag2","type"],_=["bgcolor","textcolor"],A=n=>{const r=[];for(const e of f){const o=n[e];if(o.mode==="clear")r.push({name:e,action:"clear",value:""});else if(o.mode==="set"){const t=_.includes(e)?m(o.value):o.value;if(t==="")return null;r.push({name:e,action:"set",value:t})}}return r},h=n=>{const r=[],e=o=>{(n.branches[o]?.ids??[]).forEach(t=>{n.nodes[t]&&(r.push(t),e(t))})};return e(p),r},v=(n,r)=>{const e=new Set(r);return h(n).filter(o=>e.has(o)&&!d(n,o).some(t=>e.has(t)))},C=(n,r,e)=>e===p||!r.some(o=>b(n,e,o)),L=(n,r,e)=>{const o=v(n,r);return{move:o.filter(t=>n.nodes[t].parentid!==e),skipped:o.filter(t=>n.nodes[t].parentid===e)}},w=n=>({shown:n.slice(0,a),more:Math.max(0,n.length-a)}),D=(n,r)=>{const e={allowed:[],descendants:0,refused:[],parentrule:!1};return r.forEach(({id:o,name:t,check:u})=>{u&&u.candelete?(e.allowed.push(o),e.descendants+=u.descendants,e.parentrule=e.parentrule||u.parenthasrule):e.refused.push({id:o,name:t,reasons:u?l(n,u):[]})}),e},O=(n,r,e)=>{const o=s(n,"hubv2_bulk_selected",r);return e>0?`${o} ${s(n,"hubv2_bulk_notshown",e)}`:o},R=(n,r)=>r===0?n.hubv2_bulk_appearance_none:s(n,"hubv2_bulk_appearance_done",r),S=(n,r)=>{const e=[s(n,"hubv2_bulk_move_done",r.moved)];return r.skipped>0&&e.push(s(n,"hubv2_bulk_move_skipped",r.skipped)),r.refused&&(e.push(c(n.hubv2_bulk_move_refused,{name:r.refused.name,reason:r.refused.reason})),r.left>0&&e.push(s(n,"hubv2_bulk_move_left",r.left))),e.join(" ")},N=(n,r,e)=>{const o=s(n,"hubv2_bulk_delete_done",r);return e.length>0?`${o} ${i(n.hubv2_bulk_delete_kept,e.join(", "))}`:o};export{f as APPEARANCE_FIELDS,E as BULK_MAX,_ as COLOUR_FIELDS,a as PREVIEW_NAMES,R as appearanceOutcome,A as appearancePayload,N as deleteOutcome,D as deletionSummary,O as dockCount,S as moveOutcome,L as movePlan,w as preview,C as targetAllowed,v as topmost,h as treeOrder};
