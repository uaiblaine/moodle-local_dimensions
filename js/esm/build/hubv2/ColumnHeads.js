import{useEffect as v,useState as w}from"react";import{fill as d}from"./strings";import{Fragment as y,jsx as l,jsxs as p}from"react/jsx-runtime";/**
 * The map's column headings: above each column, the taxonomy term the framework gives that level.
 *
 * Quiet text in a strip along the canvas's top edge, inside the canvas, under its controls. The strip follows
 * the map sideways (its headings move and scale with the view), not up and down, and the map's fitted view
 * leaves the strip's height free above the cards, so a heading never covers a card at rest. The strip is
 * drawing for the eye: hidden from assistive technology, which reads the levels once, in the canvas's
 * description, and never a Tab stop. A manager's pencil on a heading opens the framework's form, where the
 * taxonomies are edited, for the pointer only: the root's "Edit structure" is the keyboard's way to it.
 *
 * @module     local_dimensions/hubv2/ColumnHeads
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const f=(o,t)=>d(o.hubv2_columns,[...t].sort((e,n)=>e.level-n.level).map(e=>`${d(o.hubv2_fw_level,String(e.level))}: ${e.term}`).join("; ")),h=({labels:o,heads:t,cardwidth:e,read:n,subscribe:a,describedby:u,canedit:c,onEdit:b})=>{const[s,m]=w(n);return v(()=>{const i=a(m),r=window.requestAnimationFrame(()=>m(n()));return()=>{i(),window.cancelAnimationFrame(r)}},[n,a]),t.length===0?null:p(y,{children:[l("p",{id:u,className:"visually-hidden",children:f(o,t)}),l("div",{className:"local-dimensions-hubv2-colheads","aria-hidden":"true",children:t.map(i=>p("div",{className:"local-dimensions-hubv2-colhead",style:{left:`${s.x+i.x*s.zoom}px`,width:`${e*s.zoom}px`},children:[l("span",{className:"local-dimensions-hubv2-colhead-term",children:i.term}),c?l("button",{type:"button",className:"local-dimensions-hubv2-iconbtn local-dimensions-hubv2-colhead-edit",tabIndex:-1,title:o.hubv2_column_edit,onClick:r=>{r.stopPropagation(),b()},children:l("i",{className:"fa fa-pen","aria-hidden":"true"})}):null]},i.level))})]})};var C=h;export{C as default,f as levelsSaid};
