var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The map's column headings: above each column of competencies, its level and the taxonomy term the framework
 * gives that level ("Level 2: Competency").
 *
 * Quiet text in a strip along the canvas's top edge, inside the canvas, under its controls. The strip follows
 * the map sideways (its headings move and scale with the view), not up and down, and the map's fitted view
 * leaves the strip's height free above the cards, so a heading never covers a card at rest. The strip is
 * drawing for the eye: hidden from assistive technology, which reads the levels once, in the canvas's
 * description, and never a Tab stop.
 *
 * The level names are the framework's, so a manager's way to them is one control, named for what it does, above
 * the framework's own column: it opens the framework's form, where the taxonomies are edited. It is for the
 * pointer only: the root's "Edit structure" is the keyboard's way to the same form. A heading carries no control
 * of its own: one beside each term read as a button named after the term, once per column.
 *
 * @module     local_dimensions/hubv2/ColumnHeads
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useState } from "react";
import { headWords } from "./columns";
import { fill } from "./strings";
const levelsSaid = /* @__PURE__ */ __name((labels, heads) => fill(labels.hubv2_columns, [...heads].sort((one, other) => one.level - other.level).map((head) => headWords(labels.hubv2_fw_level, head)).join("; ")), "levelsSaid");
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
      lineNumber: 102,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-colheads", "aria-hidden": "true", children: [
      canedit ? (
        // The framework's column is column 0 in both directions (columns.ts): its heading is the way to
        // the framework's level names.
        /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: "local-dimensions-hubv2-colhead",
            style: { left: `${viewport.x}px`, width: `${cardwidth * viewport.zoom}px` },
            children: /* @__PURE__ */ jsxDEV(
              "button",
              {
                type: "button",
                className: "local-dimensions-hubv2-iconbtn local-dimensions-hubv2-colhead-edit",
                tabIndex: -1,
                onClick: (event) => {
                  event.stopPropagation();
                  onEdit();
                },
                children: [
                  /* @__PURE__ */ jsxDEV("i", { className: "fa fa-pen", "aria-hidden": "true" }, void 0, false, {
                    fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
                    lineNumber: 120,
                    columnNumber: 29
                  }),
                  /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-colhead-editlabel", children: labels.hubv2_column_edit }, void 0, false, {
                    fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
                    lineNumber: 121,
                    columnNumber: 29
                  })
                ]
              },
              void 0,
              true,
              {
                fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
                lineNumber: 111,
                columnNumber: 25
              }
            )
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
            lineNumber: 107,
            columnNumber: 21
          }
        )
      ) : null,
      heads.map((head) => /* @__PURE__ */ jsxDEV(
        "div",
        {
          className: "local-dimensions-hubv2-colhead",
          style: { left: `${viewport.x + head.x * viewport.zoom}px`, width: `${cardwidth * viewport.zoom}px` },
          children: /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-colhead-term", children: headWords(labels.hubv2_fw_level, head) }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
            lineNumber: 131,
            columnNumber: 25
          })
        },
        head.level,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
          lineNumber: 126,
          columnNumber: 21
        }
      ))
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
      lineNumber: 103,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/ColumnHeads.tsx",
    lineNumber: 101,
    columnNumber: 9
  });
}, "ColumnHeads");
var ColumnHeads_default = ColumnHeads;
export {
  ColumnHeads_default as default,
  levelsSaid
};
//# sourceMappingURL=ColumnHeads.dev.js.map
