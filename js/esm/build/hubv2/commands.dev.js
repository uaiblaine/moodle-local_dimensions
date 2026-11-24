var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The workspace's one list of actions, and the one decision of when each is offered.
 *
 * Every action exists in three places, the panel's buttons, a node's menu and the command palette, and
 * all three render from ACTIONS through actionState(), so none of them can offer what another refuses.
 * The keyboard's shortcuts (shortcutAction) name the same ids and pass through the same decision. The
 * decision is a courtesy: every write goes through a core competency service that checks
 * moodle/competency:competencymanage in the competency's own context, whatever the client offered.
 *
 * Pure: Jest pins the availability matrix and the key map without a DOM.
 *
 * @module     local_dimensions/hubv2/commands
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { ROOT } from "./tree";
const ACTIONS = [
  { id: "edit", scope: "node", group: 1, label: "hubv2_edit", icon: "fa-pen" },
  {
    id: "rename",
    scope: "node",
    group: 1,
    label: "hubv2_cmd_rename",
    icon: "fa-i-cursor",
    shortcut: { key: "F2" },
    outlineonly: true
  },
  { id: "addchild", scope: "node", group: 1, label: "managecompetencies_addchild", icon: "fa-plus" },
  {
    id: "quicksibling",
    scope: "node",
    group: 1,
    label: "hubv2_cmd_quicksibling",
    icon: "fa-turn-down",
    shortcut: { key: "Enter", mod: true },
    outlineonly: true
  },
  {
    id: "quickchild",
    scope: "node",
    group: 1,
    label: "hubv2_cmd_quickchild",
    icon: "fa-indent",
    shortcut: { key: "Enter", mod: true, shift: true },
    outlineonly: true
  },
  { id: "moveto", scope: "node", group: 2, label: "hubv2_cmd_moveto", icon: "fa-folder-tree" },
  {
    id: "movepick",
    scope: "node",
    group: 2,
    label: "hubv2_cmd_movepick",
    icon: "fa-arrows-up-down-left-right",
    maponly: true
  },
  { id: "moveup", scope: "node", group: 2, label: "hubv2_moveup", icon: "fa-arrow-up", shortcut: { key: "ArrowUp", alt: true } },
  {
    id: "movedown",
    scope: "node",
    group: 2,
    label: "hubv2_movedown",
    icon: "fa-arrow-down",
    shortcut: { key: "ArrowDown", alt: true }
  },
  { id: "rule", scope: "node", group: 3, label: "hubv2_cmd_rule", icon: "fa-diagram-project" },
  { id: "links", scope: "node", group: 3, label: "hubv2_cmd_links", icon: "fa-link" },
  { id: "relate", scope: "node", group: 3, label: "hubv2_cmd_relate", icon: "fa-share-nodes" },
  { id: "delete", scope: "node", group: 4, label: "hubv2_delete", icon: "fa-trash", shortcut: { key: "Delete" }, danger: true },
  { id: "moveroot", scope: "move", group: 1, label: "hubv2_moveroot", icon: "fa-turn-up" },
  { id: "movecancel", scope: "move", group: 1, label: "hubv2_movecancel", icon: "fa-xmark" },
  { id: "viewoutline", scope: "workspace", group: 1, label: "hubv2_cmd_viewoutline", icon: "fa-list" },
  { id: "viewmap", scope: "workspace", group: 1, label: "hubv2_cmd_viewmap", icon: "fa-sitemap" },
  { id: "viewboth", scope: "workspace", group: 1, label: "hubv2_cmd_viewboth", icon: "fa-table-columns" },
  { id: "expandall", scope: "workspace", group: 2, label: "managecompetencies_expandall", icon: "fa-angles-down" },
  { id: "collapseall", scope: "workspace", group: 2, label: "managecompetencies_collapseall", icon: "fa-angles-up" },
  { id: "addtop", scope: "workspace", group: 3, label: "managecompetencies_addcompetency", icon: "fa-plus" },
  { id: "summary", scope: "workspace", group: 3, label: "hubv2_cmd_summary", icon: "fa-chart-simple" }
];
const VIEW_ACTIONS = { viewoutline: "outline", viewmap: "map", viewboth: "both" };
const specOf = /* @__PURE__ */ __name((id) => ACTIONS.find((spec) => spec.id === id), "specOf");
const nodeState = /* @__PURE__ */ __name((spec, context) => {
  const { node } = context;
  if (!node || !node.canmanage || context.moving) {
    return "hidden";
  }
  if (spec.outlineonly && context.layout === "map" || spec.maponly && context.layout === "outline") {
    return "hidden";
  }
  switch (spec.id) {
    case "rule":
      return node.haschildren ? "enabled" : "disabled";
    case "moveup":
      return context.first ? "disabled" : "enabled";
    case "movedown":
      return context.last ? "disabled" : "enabled";
    default:
      return "enabled";
  }
}, "nodeState");
const workspaceState = /* @__PURE__ */ __name((spec, context) => {
  const view = VIEW_ACTIONS[spec.id];
  if (view) {
    return view === context.layout ? "hidden" : "enabled";
  }
  switch (spec.id) {
    case "expandall":
      if (context.empty) {
        return "hidden";
      }
      return context.expanding ? "disabled" : "enabled";
    case "collapseall":
      return context.empty ? "hidden" : "enabled";
    case "addtop":
      return context.frameworkcanmanage && !context.moving ? "enabled" : "hidden";
    default:
      return "enabled";
  }
}, "workspaceState");
const actionState = /* @__PURE__ */ __name((id, context) => {
  const spec = specOf(id);
  if (spec.scope === "node") {
    return nodeState(spec, context);
  }
  if (spec.scope === "move") {
    if (!context.moving) {
      return "hidden";
    }
    return spec.id === "moveroot" && context.moving.parentid === ROOT ? "hidden" : "enabled";
  }
  return workspaceState(spec, context);
}, "actionState");
const actionsFor = /* @__PURE__ */ __name((scopes, context) => ACTIONS.filter((spec) => scopes.includes(spec.scope)).map((spec) => ({ spec, state: actionState(spec.id, context) })).filter((view) => view.state !== "hidden"), "actionsFor");
const withMod = /* @__PURE__ */ __name((key) => !!(key.ctrlKey || key.metaKey), "withMod");
const bare = /* @__PURE__ */ __name((key) => !key.altKey && !key.ctrlKey && !key.metaKey && !key.shiftKey, "bare");
const sharedCommand = /* @__PURE__ */ __name((key) => {
  if (key.key === "F10" && key.shiftKey && !key.altKey && !withMod(key) || key.key === "ContextMenu" && bare(key)) {
    return "menu";
  }
  if (withMod(key) && !key.altKey && !key.shiftKey && key.key.toLowerCase() === "k") {
    return "palette";
  }
  const arrows = { ArrowUp: "moveup", ArrowDown: "movedown" };
  if (key.altKey && !withMod(key) && !key.shiftKey && arrows[key.key]) {
    return arrows[key.key];
  }
  return key.key === "Delete" && bare(key) ? "delete" : null;
}, "sharedCommand");
const authoringCommand = /* @__PURE__ */ __name((key) => {
  if (bare(key)) {
    const plain = { F2: "rename", Insert: "quicksibling" };
    return plain[key.key] ?? null;
  }
  if (key.key === "Enter" && withMod(key) && !key.altKey) {
    return key.shiftKey ? "quickchild" : "quicksibling";
  }
  return null;
}, "authoringCommand");
const shortcutAction = /* @__PURE__ */ __name((key, pane) => sharedCommand(key) ?? (pane === "outline" ? authoringCommand(key) : null), "shortcutAction");
const KEY_CAPS = { ArrowUp: "\u2191", ArrowDown: "\u2193", ArrowLeft: "\u2190", ArrowRight: "\u2192", Delete: "Del" };
const shortcutCaps = /* @__PURE__ */ __name((shortcut, mac) => {
  const caps = [];
  if (shortcut.mod) {
    caps.push(mac ? "\u2318" : "Ctrl");
  }
  if (shortcut.alt) {
    caps.push(mac ? "\u2325" : "Alt");
  }
  if (shortcut.shift) {
    caps.push(mac ? "\u21E7" : "Shift");
  }
  caps.push(KEY_CAPS[shortcut.key] ?? shortcut.key);
  return caps;
}, "shortcutCaps");
const ariaShortcut = /* @__PURE__ */ __name((shortcut, mac) => {
  const parts = [];
  if (shortcut.mod) {
    parts.push(mac ? "Meta" : "Control");
  }
  if (shortcut.alt) {
    parts.push("Alt");
  }
  if (shortcut.shift) {
    parts.push("Shift");
  }
  parts.push(shortcut.key);
  return parts.join("+");
}, "ariaShortcut");
const PALETTE_SHORTCUT = { key: "K", mod: true };
const isMac = /* @__PURE__ */ __name(() => /Mac|iPhone|iPad/.test(window.navigator.platform || window.navigator.userAgent), "isMac");
export {
  ACTIONS,
  PALETTE_SHORTCUT,
  VIEW_ACTIONS,
  actionState,
  actionsFor,
  ariaShortcut,
  isMac,
  shortcutAction,
  shortcutCaps,
  specOf
};
//# sourceMappingURL=commands.dev.js.map
