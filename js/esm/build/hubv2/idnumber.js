/**
 * The ID number the outline's inline editor offers for a new competency.
 *
 * Core refuses a competency without an ID number or with one its framework already has, so typing a
 * list in the outline needs one per line. When the siblings' ID numbers share a stem and end in a
 * number ("DCE-1.1", "DCE-1.2"; "C01", "C02"; "7"), the next number after the highest is offered, at
 * the same width when the numbers are zero-padded. When the siblings disagree on the stem, or one does
 * not end in a number, nothing is offered: a guess there would be wrong more often than right. The
 * offer is only a value in the field: core decides whether it is unique.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/idnumber
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const c=s=>{const e=/^(.*?)(\d+)$/.exec(s.trim());return e?{stem:e[1],digits:e[2]}:null},a=(s,e=[])=>{const i=s.filter(t=>t.trim()!=="").map(c);if(i.length===0||i.length!==s.length||i.some(t=>t===null))return"";const r=i,n=r[0].stem;if(r.some(t=>t.stem!==n))return"";const g=r.reduce((t,u)=>Number(u.digits)>Number(t.digits)?u:t,r[0]),o=g.digits.startsWith("0")?g.digits.length:0,d=new Set([...s,...e].map(t=>t.trim()));let l=Number(g.digits)+1,m=n+String(l).padStart(o,"0");for(let t=0;d.has(m)&&t<=d.size;t++)l++,m=n+String(l).padStart(o,"0");return m};export{a as suggestIdnumber};
