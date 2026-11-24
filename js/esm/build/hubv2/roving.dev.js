var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * One pane's roving tabindex: the item that carries tabindex 0, whether the focus is on one of the
 * pane's items, and moving the focus to an item once it is drawn.
 *
 * The stop moves at once and the focus with it when the item is already in the DOM, so a key
 * repeated faster than React renders still walks one item a press: the next press reads the stop
 * (activeRef), not the element the browser last focused. An item not drawn yet (a page just loaded,
 * a node React Flow places a frame later) is focused on the next frames, under Moodle's pending
 * counter so that Behat waits for it. Where the stop goes after a change is selection.nextStop().
 *
 * @module     local_dimensions/hubv2/roving
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useCallback, useEffect, useRef, useState } from "react";
import { FRAMEWORK_ID } from "./graph";
import { entryKey } from "./keynav";
import { notifyError } from "./amd";
import { track } from "./pending";
import { nextStop } from "./selection";
const FRAMES = 30;
const useRoving = /* @__PURE__ */ __name((options) => {
  const { order, tree, selected } = options;
  const [active, setActive] = useState(FRAMEWORK_ID);
  const activeRef = useRef(FRAMEWORK_ID);
  const inside = useRef(false);
  const previous = useRef(order);
  const latest = useRef(options);
  latest.current = options;
  const frame = useRef(0);
  const makeActive = useCallback((key) => {
    if (activeRef.current !== key) {
      activeRef.current = key;
      setActive(key);
    }
  }, []);
  const focusKey = useCallback((key, flag = true) => {
    makeActive(key);
    window.cancelAnimationFrame(frame.current);
    const attempt = /* @__PURE__ */ __name(() => {
      if (activeRef.current !== key) {
        return true;
      }
      const element = latest.current.find(key);
      if (!element) {
        return false;
      }
      latest.current.beforeFocus?.(key, flag);
      element.focus({ preventScroll: true });
      return true;
    }, "attempt");
    if (attempt()) {
      return;
    }
    track("focus", new Promise((resolve) => {
      let frames = 0;
      const retry = /* @__PURE__ */ __name(() => {
        if (attempt() || ++frames >= FRAMES) {
          resolve();
          return;
        }
        frame.current = window.requestAnimationFrame(retry);
      }, "retry");
      frame.current = window.requestAnimationFrame(retry);
    })).catch(notifyError);
  }, [makeActive]);
  useEffect(() => {
    const before = previous.current;
    previous.current = order;
    const stop = nextStop(before, order, tree, selected, activeRef.current, inside.current);
    if (stop.refocus) {
      const focused = document.activeElement;
      const container = latest.current.container.current;
      if (!focused || focused === document.body || container && container.contains(focused)) {
        focusKey(stop.key, true);
        return;
      }
      inside.current = false;
    }
    makeActive(stop.key);
  }, [order, tree, selected, focusKey, makeActive]);
  useEffect(() => () => window.cancelAnimationFrame(frame.current), []);
  const onFocus = useCallback((event) => {
    const element = event.target.closest(latest.current.item);
    if (!element || !event.currentTarget.contains(element)) {
      return;
    }
    inside.current = true;
    const key = latest.current.keyOf(element);
    if (key !== void 0) {
      makeActive(key);
    }
  }, [makeActive]);
  const onBlur = useCallback((event) => {
    const next = event.relatedTarget;
    if (next === null) {
      return;
    }
    if (event.currentTarget.contains(next) && next.closest(latest.current.item)) {
      return;
    }
    inside.current = false;
    const { tree: current, order: walk, selected: chosen } = latest.current;
    makeActive(entryKey(current, walk, chosen));
  }, [makeActive]);
  return { active, activeRef, inside, focusKey, onFocus, onBlur };
}, "useRoving");
export {
  useRoving
};
//# sourceMappingURL=roving.dev.js.map
