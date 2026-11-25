import{entryKey as a,survivorKey as l}from"./keynav";import{ROOT as s}from"./tree";/**
 * The workspace's one selection and its layouts: what picking an item does, where each pane's Tab
 * stop sits after a change, and which panes a layout draws.
 *
 * The outline and the map are two projections of one store (Hub.tsx's Workspace): one tree, one
 * selected competency, one move in progress. Each pane keeps only its own Tab stop, and both stops
 * follow the selection while the focus is elsewhere, so Tab enters either pane at the competency the
 * other one selected (APG Tree View).
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/selection
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const i=["outline","map","both"],c="local_dimensions:hubv2-layout",f=e=>i.includes(e),b=e=>({outline:e!=="map",map:e!=="outline"}),k=(e,t)=>e!==null?{type:"pick",parent:t}:{type:"select",id:t===s?null:t},x=(e,t,n,r,o,p)=>p?t.some(u=>u.key===o)?{key:o,refocus:!1}:{key:l(e,t,o),refocus:!0}:{key:a(n,t,r),refocus:!1};export{i as LAYOUTS,c as LAYOUT_EVENT,k as activation,f as isLayout,x as nextStop,b as panesOf};
