import{requireAsync as o}from"@moodle/lms/core/amd";/**
 * The one door from the canvas's ES modules to Moodle's AMD modules.
 *
 * A component cannot import an AMD module: the import map Moodle serves resolves `@moodle/lms/`,
 * react and react-dom, and nothing of RequireJS, so `import Ajax from 'core/ajax'` in a .tsx fails
 * to resolve in the browser and the canvas never mounts. AMD is reached through core's loader
 * (`@moodle/lms/core/amd`) instead, and only from this file. Every module the canvas may load is a
 * key of `Modules`, so any other name is a type error under the tsc gate; the hub's dialogues are
 * reached here and never copied. Strings and the pending counter are core's ES modules
 * (`@moodle/lms/core/stringUtils`, `@moodle/lms/core/pending`) and are imported, not bridged.
 *
 * @module     local_dimensions/hubv2/amd
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const n=async e=>(await o("core/first"),o(e)),i=async e=>{try{await(await n("local_dimensions/central/errors")).notifyError(e)}catch{window.console.error(e)}};export{n as load,i as notifyError};
