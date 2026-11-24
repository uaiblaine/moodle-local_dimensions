var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The command palette: one field driving one list, in a dialogue (Ctrl+K, Cmd+K on a Mac, or the
 * workspace's "Commands" button).
 *
 * The ARIA combobox pattern as the filter line's search draws it (amd/src/filter_line.js): the field is
 * the combobox and keeps the focus, the list is a listbox of labelled groups, the active option is named
 * by aria-activedescendant and marked aria-selected, a press on an option keeps the focus in the field.
 * Up and Down move (and wrap), Enter runs, Escape closes (Dialog.tsx). The groups and their filtering are
 * paletteitems.ts; the "Go to" hits come from the tree's search service with its debounce and minimum length,
 * as the workspace's own search asks it.
 *
 * @module     local_dimensions/hubv2/Palette
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useId, useMemo, useRef, useState } from "react";
import { notifyError } from "./amd";
import { ariaShortcut, shortcutCaps } from "./commands";
import Dialog from "./Dialog";
import { flatEntries, paletteGroups, searchable, stepEntry } from "./paletteitems";
import { track } from "./pending";
import { search } from "./repository";
import { fillCount } from "./strings";
const DEBOUNCE_MS = 300;
const Palette = /* @__PURE__ */ __name((props) => {
  const { labels, mac, frameworkid, actionstitle, actions, commands, onRun, onClose, onLostFocus } = props;
  const [query, setQuery] = useState("");
  const [hits, setHits] = useState(null);
  const [active, setActive] = useState(0);
  const field = useRef(null);
  const asked = useRef(0);
  const id = useId();
  const titleid = `${id}-title`;
  const listid = `${id}-list`;
  useEffect(() => {
    const text = query.trim();
    if (!searchable(text)) {
      asked.current++;
      setHits(null);
      return void 0;
    }
    const timer = window.setTimeout(() => {
      const ask = ++asked.current;
      track("palette", search(frameworkid, text)).then((answer) => {
        if (ask === asked.current) {
          setHits({ query: text, ...answer });
        }
        return null;
      }).catch(notifyError);
    }, DEBOUNCE_MS);
    return () => window.clearTimeout(timer);
  }, [query, frameworkid]);
  const gotos = useMemo(() => hits ? hits.items.map((item) => ({
    id: `goto-${item.id}`,
    label: item.shortname,
    icon: "fa-location-arrow",
    hint: [item.path, item.idnumber].filter((part) => part !== "").join(" \xB7 ")
  })) : [], [hits]);
  const groups = useMemo(() => paletteGroups({
    actions,
    commands,
    gotos,
    titles: { actions: actionstitle, hits: labels.hubv2_palette_goto, commands: labels.hubv2_palette_commands }
  }, query), [actions, commands, gotos, actionstitle, labels, query]);
  const entries = flatEntries(groups);
  const current = entries.length === 0 ? -1 : Math.min(active, entries.length - 1);
  const run = /* @__PURE__ */ __name((entry) => {
    const hit = entry.id.startsWith("goto-") ? hits?.items.find((item) => `goto-${item.id}` === entry.id) ?? null : null;
    onRun(entry.id, hit);
  }, "run");
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      setActive(stepEntry(entries.length, current, event.key === "ArrowDown"));
    } else if (event.key === "Enter") {
      event.preventDefault();
      if (current >= 0) {
        run(entries[current]);
      }
    }
  }, "onKeyDown");
  const optionId = /* @__PURE__ */ __name((index2) => `${id}-option-${index2}`, "optionId");
  let index = -1;
  return /* @__PURE__ */ jsxDEV(
    Dialog,
    {
      labelledby: titleid,
      className: "local-dimensions-hubv2-dialog local-dimensions-hubv2-palette",
      initialFocus: field,
      returnTo: "opener",
      onClose,
      onLostFocus,
      children: [
        /* @__PURE__ */ jsxDEV("h2", { id: titleid, className: "visually-hidden", children: labels.hubv2_commands }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
          lineNumber: 159,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-palette-field", children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-magnifying-glass", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
            lineNumber: 161,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(
            "input",
            {
              ref: field,
              type: "text",
              className: "local-dimensions-hubv2-palette-input",
              role: "combobox",
              "aria-label": labels.hubv2_palette_field,
              placeholder: labels.hubv2_palette_field,
              "aria-autocomplete": "list",
              "aria-expanded": entries.length > 0,
              "aria-controls": listid,
              "aria-activedescendant": current >= 0 ? optionId(current) : void 0,
              autoComplete: "off",
              value: query,
              onChange: (event) => {
                setQuery(event.target.value);
                setActive(0);
              },
              onKeyDown
            },
            void 0,
            false,
            {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
              lineNumber: 162,
              columnNumber: 17
            }
          )
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
          lineNumber: 160,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV(
          "div",
          {
            id: listid,
            className: "local-dimensions-hubv2-palette-list",
            role: "listbox",
            "aria-label": labels.hubv2_commands,
            onMouseDown: (event) => event.preventDefault(),
            children: groups.map((group) => /* @__PURE__ */ jsxDEV("div", { role: "group", "aria-labelledby": `${id}-${group.key}`, children: [
              /* @__PURE__ */ jsxDEV("div", { id: `${id}-${group.key}`, className: "local-dimensions-hubv2-palette-head", role: "presentation", children: group.title }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
                lineNumber: 191,
                columnNumber: 25
              }),
              group.entries.map((entry) => {
                index++;
                const at = index;
                const classes = at === current ? "local-dimensions-hubv2-palette-option local-dimensions-hubv2-palette-option--active" : "local-dimensions-hubv2-palette-option";
                return /* @__PURE__ */ jsxDEV(
                  "div",
                  {
                    id: optionId(at),
                    className: classes,
                    role: "option",
                    "aria-selected": at === current,
                    "aria-keyshortcuts": entry.shortcut ? ariaShortcut(entry.shortcut, mac) : void 0,
                    onClick: () => run(entry),
                    children: [
                      /* @__PURE__ */ jsxDEV("i", { className: `fa ${entry.icon} local-dimensions-hubv2-menu-icon`, "aria-hidden": "true" }, void 0, false, {
                        fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
                        lineNumber: 210,
                        columnNumber: 37
                      }),
                      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-menu-label", children: entry.label }, void 0, false, {
                        fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
                        lineNumber: 211,
                        columnNumber: 37
                      }),
                      entry.hint ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-palette-hint", children: entry.hint }, void 0, false, {
                        fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
                        lineNumber: 212,
                        columnNumber: 51
                      }) : null,
                      entry.shortcut ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-keys", "aria-hidden": "true", children: shortcutCaps(entry.shortcut, mac).map((cap) => /* @__PURE__ */ jsxDEV("kbd", { children: cap }, cap, false, {
                        fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
                        lineNumber: 215,
                        columnNumber: 93
                      })) }, void 0, false, {
                        fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
                        lineNumber: 214,
                        columnNumber: 41
                      }) : null
                    ]
                  },
                  entry.id,
                  true,
                  {
                    fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
                    lineNumber: 201,
                    columnNumber: 33
                  }
                );
              })
            ] }, group.key, true, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
              lineNumber: 190,
              columnNumber: 21
            }))
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
            lineNumber: 182,
            columnNumber: 13
          }
        ),
        entries.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-palette-none", children: labels.hubv2_palette_none }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
          lineNumber: 224,
          columnNumber: 37
        }) : null,
        /* @__PURE__ */ jsxDEV("div", { className: "visually-hidden", "aria-live": "polite", children: hits !== null ? fillCount(labels, "hubv2_searchresults", hits.total) : "" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
          lineNumber: 225,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-palette-foot", "aria-hidden": "true", children: [
          /* @__PURE__ */ jsxDEV("span", { children: [
            /* @__PURE__ */ jsxDEV("kbd", { children: "\u2191" }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
              lineNumber: 229,
              columnNumber: 23
            }),
            /* @__PURE__ */ jsxDEV("kbd", { children: "\u2193" }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
              lineNumber: 229,
              columnNumber: 35
            }),
            labels.hubv2_palette_keys_move
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
            lineNumber: 229,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { children: [
            /* @__PURE__ */ jsxDEV("kbd", { children: "Enter" }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
              lineNumber: 230,
              columnNumber: 23
            }),
            labels.hubv2_palette_keys_run
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
            lineNumber: 230,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { children: [
            /* @__PURE__ */ jsxDEV("kbd", { children: "Esc" }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
              lineNumber: 231,
              columnNumber: 23
            }),
            labels.hubv2_palette_keys_close
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
            lineNumber: 231,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
          lineNumber: 228,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Palette.tsx",
      lineNumber: 157,
      columnNumber: 9
    }
  );
}, "Palette");
var Palette_default = Palette;
export {
  Palette_default as default
};
//# sourceMappingURL=Palette.dev.js.map
