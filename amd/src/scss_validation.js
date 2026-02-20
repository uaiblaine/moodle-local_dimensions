/**
 * SCSS frontend validation and editor format hiding for template forms.
 *
 * Validates SCSS syntax client-side before form submission:
 * - Detects unmatched closing braces/parentheses.
 * - Detects unbalanced brace/parenthesis pairs.
 * - Warns about likely missing semicolons (with user confirmation).
 *
 * Also hides the editor format dropdown for the SCSS custom field,
 * since the value is forced to FORMAT_PLAIN on the server side.
 *
 * @module     local_dimensions/scss_validation
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /** @var {Object} Language strings for validation messages. */
    var strings = {};

    /** @var {string[]} Textarea selectors for the SCSS custom field. */
    var TEXTAREA_SELECTORS = [
        'textarea[name="customfield_customscss_editor[text]"]',
        'textarea[name="customfield_customscss[text]"]',
        'textarea[name="customfield_customscss"]'
    ];

    /** @var {string[]} ID selectors for dedicated format form-items (rendered as separate rows). */
    var FORMAT_FITEM_SELECTORS = [
        '#fitem_id_customfield_customscss_editorformat',
        '#fgroup_id_customfield_customscss_editorformat'
    ];

    /** @var {string[]} Selectors for the format <select> itself (may live inside the editor fitem). */
    var FORMAT_SELECT_SELECTORS = [
        '#menucustomfield_customscss_editorformat',
        '#id_customfield_customscss_editorformat',
        'select[name="customfield_customscss_editor[format]"]',
        'select[name="customfield_customscss[format]"]'
    ];

    /**
     * Hide the editor format dropdown for the SCSS field.
     *
     * Two strategies:
     * 1. If the format has its OWN .fitem row, hide the entire row.
     * 2. If the <select> lives INSIDE the editor's .fitem, hide only
     *    the <select> and its label — never the parent .fitem.
     */
    var hideFormatSelector = function() {
        // Strategy 1: dedicated form-item rows.
        FORMAT_FITEM_SELECTORS.forEach(function(sel) {
            var row = document.querySelector(sel);
            if (row) {
                row.style.display = 'none';
            }
        });

        // Strategy 2: hide <select> elements directly.
        var selects = document.querySelectorAll(FORMAT_SELECT_SELECTORS.join(', '));
        selects.forEach(function(el) {
            el.style.display = 'none';
            // Hide its label, if any.
            if (el.id) {
                var label = document.querySelector('label[for="' + el.id + '"]');
                if (label) {
                    label.style.display = 'none';
                }
            }
            // Also try to hide a thin wrapping <div> that only contains the
            // select (e.g. <div class="form-inline">) — but NOT a .fitem.
            var wrapper = el.parentElement;
            if (wrapper && !wrapper.classList.contains('fitem') &&
                !wrapper.id.startsWith('fitem_') &&
                wrapper.querySelectorAll('textarea, [contenteditable]').length === 0) {
                wrapper.style.display = 'none';
            }
        });
    };

    /**
     * Get raw SCSS text from the textarea.
     *
     * Triggers TinyMCE global save (if present) so the textarea is in sync,
     * then reads the textarea value and strips any HTML tags added by the
     * rich-text editor wrapper.
     *
     * @return {string}
     */
    var getScssContent = function() {
        // Global triggerSave syncs ALL TinyMCE instances without accessing
        // individual editor objects, avoiding the "editor.on" error.
        try {
            if (typeof window.tinyMCE !== 'undefined' &&
                typeof window.tinyMCE.triggerSave === 'function') {
                window.tinyMCE.triggerSave();
            }
        } catch (ignored) {
            // TinyMCE may not be loaded or initialized — safe to ignore.
        }

        var textarea = null;
        for (var i = 0; i < TEXTAREA_SELECTORS.length; i++) {
            textarea = document.querySelector(TEXTAREA_SELECTORS[i]);
            if (textarea) {
                break;
            }
        }

        if (!textarea) {
            return '';
        }

        var value = (textarea.value || '').trim();

        // If the editor wrapped SCSS in HTML tags, strip them to get raw text.
        if (value.indexOf('<') !== -1) {
            var tmp = document.createElement('div');
            tmp.innerHTML = value;
            value = (tmp.textContent || tmp.innerText || '').trim();
        }

        return value;
    };

    /**
     * Check for unbalanced or mismatched delimiters.
     *
     * @param {string} content SCSS content.
     * @return {string} Error message, or empty string if OK.
     */
    var getDelimiterError = function(content) {
        var braces = 0;
        var parens = 0;

        for (var i = 0; i < content.length; i++) {
            var ch = content.charAt(i);

            if (ch === '{') {
                braces++;
            } else if (ch === '}') {
                braces--;
                if (braces < 0) {
                    return strings.closingbracewithoutopen;
                }
            }

            if (ch === '(') {
                parens++;
            } else if (ch === ')') {
                parens--;
                if (parens < 0) {
                    return strings.closingparenwithoutopen;
                }
            }
        }

        if (braces !== 0) {
            return strings.unbalancedbraces;
        }
        if (parens !== 0) {
            return strings.unbalancedparentheses;
        }

        return '';
    };

    /**
     * Find lines that look like CSS/SCSS declarations but are missing a
     * trailing semicolon (or other expected punctuation).
     *
     * @param {string} content SCSS content.
     * @return {number[]} 1-based line numbers with likely issues.
     */
    var getPunctuationWarnings = function(content) {
        var lines = content.split('\n');
        var warnings = [];
        var declarationPattern = /^(-{2}|\$|[a-zA-Z_])[\w-]*\s*:\s*.+$/;

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            if (!line) {
                continue;
            }

            // Skip comment lines.
            if (/^\/\//.test(line) || /^\/\*/.test(line) ||
                /^\*/.test(line) || /^\*\//.test(line)) {
                continue;
            }

            if (!declarationPattern.test(line)) {
                continue;
            }

            if (!/[;{},]\s*$/.test(line)) {
                warnings.push(i + 1);
                if (warnings.length >= 8) {
                    break;
                }
            }
        }

        return warnings;
    };

    return {
        /**
         * Initialize SCSS validation on the current page form.
         *
         * @param {Object} config Language strings keyed by identifier.
         */
        init: function(config) {
            strings = config || {};

            // Hide format selector — retry to handle deferred DOM rendering.
            hideFormatSelector();
            setTimeout(hideFormatSelector, 500);
            setTimeout(hideFormatSelector, 1500);

            // Attach validation to form submit.
            var form = document.querySelector('form.mform');
            if (!form) {
                return;
            }

            form.addEventListener('submit', function(e) {
                var content = getScssContent();
                if (!content) {
                    return;
                }

                var error = getDelimiterError(content);
                if (error) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.alert(error);
                    return;
                }

                var warnings = getPunctuationWarnings(content);
                if (warnings.length) {
                    var lineList = warnings.join(', ');
                    var message = (strings.punctuationwarning || '').replace('{$a}', lineList);
                    if (!window.confirm(message)) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }
            });
        }
    };
});
