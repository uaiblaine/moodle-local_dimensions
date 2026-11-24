var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The map's column headings: above each column, the taxonomy term the framework gives that level.
 *
 * Quiet text in a strip along the canvas's top edge, inside the canvas, under its controls. The strip follows
 * the map sideways (its headings move and scale with the view), not up and down, and the map's fitted view
 * leaves the strip's height free above the cards, so a heading never covers a card at rest. The strip is
 * drawing for the eye: hidden from assistive technology, which reads the levels once, in the canvas's
 * description, and never a Tab stop. A manager's pencil on a heading opens the framework's form, where the
 * taxonomies are edited, for the pointer only: the root's "Edit structure" is the keyboard's way to it.
 *
 * @module     local_dimensions/hubv2/ColumnHeads
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useState } from "react";
import { fill } from "./strings";
const levelsSaid = /* @__PURE__ */ __name((labels, heads) => fill(labels.hubv2_columns, [...heads].sort((one, other) => one.level - other.level).map((head) => `${fill(labels.hubv2_fw_level, String(head.level))}: ${head.term}`).join("; ")), "levelsSaid");
const ColumnHeads = /* @__PURE__ */ __name(({ labels, heads, cardwidth, read, subscribe, describedby, canedit, onEdit }) => {
  const [viewport, setViewport] = useState(read);
  useEffect(() => {
    const stop = subscribe(setViewport);
    const frame = window.requestAnimationFrame(() => setViewport(read()));
    return () => {
      stop();
      window.cancelAnimationFrame(frame);
    };
  }, [read, subscribe]);
  if (heads.length === 0) {
    return null;
  }
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("p", { id: describedby, className: "visually-hidden", children: levelsSaid(labels, heads) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
      lineNumber: 96,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-colheads", "aria-hidden": "true", children: heads.map((head) => /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: "local-dimensions-hubv2-colhead",
        style: { left: `${viewport.x + head.x * viewport.zoom}px`, width: `${cardwidth * viewport.zoom}px` },
        children: [
          /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-colhead-term", children: head.term }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
            lineNumber: 104,
            columnNumber: 25
          }),
          canedit ? /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "local-dimensions-hubv2-iconbtn local-dimensions-hubv2-colhead-edit",
              tabIndex: -1,
              title: labels.hubv2_column_edit,
              onClick: (event) => {
                event.stopPropagation();
                onEdit();
              },
              children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-pen", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
                lineNumber: 116,
                columnNumber: 33
              })
            },
            void 0,
            false,
            {
              fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
              lineNumber: 106,
              columnNumber: 29
            }
          ) : null
        ]
      },
      head.level,
      true,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
        lineNumber: 99,
        columnNumber: 21
      }
    )) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
      lineNumber: 97,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
    lineNumber: 95,
    columnNumber: 9
  });
}, "ColumnHeads");
var ColumnHeads_default = ColumnHeads;
export {
  ColumnHeads_default as default,
  levelsSaid
};
//# sourceMappingURL=ColumnHeads.dev.js.map
