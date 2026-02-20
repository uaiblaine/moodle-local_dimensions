/**
 * Accordion functionality for plan summary view with AJAX loading.
 *
 * @module     local_dimensions/accordion
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/templates', 'core/notification', 'core/str', 'core/modal'], function (Ajax, Templates, Notification, Str, Modal) {
    'use strict';

    // Cache for loaded competency summaries to avoid reloading.
    const loadedCompetencies = new Set();

    // Display settings (loaded from page).
    let displaySettings = {
        showdescription: true,
        showpath: false,
        showrelated: false,
        showevidence: true,
        showcomments: false
    };

    /**
     * Load competency summary via AJAX.
     *
     * @param {HTMLElement} contentElement The accordion content element
     * @param {number} competencyId The competency ID
     * @param {number} planId The plan ID
     */
    function loadCompetencySummary(contentElement, competencyId, planId) {
        const loadingEl = contentElement.querySelector('.dims-competency-summary-loading');
        const contentEl = contentElement.querySelector('.dims-competency-summary-content');
        const errorEl = contentElement.querySelector('.dims-competency-summary-error');

        // Check if already loaded.
        if (loadedCompetencies.has(competencyId)) {
            return;
        }

        // Show loading state.
        if (loadingEl) {
            loadingEl.style.display = 'block';
        }
        if (contentEl) {
            contentEl.style.display = 'none';
        }
        if (errorEl) {
            errorEl.style.display = 'none';
        }

        // Call both webservices in parallel.
        // Use local wrapper instead of tool_lp_data_for_user_competency_summary_in_plan
        // to avoid a coding_exception caused by the core service's _returns() triggering
        // exporter → theme → string loading → $PAGE->context access before context is set.
        const summaryPromise = Ajax.call([{
            methodname: 'local_dimensions_get_user_competency_summary_in_plan',
            args: {
                competencyid: competencyId,
                planid: planId
            }
        }])[0].then(function(response) {
            return JSON.parse(response);
        });

        // Use custom webservice when enrollment filter is configured, otherwise use core.
        var coursesMethodName = 'tool_lp_list_courses_using_competency';
        var coursesArgs = { id: competencyId };
        if (displaySettings.summaryenrollmentfilter && displaySettings.summaryenrollmentfilter !== 'all') {
            coursesMethodName = 'local_dimensions_get_competency_courses';
            coursesArgs = { competencyid: competencyId };
        }
        const coursesPromise = Ajax.call([{
            methodname: coursesMethodName,
            args: coursesArgs
        }])[0];

        // Wait for both to complete.
        Promise.all([summaryPromise, coursesPromise]).then(function (results) {
            const summaryResponse = results[0];
            const coursesResponse = results[1];

            // Mark as loaded.
            loadedCompetencies.add(competencyId);

            // Hide loading.
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }

            // Render the summary content (including course cards).
            renderCompetencySummary(contentEl, summaryResponse, coursesResponse);
        }).catch(function (error) {
            // Hide loading, show error.
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
            if (errorEl) {
                errorEl.style.display = 'block';
            }
            Notification.exception(error);
        });
    }

    /**
     * Render the competency summary content.
     *
     * @param {HTMLElement} contentEl The content container element
     * @param {Object} data The data from the webservice
     * @param {Array} courses The courses list from tool_lp_list_courses_using_competency
     * @return {Promise} Promise that resolves when rendering is complete
     */
    function renderCompetencySummary(contentEl, data, courses) {
        if (!contentEl) {
            return Promise.resolve();
        }

        // Fetch all required language strings first.
        return Str.get_strings([
            { key: 'rating_label', component: 'local_dimensions' },
            { key: 'proficient_label', component: 'local_dimensions' },
            { key: 'evidence_label', component: 'local_dimensions' },
            { key: 'yes', component: 'local_dimensions' },
            { key: 'no', component: 'local_dimensions' },
            { key: 'competency_path', component: 'local_dimensions' },
            { key: 'in_framework', component: 'local_dimensions' },
            { key: 'related_competencies', component: 'local_dimensions' },
            { key: 'no_related', component: 'local_dimensions' },
            { key: 'evidence_type_file', component: 'local_dimensions' },
            { key: 'evidence_type_manual', component: 'local_dimensions' },
            { key: 'evidence_type_activity', component: 'local_dimensions' },
            { key: 'evidence_type_coursegrade', component: 'local_dimensions' },
            { key: 'evidence_type_prior', component: 'local_dimensions' },
            { key: 'evidence_type_other', component: 'local_dimensions' },
            { key: 'evidence_by', component: 'local_dimensions' },
            { key: 'no_evidence', component: 'local_dimensions' },
            { key: 'comments_section', component: 'local_dimensions' },
            { key: 'no_comments', component: 'local_dimensions' },
            { key: 'add_comment', component: 'local_dimensions' },
            { key: 'comment_placeholder', component: 'local_dimensions' },
            { key: 'comment_by', component: 'local_dimensions' },
            { key: 'access_course', component: 'local_dimensions' },
            { key: 'linked_courses', component: 'local_dimensions' },
            { key: 'assessment_status', component: 'local_dimensions' },
            { key: 'description_label', component: 'local_dimensions' },
            { key: 'show_more', component: 'local_dimensions' },
            { key: 'show_less', component: 'local_dimensions' },
            { key: 'proficiency', component: 'local_dimensions' },
            { key: 'access', component: 'local_dimensions' },
            { key: 'strftimedaydate', component: 'core_langconfig' },
            { key: 'evidence_slider_prev', component: 'local_dimensions' },
            { key: 'evidence_slider_next', component: 'local_dimensions' },
            { key: 'competency_path', component: 'local_dimensions' },
            { key: 'evidence_details', component: 'local_dimensions' },
            { key: 'evidence_note', component: 'local_dimensions' },
            { key: 'evidence_link', component: 'local_dimensions' },
            { key: 'evidence_grade', component: 'local_dimensions' },
            { key: 'evidence_author', component: 'local_dimensions' },
            { key: 'evidence_date', component: 'local_dimensions' },
            { key: 'evidence_view_details', component: 'local_dimensions' },
            { key: 'evidence_open_link', component: 'local_dimensions' }
        ]).then(function (strings) {
            const strMap = {
                ratingLabel: strings[0],
                proficientLabel: strings[1],
                evidenceLabel: strings[2],
                yesStr: strings[3],
                noStr: strings[4],
                pathLabel: strings[5],
                inFramework: strings[6],
                relatedLabel: strings[7],
                noRelated: strings[8],
                evidenceTypeFile: strings[9],
                evidenceTypeManual: strings[10],
                evidenceTypeActivity: strings[11],
                evidenceTypeCoursegrade: strings[12],
                evidenceTypePrior: strings[13],
                evidenceTypeOther: strings[14],
                evidenceBy: strings[15],
                noEvidence: strings[16],
                commentsSection: strings[17],
                noComments: strings[18],
                addComment: strings[19],
                commentPlaceholder: strings[20],
                commentBy: strings[21],
                accessCourse: strings[22],
                linkedCourses: strings[23],
                assessmentStatus: strings[24],
                descriptionLabel: strings[25],
                showMore: strings[26],
                showLess: strings[27],
                proficiencyLabel: strings[28],
                accessLabel: strings[29],
                dateFormat: strings[30],
                sliderPrev: strings[31],
                sliderNext: strings[32],
                pathBreadcrumbLabel: strings[33],
                evidenceDetails: strings[34],
                evidenceNote: strings[35],
                evidenceLink: strings[36],
                evidenceGrade: strings[37],
                evidenceAuthor: strings[38],
                evidenceDate: strings[39],
                evidenceViewDetails: strings[40],
                evidenceOpenLink: strings[41]
            };

            // Build HTML for the summary.
            const ucs = data.usercompetencysummary;
            const competencyData = ucs ? ucs.competency : null;
            const comp = competencyData ? competencyData.competency : null;

            // Filter visible courses.
            const visibleCourses = (courses || []).filter(function (course) {
                return course.visible == 1;
            });

            let html = '<div class="dims-competency-detail">';

            // === SECTION 1: TABS (Status | Description | Evidence) ===
            // Determine which tabs to show.
            const hasStatus = ucs && (ucs.usercompetency || ucs.usercompetencyplan);
            const hasDesc = comp && displaySettings.showdescription && comp.description;
            const hasPath = comp && displaySettings.showpath;
            const hasRelated = comp && displaySettings.showrelated && competencyData.relatedcompetencies
                && competencyData.relatedcompetencies.length > 0;
            const hasEvidence = ucs && displaySettings.showevidence;
            const tabs = [];

            if (hasStatus) {
                tabs.push({ id: 'status', label: strMap.assessmentStatus, icon: 'fa-star' });
            }
            if (hasDesc || hasPath || hasRelated) {
                tabs.push({ id: 'description', label: strMap.descriptionLabel, icon: 'fa-file-text-o' });
            }
            if (hasEvidence) {
                tabs.push({ id: 'evidence', label: strMap.evidenceLabel, icon: 'fa-check-square-o' });
            }

            if (tabs.length > 0) {
                html += '<div class="dims-tabs-wrapper">';
                // Tab navigation — underline style.
                html += '<div class="dims-tabs-nav" role="tablist">';
                tabs.forEach(function (tab, idx) {
                    const isActive = idx === 0;
                    html += '<button type="button" class="dims-tab-btn' + (isActive ? ' active' : '') + '"';
                    html += ' role="tab"';
                    html += ' aria-selected="' + (isActive ? 'true' : 'false') + '"';
                    html += ' aria-controls="dims-tabpane-' + tab.id + '"';
                    html += ' data-tab="' + tab.id + '">';
                    html += escapeHtml(tab.label);
                    html += '</button>';
                });
                html += '</div>'; // End dims-tabs-nav.

                // Tab panes.
                html += '<div class="dims-tabs-content">';

                // Status tab pane.
                if (hasStatus) {
                    const isFirst = tabs[0].id === 'status';
                    html += '<div class="dims-tab-pane dims-tab-pane-status' + (isFirst ? ' active' : '') + '"';
                    html += ' id="dims-tabpane-status" role="tabpanel">';
                    html += renderStatusSection(ucs, strMap);
                    html += '</div>';
                }

                // Description tab pane.
                if (hasDesc || hasPath || hasRelated) {
                    const isFirst = tabs[0].id === 'description';
                    html += '<div class="dims-tab-pane dims-tab-pane-description' + (isFirst ? ' active' : '') + '"';
                    html += ' id="dims-tabpane-description" role="tabpanel">';

                    // Description text with "Ver mais".
                    if (hasDesc) {
                        html += renderDescriptionSection(comp.description, strMap);
                    }

                    // Path breadcrumb.
                    if (hasPath) {
                        html += renderCompetencyPath(competencyData, strMap);
                    }

                    // Related competencies.
                    if (hasRelated) {
                        html += renderRelatedCompetencies(competencyData, strMap);
                    }

                    html += '</div>';
                }

                // Evidence tab pane.
                if (hasEvidence) {
                    const isFirst = tabs[0].id === 'evidence';
                    html += '<div class="dims-tab-pane dims-tab-pane-evidence' + (isFirst ? ' active' : '') + '"';
                    html += ' id="dims-tabpane-evidence" role="tabpanel">';
                    html += renderEvidenceSlider(ucs.evidence, strMap);
                    html += '</div>';
                }

                html += '</div>'; // End dims-tabs-content.
                html += '</div>'; // End dims-tabs-wrapper.
            }

            // === SECTION 2: LINKED COURSES (scrollable) ===
            if (visibleCourses.length > 0) {
                html += renderCourseCardsScrollable(visibleCourses, strMap);
            }

            // === SECTION 3: COMMENTS (unchanged) ===
            if (displaySettings.showcomments && ucs && ucs.commentarea && ucs.commentarea.count > 0) {
                html += renderCommentsSection(ucs.commentarea, strMap);
            }

            html += '</div>';

            // Show the content.
            contentEl.innerHTML = html;
            contentEl.style.display = 'block';

            // Attach tab listeners.
            attachTabListeners(contentEl);

            // Attach "Ver mais" toggle listeners.
            attachShowMoreListeners(contentEl, strMap);



            // Initialize evidence slider(s) — pass evidence data, strings, and scale config for modal.
            var scaleConfig = comp ? (comp.scaleconfiguration || null) : null;
            initSliders(contentEl, ucs ? ucs.evidence : [], strMap, scaleConfig);

            // Initialize course scroll navigation.
            initCourseScroll(contentEl);

            // Attach event listeners for comments toggle button (lazy loading).
            if (displaySettings.showcomments && ucs && ucs.commentarea) {
                attachCommentsToggleListeners(contentEl, strMap);
            }

            return Promise.resolve();
        });
    }

    /**
     * Attach tab click listeners for switching between panes.
     *
     * @param {HTMLElement} contentEl The content container element
     */
    function attachTabListeners(contentEl) {
        const tabBtns = contentEl.querySelectorAll('.dims-tab-btn');

        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const tabId = this.dataset.tab;
                const wrapper = this.closest('.dims-tabs-wrapper');
                if (!wrapper) {
                    return;
                }

                // Deactivate all tabs in this wrapper.
                wrapper.querySelectorAll('.dims-tab-btn').forEach(function (b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-selected', 'false');
                });

                // Deactivate all panes.
                wrapper.querySelectorAll('.dims-tab-pane').forEach(function (p) {
                    p.classList.remove('active');
                });

                // Activate clicked tab.
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');

                // Activate corresponding pane.
                var pane = wrapper.querySelector('#dims-tabpane-' + tabId);
                if (pane) {
                    pane.classList.add('active');
                    refreshScrollableControls(pane);
                }
            });
        });
    }

    /**
     * Refresh arrow visibility for scrollable controls when hidden content becomes visible.
     *
     * @param {HTMLElement} container Visible pane/container
     */
    function refreshScrollableControls(container) {
        if (!container) {
            return;
        }

        var refresh = function () {
            container.querySelectorAll('.dims-ev-slider-wrapper, .dims-courses-scroll-wrapper').forEach(function (wrapper) {
                if (typeof wrapper._dimsUpdateArrows === 'function') {
                    wrapper._dimsUpdateArrows();
                }
            });
        };

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(refresh);
        } else {
            refresh();
        }

        // Secondary pass to catch late layout updates (fonts/images).
        setTimeout(refresh, 120);
    }

    /**
     * Render evidence cards in a horizontal slider with arrows.
     *
     * @param {Array} evidence The evidence array
     * @param {Object} strMap Language strings map
     * @return {string} HTML for evidence slider
     */
    function renderEvidenceSlider(evidence, strMap) {
        let html = '';

        if (!evidence || evidence.length === 0) {
            html += '<p class="text-muted" style="font-size: 0.875rem;">' + escapeHtml(strMap.noEvidence) + '</p>';
            return html;
        }

        html += '<div class="dims-ev-slider-wrapper" data-evidence-count="' + evidence.length + '">';

        // Slider track.
        html += '<div class="dims-ev-slider-track">';

        evidence.forEach(function (ev, index) {
            var typeInfo = getEvidenceTypeInfo(ev, strMap);
            var hasExtraDetails = ev.note || ev.url || (ev.grade && ev.gradename && ev.gradename !== '-');

            html += '<div class="dims-ev-card" data-evidence-index="' + index + '">';

            // Icon.
            html += '<div class="dims-ev-icon ' + typeInfo.colorClass + '">';
            html += '<i class="fa ' + typeInfo.icon + '" aria-hidden="true"></i>';
            html += '</div>';

            // Content.
            html += '<div class="dims-ev-content">';
            html += '<h3 class="dims-ev-title">' + escapeHtml(typeInfo.label) + '</h3>';

            if (ev.description) {
                html += '<p class="dims-ev-desc">' + ev.description + '</p>';
            }

            // Author + date (hidden for manual override evidence — details available in modal).
            var isManualOverride = typeInfo.colorClass === 'dims-evidence-manual';
            if (!isManualOverride && ev.usermodified && ev.actionuser) {
                var authorName = escapeHtml(ev.actionuser.fullname || '');
                var authorProfileUrl = M.cfg.wwwroot + '/user/profile.php?id=' + ev.usermodified;
                html += '<div class="dims-ev-meta">';
                html += '<i class="fa fa-user" aria-hidden="true"></i> ';
                html += '<a href="' + escapeHtml(authorProfileUrl) + '" target="_blank">';
                html += authorName + '</a>';
                if (ev.timecreated) {
                    html += ' <span class="dims-ev-meta-sep">&middot;</span> ';
                    html += '<span>' + formatTimestamp(ev.timecreated, strMap.dateFormat) + '</span>';
                }
                html += '</div>';
            }

            // Detail button — shown when extra info is available.
            if (hasExtraDetails) {
                html += '<button type="button" class="dims-ev-detail-btn" data-evidence-index="' + index + '"';
                html += ' aria-label="' + escapeHtml(strMap.evidenceViewDetails) + ': ' + escapeHtml(typeInfo.label) + '">';
                html += '<i class="fa fa-expand" aria-hidden="true"></i> ';
                html += '<span>' + escapeHtml(strMap.evidenceViewDetails) + '</span>';
                html += '</button>';
            }

            html += '</div>'; // End dims-ev-content.
            html += '</div>'; // End dims-ev-card.
        });

        html += '</div>'; // End dims-ev-slider-track.

        // Controls block (bottom-right).
        html += '<div class="dims-ev-slider-controls" role="group" aria-label="' + escapeHtml(strMap.evidenceLabel) + '">';
        html += '<button type="button" class="dims-ev-slider-btn dims-ev-slider-prev disabled"';
        html += ' aria-label="' + escapeHtml(strMap.sliderPrev) + '">';
        html += '<i class="fa fa-chevron-left" aria-hidden="true"></i>';
        html += '</button>';
        html += '<button type="button" class="dims-ev-slider-btn dims-ev-slider-next"';
        html += ' aria-label="' + escapeHtml(strMap.sliderNext) + '">';
        html += '<i class="fa fa-chevron-right" aria-hidden="true"></i>';
        html += '</button>';
        html += '</div>'; // End dims-ev-slider-controls.

        html += '</div>'; // End dims-ev-slider-wrapper.

        return html;
    }

    /**
     * Check if a grade value is considered proficient according to the scale configuration.
     *
     * The scaleconfiguration is a JSON string like:
     * [{"id":1,"scaledefault":0,"proficient":0},{"id":2,"scaledefault":1,"proficient":1}]
     * where each entry's position (1-based) corresponds to a scale value, and "proficient"
     * indicates whether that scale value is considered proficient.
     *
     * @param {number} gradeValue The grade value (1-based index into scale)
     * @param {string} scaleConfig The JSON-encoded scale configuration string
     * @return {boolean} True if the grade is considered proficient
     */
    function isGradeProficient(gradeValue, scaleConfig) {
        try {
            var config = JSON.parse(scaleConfig);
            if (!Array.isArray(config)) {
                return false;
            }
            // gradeValue is 1-based, array is 0-based.
            var index = parseInt(gradeValue, 10) - 1;
            if (index >= 0 && index < config.length) {
                return !!(config[index].proficient && parseInt(config[index].proficient, 10) === 1);
            }
        } catch (e) {
            // Invalid JSON — fall back to not proficient.
        }
        return false;
    }

    /**
     * Open a modal with full evidence details.
     *
     * @param {Object} ev The evidence object from the API
     * @param {Object} strMap Language strings map
     * @param {string|null} scaleConfig The scale configuration JSON string from the competency
     */
    function openEvidenceDetailModal(ev, strMap, scaleConfig) {
        var typeInfo = getEvidenceTypeInfo(ev, strMap);
        var hasNote = !!(ev.note && ev.note.trim());
        var hasUrl = !!ev.url;
        var hasGrade = !!(ev.grade && ev.gradename && ev.gradename !== '-');
        var hasActionUser = !!(ev.actionuser && ev.actionuser.fullname);

        // Determine if the grade value is considered proficient using the scale configuration.
        var gradeProficient = false;
        if (hasGrade && scaleConfig) {
            gradeProficient = isGradeProficient(ev.grade, scaleConfig);
        }

        var context = {
            typelabel: typeInfo.label,
            typeicon: typeInfo.icon,
            colorclass: typeInfo.colorClass,
            description: ev.description || '',
            hasnote: hasNote,
            note: hasNote ? ev.note : '',
            hasurl: hasUrl,
            url: hasUrl ? ev.url : '',
            urllabel: hasUrl ? ev.url : '',
            hasgrade: hasGrade,
            gradename: hasGrade ? ev.gradename : '',
            gradeproficient: gradeProficient,
            hasactionuser: hasActionUser,
            actionusername: hasActionUser ? ev.actionuser.fullname : '',
            actionuserprofileurl: hasActionUser
                ? (ev.actionuser.profileurl || M.cfg.wwwroot + '/user/profile.php?id=' + ev.usermodified)
                : '',
            actionuseravatar: hasActionUser
                ? (ev.actionuser.profileimageurlsmall || '')
                : '',
            datestring: ev.userdate || '',
            strnote: strMap.evidenceNote,
            strlink: strMap.evidenceLink,
            strgrade: strMap.evidenceGrade,
            strauthor: strMap.evidenceAuthor,
            strdate: strMap.evidenceDate,
            stropenlink: strMap.evidenceOpenLink
        };

        Modal.create({
            title: strMap.evidenceDetails,
            body: Templates.render('local_dimensions/evidence_detail_modal', context),
            large: false,
            removeOnClose: true
        }).then(function (modal) {
            modal.show();
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Initialize evidence slider scroll and arrow logic.
     *
     * @param {HTMLElement} contentEl The content container element
     * @param {Array} evidenceData The evidence array from the API
     * @param {Object} strMap Language strings map
     * @param {string|null} scaleConfig The scale configuration JSON string from the competency
     */
    function initSliders(contentEl, evidenceData, strMap, scaleConfig) {
        var sliders = contentEl.querySelectorAll('.dims-ev-slider-wrapper');

        sliders.forEach(function (wrapper) {
            var track = wrapper.querySelector('.dims-ev-slider-track');
            var prevBtn = wrapper.querySelector('.dims-ev-slider-prev');
            var nextBtn = wrapper.querySelector('.dims-ev-slider-next');

            if (!track) {
                return;
            }

            var evidenceCount = parseInt(wrapper.dataset.evidenceCount, 10)
                || track.querySelectorAll('.dims-ev-card').length;
            var edgeThreshold = 2;
            wrapper.classList.add('dims-controls-hidden');
            if (prevBtn) {
                prevBtn.style.display = 'none';
            }
            if (nextBtn) {
                nextBtn.style.display = 'none';
            }

            function shouldShowControls() {
                var isMobile = window.matchMedia && window.matchMedia('(max-width: 575.98px)').matches;
                if (evidenceCount <= 1) {
                    return false;
                }
                return evidenceCount > 2 || isMobile;
            }

            function getCardOffset(card) {
                var trackRect = track.getBoundingClientRect();
                var cardRect = card.getBoundingClientRect();
                return (cardRect.left - trackRect.left) + track.scrollLeft;
            }

            function getCardOffsets() {
                return Array.prototype.map.call(track.querySelectorAll('.dims-ev-card'), function (card) {
                    return getCardOffset(card);
                });
            }

            function easeInOutCubic(progress) {
                if (progress < 0.5) {
                    return 4 * progress * progress * progress;
                }
                return 1 - Math.pow(-2 * progress + 2, 3) / 2;
            }

            function animateTrackScroll(targetLeft) {
                // Respect reduced-motion users.
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    track.classList.remove('dims-animating');
                    track.scrollLeft = targetLeft;
                    return;
                }

                var startLeft = track.scrollLeft;
                var distance = targetLeft - startLeft;
                if (Math.abs(distance) < 1) {
                    track.classList.remove('dims-animating');
                    track.scrollLeft = targetLeft;
                    return;
                }

                track.classList.add('dims-animating');

                if (track._dimsAnimFrame && window.cancelAnimationFrame) {
                    window.cancelAnimationFrame(track._dimsAnimFrame);
                }

                var duration = Math.min(520, Math.max(300, Math.abs(distance) * 1.2));
                var startedAt = null;

                function step(timestamp) {
                    if (startedAt === null) {
                        startedAt = timestamp;
                    }
                    var elapsed = timestamp - startedAt;
                    var progress = Math.min(1, elapsed / duration);
                    var eased = easeInOutCubic(progress);
                    track.scrollLeft = startLeft + (distance * eased);

                    if (progress < 1) {
                        track._dimsAnimFrame = window.requestAnimationFrame(step);
                    } else {
                        track.scrollLeft = targetLeft;
                        track._dimsAnimFrame = null;
                        if (window.requestAnimationFrame) {
                            window.requestAnimationFrame(function () {
                                track.classList.remove('dims-animating');
                            });
                        } else {
                            track.classList.remove('dims-animating');
                        }
                        updateArrows();
                    }
                }

                if (window.requestAnimationFrame) {
                    track._dimsAnimFrame = window.requestAnimationFrame(step);
                } else {
                    track.scrollLeft = targetLeft;
                    track.classList.remove('dims-animating');
                }
            }

            function scrollCardIntoView(card) {
                var cardStart = getCardOffset(card);
                var cardEnd = cardStart + card.offsetWidth;
                var viewStart = track.scrollLeft;
                var viewEnd = viewStart + track.clientWidth;

                if (cardStart >= viewStart + edgeThreshold && cardEnd <= viewEnd - edgeThreshold) {
                    return;
                }

                var maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
                var targetLeft = Math.min(maxScroll, Math.max(0, cardStart));
                animateTrackScroll(targetLeft);
            }

            function scrollToAdjacentCard(direction) {
                var offsets = getCardOffsets();
                if (!offsets.length) {
                    return;
                }

                var current = track.scrollLeft;
                var target = current;

                if (direction > 0) {
                    for (var i = 0; i < offsets.length; i++) {
                        if (offsets[i] > current + edgeThreshold) {
                            target = offsets[i];
                            break;
                        }
                    }
                    if (target === current) {
                        target = offsets[offsets.length - 1];
                    }
                } else {
                    target = 0;
                    for (var j = offsets.length - 1; j >= 0; j--) {
                        if (offsets[j] < current - edgeThreshold) {
                            target = offsets[j];
                            break;
                        }
                    }
                }

                var maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
                var targetLeft = Math.min(maxScroll, Math.max(0, target));
                animateTrackScroll(targetLeft);
            }

            function updateArrows() {
                var scrollLeft = track.scrollLeft;
                var maxScroll = track.scrollWidth - track.clientWidth;

                if (!shouldShowControls()) {
                    wrapper.classList.add('dims-controls-hidden');
                    if (prevBtn) {
                        prevBtn.style.display = 'none';
                    }
                    if (nextBtn) {
                        nextBtn.style.display = 'none';
                    }
                    return;
                }
                wrapper.classList.remove('dims-controls-hidden');

                // If all content fits, hide both arrows entirely.
                if (maxScroll <= edgeThreshold) {
                    if (prevBtn) {
                        prevBtn.style.display = '';
                        prevBtn.classList.add('disabled');
                    }
                    if (nextBtn) {
                        nextBtn.style.display = '';
                        nextBtn.classList.add('disabled');
                    }
                    return;
                }

                if (prevBtn) {
                    prevBtn.style.display = '';
                    if (scrollLeft <= edgeThreshold) {
                        prevBtn.classList.add('disabled');
                    } else {
                        prevBtn.classList.remove('disabled');
                    }
                }

                if (nextBtn) {
                    nextBtn.style.display = '';
                    if (scrollLeft >= maxScroll - edgeThreshold) {
                        nextBtn.classList.add('disabled');
                    } else {
                        nextBtn.classList.remove('disabled');
                    }
                }
            }

            // Expose updater so tabs can refresh controls after showing hidden panes.
            wrapper._dimsUpdateArrows = updateArrows;

            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    scrollToAdjacentCard(-1);
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    scrollToAdjacentCard(1);
                });
            }

            track.querySelectorAll('.dims-ev-card').forEach(function (card) {
                card.addEventListener('click', function (event) {
                    if (event.target.closest('a, button')) {
                        return;
                    }
                    scrollCardIntoView(card);
                });
            });

            track.addEventListener('scroll', updateArrows);

            // Initial checks: immediate + next frame + delayed safety pass.
            updateArrows();
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(updateArrows);
            }
            setTimeout(updateArrows, 120);

            // Recalculate when size changes (e.g., tab becomes visible).
            if (typeof ResizeObserver === 'function') {
                var resizeObserver = new ResizeObserver(updateArrows);
                resizeObserver.observe(track);
            }

            // Enable drag scrolling.
            enableDragScroll(track);

            // Evidence detail button handler — opens modal with full evidence info.
            wrapper.addEventListener('click', function (e) {
                var btn = e.target.closest('.dims-ev-detail-btn');
                if (!btn) {
                    return;
                }
                e.stopPropagation();
                var idx = parseInt(btn.dataset.evidenceIndex, 10);
                if (!isNaN(idx) && evidenceData && evidenceData[idx]) {
                    openEvidenceDetailModal(evidenceData[idx], strMap, scaleConfig);
                }
            });
        });
    }

    /**
     * Enable mouse drag scrolling on a scrollable element.
     *
     * @param {HTMLElement} el The element to enable drag scrolling on
     */
    function enableDragScroll(el) {
        var isDown = false;
        var startX;
        var scrollLeft;
        var hasDragged = false;
        var suppressNextClick = false;
        var dragThreshold = 6;

        el.addEventListener('mousedown', function (e) {
            // Ignore clicks on links/buttons.
            if (e.target.closest('a, button')) {
                return;
            }
            isDown = true;
            hasDragged = false;
            el.classList.add('dims-dragging');
            startX = e.pageX - el.offsetLeft;
            scrollLeft = el.scrollLeft;
        });

        el.addEventListener('mouseleave', function () {
            if (isDown && hasDragged) {
                suppressNextClick = true;
            }
            isDown = false;
            hasDragged = false;
            el.classList.remove('dims-dragging');
        });

        el.addEventListener('mouseup', function () {
            if (isDown && hasDragged) {
                suppressNextClick = true;
            }
            isDown = false;
            hasDragged = false;
            el.classList.remove('dims-dragging');
        });

        el.addEventListener('mousemove', function (e) {
            if (!isDown) {
                return;
            }
            var x = e.pageX - el.offsetLeft;
            var delta = x - startX;
            if (Math.abs(delta) > dragThreshold) {
                hasDragged = true;
            }
            if (!hasDragged) {
                return;
            }
            e.preventDefault();
            var walk = delta * 1.5;
            el.scrollLeft = scrollLeft - walk;
        });

        // Prevent click handlers from firing right after dragging.
        el.addEventListener('click', function (e) {
            if (!suppressNextClick) {
                return;
            }
            suppressNextClick = false;
            e.preventDefault();
            e.stopPropagation();
        }, true);
    }

    /**
     * Render linked courses as a scrollable horizontal section.
     *
     * @param {Array} courses Visible courses array
     * @param {Object} strMap Language strings map
     * @return {string} HTML for the courses scrollable section
     */
    function renderCourseCardsScrollable(courses, strMap) {
        const hasManyCourses = courses.length > 2;
        let html = '<section class="dims-section dims-courses-section">';

        // Section title.
        html += '<h2 class="dims-section-title">';
        html += escapeHtml(strMap.linkedCourses);
        html += ' <span class="dims-section-badge">' + courses.length + '</span>';
        html += '</h2>';

        html += '<div class="dims-courses-scroll-wrapper" data-course-count="' + courses.length + '">';
        html += '<div class="dims-courses-scroll' + (hasManyCourses ? '' : ' dims-courses-no-scroll') + '">';

        courses.forEach(function (course) {
            const courseUrl = M.cfg.wwwroot + '/course/view.php?id=' + course.id;
            const courseName = course.fullname || course.shortname || '';
            const progress = parseInt(course.progress, 10) || 0;
            const hasImage = course.courseimage && course.courseimage.trim() !== '';

            html += '<div class="dims-course-card-lg">';

            // Course image.
            if (hasImage) {
                html += '<div class="dims-course-img">';
                html += '<img src="' + escapeHtml(course.courseimage) + '" alt="" loading="lazy">';
                html += '</div>';
            } else {
                // Gradient placeholder with initials.
                var initials = getInitials(courseName);
                html += '<div class="dims-course-img dims-course-img-placeholder">';
                html += '<span>' + escapeHtml(initials) + '</span>';
                html += '</div>';
            }

            // Course body.
            html += '<div class="dims-course-body">';
            html += '<h3 class="dims-course-name-lg">' + escapeHtml(courseName) + '</h3>';

            // Progress bar.
            html += '<div class="dims-course-progress-lg">';
            html += '<div class="dims-course-progress-track">';
            html += '<div class="dims-course-progress-fill-lg" style="width: ' + progress + '%;"></div>';
            html += '</div>';
            html += '<span class="dims-course-progress-pct-lg">' + progress + '%</span>';
            if (progress >= 100) {
                html += '<i class="fa fa-check-circle dims-course-check" aria-hidden="true"></i>';
            }
            html += '</div>';

            // Access button (full width).
            html += '<a href="' + escapeHtml(courseUrl) + '" class="btn btn-secondary dims-course-btn">';
            html += escapeHtml(strMap.accessLabel);
            html += '</a>';

            html += '</div>'; // End dims-course-body.
            html += '</div>'; // End dims-course-card-lg.
        });

        html += '</div>'; // End dims-courses-scroll.
        html += '<div class="dims-courses-scroll-controls" role="group" aria-label="' + escapeHtml(strMap.linkedCourses) + '">';
        html += '<button type="button" class="dims-scroll-btn dims-scroll-prev disabled"';
        html += ' aria-label="' + escapeHtml(strMap.sliderPrev) + '">';
        html += '<i class="fa fa-chevron-left" aria-hidden="true"></i>';
        html += '</button>';
        html += '<button type="button" class="dims-scroll-btn dims-scroll-next"';
        html += ' aria-label="' + escapeHtml(strMap.sliderNext) + '">';
        html += '<i class="fa fa-chevron-right" aria-hidden="true"></i>';
        html += '</button>';
        html += '</div>'; // End dims-courses-scroll-controls.

        html += '</div>'; // End dims-courses-scroll-wrapper.
        html += '</section>';

        return html;
    }

    /**
     * Initialize course cards scroll navigation.
     *
     * @param {HTMLElement} contentEl The content container element
     */
    function initCourseScroll(contentEl) {
        var wrappers = contentEl.querySelectorAll('.dims-courses-scroll-wrapper');

        wrappers.forEach(function (wrapper) {
            var track = wrapper.querySelector('.dims-courses-scroll');
            var prevBtn = wrapper.querySelector('.dims-scroll-prev');
            var nextBtn = wrapper.querySelector('.dims-scroll-next');

            if (!track) {
                return;
            }

            var courseCount = parseInt(wrapper.dataset.courseCount, 10)
                || track.querySelectorAll('.dims-course-card-lg').length;
            var edgeThreshold = 2;
            wrapper.classList.add('dims-controls-hidden');
            if (prevBtn) {
                prevBtn.style.display = 'none';
            }
            if (nextBtn) {
                nextBtn.style.display = 'none';
            }

            function shouldShowControls() {
                var isMobile = window.matchMedia && window.matchMedia('(max-width: 575.98px)').matches;
                if (courseCount <= 1) {
                    return false;
                }
                return courseCount > 2 || isMobile;
            }

            function getCardOffset(card) {
                var trackRect = track.getBoundingClientRect();
                var cardRect = card.getBoundingClientRect();
                return (cardRect.left - trackRect.left) + track.scrollLeft;
            }

            function getCardOffsets() {
                return Array.prototype.map.call(track.querySelectorAll('.dims-course-card-lg'), function (card) {
                    return getCardOffset(card);
                });
            }

            function easeInOutCubic(progress) {
                if (progress < 0.5) {
                    return 4 * progress * progress * progress;
                }
                return 1 - Math.pow(-2 * progress + 2, 3) / 2;
            }

            function animateTrackScroll(targetLeft) {
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    track.classList.remove('dims-animating');
                    track.scrollLeft = targetLeft;
                    return;
                }

                var startLeft = track.scrollLeft;
                var distance = targetLeft - startLeft;
                if (Math.abs(distance) < 1) {
                    track.classList.remove('dims-animating');
                    track.scrollLeft = targetLeft;
                    return;
                }

                track.classList.add('dims-animating');

                if (track._dimsAnimFrame && window.cancelAnimationFrame) {
                    window.cancelAnimationFrame(track._dimsAnimFrame);
                }

                var duration = Math.min(520, Math.max(300, Math.abs(distance) * 1.2));
                var startedAt = null;

                function step(timestamp) {
                    if (startedAt === null) {
                        startedAt = timestamp;
                    }
                    var elapsed = timestamp - startedAt;
                    var progress = Math.min(1, elapsed / duration);
                    var eased = easeInOutCubic(progress);
                    track.scrollLeft = startLeft + (distance * eased);

                    if (progress < 1) {
                        track._dimsAnimFrame = window.requestAnimationFrame(step);
                    } else {
                        track.scrollLeft = targetLeft;
                        track._dimsAnimFrame = null;
                        if (window.requestAnimationFrame) {
                            window.requestAnimationFrame(function () {
                                track.classList.remove('dims-animating');
                            });
                        } else {
                            track.classList.remove('dims-animating');
                        }
                        updateArrows();
                    }
                }

                if (window.requestAnimationFrame) {
                    track._dimsAnimFrame = window.requestAnimationFrame(step);
                } else {
                    track.scrollLeft = targetLeft;
                    track.classList.remove('dims-animating');
                }
            }

            function scrollToAdjacentCard(direction) {
                var offsets = getCardOffsets();
                if (!offsets.length) {
                    return;
                }

                var current = track.scrollLeft;
                var target = current;

                if (direction > 0) {
                    for (var i = 0; i < offsets.length; i++) {
                        if (offsets[i] > current + edgeThreshold) {
                            target = offsets[i];
                            break;
                        }
                    }
                    if (target === current) {
                        target = offsets[offsets.length - 1];
                    }
                } else {
                    target = 0;
                    for (var j = offsets.length - 1; j >= 0; j--) {
                        if (offsets[j] < current - edgeThreshold) {
                            target = offsets[j];
                            break;
                        }
                    }
                }

                var maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
                var targetLeft = Math.min(maxScroll, Math.max(0, target));
                animateTrackScroll(targetLeft);
            }

            function updateArrows() {
                var scrollLeft = track.scrollLeft;
                var maxScroll = track.scrollWidth - track.clientWidth;

                if (!shouldShowControls()) {
                    wrapper.classList.add('dims-controls-hidden');
                    if (prevBtn) {
                        prevBtn.style.display = 'none';
                    }
                    if (nextBtn) {
                        nextBtn.style.display = 'none';
                    }
                    return;
                }
                wrapper.classList.remove('dims-controls-hidden');

                // If all content fits, keep buttons muted.
                if (maxScroll <= edgeThreshold) {
                    if (prevBtn) {
                        prevBtn.style.display = '';
                        prevBtn.classList.add('disabled');
                    }
                    if (nextBtn) {
                        nextBtn.style.display = '';
                        nextBtn.classList.add('disabled');
                    }
                    return;
                }

                if (prevBtn) {
                    prevBtn.style.display = '';
                    if (scrollLeft <= edgeThreshold) {
                        prevBtn.classList.add('disabled');
                    } else {
                        prevBtn.classList.remove('disabled');
                    }
                }

                if (nextBtn) {
                    nextBtn.style.display = '';
                    if (scrollLeft >= maxScroll - edgeThreshold) {
                        nextBtn.classList.add('disabled');
                    } else {
                        nextBtn.classList.remove('disabled');
                    }
                }
            }

            wrapper._dimsUpdateArrows = updateArrows;

            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    scrollToAdjacentCard(-1);
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    scrollToAdjacentCard(1);
                });
            }

            // Click on a course card scrolls it into view (like evidence cards).
            function scrollCardIntoView(card) {
                var cardStart = getCardOffset(card);
                var cardEnd = cardStart + card.offsetWidth;
                var viewStart = track.scrollLeft;
                var viewEnd = viewStart + track.clientWidth;

                if (cardStart >= viewStart + edgeThreshold && cardEnd <= viewEnd - edgeThreshold) {
                    return;
                }

                var maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
                var targetLeft = Math.min(maxScroll, Math.max(0, cardStart));
                animateTrackScroll(targetLeft);
            }

            track.querySelectorAll('.dims-course-card-lg').forEach(function (card) {
                card.addEventListener('click', function (event) {
                    if (event.target.closest('a, button')) {
                        return;
                    }
                    scrollCardIntoView(card);
                });
            });

            track.addEventListener('scroll', updateArrows);
            updateArrows();
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(updateArrows);
            }
            setTimeout(updateArrows, 120);

            if (typeof ResizeObserver === 'function') {
                var resizeObserver = new ResizeObserver(updateArrows);
                resizeObserver.observe(track);
            }

            enableDragScroll(track);
        });
    }

    /**
     * Render competency path/hierarchy.
     *
     * @param {Object} data The competency data
     * @param {Object} strMap Language strings map
     * @return {string} HTML for the path
     */
    function renderCompetencyPath(data, strMap) {
        let html = '';

        if (!data.competency) {
            return html;
        }

        let pathParts = [];

        // Framework name.
        if (data.framework && data.framework.shortname) {
            pathParts.push(escapeHtml(data.framework.shortname));
        }

        // Parent competencies.
        if (data.compparents && Array.isArray(data.compparents)) {
            data.compparents.forEach(function (parent) {
                if (parent.shortname) {
                    pathParts.push(escapeHtml(parent.shortname));
                }
            });
        }

        if (pathParts.length > 0) {
            html += '<nav class="dims-path-bar" aria-label="' + escapeHtml(strMap.pathBreadcrumbLabel) + '">';
            html += '<span class="dims-path-label">' + escapeHtml(strMap.pathBreadcrumbLabel) + ':</span>';
            html += '<ol class="dims-path-breadcrumb">';
            pathParts.forEach(function (part, idx) {
                html += '<li>';
                if (idx > 0) {
                    html += '<i class="fa fa-chevron-right dims-path-bar-sep" aria-hidden="true"></i>';
                }
                html += part;
                html += '</li>';
            });
            html += '</ol>';
            html += '</nav>';
        }

        return html;
    }

    /**
     * Render related competencies.
     *
     * @param {Object} data The competency data
     * @param {Object} strMap Language strings map
     * @return {string} HTML for related competencies
     */
    function renderRelatedCompetencies(data, strMap) {
        let html = '';

        if (!data.relatedcompetencies || data.relatedcompetencies.length === 0) {
            return html;
        }

        html += '<section class="dims-section dims-related-section">';
        html += '<h3 class="dims-related-header">';
        html += escapeHtml(strMap.relatedLabel);
        html += '</h3>';
        html += '<div class="dims-related-pills">';

        data.relatedcompetencies.forEach(function (related) {
            html += '<span class="dims-related-pill-v2">' + escapeHtml(related.shortname) + '</span>';
        });

        html += '</div>';
        html += '</section>';

        return html;
    }

    /**
     * Get evidence type info (icon, label, color class).
     *
     * @param {Object} evidence The evidence object
     * @param {Object} strMap Language strings map
     * @return {Object} Type info with icon, label, colorClass
     */
    function getEvidenceTypeInfo(evidence, strMap) {
        // Evidence action constants from Moodle core_competency.
        // 0 = EVIDENCE_ACTION_LOG, 1 = EVIDENCE_ACTION_SUGGEST, 2 = EVIDENCE_ACTION_COMPLETE, 3 = EVIDENCE_ACTION_OVERRIDE.
        const action = evidence.action || 0;

        // Determine type based on action and description patterns.
        if (evidence.url && evidence.url.indexOf('/mod/') !== -1) {
            return {
                icon: 'fa-check-circle',
                label: strMap.evidenceTypeActivity,
                colorClass: 'dims-evidence-activity'
            };
        }

        if (evidence.url && evidence.url.indexOf('/grade/') !== -1) {
            return {
                icon: 'fa-graduation-cap',
                label: strMap.evidenceTypeCoursegrade,
                colorClass: 'dims-evidence-grade'
            };
        }

        if (action === 3) { // OVERRIDE - typically manual rating.
            return {
                icon: 'fa-pencil',
                label: strMap.evidenceTypeManual,
                colorClass: 'dims-evidence-manual'
            };
        }

        if (evidence.desca && evidence.desca.indexOf('file') !== -1) {
            return {
                icon: 'fa-paperclip',
                label: strMap.evidenceTypeFile,
                colorClass: 'dims-evidence-file'
            };
        }

        if (action === 2) { // COMPLETE.
            return {
                icon: 'fa-trophy',
                label: strMap.evidenceTypePrior,
                colorClass: 'dims-evidence-prior'
            };
        }

        // Default.
        return {
            icon: 'fa-info-circle',
            label: strMap.evidenceTypeOther,
            colorClass: 'dims-evidence-other'
        };
    }

    /**
     * Render comments section with lazy loading.
     * Shows a toggle button that loads comments via AJAX when clicked.
     *
     * @param {Object} commentarea The comment area object with metadata
     * @param {Object} strMap Language strings map
     * @return {string} HTML for comments section
     */
    function renderCommentsSection(commentarea, strMap) {
        const count = commentarea.count || 0;

        // Create unique container ID for this commentarea.
        const containerId = 'dims-comments-container-' + (commentarea.itemid || Date.now());

        let html = '<section class="dims-section">';

        // Card wrapper without shadow (simple border-top section).
        html += '<div class="dims-cm-card">';

        // Accordion trigger (styled as card header).
        html += '<button type="button" class="dims-cm-trigger" ';
        html += 'data-container-id="' + containerId + '" ';
        html += 'data-component="' + escapeHtml(commentarea.component || 'competency') + '" ';
        html += 'data-area="' + escapeHtml(commentarea.commentarea || 'user_competency') + '" ';
        html += 'data-itemid="' + (commentarea.itemid || 0) + '" ';
        html += 'data-contextid="' + (commentarea.contextid || 1) + '" ';
        html += 'data-courseid="' + (commentarea.courseid || 1) + '" ';
        html += 'aria-expanded="false" ';
        html += 'aria-controls="' + containerId + '">';

        html += '<div class="dims-cm-trigger-label">';
        html += '<i class="fa fa-comments" aria-hidden="true"></i> ';
        html += '<span>' + escapeHtml(strMap.commentsSection) + '</span>';
        if (count > 0) {
            html += ' <span class="dims-section-badge">' + count + '</span>';
        }
        html += '</div>';

        html += '<i class="fa fa-chevron-down dims-cm-chevron" aria-hidden="true"></i>';
        html += '</button>';

        // Collapsible container (hidden by default) - keeps current timeline format.
        html += '<div id="' + containerId + '" class="dims-comments-container" hidden>';
        html += '<div class="dims-comments-loading">';
        html += '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> ';
        html += '</div>';
        html += '<div class="dims-comments-content"></div>';
        html += '<div class="dims-comments-error" style="display: none;">';
        html += '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i> ';
        html += '</div>';
        html += '</div>';

        html += '</div>'; // End dims-shadow-card.
        html += '</section>';

        return html;
    }

    /**
     * Load comments via AJAX for a specific comment area.
     *
     * @param {HTMLElement} container The comments container element
     * @param {Object} params Parameters for the AJAX call
     * @param {Object} strMap Language strings map
     * @return {Promise} Promise that resolves when comments are loaded
     */
    function loadCommentsAjax(container, params, strMap) {
        const loadingEl = container.querySelector('.dims-comments-loading');
        const contentEl = container.querySelector('.dims-comments-content');
        const errorEl = container.querySelector('.dims-comments-error');

        // Show loading state.
        if (loadingEl) {
            loadingEl.style.display = 'block';
        }
        if (contentEl) {
            contentEl.style.display = 'none';
        }
        if (errorEl) {
            errorEl.style.display = 'none';
        }

        // Use local_dimensions_get_comments webservice.
        return Ajax.call([{
            methodname: 'local_dimensions_get_comments',
            args: {
                component: params.component,
                area: params.area,
                itemid: params.itemid,
                contextid: params.contextid,
                page: 0
            }
        }])[0].then(function (response) {
            // Hide loading.
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }

            const comments = response.comments || [];
            const canPost = response.canpost || false;
            let html = '';

            // Single comment input form at the top (only if user can post).
            if (canPost) {
                html += '<div class="dims-comment-form">';
                html += '<textarea class="dims-comment-textarea form-control"';
                html += ' placeholder="' + escapeHtml(strMap.commentPlaceholder) + '"';
                html += ' rows="2"></textarea>';
                html += '<button type="button" class="dims-comment-send-btn btn btn-primary btn-sm">';
                html += '<i class="fa fa-paper-plane" aria-hidden="true"></i> ';
                html += escapeHtml(strMap.addComment);
                html += '</button>';
                html += '</div>';
            }

            if (comments.length === 0) {
                html += '<p class="dims-no-comments text-muted">' + escapeHtml(strMap.noComments) + '</p>';
            } else {
                html += '<div class="dims-comments-list">';
                comments.forEach(function (comment, index) {
                    html += renderLoadedComment(comment, strMap, index);
                });
                html += '</div>';
            }

            contentEl.innerHTML = html;
            contentEl.style.display = 'block';

            // Attach event listener for the single comment form.
            if (canPost) {
                var commentareaCtx = {
                    component: params.component,
                    commentarea: params.area,
                    itemid: params.itemid,
                    contextid: params.contextid,
                    courseid: params.courseid
                };
                attachSingleCommentFormListener(container, commentareaCtx, strMap);
            }

            return Promise.resolve();
        }).catch(function (error) {
            // Hide loading, show error.
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
            if (errorEl) {
                errorEl.style.display = 'block';
            }
            Notification.exception(error);
            return Promise.reject(error);
        });
    }

    /**
     * Render a loaded comment from the AJAX response.
     * Read-only card: initials, author, timestamp, content.
     *
     * @param {Object} comment The comment object from core_comment_get_comments
     * @param {Object} strMap Language strings map
     * @param {number} index Comment index
     * @return {string} HTML for the comment
     */
    function renderLoadedComment(comment, strMap, index) {
        const fullname = comment.fullname || comment.userfullname || '';
        const initials = getInitials(fullname || 'U');
        const timeFormatted = comment.timecreated || '';
        const userid = comment.userid || 0;
        const profileUrl = userid ? (M.cfg.wwwroot + '/user/profile.php?id=' + userid) : '';
        const profileImageUrl = comment.profileimageurl || '';

        let html = '<div class="dims-comment" data-comment-id="' + comment.id + '">';

        // Comment header with avatar and author info.
        html += '<div class="dims-comment-header">';
        if (profileImageUrl) {
            html += '<img class="dims-comment-avatar" src="' + escapeHtml(profileImageUrl) + '"';
            html += ' alt="" aria-hidden="true" />';
        } else {
            html += '<div class="dims-comment-initials">' + escapeHtml(initials) + '</div>';
        }
        html += '<div class="dims-comment-meta">';
        if (profileUrl) {
            html += '<a href="' + escapeHtml(profileUrl) + '" target="_blank" class="dims-comment-author">';
            html += escapeHtml(fullname) + '</a>';
        } else {
            html += '<span class="dims-comment-author">' + escapeHtml(fullname) + '</span>';
        }
        if (timeFormatted) {
            html += '<span class="dims-comment-time text-muted">' + escapeHtml(timeFormatted) + '</span>';
        }
        html += '</div>';
        html += '</div>';

        // Comment content.
        html += '<div class="dims-comment-body">' + (comment.content || '') + '</div>';

        html += '</div>';

        return html;
    }

    /**
     * Render a single comment (read-only card).
     *
     * @param {Object} comment The comment object
     * @param {Object} strMap Language strings map
     * @param {number} index Comment index
     * @return {string} HTML for the comment
     */
    function renderComment(comment, strMap, index) {
        const initials = getInitials(comment.fullname || 'U');
        const userid = comment.userid || 0;
        const profileUrl = userid ? (M.cfg.wwwroot + '/user/profile.php?id=' + userid) : '';
        const profileImageUrl = comment.profileimageurl || '';

        let html = '<div class="dims-comment" data-comment-id="' + comment.id + '">';

        // Comment header with avatar and author info.
        html += '<div class="dims-comment-header">';
        if (profileImageUrl) {
            html += '<img class="dims-comment-avatar" src="' + escapeHtml(profileImageUrl) + '"';
            html += ' alt="" aria-hidden="true" />';
        } else {
            html += '<div class="dims-comment-initials">' + escapeHtml(initials) + '</div>';
        }
        html += '<div class="dims-comment-meta">';
        if (profileUrl) {
            html += '<a href="' + escapeHtml(profileUrl) + '" target="_blank" class="dims-comment-author">';
            html += escapeHtml(comment.fullname || '') + '</a>';
        } else {
            html += '<span class="dims-comment-author">' + escapeHtml(comment.fullname || '') + '</span>';
        }
        if (comment.timecreated) {
            html += '<span class="dims-comment-time text-muted">' + escapeHtml(comment.timecreated) + '</span>';
        }
        html += '</div>';
        html += '</div>';

        // Comment content.
        html += '<div class="dims-comment-body">' + (comment.content || '') + '</div>';

        html += '</div>';

        return html;
    }

    /**
     * Get initials from a full name.
     *
     * @param {string} fullname The full name
     * @return {string} Initials (up to 2 characters)
     */
    function getInitials(fullname) {
        if (!fullname) {
            return 'U';
        }
        const parts = fullname.trim().split(/\s+/);
        if (parts.length >= 2) {
            return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
        }
        return parts[0].charAt(0).toUpperCase();
    }

    // Cache for loaded comments containers to avoid reloading.
    const loadedCommentsContainers = new Set();

    /**
     * Attach event listeners for comments toggle buttons.
     * Handles lazy loading of comments when the toggle button is clicked.
     *
     * @param {HTMLElement} contentEl The content container element
     * @param {Object} strMap Language strings map
     */
    function attachCommentsToggleListeners(contentEl, strMap) {
        const toggleBtns = contentEl.querySelectorAll('.dims-cm-trigger');

        toggleBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const containerId = this.dataset.containerId;
                const container = document.getElementById(containerId);

                if (!container) {
                    return;
                }

                const isExpanded = this.getAttribute('aria-expanded') === 'true';

                if (isExpanded) {
                    // Collapse.
                    this.setAttribute('aria-expanded', 'false');
                    container.hidden = true;
                } else {
                    // Expand.
                    this.setAttribute('aria-expanded', 'true');
                    container.hidden = false;

                    // Load comments if not already loaded.
                    if (!loadedCommentsContainers.has(containerId)) {
                        loadedCommentsContainers.add(containerId);

                        const params = {
                            component: this.dataset.component,
                            area: this.dataset.area,
                            itemid: parseInt(this.dataset.itemid, 10),
                            contextid: parseInt(this.dataset.contextid, 10),
                            courseid: parseInt(this.dataset.courseid, 10)
                        };

                        loadCommentsAjax(container, params, strMap);
                    }
                }
            });

            // Keyboard support.
            btn.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
    }

    /**
     * Attach event listener for the single comment form.
     *
     * @param {HTMLElement} container The comments container element
     * @param {Object} commentarea The comment area context
     * @param {Object} strMap Language strings map
     */
    function attachSingleCommentFormListener(container, commentarea, strMap) {
        const sendBtn = container.querySelector('.dims-comment-send-btn');
        const textarea = container.querySelector('.dims-comment-textarea');

        if (!sendBtn || !textarea) {
            return;
        }

        sendBtn.addEventListener('click', function () {
            const content = textarea.value.trim();
            if (!content) {
                return;
            }

            // Disable button during request.
            sendBtn.disabled = true;
            var originalHTML = sendBtn.innerHTML;
            sendBtn.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>';

            postComment(commentarea, content, sendBtn, originalHTML, textarea, container, strMap);
        });

        // Allow Ctrl+Enter / Cmd+Enter to send.
        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                sendBtn.click();
            }
        });
    }

    /**
     * Post a comment via AJAX.
     *
     * @param {Object} commentarea The comment area context
     * @param {string} content The comment content
     * @param {HTMLElement} sendBtn The send button element
     * @param {string} originalBtnHTML The original button inner HTML
     * @param {HTMLElement} textarea The textarea element
     * @param {HTMLElement} container The comments container element
     * @param {Object} strMap Language strings map
     */
    function postComment(commentarea, content, sendBtn, originalBtnHTML, textarea, container, strMap) {
        if (!commentarea || !commentarea.component || !commentarea.itemid) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = originalBtnHTML;
            return;
        }

        Ajax.call([{
            methodname: 'local_dimensions_add_comment',
            args: {
                component: commentarea.component,
                area: commentarea.commentarea || commentarea.area || 'user_competency',
                itemid: commentarea.itemid,
                contextid: commentarea.contextid || 1,
                content: content
            }
        }])[0].then(function (result) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = originalBtnHTML;

            if (result && result.success) {
                // Clear textarea.
                textarea.value = '';

                // Build new comment HTML.
                var newCommentHtml = renderLoadedComment({
                    id: result.commentid,
                    fullname: result.fullname,
                    userid: result.userid,
                    profileimageurl: result.profileimageurl || '',
                    content: result.content,
                    timecreated: result.timecreated
                }, strMap, 0);

                // Find or create the comments list.
                var contentEl = container.querySelector('.dims-comments-content');
                var commentsList = contentEl.querySelector('.dims-comments-list');

                if (!commentsList) {
                    // Remove "no comments" placeholder if present.
                    var noComments = contentEl.querySelector('.dims-no-comments');
                    if (noComments) {
                        noComments.remove();
                    }
                    // Create the list.
                    commentsList = document.createElement('div');
                    commentsList.className = 'dims-comments-list';
                    contentEl.appendChild(commentsList);
                }

                // Prepend new comment (newest first).
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = newCommentHtml;
                var newEl = tempDiv.firstElementChild;
                if (newEl) {
                    if (commentsList.firstChild) {
                        commentsList.insertBefore(newEl, commentsList.firstChild);
                    } else {
                        commentsList.appendChild(newEl);
                    }
                }
            } else if (result && result.error) {
                Notification.alert('Error', result.error);
            }
        }).catch(function (error) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = originalBtnHTML;
            Notification.exception(error);
        });
    }

    /**
     * Render assessment status section (rating + proficiency card).
     * Now rendered inside a tab pane, so no shadow card wrapper needed.
     *
     * @param {Object} ucs The user competency summary
     * @param {Object} strMap Language strings map
     * @return {string} HTML for the status section
     */
    function renderStatusSection(ucs, strMap) {
        var uc = ucs.usercompetency || ucs.usercompetencyplan;
        if (!uc) {
            return '';
        }

        var html = '<div class="dims-status-tab-content">';

        // 2-column grid.
        html += '<div class="dims-status-grid">';

        // Rating cell.
        html += '<div class="dims-status-cell">';
        html += '<p class="dims-status-label">' + escapeHtml(strMap.ratingLabel) + '</p>';
        if (uc.grade && uc.gradename) {
            if (uc.proficiency) {
                html += '<span class="dims-status-badge">';
                html += '<i class="fa fa-check-circle" aria-hidden="true"></i> ';
            } else {
                html += '<span class="dims-status-badge dims-status-badge-muted">';
            }
            html += escapeHtml(uc.gradename);
            html += '</span>';
        } else {
            html += '<span class="dims-status-value-muted">-</span>';
        }
        html += '</div>';

        // Proficiency cell.
        html += '<div class="dims-status-cell">';
        html += '<p class="dims-status-label">' + escapeHtml(strMap.proficiencyLabel) + '</p>';
        if (uc.proficiency) {
            html += '<div class="dims-status-value dims-status-success">';
            html += '<i class="fa fa-check-circle" aria-hidden="true"></i> ';
            html += escapeHtml(strMap.yesStr);
            html += '</div>';
        } else {
            html += '<div class="dims-status-value dims-status-pending">';
            html += '<i class="fa fa-clock-o" aria-hidden="true"></i> ';
            html += escapeHtml(strMap.noStr);
            html += '</div>';
        }
        html += '</div>';

        html += '</div>'; // End dims-status-grid.
        html += '</div>'; // End dims-status-tab-content.

        return html;
    }

    /**
     * Render description section with "Ver mais" truncation.
     * Now rendered inside a tab pane, no shadow card wrapper needed.
     *
     * @param {string} description The competency description HTML
     * @param {Object} strMap Language strings map
     * @return {string} HTML for the description section
     */
    function renderDescriptionSection(description, strMap) {
        var html = '<div class="dims-desc-tab-content">';

        // Description text (always start collapsed, JS will check if truncation is needed).
        html += '<div class="dims-desc-content dims-desc-collapsed">';
        html += description;
        html += '</div>';

        // Toggle button (always rendered, JS will hide if content fits).
        html += '<button type="button" class="dims-show-more" data-collapsed="true">';
        html += escapeHtml(strMap.showMore);
        html += ' <i class="fa fa-chevron-right" aria-hidden="true"></i>';
        html += '</button>';

        html += '</div>'; // End dims-desc-tab-content.

        return html;
    }

    /**
     * Attach event listeners for "Ver mais" toggle buttons.
     *
     * @param {HTMLElement} contentEl The content container element
     * @param {Object} strMap Language strings map
     */
    function attachShowMoreListeners(contentEl, strMap) {
        var toggleBtns = contentEl.querySelectorAll('.dims-show-more');

        toggleBtns.forEach(function (btn) {
            var descContent = btn.previousElementSibling;

            // Check if the content is actually truncated by comparing heights.
            if (descContent && descContent.scrollHeight <= descContent.clientHeight) {
                // Content fits without truncation - remove collapsed class and hide button.
                descContent.classList.remove('dims-desc-collapsed');
                btn.style.display = 'none';
                return;
            }

            btn.addEventListener('click', function () {
                var isCollapsed = this.getAttribute('data-collapsed') === 'true';

                if (isCollapsed) {
                    // Expand.
                    descContent.classList.remove('dims-desc-collapsed');
                    this.setAttribute('data-collapsed', 'false');
                    this.innerHTML = escapeHtml(strMap.showLess)
                        + ' <i class="fa fa-chevron-down" aria-hidden="true"></i>';
                } else {
                    // Collapse.
                    descContent.classList.add('dims-desc-collapsed');
                    this.setAttribute('data-collapsed', 'true');
                    this.innerHTML = escapeHtml(strMap.showMore)
                        + ' <i class="fa fa-chevron-right" aria-hidden="true"></i>';
                }
            });
        });
    }

    /**
     * Format a Unix timestamp using the Moodle strftimedaydate format.
     * Uses Intl.DateTimeFormat for localized month names.
     *
     * @param {number} timestamp Unix timestamp (seconds)
     * @param {string} formatStr The strftime format string (e.g. "%d %B %Y")
     * @return {string} Formatted date string
     */
    function formatTimestamp(timestamp, formatStr) {
        if (!timestamp) {
            return '';
        }
        var date = new Date(timestamp * 1000);
        var lang = document.documentElement.lang || 'en';
        lang = lang.replace('_', '-');

        try {
            var day = date.getDate();
            var monthLong = date.toLocaleDateString(lang, { month: 'long' });
            var monthShort = date.toLocaleDateString(lang, { month: 'short' });
            var year = date.getFullYear();
            var weekdayLong = date.toLocaleDateString(lang, { weekday: 'long' });
            var weekdayShort = date.toLocaleDateString(lang, { weekday: 'short' });

            return formatStr
                .replace('%A', weekdayLong)
                .replace('%a', weekdayShort)
                .replace('%d', day)
                .replace('%B', monthLong)
                .replace('%b', monthShort)
                .replace('%Y', year)
                .replace('%m', ('0' + (date.getMonth() + 1)).slice(-2));
        } catch (e) {
            return date.toLocaleDateString(lang);
        }
    }



    /**
     * Escape HTML special characters.
     *
     * @param {string} text The text to escape
     * @return {string} The escaped text
     */
    function escapeHtml(text) {
        if (!text) {
            return '';
        }
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Initialize accordion functionality.
     *
     * @param {Object} settings Display settings from admin config
     */
    function init(settings) {
        // Load display settings from parameters passed by PHP.
        if (settings && typeof settings === 'object') {
            displaySettings = Object.assign(displaySettings, settings);
        }

        const summaryContainer = document.querySelector('.dims-plan-summary');
        if (!summaryContainer) {
            return;
        }

        // Get plan ID from data attribute.
        const planId = parseInt(summaryContainer.dataset.planid, 10);

        const toggleButtons = document.querySelectorAll('.dims-accordion-toggle');

        toggleButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const expanded = this.getAttribute('aria-expanded') === 'true';
                const contentId = this.getAttribute('aria-controls');
                const content = document.getElementById(contentId);
                const accordionItem = this.closest('.dims-accordion-item');
                const competencyId = accordionItem ? parseInt(accordionItem.dataset.competencyId, 10) : null;

                if (!content) {
                    return;
                }

                // Toggle state.
                if (expanded) {
                    // Close accordion.
                    this.setAttribute('aria-expanded', 'false');
                    content.hidden = true;
                } else {
                    // Close all other accordion items first.
                    toggleButtons.forEach(function (otherBtn) {
                        if (otherBtn !== button) {
                            var otherId = otherBtn.getAttribute('aria-controls');
                            var otherContent = document.getElementById(otherId);
                            otherBtn.setAttribute('aria-expanded', 'false');
                            if (otherContent) {
                                otherContent.hidden = true;
                            }
                        }
                    });

                    // Open accordion.
                    this.setAttribute('aria-expanded', 'true');
                    content.hidden = false;

                    // Load competency summary via AJAX if not already loaded.
                    if (competencyId && planId) {
                        loadCompetencySummary(content, competencyId, planId);
                    }
                }
            });

            // Keyboard support - Enter and Space.
            button.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });

        // Initialize filter tabs functionality.
        initFilterTabs();
        initSearch();

        // Apply initial filter (show incomplete only by default).
        applyFilter();

        // Mark as initialized to enable CSS transitions (prevents flickering).
        const accordionContainer = document.querySelector('.dims-competency-accordion');
        if (accordionContainer) {
            accordionContainer.classList.add('dims-filter-initialized');
        }
    }

    /**
     * Normalize text for accent-insensitive comparison.
     * Strips diacritics and lowercases for matching.
     *
     * @param {string} str
     * @return {string}
     */
    function normalizeText(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    /**
     * Return the currently active tab filter value ('incomplete' or 'all').
     *
     * @return {string}
     */
    function getActiveFilter() {
        var active = document.querySelector('.dims-filter-tab.active');
        return active ? active.dataset.filter : 'incomplete';
    }

    /**
     * Return the current search query (normalized).
     *
     * @return {string}
     */
    function getSearchQuery() {
        var input = document.querySelector('.dims-search-input');
        return input ? normalizeText(input.value.trim()) : '';
    }

    /**
     * Initialize filter tabs click handlers.
     */
    function initFilterTabs() {
        const filterTabs = document.querySelectorAll('.dims-filter-tab');

        filterTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                // Update active state on tabs.
                filterTabs.forEach(function (t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');

                // Apply the filter (combined with search).
                applyFilter();
            });

            // Keyboard support.
            tab.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
    }

    /**
     * Initialize competency search input.
     */
    function initSearch() {
        var input = document.querySelector('.dims-search-input');
        var clearBtn = document.querySelector('.dims-search-clear');
        if (!input) {
            return;
        }

        var debounceTimer = null;

        input.addEventListener('input', function () {
            // Show/hide clear button.
            if (clearBtn) {
                clearBtn.style.display = input.value.length > 0 ? 'flex' : 'none';
            }
            // Debounce filtering (100ms).
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                applyFilter();
            }, 100);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                clearBtn.style.display = 'none';
                input.focus();
                applyFilter();
            });
        }
    }

    /**
     * Apply combined tab filter + search query to accordion items.
     */
    function applyFilter() {
        var filter = getActiveFilter();
        var query = getSearchQuery();
        var accordionItems = document.querySelectorAll('.dims-accordion-item');

        accordionItems.forEach(function (item) {
            var isCompleted = item.classList.contains('completed');

            // Tab filter.
            var passesTab = true;
            if (filter === 'incomplete' && isCompleted) {
                passesTab = false;
            }

            // Search filter.
            var passesSearch = true;
            if (query.length > 0) {
                var title = item.querySelector('.dims-accordion-title');
                if (title) {
                    passesSearch = normalizeText(title.textContent).indexOf(query) !== -1;
                }
            }

            if (passesTab && passesSearch) {
                item.style.display = '';
                item.classList.remove('dims-hidden');
            } else {
                item.style.display = 'none';
                item.classList.add('dims-hidden');
            }
        });
    }

    return {
        init: init
    };
});
