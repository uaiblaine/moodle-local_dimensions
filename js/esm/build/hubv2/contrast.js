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
 */const a=4.5,u=/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i,l=n=>{const t=n.trim();return u.test(t)?t.startsWith("#")?t:`#${t}`:""},i=n=>{const t=u.exec(n.trim());if(!t)return null;const r=t[1].length===3?[...t[1]].map(e=>e+e):t[1].match(/../g);return[parseInt(r[0],16),parseInt(r[1],16),parseInt(r[2],16)]},s=n=>{const t=n/255;return t<=.04045?t/12.92:((t+.055)/1.055)**2.4},o=n=>{const t=i(n);return t?.2126*s(t[0])+.7152*s(t[1])+.0722*s(t[2]):null},c=(n,t)=>{const r=o(n),e=o(t);return r===null||e===null?null:(Math.max(r,e)+.05)/(Math.min(r,e)+.05)},m=n=>(Math.floor(n*10)/10).toFixed(1);export{a as MIN_CONTRAST,i as channels,c as contrastRatio,o as luminance,l as normalise,m as ratioText};
