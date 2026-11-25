import{dockCount as k}from"./bulk";import{jsx as a,jsxs as i}from"react/jsx-runtime";/**
 * The bulk dock: the count of the competencies picked and the actions over them (the fleet Design System's data
 * report, "Bulk dock", D133 to D135).
 *
 * Drawn by the workspace after its panes while something is picked, and only for a manager (the registry's `bulk`
 * scope); sticky 12 px above the viewport's bottom while the panes scroll, never fixed. Its count comes first, in a
 * polite live region, saying how many picks sit in closed branches when some do; then the actions as core's buttons,
 * the main one the only solid fill, "Clear selection", and the destructive one last, outlined. It takes no focus
 * when it appears and follows the panes in the Tab order. An action needing more input opens a dialogue
 * (BulkDialogs.tsx); the dock never grows fields.
 *
 * @module     local_dimensions/hubv2/Dock
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const g=(e,t)=>e.spec.danger?"btn btn-outline-danger":t===0?"btn btn-primary":"btn btn-outline-secondary",h=e=>{const{labels:t,count:l,hidden:r,actions:o,onRun:d,onClear:u}=e;if(o.length===0)return null;const s=o.filter(n=>!n.spec.danger),b=o.filter(n=>n.spec.danger),c=(n,p)=>i("button",{type:"button",className:g(n,p),"aria-haspopup":"dialog",disabled:n.state==="disabled",onClick:m=>d(n.spec.id,m.currentTarget),children:[a("i",{className:`fa ${n.spec.icon} me-1`,"aria-hidden":"true"}),t[n.spec.label]]},n.spec.id);return i("div",{className:"local-dimensions-hubv2-dock",role:"region","aria-label":t.hubv2_bulk_dock,"data-region":"bulk-dock",children:[a("span",{className:"local-dimensions-hubv2-dockcount","aria-live":"polite",children:k(t,l,r)}),i("div",{className:"local-dimensions-hubv2-dockactions",children:[s.map(c),a("button",{type:"button",className:"btn btn-outline-secondary",onClick:u,children:t.hubv2_bulk_clear}),b.map(n=>c(n,s.length))]})]})};var y=h;export{y as default};
