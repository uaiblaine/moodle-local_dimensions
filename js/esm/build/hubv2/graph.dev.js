var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * From the tree to what React Flow draws: one node per visible competency, the framework at the
 * left, a "load more" node after a partly loaded branch, the parent-child edges and, for the
 * selected competency, a dotted edge to each related competency on the canvas.
 *
 * Pure: the nodes carry plain data, and the components read the callbacks from context.
 *
 * @module     local_dimensions/hubv2/graph
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { layoutTree } from "./layout";
import { ROOT, isExpanded, visibleItems } from "./tree";
const CARD = { width: 240, height: 76 };
const FRAMEWORK_ID = "f";
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
const competencyOf = /* @__PURE__ */ __name((key) => /^c\d+$/.test(key) ? Number(key.slice(1)) : null, "competencyOf");
const buildGraph = /* @__PURE__ */ __name((input) => {
  const { tree, framework, selected, related, moving, labels } = input;
  const items = visibleItems(tree);
  const layoutinput = [{ key: FRAMEWORK_ID, parent: null }];
  items.forEach((item) => {
    layoutinput.push({
      key: item.kind === "competency" ? competencyKey(item.id) : `m${item.parent}`,
      parent: parentKey(item.parent)
    });
  });
  const positions = layoutTree(layoutinput);
  const nodes = [{
    id: FRAMEWORK_ID,
    type: "framework",
    position: positions[FRAMEWORK_ID],
    data: { kind: "framework", framework, count: framework.competencycount },
    ...CARD,
    selected: selected === null,
    ariaLabel: framework.shortname,
    ariaRole: "group"
  }];
  const edges = [];
  const ruled = /* @__PURE__ */ new Set();
  const onCanvas = /* @__PURE__ */ new Set();
  items.forEach((item) => {
    if (item.kind === "more") {
      const key2 = `m${item.parent}`;
      nodes.push({
        id: key2,
        type: "more",
        position: positions[key2],
        data: { kind: "more", parent: item.parent, remaining: item.remaining },
        ...CARD,
        selectable: false,
        focusable: false,
        ariaLabel: labels.more(item.remaining)
      });
      edges.push({
        id: `e-${key2}`,
        source: parentKey(item.parent),
        target: key2,
        type: "smoothstep",
        className: "local-dimensions-hubv2-edge local-dimensions-hubv2-edge-more"
      });
      return;
    }
    const node = tree.nodes[item.id];
    const key = competencyKey(item.id);
    const branch = tree.branches[item.id];
    onCanvas.add(item.id);
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
      ariaLabel: node.shortname,
      ariaRole: "group",
      domAttributes: {
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
      data: { label: first && parent ? parent.rulelabel : "" }
    });
  });
  if (selected !== null && onCanvas.has(selected)) {
    related.filter((id) => id !== selected && onCanvas.has(id)).forEach((id) => {
      edges.push({
        id: `r-${selected}-${id}`,
        source: competencyKey(selected),
        target: competencyKey(id),
        type: "default",
        className: "local-dimensions-hubv2-edge-related",
        zIndex: 1
      });
    });
  }
  return { nodes, edges, positions };
}, "buildGraph");
export {
  CARD,
  FRAMEWORK_ID,
  buildGraph,
  cardCount,
  competencyKey,
  competencyOf
};
//# sourceMappingURL=graph.dev.js.map
