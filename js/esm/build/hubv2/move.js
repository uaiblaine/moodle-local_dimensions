import{escapeHtml as s}from"./html";import{fill as l,fillObject as a}from"./strings";/**
 * The HTML of the move confirmation: where the competency goes, and which rules core resets on the way.
 *
 * core_competency\api::set_parent_competency() resets the rule of the competency's current parent
 * and of its new parent, whatever the rule was. The confirmation names each one that has a rule
 * today, as the delete confirmation names the parent's; with neither, it says nothing about rules.
 * (Core also resets the moved competency's own rule when it moves under one of its descendants,
 * which the canvas refuses before asking.)
 *
 * @module     local_dimensions/hubv2/move
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const m=t=>!!t&&!!t.ruletype,p=(t,r,n,e=o=>o)=>[m(r)?l(t.hubv2_moverule_oldparent,e(r.shortname)):"",m(n)?l(t.hubv2_moverule_newparent,e(n.shortname)):""].filter(o=>o!==""),h=(t,r,n,e)=>{const o=s(r),i=e?a(t.hubv2_moveconfirm,{name:o,parent:s(e.shortname)}):l(t.hubv2_moveconfirmroot,o),u=p(t,n,e,s).map(v=>`<p>${v}</p>`).join("");return`<p>${i}</p>${u}`};export{h as moveBody,p as ruleNotes};
