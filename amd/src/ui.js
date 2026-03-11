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
 * UI interactions for the view plan page.
 *
 * @module     local_dimensions/ui
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/notification'], function ($, Ajax, Templates, Notification) {

    return {
        init: function (settings) {
            settings = settings || {};
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

            // 2. Single Batch AJAX Call.
            Ajax.call([{
                methodname: 'local_dimensions_get_course_progress',
                args: { courseids: courseIds }
            }])[0].done(function (results) {

                // 3. Process each result.
                $.each(results, function (i, data) {
                    var container = $('.local-dimensions-progress-container[data-courseid="' + data.courseid + '"]');

                    if (data.enabled && data.sections) {

                        // Pre-processing to add style class and dasharray (view logic).
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
                            // Pre-calculate stroke-dasharray for SVG progress ring.
                            // Firefox does not support calc() in SVG inline styles.
                            section.dasharray = (section.percentage * 0.754).toFixed(2);
                            section.lockiconurl = iconurls.lock || '';
                            section.checkiconurl = iconurls.checkcircle || '';
                            section.circleiconurl = iconurls.circle || '';
                            section.infoiconurl = iconurls.info || '';
                        });
                    }

                    // Inject locked card settings into template context.
                    data.islearnmore = (lockedcardmode === 'learnmore');
                    data.showlockeddate = showlockeddate;
                    data.customicon = cardiconclass;
                    data.lockiconurl = iconurls.lock || '';
                    data.courseurl = data.course_url || '';
                    data.learnmorebuttoncolor = learnmorebuttoncolor;

                    // 4. Render Moodle Template.
                    Templates.render('local_dimensions/progress_card_body', data)
                        .then(function (html, js) {
                            Templates.replaceNodeContents(container, html, js);

                            // If locked, move the overlay to card level so it covers the header too.
                            if (data.locked) {
                                var card = container.closest('.card');
                                var overlay = container.find('.local-dimensions-locked-overlay');
                                if (card.length && overlay.length) {
                                    overlay.appendTo(card);
                                    injectAnimatedBorder(card[0], overlay[0], animatelockedborder);
                                }
                            }
                        })
                        .fail(Notification.exception);
                });

            }).fail(function (ex) {
                // General request failure.
                Notification.exception(ex);
                var errorMsg = M.util.get_string('connection_error', 'local_dimensions');
                containers.html('<div class="text-danger small p-2">' + errorMsg + '</div>');
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
