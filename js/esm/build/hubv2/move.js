import{escapeHtml as t}from"./html";import{fill as n,fillObject as v}from"./strings";/**
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
 */const i=e=>!!e&&!!e.ruletype,f=(e,l,m,o)=>{const s=t(l),u=o?v(e.hubv2_moveconfirm,{name:s,parent:t(o.shortname)}):n(e.hubv2_moveconfirmroot,s),p=[i(m)?n(e.hubv2_moverule_oldparent,t(m.shortname)):"",i(o)?n(e.hubv2_moverule_newparent,t(o.shortname)):""].filter(r=>r!=="").map(r=>`<p>${r}</p>`).join("");return`<p>${u}</p>${p}`};export{f as moveBody};
