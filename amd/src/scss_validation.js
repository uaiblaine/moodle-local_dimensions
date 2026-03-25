// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * SCSS frontend validation and editor format hiding for template forms.
 *
 * Validates SCSS syntax client-side before form submission:
 * - Detects unmatched closing braces/parentheses.
 * - Detects unbalanced brace/parenthesis pairs.
 * - Warns about likely missing semicolons (with user confirmation via Moodle modal).
 *
 * Also hides the editor format dropdown for the SCSS custom field,
 * since the value is forced to FORMAT_PLAIN on the server side.
 *
 * Selectors are built dynamically from the field shortname passed by PHP,
 * so they automatically follow Frankenstyle renames.
 *
 * @module     local_dimensions/scss_validation
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification'], function(Notification) {

    /** @var {Object} Language strings for validation messages. */
    var strings = {};

    /** @var {string[]} Textarea selectors for the SCSS custom field. */
    var TEXTAREA_SELECTORS = [];

    /** @var {string[]} ID selectors for dedicated format form-items (rendered as separate rows). */
    var FORMAT_FITEM_SELECTORS = [];

    /** @var {string[]} Selectors for the format <select> itself (may live inside the editor fitem). */
    var FORMAT_SELECT_SELECTORS = [];

    /**
     * Build all DOM selectors from the custom field shortname.
     *
     * Moodle generates form element names by prefixing "customfield_" to the
     * field shortname. For example, shortname "local_dimensions_customscss"
     * produces names like "customfield_local_dimensions_customscss_editor[text]".
     *
     * @param {string} fieldname The custom field shortname (e.g. "local_dimensions_customscss").
     */
    var buildSelectors = function(fieldname) {
        var cf = 'customfield_' + fieldname;

        TEXTAREA_SELECTORS = [
            'textarea[name="' + cf + '_editor[text]"]',
            'textarea[name="' + cf + '[text]"]',
            'textarea[name="' + cf + '"]'
        ];

        FORMAT_FITEM_SELECTORS = [
            '#fitem_id_' + cf + '_editorformat',
            '#fgroup_id_' + cf + '_editorformat'
        ];

        FORMAT_SELECT_SELECTORS = [
            '#menu' + cf + '_editorformat',
            '#id_' + cf + '_editorformat',
            'select[name="' + cf + '_editor[format]"]',
            'select[name="' + cf + '[format]"]'
        ];
    };

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
     * Find the SCSS textarea element in the DOM.
     *
     * @return {HTMLTextAreaElement|null}
     */
    var findTextarea = function() {
        for (var i = 0; i < TEXTAREA_SELECTORS.length; i++) {
            var textarea = document.querySelector(TEXTAREA_SELECTORS[i]);
            if (textarea) {
                return textarea;
            }
        }
        return null;
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

        var textarea = findTextarea();
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
     * Add the Moodle 'is-invalid' visual indicator to the SCSS field container.
     */
    var highlightScssField = function() {
        var textarea = findTextarea();
        if (!textarea) {
            return;
        }
        // Walk up to the nearest .fitem container and mark it invalid.
        var fitem = textarea.closest('.fitem');
        if (fitem) {
            fitem.classList.add('has-danger');
            fitem.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
        textarea.classList.add('is-invalid');
        textarea.focus();
    };

    /**
     * Remove the visual error indicator from the SCSS field container.
     */
    var clearScssHighlight = function() {
        var textarea = findTextarea();
        if (!textarea) {
            return;
        }
        textarea.classList.remove('is-invalid');
        var fitem = textarea.closest('.fitem');
        if (fitem) {
            fitem.classList.remove('has-danger');
        }
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
         * @param {Object} config Configuration with language strings and field metadata.
         * @param {string} config.fieldname Custom field shortname (e.g. "local_dimensions_customscss").
         * @param {string} config.closingbracewithoutopen Error string.
         * @param {string} config.closingparenwithoutopen Error string.
         * @param {string} config.unbalancedbraces Error string.
         * @param {string} config.unbalancedparentheses Error string.
         * @param {string} config.punctuationwarning Warning string with {$a} placeholder.
         * @param {string} [config.errortitle] Optional modal title for errors.
         * @param {string} [config.warningtitle] Optional modal title for warnings.
         */
        init: function(config) {
            config = config || {};
            strings = config;

            // Build DOM selectors from the field shortname.
            buildSelectors(config.fieldname || 'local_dimensions_customscss');

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
                // Clear any previous error highlight.
                clearScssHighlight();

                var content = getScssContent();
                if (!content) {
                    return;
                }

                var error = getDelimiterError(content);
                if (error) {
                    e.preventDefault();
                    e.stopPropagation();
                    highlightScssField();
                    Notification.alert(
                        strings.errortitle || 'SCSS',
                        error
                    );
                    return;
                }

                var warnings = getPunctuationWarnings(content);
                if (warnings.length) {
                    e.preventDefault();
                    e.stopPropagation();
                    highlightScssField();

                    var lineList = warnings.join(', ');
                    var message = (strings.punctuationwarning || '').replace('{$a}', lineList);

                    Notification.confirm(
                        strings.warningtitle || 'SCSS',
                        message,
                        strings.saveanyway || 'Save anyway',
                        strings.goback || 'Go back',
                        function() {
                            // User chose Save anyway — clear highlight and resubmit.
                            clearScssHighlight();
                            // Temporarily remove this listener to avoid re-triggering.
                            form.setAttribute('data-scss-bypass', '1');
                            form.submit();
                        },
                        function() {
                            // User chose Go back — keep highlight, focus textarea.
                            var textarea = findTextarea();
                            if (textarea) {
                                textarea.focus();
                            }
                        }
                    );
                }
            });

            // Install a secondary listener that checks the bypass flag.
            form.addEventListener('submit', function(e) {
                if (form.getAttribute('data-scss-bypass') === '1') {
                    form.removeAttribute('data-scss-bypass');
                    // Allow submission to proceed.
                    return;
                }
            }, true); // Capture phase so it runs first on re-submit.
        }
    };
});
