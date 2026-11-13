var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The canvas's strings: read once at mount, then filled in place.
 *
 * The page hands every one of them to core's string cache (hubv2_page::STRINGS and OTHER_STRINGS,
 * through strings_for_js), so {@see loadLabels()} resolves without a request. hubv2_page_test holds
 * these lists equal to the server's and every key present in both languages.
 *
 * @module     local_dimensions/hubv2/strings
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { getStrings } from "@moodle/lms/core/stringUtils";
const COMPONENT = "local_dimensions";
const KEYS = [
  "central_browseframeworks_framework",
  "central_links_button",
  "central_related_button",
  "central_related_empty",
  "central_rule_nochildren",
  "hubv2_a11y_controls",
  "hubv2_a11y_handle",
  "hubv2_a11y_node",
  "hubv2_actions",
  "hubv2_appearance",
  "hubv2_assessment",
  "hubv2_canvas",
  "hubv2_children",
  "hubv2_children_one",
  "hubv2_collapse",
  "hubv2_colour",
  "hubv2_competencies",
  "hubv2_competencies_one",
  "hubv2_courses",
  "hubv2_courses_one",
  "hubv2_delete",
  "hubv2_delete_blocked_badges",
  "hubv2_delete_blocked_closing_links",
  "hubv2_delete_blocked_closing_users",
  "hubv2_delete_blocked_courses",
  "hubv2_delete_blocked_courses_one",
  "hubv2_delete_blocked_intro",
  "hubv2_delete_blocked_intro_tree",
  "hubv2_delete_blocked_intro_tree_one",
  "hubv2_delete_blocked_plans",
  "hubv2_delete_blocked_plans_one",
  "hubv2_delete_blocked_templates",
  "hubv2_delete_blocked_templates_one",
  "hubv2_delete_blocked_title",
  "hubv2_delete_blocked_users",
  "hubv2_delete_blocked_users_one",
  "hubv2_delete_confirm_leaf",
  "hubv2_delete_confirm_tree",
  "hubv2_delete_confirm_tree_one",
  "hubv2_delete_parentrule",
  "hubv2_description",
  "hubv2_edit",
  "hubv2_expand",
  "hubv2_fitview",
  "hubv2_loading",
  "hubv2_loadmore",
  "hubv2_minimap",
  "hubv2_move",
  "hubv2_movecancel",
  "hubv2_movecancelled",
  "hubv2_moveconfirm",
  "hubv2_moveconfirmroot",
  "hubv2_movedown",
  "hubv2_moved",
  "hubv2_moveinvalid",
  "hubv2_movepick",
  "hubv2_moveroot",
  "hubv2_movetitle",
  "hubv2_moveup",
  "hubv2_nocolour",
  "hubv2_noderole",
  "hubv2_noselection",
  "hubv2_path",
  "hubv2_relate",
  "hubv2_related_offcanvas",
  "hubv2_rootlevel",
  "hubv2_rule",
  "hubv2_scale",
  "hubv2_search",
  "hubv2_searchnone",
  "hubv2_searchresults",
  "hubv2_searchresults_one",
  "hubv2_seeusage",
  "hubv2_tag1",
  "hubv2_tag2",
  "hubv2_type",
  "hubv2_usage",
  "hubv2_usage_activities",
  "hubv2_usage_courses",
  "hubv2_usage_none",
  "hubv2_usage_templates",
  "hubv2_zoomhint",
  "hubv2_zoomin",
  "hubv2_zoomout",
  "managecompetencies_addchild",
  "managecompetencies_addcompetency",
  "managecompetencies_norule",
  "managecompetencies_searchplaceholder"
];
const OTHER = [
  ["core", "changessaved"],
  ["core", "idnumber"],
  ["tool_lp", "addcompetency"],
  ["tool_lp", "competencycannotbedeleted"],
  ["tool_lp", "deletecompetency"],
  ["tool_lp", "editcompetency"]
];
const loadLabels = /* @__PURE__ */ __name(() => {
  const requests = [
    ...KEYS.map((key) => ({ key, component: COMPONENT })),
    ...OTHER.map(([component, key]) => ({ key, component }))
  ];
  return getStrings(requests).then((values) => {
    const labels = {};
    KEYS.forEach((key, index) => {
      labels[key] = values[index];
    });
    OTHER.forEach(([component, key], index) => {
      labels[`${component}:${key}`] = values[KEYS.length + index];
    });
    return labels;
  });
}, "loadLabels");
const fill = /* @__PURE__ */ __name((template, value) => (template || "").split("{$a}").join(value), "fill");
const fillObject = /* @__PURE__ */ __name((template, values) => Object.entries(values).reduce(
  (text, [name, value]) => text.split(`{$a->${name}}`).join(value),
  template || ""
), "fillObject");
const fillCount = /* @__PURE__ */ __name((labels, key, count) => fill(count === 1 && labels[`${key}_one`] ? labels[`${key}_one`] : labels[key], String(count)), "fillCount");
export {
  KEYS,
  OTHER,
  fill,
  fillCount,
  fillObject,
  loadLabels
};
//# sourceMappingURL=strings.dev.js.map
