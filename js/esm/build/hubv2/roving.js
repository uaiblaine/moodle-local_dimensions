import{useCallback as l,useEffect as F,useRef as u,useState as H}from"react";import{FRAMEWORK_ID as T}from"./graph";import{entryKey as L}from"./keynav";import{notifyError as k}from"./amd";import{track as A}from"./pending";import{nextStop as O}from"./selection";/**
 * One pane's roving tabindex: the item that carries tabindex 0, whether the focus is on one of the
 * pane's items, and moving the focus to an item once it is drawn.
 *
 * The stop moves at once and the focus with it when the item is already in the DOM, so a key
 * repeated faster than React renders still walks one item a press: the next press reads the stop
 * (activeRef), not the element the browser last focused. An item not drawn yet (a page just loaded,
 * a node React Flow places a frame later) is focused on the next frames, under Moodle's pending
 * counter so that Behat waits for it. Where the stop goes after a change is selection.nextStop().
 *
 * @module     local_dimensions/hubv2/roving
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const x=30,q=f=>{const{order:a,tree:g,selected:p}=f,[b,R]=H(T),i=u(T),s=u(!1),E=u(a),o=u(f);o.current=f;const m=u(0),c=l(e=>{i.current!==e&&(i.current=e,R(e))},[]),d=l((e,t=!0)=>{c(e),window.cancelAnimationFrame(m.current);const r=()=>{if(i.current!==e)return!0;const n=o.current.find(e);return n?(o.current.beforeFocus?.(e,t),n.focus({preventScroll:!0}),!0):!1};r()||A("focus",new Promise(n=>{let v=0;const y=()=>{if(r()||++v>=x){n();return}m.current=window.requestAnimationFrame(y)};m.current=window.requestAnimationFrame(y)})).catch(k)},[c]);F(()=>{const e=E.current;E.current=a;const t=O(e,a,g,p,i.current,s.current);if(t.refocus){const r=document.activeElement,n=o.current.container.current;if(!r||r===document.body||n&&n.contains(r)){d(t.key,!0);return}s.current=!1}c(t.key)},[a,g,p,d,c]),F(()=>()=>window.cancelAnimationFrame(m.current),[]);const M=l(e=>{const t=e.target.closest(o.current.item);if(!t||!e.currentTarget.contains(t))return;s.current=!0;const r=o.current.keyOf(t);r!==void 0&&c(r)},[c]),w=l(e=>{const t=e.relatedTarget;if(t===null||e.currentTarget.contains(t)&&t.closest(o.current.item))return;s.current=!1;const{tree:r,order:n,selected:v}=o.current;c(L(r,n,v))},[c]);return{active:b,activeRef:i,inside:s,focusKey:d,onFocus:M,onBlur:w}};export{q as useRoving};
