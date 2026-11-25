/**
 * The workspace's multi-selection: the competencies a manager picked for the bulk dock, beside the one competency
 * the panel shows (selection.ts).
 *
 * A set of competency ids, never the framework and never a "load more" item, with an anchor: the last competency
 * toggled, from which Shift+Up and Shift+Down extend. `base` is the set as it stood when the anchor was set, so a
 * range drawn from the anchor replaces the previous range instead of piling ranges up (the APG multi-select
 * listbox's Shift+arrow). The set survives opening and closing branches (it names ids, not rows) and loses the ids
 * that leave the tree (prune): a deleted competency, or one forgotten after a move.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/multiselect
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const h={ids:[],anchor:null,base:[]},m=e=>e.filter(n=>n.kind==="competency").map(n=>n.id),b=(e,n)=>{const r=e.ids.includes(n)?e.ids.filter(t=>t!==n):[...e.ids,n];return{ids:r,anchor:n,base:r}},p=(e,n,r,t)=>{const o=m(n),l=e.anchor!==null&&o.includes(e.anchor),s=l?e.anchor:r??t,c=l?e.base:e.ids,i=o.indexOf(s),a=o.indexOf(t);if(i<0||a<0)return e;const u=o.slice(Math.min(i,a),Math.max(i,a)+1);return{ids:[...c,...u.filter(d=>!c.includes(d))],anchor:s,base:c}},x=(e,n,r)=>{const t=n.find(s=>s.key===r);if(!t||t.parent===null)return e;const o=n.filter(s=>s.kind==="competency"&&s.parent===t.parent).map(s=>s.id),l=[...e.ids,...o.filter(s=>!e.ids.includes(s))];return{ids:l,anchor:e.anchor,base:l}},P=(e,n)=>{const r=e.ids.filter(o=>n.nodes[o]!==void 0);if(r.length===e.ids.length)return e;const t=e.anchor!==null&&n.nodes[e.anchor]?e.anchor:null;return{ids:r,anchor:t,base:e.base.filter(o=>n.nodes[o]!==void 0)}},y=(e,n)=>{const r=e.ids.filter(t=>n.includes(t));return{ids:r,anchor:null,base:r}},g=e=>{const n=!!(e.ctrlKey||e.metaKey);return e.altKey?null:e.key===" "&&!n&&!e.shiftKey?"toggle":e.shiftKey&&!n&&(e.key==="ArrowUp"||e.key==="ArrowDown")?e.key==="ArrowUp"?"up":"down":n&&!e.shiftKey&&e.key.toLowerCase()==="a"?"level":null};export{h as NONE,m as drawnCompetencies,p as extend,y as keepOnly,g as pickGesture,x as pickLevel,P as prune,b as toggle};
