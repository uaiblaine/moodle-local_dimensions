/**
 * The companion sections of the panel as the workspace keeps them: what other plugins add for the selected
 * competency through the hook competency_panel_sections (local_dimensions_get_competency_panel_sections).
 *
 * The workspace asks when a competency is selected and after every action of the registry that changes it
 * (commands.ts `changes`), and only when the page says some plugin listens (HubProps `sections`). Each request
 * has its number; an answer or a failure of any request but the newest is dropped, so a slow answer for an
 * earlier selection never shows under a later one. A new selection clears what was shown at once; a reload of
 * the same selection keeps it until the answer replaces it. A failure clears the sections and is said in one
 * quiet line: the rest of the panel stays as it is, and no error dialogue opens.
 *
 * The answer is read again here as the server declared it (an allowlist): a malformed entry is dropped, a tone
 * outside the hook's is none, and a link is kept only when it is an http(s) address of the page's own origin.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/sections
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const d=["success","warning","danger","info","neutral"],k={competencyid:null,ask:0,status:"idle",sections:[]},g=(n,e)=>{switch(e.type){case"ask":return{competencyid:e.competencyid,ask:e.ask,status:"loading",sections:e.keep&&e.competencyid===n.competencyid?n.sections:[]};case"answer":return e.ask===n.ask?{...n,status:"loaded",sections:e.sections}:n;case"fail":return e.ask===n.ask?{...n,status:"failed",sections:[]}:n;case"clear":return{competencyid:null,ask:e.ask,status:"idle",sections:[]};default:return n}},b=(n,e)=>e!==null&&n.competencyid===e?n:void 0,m=(n,e)=>{let t;try{t=new URL(n)}catch{return""}return t.protocol!=="http:"&&t.protocol!=="https:"||t.origin!==e?"":n},s=(n,e)=>{const t=n[e];return typeof t=="string"?t:""},p=n=>typeof n=="object"&&n!==null&&!Array.isArray(n),S=n=>Array.isArray(n)?n.filter(p).map(e=>{const t=s(e,"tone");return{label:s(e,"label"),value:s(e,"value"),tone:d.includes(t)?t:""}}).filter(e=>e.label!==""&&e.value!==""):[],y=(n,e)=>{if(!Array.isArray(n))return[];const t=new Set,r=[];return n.filter(p).forEach(o=>{const i=s(o,"key"),c=s(o,"title");if(i===""||c===""||t.has(i))return;t.add(i);const a=s(o,"linklabel"),l=a===""?"":m(s(o,"linkurl"),e);r.push({key:i,component:s(o,"component"),title:c,rows:S(o.rows),note:s(o,"note"),linklabel:l===""?"":a,linkurl:l})}),r},u={success:"local-dimensions-hubv2-status local-dimensions-hubv2-status--success",warning:"local-dimensions-hubv2-status local-dimensions-hubv2-status--warning",danger:"local-dimensions-hubv2-status local-dimensions-hubv2-status--danger",info:"local-dimensions-hubv2-status local-dimensions-hubv2-status--info",neutral:"local-dimensions-hubv2-status"},f=n=>d.includes(n)?u[n]:u.neutral,h=n=>`local-dimensions-hubv2-companion-${n.replace(/[^a-z0-9_-]/g,"")}`;export{k as NO_SECTIONS,d as TONES,u as TONE_CLASSES,h as headingId,y as readSections,m as sameOriginHref,b as sectionsFor,g as sectionsReducer,f as toneClass};
