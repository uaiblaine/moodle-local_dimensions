import{FRAMEWORK_ID as s,competencyKey as p,moreKey as c,parentKey as o}from"./graph";import{ROOT as d,isExpanded as m,visibleItems as b}from"./tree";/**
 * The outline's rows: one per stop of the keyboard walk (keynav.navOrder), with what the APG Tree View
 * pattern needs on each when the whole set is not in the DOM.
 *
 * The outline is a flat list of treeitems, each carrying aria-level, aria-setsize and aria-posinset,
 * rather than nested groups: the tree is lazy, so a parent's set is never whole in the DOM and the
 * three attributes are needed on every item anyway, and a flat list keeps the rows in exactly the
 * walk's order, one to one, which is what the shared key map moves through. The framework is the
 * root item at level 1; a competency's level is its depth plus one; a parent's set size is its total
 * from the server, not what is loaded. A "load more" item stands at the level of the children it
 * loads, after them, at the position the next child will take.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/outlinerows
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const f=(e,t)=>{const r=[{key:s,kind:"framework",id:d,parent:null,level:1,setsize:1,posinset:1,expandable:!0,expanded:!0,name:t,childcount:e.branches[d]?.total??0,remaining:0,node:null}];return b(e).forEach(n=>{const l=e.branches[n.parent],u=l?l.total:1;if(n.kind==="more"){r.push({key:c(n.parent),kind:"more",id:n.parent,parent:o(n.parent),level:n.depth+1,setsize:u,posinset:(l?l.ids.length:0)+1,expandable:!1,expanded:!1,name:"",childcount:null,remaining:n.remaining,node:null});return}const i=e.nodes[n.id],a=e.branches[n.id];r.push({key:p(n.id),kind:"competency",id:n.id,parent:o(n.parent),level:n.depth+1,setsize:u,posinset:(l?l.ids.indexOf(n.id):0)+1,expandable:i.haschildren,expanded:i.haschildren&&m(e,n.id),name:i.shortname,childcount:a?a.total:null,remaining:0,node:i})}),r},x=(e,t,r)=>{if(e.kind==="more")return[];if(e.kind==="framework")return[{kind:"count",value:t}];const n=[];return e.childcount!==null&&n.push({kind:"count",value:e.childcount}),e.node?.ruletype&&n.push({kind:"rule",label:e.node.rulelabel}),r!==null&&r>0&&n.push({kind:"related",value:r}),n},y=(e,t,r,n)=>e<r?e:e+t>r+n?Math.max(0,e+t-n):null,g=(e,t,r)=>{const n=r??t,l=e.findIndex(i=>i.key===n);if(l<0)return null;if(r===null)return n;let u=l;for(;u+1<e.length&&e[u+1].level>e[l].level;)u++;return e[u].key};export{g as editorSlot,f as outlineRows,x as rowMeta,y as scrollToShow};
