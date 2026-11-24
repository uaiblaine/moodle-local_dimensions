var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * "Move to": the searchable way to give a competency a new parent, beside picking it on the map.
 *
 * A dialogue titled with the competency's name: a search field, then either the possible parents as a
 * single-select tree (an APG Tree View over the workspace's store, moveparents.ts; one Tab stop, the
 * keys of keynav.ts, its branches loaded as they are opened) or, for a text of two characters or more,
 * the search service's hits as a single-select list, each with its path. The framework's row stands for
 * the top level. The competency, its descendants and its current parent cannot be chosen.
 *
 * Once a parent is chosen, the dialogue reads both parents afresh and says, before its buttons, which of
 * them has a rule that core resets (move.ruleNotes, the same sentences the pick-on-the-map flow shows).
 * The commit button names the target and calls the workspace's one move (Hub.tsx moveUnder), which the
 * dialogue itself is the confirmation of. "Pick on the map" starts the pick-on-the-map flow instead.
 * It is opened by another layer (a menu, the palette), already closed: on close the focus goes to
 * the selected item of the pane that last had it (focusreturn.ts).
 *
 * The bulk dock opens it with several competencies, the topmost picked (bulk.topmost): its title counts them, its
 * body names the first five, none of them or their descendants can be chosen, a parent they all have is refused, and
 * once a parent is chosen it says how many already under it are skipped. Its rule notes name the parents of the
 * ones that move. It then returns the focus to the dock's button, and "Pick on the map" is not offered.
 *
 * @module     local_dimensions/hubv2/MoveDialog
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useId, useMemo, useRef, useState } from "react";
import { notifyError } from "./amd";
import Dialog from "./Dialog";
import { FRAMEWORK_ID } from "./graph";
import { keyAction, navOrder } from "./keynav";
import { ruleNotes } from "./move";
import { movePlan, preview } from "./bulk";
import { hitSelectable, parentChoices, pickerTree, sharedParent } from "./moveparents";
import { searchable } from "./paletteitems";
import { track } from "./pending";
import { search } from "./repository";
import { fill, fillCount, fillObject } from "./strings";
import { ROOT } from "./tree";
const DEBOUNCE_MS = 300;
const chevron = /* @__PURE__ */ __name((expanded, rtl) => {
  if (expanded) {
    return "fa-chevron-down";
  }
  return rtl ? "fa-chevron-left" : "fa-chevron-right";
}, "chevron");
const choiceClass = /* @__PURE__ */ __name((chosen) => chosen ? "local-dimensions-hubv2-pick local-dimensions-hubv2-pick--chosen" : "local-dimensions-hubv2-pick", "choiceClass");
const titleOf = /* @__PURE__ */ __name((labels, subjects) => subjects.length > 1 ? fillCount(labels, "hubv2_bulk_move_title", subjects.length) : fill(labels.hubv2_moveto_title, subjects[0].shortname), "titleOf");
const commitLabelOf = /* @__PURE__ */ __name((labels, target, bulk, count) => {
  if (!target) {
    return labels.hubv2_move;
  }
  if (bulk) {
    return target.id === ROOT ? fillCount(labels, "hubv2_bulk_move_commitroot", count) : fillObject(labels.hubv2_bulk_move_commit, { count: String(count), name: target.name });
  }
  return target.id === ROOT ? labels.hubv2_moveto_commitroot : fill(labels.hubv2_moveto_commit, target.name);
}, "commitLabelOf");
const BulkLines = /* @__PURE__ */ __name((props) => {
  const { labels, subjects, skipped } = props;
  if (subjects.length < 2) {
    return null;
  }
  const names = preview(subjects.map((subject) => subject.shortname));
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("ul", { className: "local-dimensions-hubv2-bulknames", children: [
      names.shown.map((name, index) => /* @__PURE__ */ jsxDEV("li", { children: name }, `${index}-${name}`, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
        lineNumber: 164,
        columnNumber: 51
      })),
      names.more > 0 ? /* @__PURE__ */ jsxDEV("li", { children: fill(labels.hubv2_bulk_more, String(names.more)) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
        lineNumber: 165,
        columnNumber: 35
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
      lineNumber: 163,
      columnNumber: 13
    }),
    skipped > 0 ? /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-dialog-label", children: fillCount(labels, "hubv2_bulk_move_skipped", skipped) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
      lineNumber: 168,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
    lineNumber: 162,
    columnNumber: 9
  });
}, "BulkLines");
const MoveDialog = /* @__PURE__ */ __name((props) => {
  const {
    labels,
    tree,
    subjects,
    frameworkid,
    frameworkname,
    rtl,
    loadBranch,
    freshParent,
    onPick,
    onCommit,
    onClose,
    onLostFocus,
    onTarget
  } = props;
  const bulk = subjects.length > 1;
  const canpick = props.canpick && !bulk;
  const ids = useMemo(() => subjects.map((subject) => subject.id), [subjects]);
  const currentparent = sharedParent(tree, ids);
  const [open, setOpen] = useState([]);
  const [active, setActive] = useState(FRAMEWORK_ID);
  const [target, setTarget] = useState(null);
  const [notes, setNotes] = useState([]);
  const [query, setQuery] = useState("");
  const [hits, setHits] = useState(null);
  const [activehit, setActivehit] = useState(0);
  const field = useRef(null);
  const treeRef = useRef(null);
  const hitsRef = useRef(null);
  const asked = useRef(0);
  const noted = useRef(0);
  const id = useId();
  const titleid = `${id}-title`;
  const topname = fill(labels.hubv2_moveto_top, frameworkname);
  const targetid = target ? target.id : null;
  useEffect(() => {
    onTarget?.(targetid);
  }, [targetid, onTarget]);
  const choices = useMemo(() => parentChoices(tree, open, ids, topname), [tree, open, ids, topname]);
  const order = useMemo(() => navOrder(pickerTree(tree, open, ids)), [tree, open, ids]);
  const plan = target ? movePlan(tree, ids, target.id) : { move: ids, skipped: [] };
  const leaving = [...new Set(plan.move.map((subject) => tree.nodes[subject]?.parentid ?? ROOT))].join(",");
  const searching = searchable(query);
  useEffect(() => {
    const text = query.trim();
    if (!searchable(text)) {
      asked.current++;
      setHits(null);
      return void 0;
    }
    const timer = window.setTimeout(() => {
      const ask = ++asked.current;
      track("moveto", search(frameworkid, text)).then((answer) => {
        if (ask === asked.current) {
          setHits(answer);
          setActivehit(0);
        }
        return null;
      }).catch(notifyError);
    }, DEBOUNCE_MS);
    return () => window.clearTimeout(timer);
  }, [query, frameworkid]);
  useEffect(() => {
    const ask = ++noted.current;
    if (!target) {
      setNotes([]);
      return;
    }
    const parents = leaving === "" ? [] : leaving.split(",").map(Number);
    track("moveto", Promise.all([Promise.all(parents.map(freshParent)), freshParent(target.id)])).then(([oldparents, newparent]) => {
      if (ask === noted.current) {
        const notes2 = oldparents.flatMap((oldparent) => ruleNotes(labels, oldparent, null));
        setNotes([.../* @__PURE__ */ new Set([...notes2, ...ruleNotes(labels, null, newparent)])]);
      }
      return null;
    }).catch(notifyError);
  }, [target, leaving, freshParent, labels]);
  const focusRow = /* @__PURE__ */ __name((key) => {
    setActive(key);
    window.requestAnimationFrame(() => {
      treeRef.current?.querySelector(`[data-key="${key}"]`)?.focus();
    });
  }, "focusRow");
  const openBranch = /* @__PURE__ */ __name((parent) => {
    const loaded = tree.branches[parent] !== void 0 ? Promise.resolve() : loadBranch(parent, false);
    track("moveto", loaded.then(() => setOpen((current) => current.includes(parent) ? current : [...current, parent]))).catch(notifyError);
  }, "openBranch");
  const chooseRow = /* @__PURE__ */ __name((row) => {
    if (row.kind === "more") {
      track("moveto", loadBranch(row.id, true)).catch(notifyError);
      return;
    }
    setActive(row.key);
    if (!row.disabled) {
      setTarget({ id: row.id, name: row.kind === "framework" ? topname : row.name });
    }
  }, "chooseRow");
  const onTreeKey = /* @__PURE__ */ __name((event) => {
    const action = keyAction(order, active, event, rtl);
    if (!action) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    switch (action.type) {
      case "focus":
        focusRow(action.key);
        break;
      case "expand":
        openBranch(action.id);
        break;
      case "collapse":
        setOpen((current) => current.filter((other) => other !== action.id));
        break;
      case "select": {
        const row = choices.find((choice) => choice.key === active);
        if (row) {
          chooseRow(row);
        }
        break;
      }
      case "loadmore":
        track("moveto", loadBranch(action.parent, true)).catch(notifyError);
        break;
      default:
        break;
    }
  }, "onTreeKey");
  const onHitsKey = /* @__PURE__ */ __name((event) => {
    const items = hits?.items ?? [];
    const moves = {
      ArrowDown: Math.min(activehit + 1, items.length - 1),
      ArrowUp: Math.max(activehit - 1, 0),
      Home: 0,
      End: items.length - 1
    };
    if (event.key in moves) {
      event.preventDefault();
      event.stopPropagation();
      const next = moves[event.key];
      setActivehit(next);
      window.requestAnimationFrame(() => hitsRef.current?.querySelectorAll('[role="option"]')[next]?.focus());
    } else if ((event.key === "Enter" || event.key === " ") && items[activehit]) {
      event.preventDefault();
      event.stopPropagation();
      chooseHit(items[activehit]);
    }
  }, "onHitsKey");
  const chooseHit = /* @__PURE__ */ __name((item) => {
    if (hitSelectable(item, ids, currentparent)) {
      setTarget({ id: item.id, name: item.shortname });
    }
  }, "chooseHit");
  const commitlabel = commitLabelOf(labels, target, bulk, plan.move.length);
  const activekey = choices.some((choice) => choice.key === active) ? active : FRAMEWORK_ID;
  return /* @__PURE__ */ jsxDEV(
    Dialog,
    {
      labelledby: titleid,
      className: "local-dimensions-hubv2-dialog local-dimensions-hubv2-movedialog",
      initialFocus: field,
      returnTo: "item",
      onClose,
      onLostFocus,
      children: [
        /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-dialog-head", children: [
          /* @__PURE__ */ jsxDEV("h2", { id: titleid, className: "local-dimensions-hubv2-dialog-title", children: titleOf(labels, subjects) }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
            lineNumber: 385,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "local-dimensions-hubv2-iconbtn",
              "aria-label": labels["core:closebuttontitle"],
              onClick: onClose,
              children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-xmark", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                lineNumber: 390,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
              lineNumber: 388,
              columnNumber: 17
            }
          )
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
          lineNumber: 384,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-dialog-body", children: [
          /* @__PURE__ */ jsxDEV(BulkLines, { labels, subjects, skipped: target ? plan.skipped.length : 0 }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
            lineNumber: 394,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("label", { className: "visually-hidden", htmlFor: `${id}-search`, children: labels.hubv2_moveto_search }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
            lineNumber: 395,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(
            "input",
            {
              ref: field,
              id: `${id}-search`,
              type: "search",
              className: "form-control form-control-sm",
              placeholder: labels.hubv2_moveto_search,
              autoComplete: "off",
              value: query,
              onChange: (event) => setQuery(event.target.value),
              onKeyDown: (event) => {
                if (event.key === "Enter") {
                  event.preventDefault();
                }
              }
            },
            void 0,
            false,
            {
              fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
              lineNumber: 396,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("p", { id: `${id}-parent`, className: "local-dimensions-hubv2-dialog-label", children: labels.hubv2_moveto_parent }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
            lineNumber: 412,
            columnNumber: 17
          }),
          searching ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
            /* @__PURE__ */ jsxDEV("div", { className: "visually-hidden", "aria-live": "polite", children: hits !== null ? fillCount(labels, "hubv2_searchresults", hits.total) : "" }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
              lineNumber: 415,
              columnNumber: 25
            }),
            hits !== null && hits.items.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_searchnone }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
              lineNumber: 419,
              columnNumber: 29
            }) : null,
            /* @__PURE__ */ jsxDEV(
              "ul",
              {
                ref: hitsRef,
                className: "local-dimensions-hubv2-picklist",
                role: "listbox",
                "aria-labelledby": `${id}-parent`,
                onKeyDown: onHitsKey,
                children: (hits?.items ?? []).map((item, index) => {
                  const selectable = hitSelectable(item, ids, currentparent);
                  return /* @__PURE__ */ jsxDEV(
                    "li",
                    {
                      className: choiceClass(target?.id === item.id),
                      role: "option",
                      tabIndex: index === activehit ? 0 : -1,
                      "aria-selected": target?.id === item.id,
                      "aria-disabled": selectable ? void 0 : true,
                      onClick: () => {
                        setActivehit(index);
                        chooseHit(item);
                      },
                      children: [
                        /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-pick-name", children: item.shortname }, void 0, false, {
                          fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                          lineNumber: 438,
                          columnNumber: 41
                        }),
                        item.path ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-pick-note", children: item.path }, void 0, false, {
                          fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                          lineNumber: 439,
                          columnNumber: 54
                        }) : null
                      ]
                    },
                    item.id,
                    true,
                    {
                      fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                      lineNumber: 426,
                      columnNumber: 37
                    }
                  );
                })
              },
              void 0,
              false,
              {
                fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                lineNumber: 421,
                columnNumber: 25
              }
            )
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
            lineNumber: 414,
            columnNumber: 21
          }) : /* @__PURE__ */ jsxDEV(
            "ul",
            {
              ref: treeRef,
              className: "local-dimensions-hubv2-picklist",
              role: "tree",
              "aria-labelledby": `${id}-parent`,
              onKeyDown: onTreeKey,
              children: choices.map((row) => /* @__PURE__ */ jsxDEV(
                "li",
                {
                  "data-key": row.key,
                  className: choiceClass(target !== null && row.kind !== "more" && target.id === row.id),
                  role: "treeitem",
                  "aria-level": row.level,
                  "aria-setsize": row.setsize,
                  "aria-posinset": row.posinset,
                  "aria-expanded": row.expandable ? row.expanded : void 0,
                  "aria-selected": row.kind === "more" ? void 0 : target !== null && target.id === row.id,
                  "aria-disabled": row.disabled ? true : void 0,
                  tabIndex: row.key === activekey ? 0 : -1,
                  style: { paddingInlineStart: `${0.25 + (row.level - 1) * 1.25}rem` },
                  onClick: () => chooseRow(row),
                  children: [
                    row.expandable ? /* @__PURE__ */ jsxDEV(
                      "span",
                      {
                        className: "local-dimensions-hubv2-otoggle local-dimensions-hubv2-otoggle-on",
                        "aria-hidden": "true",
                        onClick: (event) => {
                          event.stopPropagation();
                          if (row.expanded) {
                            setOpen((current) => current.filter((other) => other !== row.id));
                          } else {
                            openBranch(row.id);
                          }
                        },
                        children: /* @__PURE__ */ jsxDEV("i", { className: `fa ${chevron(row.expanded, rtl)}` }, void 0, false, {
                          fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                          lineNumber: 476,
                          columnNumber: 41
                        })
                      },
                      void 0,
                      false,
                      {
                        fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                        lineNumber: 465,
                        columnNumber: 37
                      }
                    ) : /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-otoggle", "aria-hidden": "true" }, void 0, false, {
                      fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                      lineNumber: 478,
                      columnNumber: 37
                    }),
                    /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-pick-name", children: row.kind === "more" ? fill(labels.hubv2_loadmore, String(row.remaining)) : row.name }, void 0, false, {
                      fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                      lineNumber: 479,
                      columnNumber: 33
                    }),
                    row.current ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-pick-note", children: labels.hubv2_moveto_current }, void 0, false, {
                      fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                      lineNumber: 483,
                      columnNumber: 37
                    }) : null,
                    row.kind === "competency" && ids.includes(row.id) ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-pick-note", children: bulk ? labels.hubv2_bulk_moveto_picked : labels.hubv2_moveto_self }, void 0, false, {
                      fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                      lineNumber: 486,
                      columnNumber: 37
                    }) : null
                  ]
                },
                row.key,
                true,
                {
                  fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
                  lineNumber: 449,
                  columnNumber: 29
                }
              ))
            },
            void 0,
            false,
            {
              fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
              lineNumber: 446,
              columnNumber: 21
            }
          ),
          notes.length > 0 ? /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-dialog-note", children: [
            /* @__PURE__ */ jsxDEV("i", { className: "fa fa-circle-info", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
              lineNumber: 496,
              columnNumber: 25
            }),
            /* @__PURE__ */ jsxDEV("div", { children: notes.map((note) => /* @__PURE__ */ jsxDEV("p", { className: "mb-0", children: note }, note, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
              lineNumber: 497,
              columnNumber: 51
            })) }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
              lineNumber: 497,
              columnNumber: 25
            })
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
            lineNumber: 495,
            columnNumber: 21
          }) : null
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
          lineNumber: 393,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-dialog-foot", children: [
          canpick ? /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary me-auto", onClick: onPick, children: labels.hubv2_moveto_pick }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
            lineNumber: 503,
            columnNumber: 21
          }) : null,
          /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-secondary", onClick: onClose, children: labels["core:cancel"] }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
            lineNumber: 507,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn btn-sm btn-primary",
              disabled: !target || plan.move.length === 0,
              onClick: () => {
                if (target) {
                  onCommit(target.id);
                }
              },
              children: commitlabel
            },
            void 0,
            false,
            {
              fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
              lineNumber: 508,
              columnNumber: 17
            }
          )
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
          lineNumber: 501,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/MoveDialog.tsx",
      lineNumber: 382,
      columnNumber: 9
    }
  );
}, "MoveDialog");
var MoveDialog_default = MoveDialog;
export {
  MoveDialog_default as default
};
//# sourceMappingURL=MoveDialog.dev.js.map
