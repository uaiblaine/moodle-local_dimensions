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
 * UI interactions for the view competency page (Competency Tracker Mode).
 *
 * @module     local_dimensions/competency_view
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/str',
    'local_dimensions/chip_filters', 'local_dimensions/collapsible_description'],
function ($, Ajax, Templates, Str, ChipFilters, CollapsibleDescription) {

    /**
     * Apply the active chip selection to all course-card wrappers.
     *
     * @param {Object<string, string[]>} selection
     */
    function applyCardChipFilter(selection) {
        $('.local-dimensions-course-card-wrapper').each(function () {
            var $wrapper = $(this);
            var raw = $wrapper.attr('data-filtervalues');
            var values = {};
            if (raw) {
                try {
                    values = JSON.parse(raw);
                } catch (e) {
                    values = {};
                }
            }
            // Treat data-completed as a virtual filter key so the
            // "completed/not completed" chip group integrates with the rest.
            if ($wrapper.attr('data-completed') === '1') {
                values['__status'] = 'completed';
            } else if ($wrapper.attr('data-completed') === '0') {
                values['__status'] = 'not_completed';
            }
            if (ChipFilters.matchesSelection(selection || {}, values)) {
                $wrapper.show();
            } else {
                $wrapper.hide();
            }
        });
    }

    return {
        init: function (settings) {
            settings = settings || {};
            // Activate chip filters and collapsible hero description; both
            // are no-ops when the host page does not render them.
            ChipFilters.init('local-dimensions-viewcompetency-chip-filters', applyCardChipFilter);
            CollapsibleDescription.init();

            var lockedcardmode = settings.lockedcardmode || 'blocked';
            var showlockeddate = settings.showlockeddate !== undefined ? settings.showlockeddate : true;
            var cardicon = settings.cardicon || '';
            var learnmorebuttoncolor = settings.learnmorebuttoncolor || '#667eea';
            var animatelockedborder = !!settings.animatelockedborder;
            var iconurls = {
                lock: M.util.image_url('status/lock', 'local_dimensions'),
                checkcircle: M.util.image_url('status/check-circle-fill', 'local_dimensions'),
                circle: M.util.image_url('status/circle-outline', 'local_dimensions'),
                info: M.util.image_url('status/info-circle', 'local_dimensions')
            };

            // Resolve the card icon CSS class from the stored identifier.
            var cardiconclass = '';
            if (cardicon) {
                cardiconclass = resolveIconClass(cardicon);
            }

            var containers = $('.local-dimensions-progress-container');
            var courseIds = [];

            // 1. Collect IDs.
            containers.each(function () {
                var id = $(this).data('courseid');
                if (id) {
                    courseIds.push(id);
                }
            });

            if (courseIds.length === 0) {
                return;
            }

            // Pre-load strings used by the error/retry UI so the synchronous
            // renderer below can reference them without race conditions.
            var loaderStrings = {
                course_load_error: 'Could not load progress.',
                course_load_retry: 'Retry',
                course_loading: 'Loading…'
            };
            Str.get_strings([
                { key: 'course_load_error', component: 'local_dimensions' },
                { key: 'course_load_retry', component: 'local_dimensions' },
                { key: 'course_loading', component: 'local_dimensions' }
            ]).done(function (s) {
                loaderStrings.course_load_error = s[0];
                loaderStrings.course_load_retry = s[1];
                loaderStrings.course_loading = s[2];
            });

            /**
             * Render a single course's progress data into its container.
             *
             * @param {Object} data Result payload from get_course_progress.
             * @return {Promise}
             */
            function renderCourse(data) {
                var container = $('.local-dimensions-progress-container[data-courseid="' + data.courseid + '"]');
                if (data.enabled && data.sections) {
                    $.each(data.sections, function (j, section) {
                        if (section.has_activities) {
                            if (section.percentage == 100) {
                                section.badge_class = 'badge-success';
                            } else if (section.percentage > 0) {
                                section.badge_class = 'badge-info';
                            } else {
                                section.badge_class = 'badge-secondary';
                            }
                        }
                        section.dasharray = (section.percentage * 0.754).toFixed(2);
                        section.lockiconurl = iconurls.lock || '';
                        section.checkiconurl = iconurls.checkcircle || '';
                        section.circleiconurl = iconurls.circle || '';
                        section.infoiconurl = iconurls.info || '';
                    });
                }
                data.islearnmore = (lockedcardmode === 'learnmore');
                data.showlockeddate = showlockeddate;
                data.customicon = cardiconclass;
                data.lockiconurl = iconurls.lock || '';
                data.courseurl = data.course_url || '';
                data.learnmorebuttoncolor = learnmorebuttoncolor;

                return Templates.render('local_dimensions/progress_card_body', data)
                    .then(function (html, js) {
                        Templates.replaceNodeContents(container, html, js);
                        if (data.locked) {
                            var card = container.closest('.card');
                            var overlay = container.find('.local-dimensions-locked-overlay');
                            if (card.length && overlay.length) {
                                overlay.appendTo(card);
                                injectAnimatedBorder(card[0], overlay[0], animatelockedborder);
                            }
                        }
                    });
            }

            /**
             * Render a generic error state with a Retry button inside the card.
             *
             * @param {number} courseid
             * @param {Function} retryFn
             */
            function renderErrorState(courseid, retryFn) {
                var container = $('.local-dimensions-progress-container[data-courseid="' + courseid + '"]');
                var errorMsg = loaderStrings.course_load_error;
                var retryMsg = loaderStrings.course_load_retry;
                // role="alert" surfaces the failure to assistive tech without
                // requiring focus; aria-label on the button names the action
                // even though the visible text already says "Retry".
                var $wrap = $('<div class="text-danger small p-3 text-center" role="alert"></div>');
                $('<p class="mb-2"></p>').text(errorMsg).appendTo($wrap);
                var $btn = $('<button type="button" class="btn btn-sm btn-outline-secondary local-dimensions-retry-btn"></button>')
                    .text(retryMsg)
                    .attr('aria-label', retryMsg);
                $btn.appendTo($wrap);
                container.empty().append($wrap);
                $btn.one('click', function () {
                    container.html(
                        '<div class="text-center p-3" role="status" aria-live="polite">' +
                            '<i class="fa fa-spinner fa-spin fa-2x text-primary" aria-hidden="true"></i>' +
                            '<span class="sr-only">' + loaderStrings.course_loading + '</span>' +
                        '</div>'
                    );
                    retryFn();
                });
            }

            /**
             * Fetch a single course's progress with a 2s soft-timeout race.
             *
             * The promise returned by Race resolves either when the AJAX call
             * completes OR after 2 seconds — whichever comes first. The next
             * card therefore begins loading even if the previous request is
             * still pending, but the late response is still rendered when it
             * arrives. Hard rejections fall through to the error state.
             *
             * @param {number} courseid
             * @return {Promise}
             */
            function loadCourseWithSoftTimeout(courseid) {
                var call = Ajax.call([{
                    methodname: 'local_dimensions_get_course_progress',
                    args: { courseids: [courseid] }
                }])[0];

                var ajaxPromise = $.Deferred();
                call.done(function (results) {
                    if (results && results[0]) {
                        renderCourse(results[0]).fail(function () {
                            renderErrorState(courseid, function () {
                                loadCourseWithSoftTimeout(courseid);
                            });
                        });
                    } else {
                        renderErrorState(courseid, function () {
                            loadCourseWithSoftTimeout(courseid);
                        });
                    }
                    ajaxPromise.resolve();
                }).fail(function () {
                    renderErrorState(courseid, function () {
                        loadCourseWithSoftTimeout(courseid);
                    });
                    ajaxPromise.resolve();
                });

                // Soft timeout: return whichever fires first.
                var timeoutPromise = $.Deferred();
                window.setTimeout(function () { timeoutPromise.resolve(); }, 2000);
                return $.when(
                    $.Deferred(function (d) {
                        var done = false;
                        ajaxPromise.then(function () {
                            if (!done) { done = true; d.resolve(); }
                        });
                        timeoutPromise.then(function () {
                            if (!done) { done = true; d.resolve(); }
                        });
                    })
                );
            }

            /**
             * Load a list of course IDs sequentially (with soft 2s overlap).
             *
             * @param {number[]} ids
             * @return {Promise}
             */
            function loadSequentially(ids) {
                var chain = $.Deferred().resolve();
                $.each(ids, function (i, cid) {
                    chain = chain.then(function () {
                        return loadCourseWithSoftTimeout(cid);
                    });
                });
                return chain;
            }

            // 2. PHASE A — lightweight batch lookup of completion + lock state.
            // Used to (a) tag wrappers with data-completed for filtering and
            // (b) prioritise not-completed/not-locked courses in the loader.
            Ajax.call([{
                methodname: 'local_dimensions_get_courses_completion_status',
                args: { courseids: courseIds }
            }])[0].done(function (statuses) {
                var notCompletedIds = [];
                var completedOrLockedIds = [];
                $.each(statuses, function (i, s) {
                    var $wrapper = $('.local-dimensions-course-card-wrapper[data-courseid="' + s.courseid + '"]');
                    var done = !!s.iscompleted;
                    $wrapper.attr('data-completed', done ? '1' : '0');
                    if (s.islocked || done) {
                        completedOrLockedIds.push(s.courseid);
                    } else {
                        notCompletedIds.push(s.courseid);
                    }
                });

                // PHASE B — sequential FIFO with 2s soft timeout. Not-completed
                // courses go first so the user sees actionable cards earliest.
                loadSequentially(notCompletedIds).then(function () {
                    return loadSequentially(completedOrLockedIds);
                });
            }).fail(function () {
                // Lightweight call failed — fall back to the previous behaviour
                // and load every course sequentially without prioritisation.
                loadSequentially(courseIds);
            });
        }
    };

    /**
     * Injects an SVG with an animated dashed <rect> inside the locked overlay.
     *
     * The SVG is absolutely positioned to fill the overlay and uses
     * pointer-events: none so it never blocks interaction.
     * A ResizeObserver keeps the SVG viewBox and rect dimensions
     * synchronised with the card's actual pixel size.
     *
     * @param {HTMLElement} card  The .card element (size source)
     * @param {HTMLElement} overlay  The .local-dimensions-locked-overlay element
     * @param {boolean} animate  Whether to animate the dash offset
     */
    function injectAnimatedBorder(card, overlay, animate) {
        var SVG_NS = 'http://www.w3.org/2000/svg';
        var STROKE_WIDTH = 2;
        var DASH_ARRAY = '8 8';
        var BORDER_RADIUS = 6; // matches 0.375rem at 16px base

        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('class', 'local-dimensions-locked-border-svg');
        svg.setAttribute('aria-hidden', 'true');

        var rect = document.createElementNS(SVG_NS, 'rect');
        rect.setAttribute('fill', 'none');
        rect.setAttribute('stroke', '#ddd');
        rect.setAttribute('stroke-width', STROKE_WIDTH);
        rect.setAttribute('stroke-dasharray', DASH_ARRAY);
        rect.setAttribute('rx', BORDER_RADIUS);
        rect.setAttribute('ry', BORDER_RADIUS);
        if (animate) {
            rect.style.animation = 'local-dimensions-dashoffset-move 2s linear infinite';
        }

        svg.appendChild(rect);
        overlay.insertBefore(svg, overlay.firstChild);

        /**
         * Updates SVG and rect dimensions to match the card.
         */
        function updateSize() {
            var w = card.offsetWidth;
            var h = card.offsetHeight;
            if (w === 0 || h === 0) {
                return;
            }
            svg.setAttribute('width', w);
            svg.setAttribute('height', h);
            svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);

            var half = STROKE_WIDTH / 2;
            rect.setAttribute('x', half);
            rect.setAttribute('y', half);
            rect.setAttribute('width', w - STROKE_WIDTH);
            rect.setAttribute('height', h - STROKE_WIDTH);
        }

        // Initial sizing.
        updateSize();

        // Keep in sync on resize.
        if (typeof ResizeObserver !== 'undefined') {
            var ro = new ResizeObserver(updateSize);
            ro.observe(card);
        }
    }

    /**
     * Resolves a stored icon identifier to its full CSS class string.
     *
     * All output uses "fa fa-fw" as base prefix for Moodle FA4/FA6 compatibility.
     *
     * Stored values follow the pattern from the icon picker:
     * - Direct FA class: "fa-star" -> "fa fa-fw fa-star" (includes core icons post-fix)
     * - FA Solid: "xxx:fa-book" -> "fa fa-fw fa-book"
     * - FA Brand: "xxx:fab-github" -> "fa fa-fw fab fa-github"
     *
     * @param {string} iconIdentifier The stored icon value
     * @return {string} The CSS class(es) for the icon
     */
    function resolveIconClass(iconIdentifier) {
        if (!iconIdentifier) {
            return '';
        }

        // If it contains a colon, it follows the component:name pattern.
        if (iconIdentifier.indexOf(':') !== -1) {
            var parts = iconIdentifier.split(':');
            var iconName = parts[1] || '';

            // FA brand icons: "xxx:fab-iconname" -> "fa fa-fw fab fa-iconname".
            if (iconName.indexOf('fab-') === 0) {
                return 'fa fa-fw fab fa-' + iconName.substring(4);
            }

            // FA solid icons: "xxx:fa-iconname" -> "fa fa-fw fa-iconname".
            if (iconName.indexOf('fa-') === 0) {
                return 'fa fa-fw ' + iconName;
            }

            // Legacy core Moodle identifiers ("core:i/xxx") can't be resolved
            // client-side without an icon map. Log a warning for debugging.
            // eslint-disable-next-line no-console
            console.warn('[local_dimensions] Cannot resolve icon "' + iconIdentifier + '". ' +
                'Re-save the setting to update the stored value.');
            return 'fa fa-fw fa-' + iconName.replace(/\//g, '-');
        }

        // Direct FA class (e.g. "fa-wand-magic-sparkles").
        if (iconIdentifier.indexOf('fa-') === 0) {
            return 'fa fa-fw ' + iconIdentifier;
        }

        // Already a full class (e.g. "fa fa-fw fa-star").
        return iconIdentifier;
    }
});
