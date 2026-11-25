var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The hex colours the appearance dialogue takes, and the WCAG 2 contrast ratio between two of them.
 *
 * A colour is what the server's helper::normalise_hex_color() accepts: #rgb or #rrggbb, the # optional, any case;
 * normalise() spells it as the server stores it. The ratio is WCAG 2's: (L1 + 0.05) / (L2 + 0.05) over the relative
 * luminances, each channel linearised with the sRGB curve (threshold 0.04045, the IEC 61966-2-1 value).
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/contrast
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const MIN_CONTRAST = 4.5;
const HEX = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i;
const normalise = /* @__PURE__ */ __name((value) => {
  const text = value.trim();
  if (!HEX.test(text)) {
    return "";
  }
  return text.startsWith("#") ? text : `#${text}`;
}, "normalise");
const channels = /* @__PURE__ */ __name((colour) => {
  const match = HEX.exec(colour.trim());
  if (!match) {
    return null;
  }
  const digits = match[1].length === 3 ? [...match[1]].map((digit) => digit + digit) : match[1].match(/../g);
  return [parseInt(digits[0], 16), parseInt(digits[1], 16), parseInt(digits[2], 16)];
}, "channels");
const linear = /* @__PURE__ */ __name((value) => {
  const ratio = value / 255;
  return ratio <= 0.04045 ? ratio / 12.92 : ((ratio + 0.055) / 1.055) ** 2.4;
}, "linear");
const luminance = /* @__PURE__ */ __name((colour) => {
  const rgb = channels(colour);
  if (!rgb) {
    return null;
  }
  return 0.2126 * linear(rgb[0]) + 0.7152 * linear(rgb[1]) + 0.0722 * linear(rgb[2]);
}, "luminance");
const contrastRatio = /* @__PURE__ */ __name((first, second) => {
  const a = luminance(first);
  const b = luminance(second);
  if (a === null || b === null) {
    return null;
  }
  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}, "contrastRatio");
const ratioText = /* @__PURE__ */ __name((ratio) => (Math.floor(ratio * 10) / 10).toFixed(1), "ratioText");
export {
  MIN_CONTRAST,
  channels,
  contrastRatio,
  luminance,
  normalise,
  ratioText
};
//# sourceMappingURL=contrast.dev.js.map
