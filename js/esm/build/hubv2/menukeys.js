import{typeAhead as f}from"./keynav";/**
 * A menu's keyboard and its place on the screen, for Menu.tsx.
 *
 * The keys are the APG Menu pattern's and the fleet Design System's (menu.md): Up and Down move through
 * every item and wrap, Home and End go to the ends, a printable character moves to the next label it
 * starts (keynav.typeAhead), Enter and Space run the item, Escape and Tab close the menu. A disabled
 * item is reached like the others and runs nothing.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/menukeys
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const y=n=>{if(n.length===0)return-1;const e=n.findIndex(t=>!t.disabled);return e<0?0:e},g=(n,e,t,l="")=>{const o={active:e,run:null,close:!1,handled:!0},r=n.length;if(t.key==="Escape"||t.key==="Tab")return{...o,close:!0};if(r===0||t.altKey||t.ctrlKey||t.metaKey)return{...o,handled:!1};switch(t.key){case"ArrowDown":return{...o,active:(e+1)%r};case"ArrowUp":return{...o,active:(e-1+r)%r};case"Home":return{...o,active:0};case"End":return{...o,active:r-1};case"Enter":case" ":return n[e]&&!n[e].disabled?{...o,run:e}:o;default:break}if(t.key.length===1&&l!==""){const u=n.map((a,s)=>({key:String(s),name:a.label})),b=f(u,String(e),l);return b===null?o:{...o,active:Number(b)}}return{...o,handled:!1}},M=(n,e,t,l,o,r=6,u=8)=>{const b=o?n.left:n.right-e.width,a=Math.max(u,Math.min(b,t.width-u-e.width)),s=Math.min(t.height,l?l.bottom:t.height)-u,p=Math.max(0,l?l.top:0)+u,h=n.bottom+r,m=n.top-r-e.height;if(h+e.height<=s)return{left:a,top:h,above:!1};if(m>=p)return{left:a,top:m,above:!0};const c=t.height-h,d=n.top-r,i=c>=d?h:m;return{left:a,top:Math.max(u,Math.min(i,t.height-u-e.height)),above:c<d}};export{y as firstActive,g as menuKey,M as placeMenu};
