import{useRef as f}from"react";import{OUTLINE_WIDTH as P,dragWidth as y,keyWidth as h,upperBound as D}from"./outlinewidth";import{jsx as s}from"react/jsx-runtime";/**
 * The splitter between the outline and the map in the Both layout: an APG Window Splitter, moved by
 * the arrow keys, Home and End, or a drag; a double click goes back to the default width.
 *
 * The widths and the keys are outlinewidth.ts's; the width lives in the workspace, which hands it to the
 * stylesheet. Below the stacked breakpoint the stylesheet does not draw it.
 *
 * @module     local_dimensions/hubv2/Splitter
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const E=u=>{const{label:c,controls:d,width:r,available:o,rtl:l,onWidth:a,onReset:p}=u,n=f(null),m=t=>{if(t.altKey||t.ctrlKey||t.metaKey||t.shiftKey)return;const e=h(t.key,r,o(),l);e!==null&&(t.preventDefault(),a(e,!0))},v=t=>{t.preventDefault(),n.current={startx:t.clientX,start:r,last:r},t.currentTarget.setPointerCapture(t.pointerId)},b=t=>{const e=n.current;e&&(e.last=y(e.start,e.startx,t.clientX,o(),l),a(e.last,!1))},i=()=>{const t=n.current;t&&(n.current=null,a(t.last,!0))};return s("div",{className:"local-dimensions-hubv2-splitter",role:"separator","aria-orientation":"vertical","aria-label":c,"aria-controls":d,"aria-valuenow":r,"aria-valuemin":P.minimum,"aria-valuemax":D(o()),tabIndex:0,onKeyDown:m,onPointerDown:v,onPointerMove:b,onPointerUp:i,onPointerCancel:i,onLostPointerCapture:i,onDoubleClick:p,children:s("span",{className:"local-dimensions-hubv2-splitter-grip","aria-hidden":"true"})})};var x=E;export{x as default};
