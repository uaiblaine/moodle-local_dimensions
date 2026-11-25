import{GRID as p}from"./layout";import{visibleItems as i}from"./tree";/**
 * The map's column headings: the framework's taxonomy term of each level, above its column.
 *
 * The map lays one column per depth (layout.ts), the framework in column 0 and a competency of level N (its depth,
 * 1 for a top-level one) in column N; a language written right to left mirrors the columns. Each level is named
 * as core names it (competency_framework::get_taxonomy()): the term the framework gives that level, else core's
 * fallback. The outline's "Show taxonomy" meta reads the same function, so both panes say the same word.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/columns
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const l=(t,n,e)=>e>=1&&t[e-1]?t[e-1]:n,a=t=>i(t).reduce((n,e)=>e.kind==="competency"?Math.max(n,e.depth):n,0),b=(t,n,e,u,m=p.columnWidth)=>{const s=[];for(let r=1;r<=t;r++){const o=u?0-r*m:r*m;s.push({level:r,x:o,term:l(n,e,r)})}return s.sort((r,o)=>r.x-o.x)};export{b as columnHeads,a as deepestLevel,l as levelTerm};
