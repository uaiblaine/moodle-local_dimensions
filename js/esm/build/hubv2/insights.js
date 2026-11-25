/**
 * The framework's insights as the workspace keeps them: one answer of local_dimensions_get_framework_insights
 * per framework, indexed by competency for the lenses (lenses.ts) and the checker (findings.ts).
 *
 * The workspace asks once shortly after it mounts (so the checker's count can show), whenever a lens or the
 * checker is first asked for, after every action of the registry that changes what the answer says
 * (commands.ts `changes`), and on "Check again". Only the newest request's answer is kept (latest()): an
 * answer that arrives after a later request was made is dropped, whatever order the server answers in.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/insights
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const a=["none","usage","rules","relations"],c=e=>a.includes(e),p=["courses","modules","templates","plans"],g=e=>{const s=new Map;e.usage.forEach(n=>s.set(n.competencyid,n));const t=new Map,u=new Map;e.rules.forEach(n=>{t.set(n.competencyid,n),n.children.forEach(o=>u.set(o.id,{parent:n.competencyid,points:o.points,required:o.required}))});const i=new Map;e.relations.forEach(n=>i.set(n.competencyid,n.count));const r=new Map;return e.checks.forEach(n=>{r.set(n.competencyid,[...r.get(n.competencyid)??[],n])}),{answer:e,usage:s,rules:t,ruled:u,relations:i,checks:r}},m=(e,s,t)=>e.usage.get(s)?.[t]??0,h=()=>{let e=0;return{begin:()=>++e,current:s=>s===e,drop:()=>{e++}}},l={status:"idle",index:null},d=e=>c(e)?e:"none",I=e=>e?{findings:e.answer.checks.length,summary:e.answer.summary,checks:e.answer.checks}:{findings:null,summary:null,checks:[]};export{l as IDLE,a as LENSES,p as MEASURES,g as indexInsights,I as insightFacts,c as isLens,h as latest,d as lensOf,m as usageOf};
