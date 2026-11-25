import{isWithin as m}from"./tree";/**
 * The impact preview (idea I28): which items a change marks on the map and in the outline while its dialogue
 * asks for the confirmation. The dialogue itself says the change and its count in words; the marks only show
 * where it lands.
 *
 * A delete touches the competency and every descendant the tree has loaded (core deletes the whole subtree;
 * the confirmation counts all of it, loaded or not). A move touches the old parent and the new one, ROOT for
 * the framework.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/impact
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const o=new Set,p=(e,t)=>new Set(Object.keys(e.nodes).map(Number).filter(r=>m(e,r,t))),s=(e,t)=>new Set([e,t]),b=(e,t)=>e.size===t.size&&[...e].every(r=>t.has(r));export{o as NO_IMPACT,p as deleteImpact,s as moveImpact,b as sameImpact};
