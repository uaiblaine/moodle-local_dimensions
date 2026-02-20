define(['jquery', 'core/ajax', 'core/templates', 'core/notification'], function ($, Ajax, Templates, Notification) {

    return {
        init: function (settings) {
            settings = settings || {};
            var lockedcardmode = settings.lockedcardmode || 'blocked';
            var showlockeddate = settings.showlockeddate !== undefined ? settings.showlockeddate : true;
            var cardicon = settings.cardicon || '';
            var learnmorebuttoncolor = settings.learnmorebuttoncolor || '#667eea';

            // Resolve the card icon CSS class from the stored identifier.
            var cardiconclass = '';
            if (cardicon) {
                cardiconclass = resolveIconClass(cardicon);
            }

            var containers = $('.dims-progress-container');
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
                    var container = $('.dims-progress-container[data-courseid="' + data.courseid + '"]');

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
                        });
                    }

                    // Inject locked card settings into template context.
                    data.islearnmore = (lockedcardmode === 'learnmore');
                    data.showlockeddate = showlockeddate;
                    data.customicon = cardiconclass;
                    data.courseurl = data.course_url || '';
                    data.learnmorebuttoncolor = learnmorebuttoncolor;

                    // 4. Render Moodle Template.
                    Templates.render('local_dimensions/progress_card_body', data)
                        .then(function (html, js) {
                            Templates.replaceNodeContents(container, html, js);

                            // If locked, move the overlay to card level so it covers the header too.
                            if (data.locked) {
                                var card = container.closest('.card');
                                var overlay = container.find('.dims-locked-overlay');
                                if (card.length && overlay.length) {
                                    overlay.appendTo(card);
                                }
                            }
                        })
                        .fail(Notification.exception);
                });

            }).fail(function (ex) {
                // General request failure.
                console.error(ex);
                containers.html('<div class="text-danger small p-2">' + M.util.get_string('connection_error', 'local_dimensions') + '</div>');
            });
        }
    };

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
