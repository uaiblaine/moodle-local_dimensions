/**
 * What the command palette lists for a text: its three groups and the order the arrows walk.
 *
 * The actions on the selected competency and the workspace's commands come from the registry
 * (commands.ts) and are filtered here by their labels; the "Go to" hits are the search service's
 * answer for the text, listed as they come. With no text the palette lists the actions and the
 * commands; a text shorter than the search's minimum filters them and asks the server nothing.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/paletteitems
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const l=2,n=t=>t.normalize("NFD").replace(/[̀-ͯ]/g,"").toLocaleLowerCase(),i=t=>t.trim().length>=2,c=(t,e)=>{const r=n(e.trim()),s=o=>r===""||n(o.label).includes(r);return[{key:"actions",title:t.titles.actions,entries:t.actions.filter(s)},{key:"goto",title:t.titles.hits,entries:i(e)?t.gotos:[]},{key:"commands",title:t.titles.commands,entries:t.commands.filter(s)}].filter(o=>o.entries.length>0)},m=t=>t.flatMap(e=>e.entries),g=(t,e,r)=>t===0?-1:e<0?r?0:t-1:(e+(r?1:-1)+t)%t;export{l as MIN_QUERY,m as flatEntries,n as fold,c as paletteGroups,i as searchable,g as stepEntry};
