import{useEffect as k,useId as C,useRef as g,useState as o}from"react";import{jsx as t,jsxs as y}from"react/jsx-runtime";/**
 * The outline's inline editor: a row with a name and an ID number for a new competency, or the name
 * alone to rename one in place (owner's decision H5: fast authoring in the outline only).
 *
 * A form in all but the element: Tab and Shift+Tab move through its fields and its two buttons, Enter
 * in a field saves, Escape cancels and the workspace gives the focus back to the row. The row sits in the
 * tree's list as a presentational item holding a labelled group, so the tree's keys (which act on
 * treeitems only) leave its fields alone. What the server refuses (an ID number already taken, a
 * missing right) is said under the fields, tied to them by aria-describedby and announced, and the
 * editor stays open with what was typed.
 *
 * @module     local_dimensions/hubv2/Editor
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const K=({labels:r,mode:c,label:h,level:E,initialname:N,initialidnumber:L,onSave:x,onCancel:d})=>{const[i,D]=o(N),[a,I]=o(L),[n,m]=o(""),[b,s]=o(!1),l=g(null),u=g(!0),p=`${C()}-error`;k(()=>(l.current?.focus(),l.current?.select(),()=>{u.current=!1}),[]);const f=()=>{if(!b){if(i.trim()===""||c==="create"&&a.trim()===""){m(r["core:required"]);return}s(!0),x(i.trim(),a.trim()).then(e=>(u.current&&(s(!1),m(e??"")),null)).catch(()=>{u.current&&s(!1)})}},P=e=>{if(e.key==="Escape"){e.preventDefault(),e.stopPropagation(),d();return}e.key==="Enter"&&e.target instanceof HTMLInputElement&&(e.preventDefault(),e.stopPropagation(),f())},v=n?p:void 0;return y("li",{role:"none",className:"local-dimensions-hubv2-oeditor",style:{paddingInlineStart:`${.25+(E-1)*1.25}rem`},children:[y("div",{role:"group","aria-label":h,className:"local-dimensions-hubv2-oeditor-group",onKeyDown:P,children:[t("span",{className:"local-dimensions-hubv2-otoggle","aria-hidden":"true"}),t("input",{ref:l,type:"text",className:"form-control form-control-sm local-dimensions-hubv2-oeditor-name","aria-label":r.hubv2_editor_name,placeholder:r.hubv2_editor_name,"aria-invalid":n?!0:void 0,"aria-describedby":v,maxLength:100,value:i,onChange:e=>D(e.target.value)}),c==="create"?t("input",{type:"text",className:"form-control form-control-sm local-dimensions-hubv2-oeditor-id","aria-label":r["core:idnumber"],placeholder:r["core:idnumber"],"aria-invalid":n?!0:void 0,"aria-describedby":v,maxLength:100,value:a,onChange:e=>I(e.target.value)}):null,t("button",{type:"button",className:"btn btn-sm btn-primary",disabled:b,onClick:f,children:r["core:save"]}),t("button",{type:"button",className:"btn btn-sm btn-secondary",onClick:d,children:r["core:cancel"]})]}),t("p",{id:p,className:"local-dimensions-hubv2-oeditor-error",role:"alert",children:n})]})};var M=K;export{M as default};
