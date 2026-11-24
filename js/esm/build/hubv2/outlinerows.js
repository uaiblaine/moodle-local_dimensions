import{FRAMEWORK_ID as s,competencyKey as p,moreKey as c,parentKey as i}from"./graph";import{ROOT as d,isExpanded as m,visibleItems as b}from"./tree";/**
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
 */const f=(n,l)=>{const r=[{key:s,kind:"framework",id:d,parent:null,level:1,setsize:1,posinset:1,expandable:!0,expanded:!0,name:l,childcount:n.branches[d]?.total??0,remaining:0,node:null}];return b(n).forEach(e=>{const t=n.branches[e.parent],a=t?t.total:1;if(e.kind==="more"){r.push({key:c(e.parent),kind:"more",id:e.parent,parent:i(e.parent),level:e.depth+1,setsize:a,posinset:(t?t.ids.length:0)+1,expandable:!1,expanded:!1,name:"",childcount:null,remaining:e.remaining,node:null});return}const u=n.nodes[e.id],o=n.branches[e.id];r.push({key:p(e.id),kind:"competency",id:e.id,parent:i(e.parent),level:e.depth+1,setsize:a,posinset:(t?t.ids.indexOf(e.id):0)+1,expandable:u.haschildren,expanded:u.haschildren&&m(n,e.id),name:u.shortname,childcount:o?o.total:null,remaining:0,node:u})}),r},x=(n,l,r)=>{if(n.kind==="more")return[];if(n.kind==="framework")return[{kind:"count",value:l}];const e=[];return n.childcount!==null&&e.push({kind:"count",value:n.childcount}),n.node?.ruletype&&e.push({kind:"rule",label:n.node.rulelabel}),r!==null&&r>0&&e.push({kind:"related",value:r}),e},y=(n,l,r,e)=>n<r?n:n+l>r+e?Math.max(0,n+l-e):null;export{f as outlineRows,x as rowMeta,y as scrollToShow};
