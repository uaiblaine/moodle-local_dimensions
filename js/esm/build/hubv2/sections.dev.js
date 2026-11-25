var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The companion sections of the panel as the workspace keeps them: what other plugins add for the selected
 * competency through the hook competency_panel_sections (local_dimensions_get_competency_panel_sections).
 *
 * The workspace asks when a competency is selected and after every action of the registry that changes it
 * (commands.ts `changes`), and only when the page says some plugin listens (HubProps `sections`). Each request
 * has its number; an answer or a failure of any request but the newest is dropped, so a slow answer for an
 * earlier selection never shows under a later one. A new selection clears what was shown at once; a reload of
 * the same selection keeps it until the answer replaces it. A failure clears the sections and is said in one
 * quiet line: the rest of the panel stays as it is, and no error dialogue opens.
 *
 * The answer is read again here as the server declared it (an allowlist): a malformed entry is dropped, a tone
 * outside the hook's is none, and a link is kept only when it is an http(s) address of the page's own origin.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/sections
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const TONES = ["success", "warning", "danger", "info", "neutral"];
const NO_SECTIONS = { competencyid: null, ask: 0, status: "idle", sections: [] };
const sectionsReducer = /* @__PURE__ */ __name((state, action) => {
  switch (action.type) {
    case "ask":
      return {
        competencyid: action.competencyid,
        ask: action.ask,
        status: "loading",
        sections: action.keep && action.competencyid === state.competencyid ? state.sections : []
      };
    case "answer":
      return action.ask === state.ask ? { ...state, status: "loaded", sections: action.sections } : state;
    case "fail":
      return action.ask === state.ask ? { ...state, status: "failed", sections: [] } : state;
    case "clear":
      return { competencyid: null, ask: action.ask, status: "idle", sections: [] };
    default:
      return state;
  }
}, "sectionsReducer");
const sectionsFor = /* @__PURE__ */ __name((state, selected) => selected !== null && state.competencyid === selected ? state : void 0, "sectionsFor");
const sameOriginHref = /* @__PURE__ */ __name((value, origin) => {
  let url;
  try {
    url = new URL(value);
  } catch {
    return "";
  }
  if (url.protocol !== "http:" && url.protocol !== "https:" || url.origin !== origin) {
    return "";
  }
  return value;
}, "sameOriginHref");
const text = /* @__PURE__ */ __name((entry, field) => {
  const value = entry[field];
  return typeof value === "string" ? value : "";
}, "text");
const isRecord = /* @__PURE__ */ __name((value) => typeof value === "object" && value !== null && !Array.isArray(value), "isRecord");
const readRows = /* @__PURE__ */ __name((rows) => {
  if (!Array.isArray(rows)) {
    return [];
  }
  return rows.filter(isRecord).map((row) => {
    const tone = text(row, "tone");
    return {
      label: text(row, "label"),
      value: text(row, "value"),
      tone: TONES.includes(tone) ? tone : ""
    };
  }).filter((row) => row.label !== "" && row.value !== "");
}, "readRows");
const readSections = /* @__PURE__ */ __name((answer, origin) => {
  if (!Array.isArray(answer)) {
    return [];
  }
  const seen = /* @__PURE__ */ new Set();
  const kept = [];
  answer.filter(isRecord).forEach((entry) => {
    const key = text(entry, "key");
    const title = text(entry, "title");
    if (key === "" || title === "" || seen.has(key)) {
      return;
    }
    seen.add(key);
    const linklabel = text(entry, "linklabel");
    const linkurl = linklabel === "" ? "" : sameOriginHref(text(entry, "linkurl"), origin);
    kept.push({
      key,
      component: text(entry, "component"),
      title,
      rows: readRows(entry.rows),
      note: text(entry, "note"),
      linklabel: linkurl === "" ? "" : linklabel,
      linkurl
    });
  });
  return kept;
}, "readSections");
const TONE_CLASSES = {
  success: "local-dimensions-hubv2-status local-dimensions-hubv2-status--success",
  warning: "local-dimensions-hubv2-status local-dimensions-hubv2-status--warning",
  danger: "local-dimensions-hubv2-status local-dimensions-hubv2-status--danger",
  info: "local-dimensions-hubv2-status local-dimensions-hubv2-status--info",
  neutral: "local-dimensions-hubv2-status"
};
const toneClass = /* @__PURE__ */ __name((tone) => TONES.includes(tone) ? TONE_CLASSES[tone] : TONE_CLASSES.neutral, "toneClass");
const headingId = /* @__PURE__ */ __name((key) => `local-dimensions-hubv2-companion-${key.replace(/[^a-z0-9_-]/g, "")}`, "headingId");
export {
  NO_SECTIONS,
  TONES,
  TONE_CLASSES,
  headingId,
  readSections,
  sameOriginHref,
  sectionsFor,
  sectionsReducer,
  toneClass
};
//# sourceMappingURL=sections.dev.js.map
