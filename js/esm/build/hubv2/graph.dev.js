var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * From the tree to what React Flow draws: one node per visible competency, the framework at the
 * left, a "load more" node after a partly loaded branch, the parent-child edges and, for the
 * selected competency, a dotted edge to each related competency on the canvas.
 *
 * Every edge is drawing only: what an edge says (a parent, a rule, a relation) the nodes and the panel
 * say in words, so each edge is left out of the accessibility tree ({@see EDGE_A11Y}). Every node is a
 * group named with its place in the tree (keynav.nodeName): React Flow's wrapper is an application,
 * which no tree role can cross, so the map says the structure in words and the outline beside it is
 * the workspace's real tree.
 *
 * Pure: the nodes carry plain data, and the components read the callbacks from context.
 *
 * @module     local_dimensions/hubv2/graph
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { layoutTree, mirrorLayout } from "./layout";
import { ROOT, isExpanded, visibleItems } from "./tree";
const CARD = { width: 240, height: 76 };
const FRAMEWORK_ID = "f";
const EDGE_A11Y = { ariaRole: "presentation", ariaLabel: null, domAttributes: { "aria-hidden": true } };
const cardCount = /* @__PURE__ */ __name((node, childcount) => {
  if (childcount !== null) {
    return { kind: "children", count: childcount };
  }
  if (node.haschildren) {
    return null;
  }
  return node.coursecount > 0 ? { kind: "courses", count: node.coursecount } : null;
}, "cardCount");
const competencyKey = /* @__PURE__ */ __name((id) => `c${id}`, "competencyKey");
const parentKey = /* @__PURE__ */ __name((id) => id === ROOT ? FRAMEWORK_ID : competencyKey(id), "parentKey");
const moreKey = /* @__PURE__ */ __name((parent) => `m${parent}`, "moreKey");
const NODE_ATTRIBUTES = { tabIndex: -1, "aria-roledescription": void 0 };
const competencyOf = /* @__PURE__ */ __name((key) => /^c\d+$/.test(key) ? Number(key.slice(1)) : null, "competencyOf");
const buildGraph = /* @__PURE__ */ __name((input) => {
  const { tree, framework, selected, related, moving, labels } = input;
  const items = visibleItems(tree);
  const layoutinput = [{ key: FRAMEWORK_ID, parent: null }];
  items.forEach((item) => {
    layoutinput.push({
      key: item.kind === "competency" ? competencyKey(item.id) : moreKey(item.parent),
      parent: parentKey(item.parent)
    });
  });
  const placed = layoutTree(layoutinput);
  const positions = input.rtl ? mirrorLayout(placed) : placed;
  const drawn = new Set(items.flatMap((item) => item.kind === "competency" ? [item.id] : []));
  const relatedto = selected !== null && drawn.has(selected) ? tree.nodes[selected].shortname : null;
  const relatedset = new Set(relatedto !== null ? related.filter((id) => id !== selected) : []);
  const nodes = [{
    id: FRAMEWORK_ID,
    type: "framework",
    position: positions[FRAMEWORK_ID],
    data: { kind: "framework", framework, count: framework.competencycount },
    ...CARD,
    selected: selected === null,
    ariaLabel: labels.framework ? labels.framework(framework.competencycount) : framework.shortname,
    ariaRole: "group",
    domAttributes: { ...NODE_ATTRIBUTES, "aria-current": selected === null ? "true" : void 0 }
  }];
  const edges = [];
  const ruled = /* @__PURE__ */ new Set();
  items.forEach((item) => {
    if (item.kind === "more") {
      const key2 = moreKey(item.parent);
      nodes.push({
        id: key2,
        type: "more",
        position: positions[key2],
        data: { kind: "more", parent: item.parent, remaining: item.remaining },
        ...CARD,
        selectable: false,
        ariaLabel: labels.more(item.remaining),
        ariaRole: "group",
        domAttributes: NODE_ATTRIBUTES
      });
      edges.push({
        id: `e-${key2}`,
        source: parentKey(item.parent),
        target: key2,
        type: "smoothstep",
        className: "local-dimensions-hubv2-edge local-dimensions-hubv2-edge-more",
        ...EDGE_A11Y
      });
      return;
    }
    const node = tree.nodes[item.id];
    const key = competencyKey(item.id);
    const branch = tree.branches[item.id];
    const siblings = tree.branches[item.parent];
    const info = {
      name: node.shortname,
      // The framework is level 1 in both panes, so a competency is its depth plus one.
      level: item.depth + 1,
      position: (siblings ? siblings.ids.indexOf(item.id) : 0) + 1,
      total: siblings ? siblings.total : 1,
      expandable: node.haschildren,
      expanded: isExpanded(tree, item.id),
      children: branch ? branch.total : null,
      rule: node.ruletype ? node.rulelabel : "",
      relatedto: relatedset.has(item.id) ? relatedto : null
    };
    nodes.push({
      id: key,
      type: "competency",
      position: positions[key],
      data: {
        kind: "competency",
        node,
        expanded: isExpanded(tree, item.id),
        childcount: branch ? branch.total : null,
        depth: item.depth,
        moving: moving === item.id
      },
      ...CARD,
      selected: selected === item.id,
      ariaLabel: labels.name ? labels.name(info) : node.shortname,
      ariaRole: "group",
      domAttributes: {
        ...NODE_ATTRIBUTES,
        "aria-roledescription": labels.roledescription,
        "aria-current": selected === item.id ? "true" : void 0
      }
    });
    const parent = item.parent === ROOT ? null : tree.nodes[item.parent];
    const hasrule = !!(parent && parent.ruletype);
    const first = hasrule && !ruled.has(item.parent);
    if (first) {
      ruled.add(item.parent);
    }
    edges.push({
      id: `e-${key}`,
      source: parentKey(item.parent),
      target: key,
      type: hasrule ? "rule" : "smoothstep",
      className: hasrule ? "local-dimensions-hubv2-edge local-dimensions-hubv2-edge-rule" : "local-dimensions-hubv2-edge",
      data: { label: first && parent ? parent.rulelabel : "" },
      ...EDGE_A11Y
    });
  });
  if (selected !== null && drawn.has(selected)) {
    related.filter((id) => id !== selected && drawn.has(id)).forEach((id) => {
      edges.push({
        id: `r-${selected}-${id}`,
        source: competencyKey(selected),
        target: competencyKey(id),
        type: "default",
        className: "local-dimensions-hubv2-edge-related",
        zIndex: 1,
        ...EDGE_A11Y
      });
    });
  }
  return { nodes, edges, positions };
}, "buildGraph");
export {
  CARD,
  EDGE_A11Y,
  FRAMEWORK_ID,
  buildGraph,
  cardCount,
  competencyKey,
  competencyOf,
  moreKey,
  parentKey
};
//# sourceMappingURL=graph.dev.js.map
