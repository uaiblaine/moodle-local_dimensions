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
define(['jquery', 'core/ajax', 'core/templates', 'core/str', 'core/notification',
    'local_dimensions/chip_filters', 'local_dimensions/collapsible_description',
    'local_dimensions/filter_line', 'local_dimensions/learner_prefs'],
function($, Ajax, Templates, Str, Notification, ChipFilters, CollapsibleDescription, Line, LearnerPrefs) {

    /** @type {string} The filter line's scope cells (templates/filter_line.mustache). */
    var SCOPE_CELL_SELECTOR = '[data-region="local-dimensions-line-scope"] [data-filter]';
    /** @type {string} The chip filters' container on this page. */
    var CHIP_CONTAINER_ID = 'local-dimensions-viewcompetency-chip-filters';
    /** @type {string} Every course card's wrapper, which the filters show and hide. */
    var CARD_SELECTOR = '.local-dimensions-course-card-wrapper';

    /** @type {Object} The active chip selection, shortname => values. */
    var activeChipSelection = {};

    /**
     * The scope in force, read off the line: the server drew the remembered choice checked, and
     * "To do" when the learner never chose.
     *
     * @return {string} 'incomplete' or 'all'.
     */
    function activeCourseTab() {
        var cell = document.querySelector(SCOPE_CELL_SELECTOR + '[aria-checked="true"]');
        return cell ? cell.dataset.filter : 'incomplete';
    }

    /**
     * The typed text, normalised by the line's own rule.
     *
     * @return {string}
     */
    function searchQuery() {
        var input = document.querySelector('.local-dimensions-search-input');
        return input ? Line.normalise(input.value) : '';
    }

    /**
     * The criteria in force: the scope, the typed text and the chips.
     *
     * @return {Object} Keys: filter, query, chips.
     */
    function currentCriteria() {
        return {filter: activeCourseTab(), query: searchQuery(), chips: activeChipSelection};
    }

    /**
     * A card's name, as drawn in its header.
     *
     * @param {HTMLElement} wrapper The card's wrapper.
     * @return {string}
     */
    function nameOf(wrapper) {
        var header = wrapper.querySelector('.card-header');
        return header ? header.textContent.trim() : '';
    }

    /**
     * Whether a card passes a set of criteria: every one of them, together.
     *
     * A card whose completion is not known yet (the batch has not answered) passes "To do": the
     * scope is not drawn until it can tell the truth, and hiding every card meanwhile would not.
     *
     * @param {HTMLElement} wrapper The card's wrapper.
     * @param {Object} criteria Keys: filter, query, chips.
     * @return {boolean}
     */
    function passes(wrapper, criteria) {
        if (criteria.filter === 'incomplete' && wrapper.getAttribute('data-completed') === '1') {
            return false;
        }
        if (criteria.query !== '' && Line.normalise(nameOf(wrapper)).indexOf(criteria.query) === -1) {
            return false;
        }
        var values = {};
        try {
            values = JSON.parse(wrapper.getAttribute('data-filtervalues') || '{}') || {};
        } catch (e) {
            values = {};
        }
        return ChipFilters.matchesSelection(criteria.chips, values);
    }

    /**
     * How many cards a control of the line would leave once pressed: a scope cell or a chip.
     *
     * The suggestions ask without the typed text, the panel's chips with it.
     *
     * @param {HTMLElement} control The control.
     * @param {boolean} keepQuery Whether the typed text still applies.
     * @return {number}
     */
    function countWith(control, keepQuery) {
        var criteria = currentCriteria();
        if (!keepQuery) {
            criteria.query = '';
        }
        if (control.matches(SCOPE_CELL_SELECTOR)) {
            criteria.filter = control.dataset.filter;
        } else if (control.dataset.chipValue !== undefined) {
            var group = control.closest('[data-chip-field]');
            var field = group ? group.dataset.chipField : '';
            var chips = Object.assign({}, criteria.chips);
            var values = (chips[field] || []).slice();
            if (values.indexOf(control.dataset.chipValue) === -1) {
                values.push(control.dataset.chipValue);
            }
            chips[field] = values;
            criteria.chips = chips;
        }
        return Array.prototype.filter.call(document.querySelectorAll(CARD_SELECTOR), function(wrapper) {
            return passes(wrapper, criteria);
        }).length;
    }

    /**
     * The cards on screen, by name: what the line's suggestions offer after the filters.
     *
     * @return {Array} Entries of {label, element}.
     */
    function drawnItems() {
        return Array.prototype.filter.call(document.querySelectorAll(CARD_SELECTOR), function(wrapper) {
            return wrapper.style.display !== 'none';
        }).map(function(wrapper) {
            return {label: nameOf(wrapper), element: wrapper};
        });
    }

    /**
     * Take the reader to a card a suggestion named: into view, the focus on its course link.
     *
     * A locked card has no link, so the card itself takes the focus.
     *
     * @param {HTMLElement} wrapper The card's wrapper.
     */
    function openItem(wrapper) {
        var link = wrapper.querySelector('.card-header a');
        var target = link || wrapper;
        if (!link) {
            wrapper.tabIndex = -1;
        }
        wrapper.scrollIntoView({block: 'center'});
        target.focus({preventScroll: true});
    }

    /**
     * Show the course cards that pass the scope, the typed text and the chip selection.
     *
     * A list left empty by the filters says so, with a way back; the panel counts what each chip
     * would leave; the line follows.
     */
    function applyCardFilters() {
        var criteria = currentCriteria();
        var visible = 0;
        document.querySelectorAll(CARD_SELECTOR).forEach(function(wrapper) {
            var on = passes(wrapper, criteria);
            wrapper.style.display = on ? '' : 'none';
            if (on) {
                visible++;
            }
        });
        var noresults = document.getElementById('local-dimensions-viewcompetency-noresults');
        if (noresults) {
            noresults.hidden = visible > 0;
        }
        ChipFilters.paintCounts(CHIP_CONTAINER_ID, function(chip) {
            return countWith(chip, true);
        });
        Line.repaint();
    }

    /**
     * Wire the search over the cards drawn, a moment after the last key.
     */
    function initSearch() {
        var input = document.querySelector('.local-dimensions-search-input');
        if (!input) {
            return;
        }
        var timer = null;
        input.addEventListener('input', function() {
            window.clearTimeout(timer);
            timer = window.setTimeout(applyCardFilters, 100);
        });
    }

    /**
     * Wire the "no results" state's "Clear filters": the chips, the text and the scope, all three.
     */
    function initNoResultsClear() {
        var button = document.querySelector('[data-noresults-clear]');
        if (!button) {
            return;
        }
        button.addEventListener('click', function() {
            var chipClear = document.querySelector('[data-chip-clear]');
            if (chipClear) {
                chipClear.click();
            }
            var input = document.querySelector('.local-dimensions-search-input');
            if (input) {
                input.value = '';
            }
            var allCell = document.querySelector(SCOPE_CELL_SELECTOR + '[data-filter="all"]');
            if (allCell && allCell.getAttribute('aria-checked') !== 'true') {
                allCell.click();
            } else {
                applyCardFilters();
            }
            var target = Line.focusTarget(input);
            if (target) {
                target.focus();
            }
        });
    }

    /**
     * Apply the active chip selection to all course-card wrappers.
     *
     * @param {Object<string, string[]>} selection
     */
    function applyCardChipFilter(selection) {
        activeChipSelection = selection || {};
        applyCardFilters();
    }

    /**
     * Reveal and wire the completion tabs once the batch status call has answered.
     *
     * The counts cannot be server-rendered: whether a course is complete is only known after
     * get_courses_completion_status resolves. Rather than render a tab strip with unknown
     * numbers and a disabled state to explain them, the strip stays hidden until it can tell
     * the truth. Selector scoped to the toolbar - the chip buttons carry the same class.
     *
     * @param {number} completed How many courses are complete
     * @param {number} total How many courses there are
     */
    function initCourseTabs(completed, total) {
        var scope = document.querySelector('[data-region="local-dimensions-line-scope"]');
        if (!scope) {
            return;
        }
        var counts = {incomplete: total - completed, all: total};
        var cells = scope.querySelectorAll('[data-filter]');
        cells.forEach(function(cell) {
            var count = counts[cell.dataset.filter];
            var slot = cell.querySelector('[data-count]');
            if (slot) {
                slot.textContent = String(count);
                slot.hidden = false;
            }
            // A zero cell is not drawn unless it is the chosen one.
            cell.hidden = count === 0 && cell.getAttribute('aria-checked') !== 'true';
            cell.addEventListener('click', function() {
                cells.forEach(function(other) {
                    other.setAttribute('aria-checked', other === cell ? 'true' : 'false');
                });
                LearnerPrefs.save({trackerfilter: cell.dataset.filter});
                applyCardFilters();
            });
        });
        scope.hidden = false;
        applyCardFilters();
    }

    return {
        init: function(settings) {
            settings = settings || {};
            // Activate chip filters and collapsible hero description; both
            // are no-ops when the host page does not render them.
            ChipFilters.init(CHIP_CONTAINER_ID, applyCardChipFilter);
            ChipFilters.initPanel(CHIP_CONTAINER_ID);
            CollapsibleDescription.init();

            /* Seed the preference store with the whole state the server resolved, before any control
               can save: a save replaces the entire preference, so a key not seeded would be reset. */
            var tracker = document.querySelector('[data-region="local-dimensions-tracker"]');
            var viewstate = {};
            try {
                viewstate = JSON.parse((tracker && tracker.dataset.viewstate) || '{}');
            } catch (e) {
                viewstate = {};
            }
            LearnerPrefs.init(viewstate);

            initSearch();
            initNoResultsClear();
            Line.arm(document, {
                countFor: function(control) {
                    return countWith(control, false);
                },
                items: drawnItems,
                openItem: openItem,
                dropTerm: applyCardFilters,
            });
            applyCardFilters();

            var lockedcardmode = settings.lockedcardmode || 'blocked';
            var showlockeddate = settings.showlockeddate !== undefined ? settings.showlockeddate : true;
            var cardicon = settings.cardicon || '';
            var learnmorebuttoncolor = settings.learnmorebuttoncolor || '#0f6cbf';
            /* The marching-ants border is applied as an inline style on the injected SVG,
               which a stylesheet cannot override without !important, so the reduced-motion
               preference has to be honoured here rather than in a media query. */
            var reducedmotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var animatelockedborder = !!settings.animatelockedborder && !reducedmotion;
            /* The plan and competency the page shows. Both card services take them, so the cards
               describe the plan's owner rather than the viewer, and answer only for the courses
               linked to this competency. */
            var planid = settings.planid || 0;
            var competencyid = settings.competencyid || 0;

            // Resolve the card icon CSS class from the stored identifier.
            var cardiconclass = '';
            if (cardicon) {
                cardiconclass = resolveIconClass(cardicon);
            }

            var containers = $('.local-dimensions-progress-container');
            var courseIds = [];

            // 1. Collect IDs.
            containers.each(function() {
                var id = $(this).data('courseid');
                if (id) {
                    courseIds.push(id);
                }
            });

            if (courseIds.length === 0) {
                return;
            }

            // Strings for the error/retry UI, filled in before any card is requested (see below).
            var loaderStrings = {};

            /**
             * Render a single course's progress data into its container.
             *
             * @param {Object} data Result payload from get_course_progress.
             * @return {Promise}
             */
            function renderCourse(data) {
                var container = $('.local-dimensions-progress-container[data-courseid="' + data.courseid + '"]');
                if (data.enabled && data.sections) {
                    $.each(data.sections, function(j, section) {
                        section.dasharray = (section.percentage * 0.754).toFixed(2);

                        /* The first section already under way but not finished is where the
                           learner left off. Locked sections are skipped: their URL points at
                           the course page, so "continue" there would lead nowhere useful. */
                        if (!data.hascontinue && section.is_started && !section.is_completed
                                && !section.locked && section.url) {
                            data.hascontinue = true;
                            data.continueurl = section.url;
                            data.continuename = section.name;
                        }
                    });
                }
                /* The course-level completion stamp, from the batch call that ran before this
                   card was ever requested. It is the course's own completion rule, which is a
                   different fact from every section happening to be done. */
                data.iscompleted = container
                    .closest('.local-dimensions-course-card-wrapper')
                    .attr('data-completed') === '1';
                /* The server picks the card shape and sends it as data.cardmode. Mustache
                   cannot compare a value, so the timeline branch gets a boolean of its own;
                   the activity and section branches key on their own payloads. */
                data.istimeline = data.cardmode === 'timeline';
                /* A one-section course has exactly one row, and its percentage is the
                   course's own: every tracked activity lives in that section. */
                data.sectionpercentage = (data.sections && data.sections.length === 1)
                    ? (data.sections[0].percentage || 0)
                    : 0;
                /* The section card draws the same ring the timeline draws for a started
                   section, only larger, so the arc length uses the same geometry and the
                   same factor: the r=12 circle's circumference is 75.4. Scaling is CSS's
                   job, which is why there is no second formula here. */
                data.sectiondasharray = (data.sectionpercentage * 0.754).toFixed(2);
                /* The state area comes from the server, label and destinations included. A locked
                   card that offers nothing (none) is the one the locked-card settings dress - the
                   learn-more button, the admin's icon and the start date. */
                var statekey = data.state ? data.state.key : '';
                var isnone = statekey === 'none';
                data.islearnmore = isnone && lockedcardmode === 'learnmore';
                /* The start date tells a learner that a later course or stage exists, so it shows
                   only while it is still ahead: a date that has passed says nothing true. A card
                   whose enrolment opens later carries state.openslabel, which the template prints
                   whatever this setting says; the service then sends is_future_date false. */
                data.showlockeddate = isnone && showlockeddate && !!data.is_future_date;
                data.customicon = isnone ? cardiconclass : '';
                data.courseurl = data.course_url || '';
                data.learnmorebuttoncolor = learnmorebuttoncolor;

                return Templates.render('local_dimensions/progress_card_body', data)
                    .then(function(html, js) {
                        Templates.replaceNodeContents(container, html, js);
                        container.find('[data-timeline-toggle]').on('click', function() {
                            var $toggle = $(this);
                            var expanded = $toggle.attr('aria-expanded') === 'true';
                            $toggle.attr('aria-expanded', expanded ? 'false' : 'true');
                            container.find('#' + $toggle.attr('aria-controls')).prop('hidden', expanded);
                        });
                        if (data.locked) {
                            var card = container.closest('.card');
                            var overlay = container.find('.local-dimensions-locked-overlay');
                            if (card.length && overlay.length) {
                                overlay.appendTo(card);
                                injectAnimatedBorder(card[0], overlay[0], animatelockedborder);
                            }
                        }
                        return null;
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
                var errorMsg = loaderStrings.error;
                var retryMsg = loaderStrings.retry;
                // Role="alert" surfaces the failure to assistive tech without
                // requiring focus; aria-label on the button names the action
                // even though the visible text already says "Retry".
                var $wrap = $('<div class="text-danger small p-3 text-center" role="alert"></div>');
                $('<p class="mb-2"></p>').text(errorMsg).appendTo($wrap);
                var $btn = $('<button type="button" class="btn btn-sm btn-outline-secondary local-dimensions-retry-btn"></button>')
                    .text(retryMsg)
                    .attr('aria-label', retryMsg);
                $btn.appendTo($wrap);
                container.empty().append($wrap);
                $btn.one('click', function() {
                    container.html(
                        '<div class="text-center p-3" role="status" aria-live="polite">' +
                            '<i class="fa fa-spinner fa-spin fa-2x text-primary" aria-hidden="true"></i>' +
                            '<span class="visually-hidden">' + loaderStrings.loading + '</span>' +
                        '</div>'
                    );
                    retryFn();
                });
            }

            /**
             * Fetch a single course's progress with a 2s soft-timeout race.
             *
             * The returned promise resolves either when the AJAX call settles
             * or after 2 seconds, whichever comes first. The next
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
                    args: {courseids: [courseid], planid: planid, competencyid: competencyid}
                }])[0];

                var ajaxPromise = $.Deferred();
                call.done(function(results) {
                    if (results && results[0]) {
                        renderCourse(results[0]).fail(function() {
                            renderErrorState(courseid, function() {
                                loadCourseWithSoftTimeout(courseid);
                            });
                        });
                    } else {
                        renderErrorState(courseid, function() {
                            loadCourseWithSoftTimeout(courseid);
                        });
                    }
                    ajaxPromise.resolve();
                }).fail(function() {
                    renderErrorState(courseid, function() {
                        loadCourseWithSoftTimeout(courseid);
                    });
                    ajaxPromise.resolve();
                });

                // Soft timeout: return whichever fires first.
                var timeoutPromise = $.Deferred();
                window.setTimeout(function() {
 timeoutPromise.resolve();
}, 2000);
                return $.when(
                    $.Deferred(function(d) {
                        var done = false;
                        /* The jQuery Deferred race resolves d on whichever of
                         * ajax / timeout settles first; neither .then() can be
                         * returned or caught without disabling the other branch. */
                        /* eslint-disable promise/always-return, promise/catch-or-return */
                        ajaxPromise.then(function() {
                            if (!done) {
 done = true; d.resolve();
}
                        });
                        timeoutPromise.then(function() {
                            if (!done) {
 done = true; d.resolve();
}
                        });
                        /* eslint-enable promise/always-return, promise/catch-or-return */
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
                $.each(ids, function(i, cid) {
                    chain = chain.then(function() {
                        return loadCourseWithSoftTimeout(cid);
                    });
                });
                return chain;
            }

            /**
             * Load every card: the completion batch first, then each course's progress.
             */
            function loadAllCourses() {
                // 2. PHASE A — lightweight batch lookup of completion + lock state.
                // Used to (a) tag wrappers with data-completed for filtering and
                // (b) prioritise not-completed/not-locked courses in the loader.
                Ajax.call([{
                    methodname: 'local_dimensions_get_courses_completion_status',
                    args: {courseids: courseIds, planid: planid, competencyid: competencyid}
                }])[0].done(function(statuses) {
                    var notCompletedIds = [];
                    var completedOrLockedIds = [];
                    var completedcount = 0;
                    $.each(statuses, function(i, s) {
                        var $wrapper = $('.local-dimensions-course-card-wrapper[data-courseid="' + s.courseid + '"]');
                        var done = !!s.iscompleted;
                        $wrapper.attr('data-completed', done ? '1' : '0');
                        if (done) {
                            completedcount++;
                        }
                        if (s.islocked || done) {
                            completedOrLockedIds.push(s.courseid);
                        } else {
                            notCompletedIds.push(s.courseid);
                        }
                    });

                    // The completion tabs only become truthful here, so this is where they appear.
                    initCourseTabs(completedcount, courseIds.length);

                    // PHASE B — sequential FIFO with 2s soft timeout. Not-completed
                    // courses go first so the user sees actionable cards earliest.
                    return loadSequentially(notCompletedIds).then(function() {
                        return loadSequentially(completedOrLockedIds);
                    });
                }).fail(function() {
                    // The status call failed: load every course in page order, without
                    // prioritisation; the completion tabs stay hidden.
                    loadSequentially(courseIds);
                });
            }

            /* The error and retry text renders synchronously, so every request waits for the
               strings: a fast failure must not draw its message before they arrive. */
            Str.get_strings([
                {key: 'course_load_error', component: 'local_dimensions'},
                {key: 'course_load_retry', component: 'local_dimensions'},
                {key: 'course_loading', component: 'local_dimensions'}
            ]).then(function(s) {
                loaderStrings.error = s[0];
                loaderStrings.retry = s[1];
                loaderStrings.loading = s[2];
                loadAllCourses();
                return null;
            }).catch(Notification.exception);
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
        var BORDER_RADIUS = 8; // Matches the card's 0.5rem radius at 16px base.

        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('class', 'local-dimensions-locked-border-svg');
        svg.setAttribute('aria-hidden', 'true');

        var rect = document.createElementNS(SVG_NS, 'rect');
        rect.setAttribute('fill', 'none');
        /* No stroke attribute: the colour comes from a token through this class in styles.css,
           so it follows the page's colour mode. */
        rect.setAttribute('class', 'local-dimensions-locked-border-rect');
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
     * Stored values come from the icon picker (local_dimensions\admin\setting_iconpicker):
     * - FA class, the form core icons are stored in: "fa-star" -> "fa fa-fw fa-star"
     * - FA Solid: "<component>:fa-book" -> "fa fa-fw fa-book"
     * - FA Brand: "<component>:fab-github" -> "fa fa-fw fab fa-github"
     * - A full class string ("fa fa-fw fa-star") is returned unchanged.
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

            // Legacy "core:i/xxx" values need the core icon map, which only the server
            // has; re-saving the setting stores the plain FA class instead.
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
