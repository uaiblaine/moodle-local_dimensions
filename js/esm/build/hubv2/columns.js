import{GRID as l}from"./layout";import{fill as p}from"./strings";import{visibleItems as u}from"./tree";/**
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
 */const c=(r,e,t)=>t>=1&&r[t-1]?r[t-1]:e,g=(r,e)=>`${p(r,String(e.level))}: ${e.term}`,b=r=>u(r).reduce((e,t)=>t.kind==="competency"?Math.max(e,t.depth):e,0),f=(r,e,t,i,m=l.columnWidth)=>{const s=[];for(let n=1;n<=r;n++){const o=i?0-n*m:n*m;s.push({level:n,x:o,term:c(e,t,n)})}return s.sort((n,o)=>n.x-o.x)};export{f as columnHeads,b as deepestLevel,g as headWords,c as levelTerm};
