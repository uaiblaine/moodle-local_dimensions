/**
 * A left-to-right tidy tree: the root at the left, one column per depth.
 *
 * Every leaf takes the next row, in the order given, and every parent sits level with the middle
 * of its first and last child, so subtrees never overlap and siblings keep the server's order. A
 * gap after each top-level subtree that has children keeps the framework's areas apart. In a language
 * written right to left the same tree is mirrored, root at the right ({@see mirrorLayout()}). Pure: no
 * library and no DOM, so Jest pins it; positions are the top-left corners React Flow places nodes by.
 *
 * @module     local_dimensions/hubv2/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const h={columnWidth:320,rowHeight:100,groupGap:40},x=(s,t=h)=>{const c=new Set(s.map(r=>r.key)),n={},a=[];s.forEach(r=>{if(r.parent===null||!c.has(r.parent)){a.push(r.key);return}(n[r.parent]??=[]).push(r.key)});const o={};let i=0;const p=(r,u)=>{const e=n[r]??[];if(e.length===0){o[r]={x:u*t.columnWidth,y:i},i+=t.rowHeight;return}e.forEach(g=>{p(g,u+1),u===0&&(n[g]??[]).length>0&&(i+=t.groupGap)});const y=o[e[0]].y,l=o[e[e.length-1]].y;o[r]={x:u*t.columnWidth,y:(y+l)/2}};return a.forEach(r=>p(r,0)),o},d=s=>{const t={};return Object.entries(s).forEach(([c,n])=>{t[c]={x:0-n.x,y:n.y}}),t};export{h as GRID,x as layoutTree,d as mirrorLayout};
