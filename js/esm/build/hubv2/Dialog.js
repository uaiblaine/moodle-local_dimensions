import{useEffect as E,useRef as u}from"react";import{createPortal as v}from"react-dom";import{jsx as d}from"react/jsx-runtime";/**
 * The workspace's modal dialogue: the command palette's and "Move to"'s frame.
 *
 * Labelled by its title, modal, the focus kept inside (Tab and Shift+Tab wrap), Escape or a press on
 * the veil around it closes it, and the focus goes back to the element that had it when the dialogue
 * opened (the fleet Design System's dialog variant, panel-popover-sheet.md). When that element has left
 * the page meanwhile (a view switched from the palette), or when the dialogue was opened by another
 * layer that has closed (returnTo 'item'), onLostFocus decides where the focus goes (focusreturn.ts).
 *
 * Drawn on the page's body, over everything, as core's dialogues are; React still delivers its events
 * through the workspace.
 *
 * @module     local_dimensions/hubv2/Dialog
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const y='button:not([disabled]), input:not([disabled]), [tabindex="0"], a[href]',D=({labelledby:f,className:m,initialFocus:r,returnTo:l,onClose:c,onLostFocus:i,children:p})=>{const o=u(null),n=u({onLostFocus:i,returnTo:l});return n.current={onLostFocus:i,returnTo:l},E(()=>{const e=document.activeElement instanceof HTMLElement?document.activeElement:null;return r.current?.focus(),()=>{n.current.returnTo==="opener"&&e&&e!==document.body&&e.isConnected?e.focus({preventScroll:!0}):n.current.onLostFocus()}},[r]),v(d("div",{className:"local-dimensions-hubv2-scrim",onMouseDown:e=>{e.target===e.currentTarget&&c()},children:d("div",{ref:o,className:m,role:"dialog","aria-modal":"true","aria-labelledby":f,onKeyDown:e=>{if(e.key==="Escape"){e.preventDefault(),e.stopPropagation(),c();return}if(e.key!=="Tab"||!o.current)return;const t=[...o.current.querySelectorAll(y)].filter(b=>!b.hidden);if(t.length===0){e.preventDefault();return}const a=t[0],s=t[t.length-1];e.shiftKey&&document.activeElement===a?(e.preventDefault(),s.focus()):!e.shiftKey&&document.activeElement===s&&(e.preventDefault(),a.focus())},children:p})}),document.body)};var L=D;export{L as default};
