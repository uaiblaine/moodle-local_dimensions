/**
 * A left-to-right tidy tree: the root at the left, one column per depth.
 *
 * Every leaf takes the next row, in the order given, and every parent sits level with the middle
 * of its first and last child, so subtrees never overlap and siblings keep the server's order. A
 * gap after each top-level subtree that has children keeps the framework's areas apart. Pure: no
 * library and no DOM, so Jest pins it; positions are the top-left corners React Flow places nodes by.
 *
 * @module     local_dimensions/hubv2/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const h={columnWidth:320,rowHeight:100,groupGap:40},x=(c,o=h)=>{const g=new Set(c.map(t=>t.key)),u={},p=[];c.forEach(t=>{if(t.parent===null||!g.has(t.parent)){p.push(t.key);return}(u[t.parent]??=[]).push(t.key)});const n={};let s=0;const i=(t,e)=>{const r=u[t]??[];if(r.length===0){n[t]={x:e*o.columnWidth,y:s},s+=o.rowHeight;return}r.forEach(a=>{i(a,e+1),e===0&&(u[a]??[]).length>0&&(s+=o.groupGap)});const y=n[r[0]].y,l=n[r[r.length-1]].y;n[t]={x:e*o.columnWidth,y:(y+l)/2}};return p.forEach(t=>i(t,0)),n};export{h as GRID,x as layoutTree};
