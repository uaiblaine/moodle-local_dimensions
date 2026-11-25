/**
 * Where the focus goes when one of the workspace's layers closes (a node's menu, the palette, "Move to"),
 * so it never falls to the page's body.
 *
 * A layer opened on an item (a row or a node, by a key or by its "more" button) gives the focus back to
 * that item, in its pane, which owns the Tab stop: the "more" button itself is out of the Tab order. A
 * layer opened by a control outside the panes (the panel's "More actions", the "Commands" button) gives
 * it back to that control while it is still on the page. A layer opened by another layer that has since
 * closed (the palette or a menu running "Move to"), or whose control is gone, gives it to the selected
 * item of the pane that last had the focus, or of the pane still drawn.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/focusreturn
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const r=(n,e)=>n&&e[n]?n:e.outline?"outline":e.map?"map":null,l=(n,e,t)=>{if(n.kind==="control"&&n.connected)return{kind:"control"};const o=r(n.kind==="item"?n.pane:e,t);return o?{kind:"item",pane:o}:{kind:"none"}};export{r as drawnPane,l as focusReturn};
