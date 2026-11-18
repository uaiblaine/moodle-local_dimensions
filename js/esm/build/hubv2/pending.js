import r from"@moodle/lms/core/pending";/**
 * Holds on Moodle's pending-JavaScript counter across the canvas's asynchronous gaps.
 *
 * core/ajax holds the counter only while a request is in flight; a chain of requests separated by
 * a React render (children loaded, state changed, the layout recomputed, the node centred) leaves
 * a gap Behat would read as a settled page. Each gap is spanned by one `local_dimensions/hubv2:<what>`
 * token from start to the last state change.
 *
 * @module     local_dimensions/hubv2/pending
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s=(n,e)=>{const o=new r(`local_dimensions/hubv2:${n}`);return e.finally(()=>o.resolve())};export{s as track};
