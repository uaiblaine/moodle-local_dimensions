var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The tree's search: a field, and its hits in a list under it. Choosing a hit reveals it on the
 * canvas (its ancestors opened, loading as needed), selects it and centres it.
 *
 * @module     local_dimensions/hubv2/Search
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useId, useRef, useState } from "react";
import { notifyError } from "./amd";
import { track } from "./pending";
import { search } from "./repository";
import { fillCount } from "./strings";
const DEBOUNCE_MS = 300;
const MIN_QUERY = 2;
const Search = /* @__PURE__ */ __name(({ labels, frameworkid, onChoose }) => {
  const [query, setQuery] = useState("");
  const [hits, setHits] = useState(null);
  const [open, setOpen] = useState(false);
  const listid = useId();
  const fieldid = useId();
  const list = useRef(null);
  const asked = useRef(0);
  useEffect(() => {
    const text = query.trim();
    if (text.length < MIN_QUERY) {
      setHits(null);
      return void 0;
    }
    const timer = window.setTimeout(() => {
      const ask = ++asked.current;
      track("search", search(frameworkid, text)).then((answer) => {
        if (ask === asked.current) {
          setHits(answer);
          setOpen(true);
        }
        return null;
      }).catch(notifyError);
    }, DEBOUNCE_MS);
    return () => window.clearTimeout(timer);
  }, [query, frameworkid]);
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Escape") {
      setOpen(false);
    } else if (event.key === "ArrowDown" && open && list.current) {
      event.preventDefault();
      list.current.querySelector("button")?.focus();
    }
  }, "onKeyDown");
  const onListKeyDown = /* @__PURE__ */ __name((event) => {
    const buttons = [...list.current?.querySelectorAll("button") ?? []];
    const index = buttons.indexOf(document.activeElement);
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      const next = event.key === "ArrowDown" ? Math.min(index + 1, buttons.length - 1) : index - 1;
      (next < 0 ? document.getElementById(fieldid) : buttons[next])?.focus();
    } else if (event.key === "Escape") {
      setOpen(false);
      document.getElementById(fieldid)?.focus();
    }
  }, "onListKeyDown");
  const shown = open && hits !== null;
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-search", children: [
    /* @__PURE__ */ jsxDEV("label", { className: "visually-hidden", htmlFor: fieldid, children: labels.hubv2_search }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Search.tsx",
      lineNumber: 117,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "input",
      {
        id: fieldid,
        type: "search",
        className: "form-control form-control-sm",
        placeholder: labels.managecompetencies_searchplaceholder,
        value: query,
        autoComplete: "off",
        "aria-expanded": shown,
        "aria-controls": listid,
        onChange: (event) => setQuery(event.target.value),
        onFocus: () => setOpen(hits !== null),
        onKeyDown
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Search.tsx",
        lineNumber: 118,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("div", { className: "visually-hidden", "aria-live": "polite", children: hits !== null ? fillCount(labels, "hubv2_searchresults", hits.total) : "" }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Search.tsx",
      lineNumber: 131,
      columnNumber: 13
    }),
    shown ? /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-search-results", children: [
      hits.items.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted small m-2", children: labels.hubv2_searchnone }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Search.tsx",
        lineNumber: 136,
        columnNumber: 48
      }) : null,
      /* @__PURE__ */ jsxDEV("ul", { id: listid, ref: list, className: "list-unstyled mb-0", onKeyDown: onListKeyDown, children: hits.items.map((item) => /* @__PURE__ */ jsxDEV("li", { children: /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "local-dimensions-hubv2-search-hit",
          onClick: () => {
            setOpen(false);
            onChoose(item);
          },
          children: [
            /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-search-name", children: item.shortname }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Search.tsx",
              lineNumber: 148,
              columnNumber: 37
            }),
            item.path ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-search-path", children: item.path }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Search.tsx",
              lineNumber: 149,
              columnNumber: 50
            }) : null
          ]
        },
        void 0,
        true,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Search.tsx",
          lineNumber: 140,
          columnNumber: 33
        }
      ) }, item.id, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Search.tsx",
        lineNumber: 139,
        columnNumber: 29
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Search.tsx",
        lineNumber: 137,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Search.tsx",
      lineNumber: 135,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Search.tsx",
    lineNumber: 116,
    columnNumber: 9
  });
}, "Search");
var Search_default = Search;
export {
  Search_default as default
};
//# sourceMappingURL=Search.dev.js.map
