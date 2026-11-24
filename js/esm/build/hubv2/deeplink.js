/**
 * The canvas's deep link: the selected competency and the lens kept in the hub's address.
 *
 * The address is rewritten in place (history.replaceState: selecting never adds a history entry), and
 * only its competencyid (or lens) parameter changes; the path, the other parameters and the fragment that names
 * the hub's open tab stay. What the link reveals on the next visit is decided by the server, which
 * reveals it only when the competency belongs to the framework the map shows. The list view's AMD
 * twin is amd/src/central/deeplink.js; keep the two in step.
 *
 * @module     local_dimensions/hubv2/deeplink
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const r="competencyid",s=(t,e)=>{const n=new URL(t);return e!==null&&Number.isInteger(e)&&e>0?n.searchParams.set(r,String(e)):n.searchParams.delete(r),n.href},c=t=>{const e=s(window.location.href,t);e!==window.location.href&&window.history.replaceState(window.history.state,"",e)},o="lens",i=(t,e)=>{const n=new URL(t);return e&&e!=="none"?n.searchParams.set(o,e):n.searchParams.delete(o),n.href},a=t=>{const e=i(window.location.href,t);e!==window.location.href&&window.history.replaceState(window.history.state,"",e)};export{o as LENS_PARAM,r as PARAM,a as rememberLens,c as rememberSelection,s as withCompetency,i as withLens};
