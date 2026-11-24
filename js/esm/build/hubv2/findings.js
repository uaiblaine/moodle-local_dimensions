import{fillObject as s}from"./strings";/**
 * The checker's findings (idea I27): the order the panel lists them in and F8 walks, the codes present with
 * their counts, the codes checked and found nowhere, the words of each finding, and the one action of the
 * registry that fixes each kind.
 *
 * The findings come from the framework's insights (insights.ts); their words are the server's strings,
 * hubv2_check_<code> (the title) and hubv2_check_<code>_desc (a sentence whose {$a->name} placeholders the
 * finding's params fill). The codes are listed here by literal key: a code the client does not know yet is
 * listed last, titled by its code, with no sentence and no fix.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/findings
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const c=["rule_cannot_fire","rule_without_children","rule_single_child","unused","idnumber_empty","description_empty","colour_contrast"],u={rule_cannot_fire:"hubv2_check_rule_cannot_fire",rule_without_children:"hubv2_check_rule_without_children",rule_single_child:"hubv2_check_rule_single_child",unused:"hubv2_check_unused",idnumber_empty:"hubv2_check_idnumber_empty",description_empty:"hubv2_check_description_empty",colour_contrast:"hubv2_check_colour_contrast"},_={rule_cannot_fire:"hubv2_check_rule_cannot_fire_desc",rule_without_children:"hubv2_check_rule_without_children_desc",rule_single_child:"hubv2_check_rule_single_child_desc",unused:"hubv2_check_unused_desc",idnumber_empty:"hubv2_check_idnumber_empty_desc",description_empty:"hubv2_check_description_empty_desc",colour_contrast:"hubv2_check_colour_contrast_desc"},d={rule_cannot_fire:"rule",rule_without_children:"rule",rule_single_child:"rule",unused:"links",idnumber_empty:"edit",description_empty:"edit",colour_contrast:"edit"},o=n=>c.includes(n),i=n=>o(n)?c.indexOf(n):c.length,l=n=>n.map((e,t)=>({check:e,index:t})).sort((e,t)=>i(e.check.code)-i(t.check.code)||e.index-t.index).map(({check:e})=>e),p=n=>{const e=new Map;return l(n).forEach(t=>e.set(t.code,(e.get(t.code)??0)+1)),[...e.entries()].map(([t,r])=>({code:t,count:r}))},m=(n,e)=>{const t=new Set(n.map(r=>r.code));return c.filter(r=>!t.has(r)&&(r!=="unused"||e))},b=(n,e,t)=>n<=0?null:e===null||e<0||e>=n?t?0:n-1:(e+(t?1:-1)+n)%n,a=(n,e)=>!e||n.competencyid<=0||!o(n.code)?null:d[n.code],g=(n,e)=>o(e)&&n[u[e]]||e,C=(n,e)=>{if(!o(e.code))return"";const t={};return e.params.forEach(r=>{t[r.name]=r.value}),s(n[_[e.code]],t)},k=n=>{const e=new Map;return n.forEach(t=>e.set(t.competencyid,(e.get(t.competencyid)??0)+1)),e};export{c as CHECK_CODES,m as absentCodes,g as codeTitle,k as findingCounts,p as findingGroups,C as findingSentence,a as fixFor,o as isKnownCode,l as orderFindings,b as stepFinding};
