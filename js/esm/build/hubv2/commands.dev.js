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
 * The framework is the tree's root: its own actions (the Structures tab's, run by the same AMD functions) have
 * the `framework` scope and are offered on the root alone; creating and importing a framework, which are not
 * about the one shown, have the `structures` scope. Their forms and services check the capability again, in the
 * framework's context or in the context the tab lists.
 *
 * Pure: Jest pins the availability matrix and the key map without a DOM.
 *
 * @module     local_dimensions/hubv2/commands
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { ROOT } from "./tree";
const ACTIONS = [
  { id: "edit", scope: "node", group: 1, label: "hubv2_edit", icon: "fa-pen", changes: true },
  {
    id: "rename",
    scope: "node",
    group: 1,
    label: "hubv2_cmd_rename",
    icon: "fa-i-cursor",
    shortcut: { key: "F2" },
    outlineonly: true
  },
  { id: "addchild", scope: "node", group: 1, label: "managecompetencies_addchild", icon: "fa-plus", changes: true },
  {
    id: "quicksibling",
    scope: "node",
    group: 1,
    label: "hubv2_cmd_quicksibling",
    icon: "fa-turn-down",
    shortcut: { key: "Enter", mod: true },
    outlineonly: true,
    changes: true
  },
  {
    id: "quickchild",
    scope: "node",
    group: 1,
    label: "hubv2_cmd_quickchild",
    icon: "fa-indent",
    shortcut: { key: "Enter", mod: true, shift: true },
    outlineonly: true,
    changes: true
  },
  { id: "moveto", scope: "node", group: 2, label: "hubv2_cmd_moveto", icon: "fa-folder-tree", changes: true },
  {
    id: "movepick",
    scope: "node",
    group: 2,
    label: "hubv2_cmd_movepick",
    icon: "fa-arrows-up-down-left-right",
    maponly: true,
    changes: true
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
  { id: "rule", scope: "node", group: 3, label: "hubv2_cmd_rule", icon: "fa-diagram-project", changes: true },
  { id: "links", scope: "node", group: 3, label: "hubv2_cmd_links", icon: "fa-link", changes: true },
  { id: "relate", scope: "node", group: 3, label: "hubv2_cmd_relate", icon: "fa-share-nodes", changes: true },
  {
    id: "delete",
    scope: "node",
    group: 4,
    label: "hubv2_delete",
    icon: "fa-trash",
    shortcut: { key: "Delete" },
    danger: true,
    changes: true
  },
  { id: "fwedit", scope: "framework", group: 1, label: "central_frameworks_edit", icon: "fa-pen" },
  { id: "fwscale", scope: "framework", group: 1, label: "central_frameworks_configurescale", icon: "fa-scale-balanced" },
  { id: "fwhide", scope: "framework", group: 2, label: "hubv2_fw_hide", icon: "fa-eye-slash" },
  { id: "fwshow", scope: "framework", group: 2, label: "hubv2_fw_show", icon: "fa-eye" },
  { id: "fwduplicate", scope: "framework", group: 2, label: "central_frameworks_duplicate", icon: "fa-copy" },
  { id: "fwexport", scope: "framework", group: 2, label: "hubv2_fw_export", icon: "fa-download" },
  { id: "fwdelete", scope: "framework", group: 3, label: "hubv2_fw_delete", icon: "fa-trash", danger: true },
  { id: "moveroot", scope: "move", group: 1, label: "hubv2_moveroot", icon: "fa-turn-up", changes: true },
  { id: "movecancel", scope: "move", group: 1, label: "hubv2_movecancel", icon: "fa-xmark" },
  { id: "viewoutline", scope: "workspace", group: 1, label: "hubv2_cmd_viewoutline", icon: "fa-list" },
  { id: "viewmap", scope: "workspace", group: 1, label: "hubv2_cmd_viewmap", icon: "fa-sitemap" },
  { id: "viewboth", scope: "workspace", group: 1, label: "hubv2_cmd_viewboth", icon: "fa-table-columns" },
  { id: "expandall", scope: "workspace", group: 2, label: "managecompetencies_expandall", icon: "fa-angles-down" },
  { id: "collapseall", scope: "workspace", group: 2, label: "managecompetencies_collapseall", icon: "fa-angles-up" },
  { id: "addtop", scope: "workspace", group: 3, label: "managecompetencies_addcompetency", icon: "fa-plus", changes: true },
  { id: "summary", scope: "workspace", group: 3, label: "hubv2_cmd_summary", icon: "fa-chart-simple" },
  { id: "lensnone", scope: "workspace", group: 4, label: "hubv2_cmd_lensnone", icon: "fa-eye-slash" },
  { id: "lensusage", scope: "workspace", group: 4, label: "hubv2_cmd_lensusage", icon: "fa-chart-column" },
  { id: "lensrules", scope: "workspace", group: 4, label: "hubv2_cmd_lensrules", icon: "fa-diagram-project" },
  { id: "lensrelations", scope: "workspace", group: 4, label: "hubv2_cmd_lensrelations", icon: "fa-share-nodes" },
  { id: "check", scope: "workspace", group: 5, label: "hubv2_cmd_check", icon: "fa-list-check" },
  {
    id: "nextfinding",
    scope: "workspace",
    group: 5,
    label: "hubv2_cmd_nextfinding",
    icon: "fa-arrow-down",
    shortcut: { key: "F8" }
  },
  {
    id: "prevfinding",
    scope: "workspace",
    group: 5,
    label: "hubv2_cmd_prevfinding",
    icon: "fa-arrow-up",
    shortcut: { key: "F8", shift: true }
  },
  { id: "fwnew", scope: "structures", group: 1, label: "central_frameworks_new", icon: "fa-plus" },
  { id: "fwimport", scope: "structures", group: 1, label: "central_frameworks_import_title", icon: "fa-upload" },
  { id: "bulkappearance", scope: "bulk", group: 1, label: "hubv2_bulk_appearance", icon: "fa-palette", changes: true },
  { id: "bulkmove", scope: "bulk", group: 1, label: "hubv2_bulk_move", icon: "fa-folder-tree", changes: true },
  { id: "bulkdelete", scope: "bulk", group: 2, label: "hubv2_bulk_delete", icon: "fa-trash", danger: true, changes: true }
];
const VIEW_ACTIONS = { viewoutline: "outline", viewmap: "map", viewboth: "both" };
const LENS_ACTIONS = {
  lensnone: "none",
  lensusage: "usage",
  lensrules: "rules",
  lensrelations: "relations"
};
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
const lensState = /* @__PURE__ */ __name((lens, context) => {
  if (lens === (context.lens ?? "none") || lens === "usage" && context.usagelevel === "none") {
    return "hidden";
  }
  return "enabled";
}, "lensState");
const workspaceState = /* @__PURE__ */ __name((spec, context) => {
  const view = VIEW_ACTIONS[spec.id];
  if (view) {
    return view === context.layout ? "hidden" : "enabled";
  }
  const lens = LENS_ACTIONS[spec.id];
  if (lens) {
    return lensState(lens, context);
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
    case "nextfinding":
    case "prevfinding":
      return context.findings === 0 ? "hidden" : "enabled";
    default:
      return "enabled";
  }
}, "workspaceState");
const frameworkState = /* @__PURE__ */ __name((spec, context) => {
  if (context.node || !context.frameworkcanmanage || context.moving) {
    return "hidden";
  }
  const visible = context.frameworkvisible !== false;
  if (spec.id === "fwhide") {
    return visible ? "enabled" : "hidden";
  }
  if (spec.id === "fwshow") {
    return visible ? "hidden" : "enabled";
  }
  return "enabled";
}, "frameworkState");
const actionState = /* @__PURE__ */ __name((id, context) => {
  const spec = specOf(id);
  if (spec.scope === "node") {
    return nodeState(spec, context);
  }
  if (spec.scope === "framework") {
    return frameworkState(spec, context);
  }
  if (spec.scope === "structures") {
    return context.cancreate && !context.moving ? "enabled" : "hidden";
  }
  if (spec.scope === "bulk") {
    return context.frameworkcanmanage && (context.picked ?? 0) > 0 && !context.moving ? "enabled" : "hidden";
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
const findingCommand = /* @__PURE__ */ __name((key) => {
  if (key.key !== "F8" || key.altKey || withMod(key)) {
    return null;
  }
  return key.shiftKey ? "prevfinding" : "nextfinding";
}, "findingCommand");
const sharedCommand = /* @__PURE__ */ __name((key) => {
  if (key.key === "F10" && key.shiftKey && !key.altKey && !withMod(key) || key.key === "ContextMenu" && bare(key)) {
    return "menu";
  }
  if (withMod(key) && !key.altKey && !key.shiftKey && key.key.toLowerCase() === "k") {
    return "palette";
  }
  const finding = findingCommand(key);
  if (finding) {
    return finding;
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
  LENS_ACTIONS,
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
