var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * What the command palette lists for a text: its three groups and the order the arrows walk.
 *
 * The actions on the selected competency and the workspace's commands come from the registry
 * (commands.ts) and are filtered here by their labels; the "Go to" hits are the search service's
 * answer for the text, listed as they come. With no text the palette lists the actions and the
 * commands; a text shorter than the search's minimum filters them and asks the server nothing.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/paletteitems
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const MIN_QUERY = 2;
const fold = /* @__PURE__ */ __name((text) => text.normalize("NFD").replace(/[̀-ͯ]/g, "").toLocaleLowerCase(), "fold");
const searchable = /* @__PURE__ */ __name((query) => query.trim().length >= MIN_QUERY, "searchable");
const paletteGroups = /* @__PURE__ */ __name((input, query) => {
  const wanted = fold(query.trim());
  const matches = /* @__PURE__ */ __name((entry) => wanted === "" || fold(entry.label).includes(wanted), "matches");
  const groups = [
    { key: "actions", title: input.titles.actions, entries: input.actions.filter(matches) },
    { key: "goto", title: input.titles.hits, entries: searchable(query) ? input.gotos : [] },
    { key: "commands", title: input.titles.commands, entries: input.commands.filter(matches) }
  ];
  return groups.filter((group) => group.entries.length > 0);
}, "paletteGroups");
const flatEntries = /* @__PURE__ */ __name((groups) => groups.flatMap((group) => group.entries), "flatEntries");
const stepEntry = /* @__PURE__ */ __name((count, active, down) => {
  if (count === 0) {
    return -1;
  }
  if (active < 0) {
    return down ? 0 : count - 1;
  }
  return (active + (down ? 1 : -1) + count) % count;
}, "stepEntry");
export {
  MIN_QUERY,
  flatEntries,
  fold,
  paletteGroups,
  searchable,
  stepEntry
};
//# sourceMappingURL=paletteitems.dev.js.map
