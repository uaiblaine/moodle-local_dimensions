/**
 * The splitter between the outline and the map in the Both layout: the outline's width, its bounds,
 * and how a key or a drag changes it.
 *
 * It follows the hub's own pane divider (amd/src/central/pane_resizer.js): the width is kept in
 * localStorage under a local_dimensions_* key, the arrow keys move it 24 px a step, a double click
 * goes back to the stylesheet's default; and, as the APG Window Splitter pattern adds, Home and End
 * go to the limits. The outline is at the start side of the page, so in a right-to-left language it
 * is at the right and the keys and the drag mirror.
 *
 * Pure but for the two storage helpers, which swallow a browser that refuses storage.
 *
 * @module     local_dimensions/hubv2/outlinewidth
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const n={initial:264,minimum:200,maximum:560,step:24},a=320,u="local_dimensions_hubv2_outline_width",s=t=>{const r=t>0?t-a:n.maximum;return Math.max(n.minimum,Math.min(n.maximum,r))},i=(t,r)=>{const e=Number.isFinite(t)?t:n.initial;return Math.round(Math.min(Math.max(e,n.minimum),s(r)))},l=(t,r,e,o)=>{const m=o?"ArrowLeft":"ArrowRight",c=o?"ArrowRight":"ArrowLeft";switch(t){case m:return i(r+n.step,e);case c:return i(r-n.step,e);case"Home":return n.minimum;case"End":return s(e);default:return null}},b=(t,r,e,o,m)=>i(t+(m?r-e:e-r),o),d=()=>{try{const t=Number(window.localStorage.getItem(u));return t>0?t:n.initial}catch{return n.initial}},h=t=>{try{t===null?window.localStorage.removeItem(u):window.localStorage.setItem(u,String(Math.round(t)))}catch{}};export{a as MAP_RESERVE,n as OUTLINE_WIDTH,u as STORAGE_KEY,i as clampWidth,b as dragWidth,l as keyWidth,h as storeWidth,d as storedWidth,s as upperBound};
