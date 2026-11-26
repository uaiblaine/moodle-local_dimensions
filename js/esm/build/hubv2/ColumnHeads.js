import{useEffect as b,useState as h}from"react";import{headWords as d}from"./columns";import{fill as f}from"./strings";import{Fragment as y,jsx as i,jsxs as a}from"react/jsx-runtime";/**
 * The map's column headings: above each column of competencies, its level and the taxonomy term the framework
 * gives that level ("Level 2: Competency").
 *
 * Quiet text in a strip along the canvas's top edge, inside the canvas, under its controls. The strip follows
 * the map sideways (its headings move and scale with the view), not up and down, and the map's fitted view
 * leaves the strip's height free above the cards, so a heading never covers a card at rest. The strip is
 * drawing for the eye: hidden from assistive technology, which reads the levels once, in the canvas's
 * description, and never a Tab stop.
 *
 * The level names are the framework's, so a manager's way to them is one control, named for what it does, above
 * the framework's own column: it opens the framework's form, where the taxonomies are edited. It is for the
 * pointer only: the root's "Edit structure" is the keyboard's way to the same form. A heading carries no control
 * of its own: one beside each term read as a button named after the term, once per column.
 *
 * @module     local_dimensions/hubv2/ColumnHeads
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const w=(o,n)=>f(o.hubv2_columns,[...n].sort((t,l)=>t.level-l.level).map(t=>d(o.hubv2_fw_level,t)).join("; ")),x=({labels:o,heads:n,cardwidth:t,read:l,subscribe:r,describedby:p,canedit:u,onEdit:c})=>{const[s,m]=h(l);return b(()=>{const e=r(m),v=window.requestAnimationFrame(()=>m(l()));return()=>{e(),window.cancelAnimationFrame(v)}},[l,r]),n.length===0?null:a(y,{children:[i("p",{id:p,className:"visually-hidden",children:w(o,n)}),a("div",{className:"local-dimensions-hubv2-colheads","aria-hidden":"true",children:[u?i("div",{className:"local-dimensions-hubv2-colhead",style:{left:`${s.x}px`,width:`${t*s.zoom}px`},children:a("button",{type:"button",className:"local-dimensions-hubv2-iconbtn local-dimensions-hubv2-colhead-edit",tabIndex:-1,onClick:e=>{e.stopPropagation(),c()},children:[i("i",{className:"fa fa-pen","aria-hidden":"true"}),i("span",{className:"local-dimensions-hubv2-colhead-editlabel",children:o.hubv2_column_edit})]})}):null,n.map(e=>i("div",{className:"local-dimensions-hubv2-colhead",style:{left:`${s.x+e.x*s.zoom}px`,width:`${t*s.zoom}px`},children:i("span",{className:"local-dimensions-hubv2-colhead-term",children:d(o.hubv2_fw_level,e)})},e.level))]})]})};var V=x;export{V as default,w as levelsSaid};
