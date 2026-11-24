import{isExpanded as f,setExpanded as c,visibleItems as i}from"./tree";/**
 * The outline's "Expand all" and "Collapse all": which closed branches to open next, and where to stop.
 *
 * The tree is lazy, so expanding all is a walk, level by level: each round opens every closed parent
 * at the shallowest depth that has one, loading the first page of those never opened, and the next
 * round looks again at what that revealed. It stops before the visible competencies would pass
 * EXPAND_ALL_BOUND, and says so; a parent's further pages stay behind its "load more" item, as they
 * do when it is opened by hand.
 *
 * Pure: the workspace loads and commits what a step names.
 *
 * @module     local_dimensions/hubv2/expandall
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const m=500,s=p=>i(p).filter(t=>t.kind==="competency").length,b=(p,t,h)=>{const a=i(p).flatMap(e=>e.kind==="competency"&&p.nodes[e.id]?.haschildren&&!f(p,e.id)?[{id:e.id,depth:e.depth}]:[]);if(a.length===0)return{open:[],load:[],capped:!1};const u=Math.min(...a.map(e=>e.depth)),n={open:[],load:[],capped:!1};let r=s(p),d=p;for(const e of a.filter(o=>o.depth===u)){const o=p.branches[e.id]!==void 0,l=o?s(c(d,e.id,!0))-s(d):h;if(r+l>t){n.capped=!0;break}r+=l,o?(d=c(d,e.id,!0),n.open.push(e.id)):n.load.push(e.id)}return n},E=p=>p.expanded.length===0?p:{...p,expanded:[]};export{m as EXPAND_ALL_BOUND,E as collapseAll,b as expandStep,s as visibleCount};
