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
 * Accordion functionality for full plan overview with AJAX loading.
 *
 * @module     local_dimensions/accordion
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(
    ['core/ajax', 'core/templates', 'core/notification', 'core/str', 'core/modal', 'core/log'],
    function (Ajax, Templates, Notification, Str, Modal, Log) {
        'use strict';

        // Cache for loaded competency summaries to avoid reloading.
        const loadedCompetencies = new Set();

        // Display settings (loaded from page).
        let displaySettings = {
            showdescription: true,
            showtaxonomycard: false,
            showpath: false,
            showrelated: false,
            showrelatedlink: false,
            viewplanurl: '',
            showevidence: true,
            enableevidencesubmitbutton: false
        };

        /**
         * Load competency summary via AJAX.
         *
         * @param {HTMLElement} contentElement The accordion content element
         * @param {number} competencyId The competency ID
         * @param {number} planId The plan ID
         */
        function loadCompetencySummary(contentElement, competencyId, planId) {
            const loadingEl = contentElement.querySelector('.local-dimensions-competency-summary-loading');
            const contentEl = contentElement.querySelector('.local-dimensions-competency-summary-content');
            const errorEl = contentElement.querySelector('.local-dimensions-competency-summary-error');

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
            }])[0].then(function (response) {
                return JSON.parse(response);
            });

            // Use custom webservice when enrollment filter is configured, otherwise use core.
            let coursesMethodName = 'tool_lp_list_courses_using_competency';
            let coursesArgs = { id: competencyId };
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
                renderCompetencySummary(contentEl, summaryResponse, coursesResponse, planId);
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
         * @param {number} planId The plan ID (used for related competency links)
         * @return {Promise} Promise that resolves when rendering is complete
         */
        function renderCompetencySummary(contentEl, data, courses, planId) {
            if (!contentEl) {
                return Promise.resolve(null);
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
                { key: 'related_dimensions', component: 'local_dimensions' },
                { key: 'evidence_type_file', component: 'local_dimensions' },
                { key: 'evidence_type_manual', component: 'local_dimensions' },
                { key: 'evidence_type_activity', component: 'local_dimensions' },
                { key: 'evidence_type_coursegrade', component: 'local_dimensions' },
                { key: 'evidence_type_prior', component: 'local_dimensions' },
                { key: 'evidence_type_other', component: 'local_dimensions' },
                { key: 'evidence_by', component: 'local_dimensions' },
                { key: 'no_evidence', component: 'local_dimensions' },
                { key: 'access_course', component: 'local_dimensions' },
                { key: 'linked_courses', component: 'local_dimensions' },
                { key: 'assessment_status', component: 'local_dimensions' },
                { key: 'description_label', component: 'local_dimensions' },
                { key: 'taxonomycard_label', component: 'local_dimensions' },
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
                { key: 'evidence_open_link', component: 'local_dimensions' },
                { key: 'rules_tab', component: 'local_dimensions' },
                { key: 'rules_progress', component: 'local_dimensions' },
                { key: 'rules_total_competencies', component: 'local_dimensions' },
                { key: 'rules_required_tag', component: 'local_dimensions' },
                { key: 'rules_assessment_prefix', component: 'local_dimensions' },
                { key: 'rules_pts', component: 'local_dimensions' },
                { key: 'rules_no_points', component: 'local_dimensions' },
                { key: 'evidence_submit', component: 'local_dimensions' },
                { key: 'rules_todo', component: 'local_dimensions' },
                { key: 'rules_completed_count', component: 'local_dimensions' },
                { key: 'rules_info_title', component: 'local_dimensions' },
                { key: 'rules_missing_mandatory_notice', component: 'local_dimensions' },
                { key: 'rules_filter_label', component: 'local_dimensions' },
                { key: 'rules_filter_all', component: 'local_dimensions' },
                { key: 'rules_filter_required', component: 'local_dimensions' },
                { key: 'rules_sr_alert', component: 'local_dimensions' },
                { key: 'rules_sr_proficient', component: 'local_dimensions' },
                { key: 'rules_sr_inprogress', component: 'local_dimensions' },
                { key: 'rules_sr_todo', component: 'local_dimensions' },
                { key: 'rules_sr_progress', component: 'local_dimensions' }
            ]).then(function (strings) {
                const strMap = {
                    ratingLabel: strings[0],
                    proficientLabel: strings[1],
                    evidenceLabel: strings[2],
                    yesStr: strings[3],
                    noStr: strings[4],
                    pathLabel: strings[5],
                    inFramework: strings[6],
                    relatedDimensions: strings[7],
                    evidenceTypeFile: strings[8],
                    evidenceTypeManual: strings[9],
                    evidenceTypeActivity: strings[10],
                    evidenceTypeCoursegrade: strings[11],
                    evidenceTypePrior: strings[12],
                    evidenceTypeOther: strings[13],
                    evidenceBy: strings[14],
                    noEvidence: strings[15],
                    accessCourse: strings[16],
                    linkedCourses: strings[17],
                    assessmentStatus: strings[18],
                    descriptionLabel: strings[19],
                    taxonomyCardLabel: strings[20],
                    showMore: strings[21],
                    showLess: strings[22],
                    proficiencyLabel: strings[23],
                    accessLabel: strings[24],
                    dateFormat: strings[25],
                    sliderPrev: strings[26],
                    sliderNext: strings[27],
                    pathBreadcrumbLabel: strings[28],
                    evidenceDetails: strings[29],
                    evidenceNote: strings[30],
                    evidenceLink: strings[31],
                    evidenceGrade: strings[32],
                    evidenceAuthor: strings[33],
                    evidenceDate: strings[34],
                    evidenceViewDetails: strings[35],
                    evidenceOpenLink: strings[36],
                    rulesTab: strings[37],
                    rulesProgress: strings[38],
                    rulesTotalCompetencies: strings[39],
                    rulesRequiredTag: strings[40],
                    rulesAssessmentPrefix: strings[41],
                    rulesPts: strings[42],
                    rulesNoPoints: strings[43],
                    evidenceSubmit: strings[44],
                    rulesTodo: strings[45],
                    rulesCompletedCount: strings[46],
                    rulesInfoTitle: strings[47],
                    rulesMissingMandatoryNotice: strings[48],
                    rulesFilterLabel: strings[49],
                    rulesFilterAll: strings[50],
                    rulesFilterRequired: strings[51],
                    rulesSrAlert: strings[52],
                    rulesSrProficient: strings[53],
                    rulesSrInprogress: strings[54],
                    rulesSrTodo: strings[55],
                    rulesSrProgress: strings[56]
                };

                const summaryState = getSummaryState(data, courses);
                let html = '<div class="local-dimensions-competency-detail">';
                html += renderSummaryTabs(summaryState, strMap, planId);

                if (summaryState.visibleCourses.length > 0) {
                    html += renderCourseCardsScrollable(summaryState.visibleCourses, strMap);
                }

                html += '</div>';

                // Show the content.
                contentEl.innerHTML = html;
                contentEl.style.display = 'block';

                // Attach tab listeners.
                attachTabListeners(contentEl, strMap);

                // Attach "Ver mais" toggle listeners.
                attachShowMoreListeners(contentEl, strMap);



                // Initialize evidence slider(s) — pass evidence data, strings, and scale config for modal.
                // Competency-level scaleconfiguration is null when it inherits from the framework.
                // Fall back to the resolved scaleconfiguration on the competency tree data object.
                const scaleConfig = summaryState.comp?.scaleconfiguration
                    || summaryState.competencyData?.scaleconfiguration
                    || null;
                initSliders(contentEl, summaryState.ucs ? summaryState.ucs.evidence : [], strMap, scaleConfig);

                // Initialize course scroll navigation.
                initCourseScroll(contentEl);

                // If the Rules tab is currently active (first tab), trigger lazy load immediately.
                const activeRulesPane = contentEl.querySelector('.local-dimensions-tab-pane-rules.active');
                if (activeRulesPane) {
                    loadRulesTabIfNeeded(activeRulesPane, strMap);
                }

                return null;
            });
        }

        /**
         * Build the render state used by the summary tabs.
         *
         * @param {Object} data The competency summary payload
         * @param {Array} courses Course list
         * @return {Object} Normalized summary state
         */
        function getSummaryState(data, courses) {
            const ucs = data.usercompetencysummary;
            const competencyData = ucs ? ucs.competency : null;
            const comp = competencyData ? competencyData.competency : null;
            const visibleCourses = (courses || []).filter(function (course) {
                return course.visible == 1;
            });
            const primaryTaxonomy = getPrimaryTaxonomy(competencyData);
            const hasStatus = !!(ucs && (ucs.usercompetency || ucs.usercompetencyplan));
            const hasDesc = !!(comp && displaySettings.showdescription && comp.description);
            const hasTaxonomyCard = !!(displaySettings.showtaxonomycard && primaryTaxonomy?.term);
            const hasPath = !!(comp && displaySettings.showpath);
            const hasRelated = !!(
                comp && displaySettings.showrelated && competencyData?.relatedcompetencies
                && competencyData.relatedcompetencies.length > 0
            );
            const hasEvidence = !!(ucs && displaySettings.showevidence);
            const hasRules = !!(
                comp?.ruleoutcome && Number.parseInt(comp.ruleoutcome, 10) !== 0 && comp.ruletype
            );

            return {
                ucs: ucs,
                competencyData: competencyData,
                comp: comp,
                visibleCourses: visibleCourses,
                primaryTaxonomy: primaryTaxonomy,
                hasStatus: hasStatus,
                hasDesc: hasDesc,
                hasTaxonomyCard: hasTaxonomyCard,
                hasPath: hasPath,
                hasRelated: hasRelated,
                hasEvidence: hasEvidence,
                hasRules: hasRules
            };
        }

        /**
         * Return the visible tabs for the summary.
         *
         * @param {Object} summaryState Normalized summary state
         * @param {Object} strMap Language strings map
         * @return {Array} Visible tabs
         */
        function buildSummaryTabs(summaryState, strMap) {
            const tabs = [];

            if (summaryState.hasStatus) {
                tabs.push({ id: 'status', label: strMap.assessmentStatus, icon: 'fa-star' });
            }
            if (summaryState.hasDesc || summaryState.hasTaxonomyCard || summaryState.hasPath || summaryState.hasRelated) {
                tabs.push({ id: 'description', label: strMap.descriptionLabel, icon: 'fa-file-text-o' });
            }
            if (summaryState.hasEvidence) {
                tabs.push({ id: 'evidence', label: strMap.evidenceLabel, icon: 'fa-check-square-o' });
            }
            if (summaryState.hasRules) {
                tabs.push({ id: 'rules', label: strMap.rulesTab, icon: 'fa-gavel' });
            }

            return tabs;
        }

        /**
         * Render the full tabs wrapper for the summary.
         *
         * @param {Object} summaryState Normalized summary state
         * @param {Object} strMap Language strings map
         * @param {number} planId Plan ID
         * @return {string} HTML
         */
        function renderSummaryTabs(summaryState, strMap, planId) {
            const tabs = buildSummaryTabs(summaryState, strMap);

            if (tabs.length === 0 || !summaryState.comp) {
                return '';
            }

            let html = '<div class="local-dimensions-tabs-wrapper">';
            html += renderSummaryTabNavigation(tabs, summaryState.comp.id);
            html += renderSummaryTabPanes(summaryState, tabs, strMap, planId);
            html += '</div>';

            return html;
        }

        /**
         * Render the summary tab buttons.
         *
         * @param {Array} tabs Visible tabs
         * @param {number} competencyId Competency ID
         * @return {string} HTML
         */
        function renderSummaryTabNavigation(tabs, competencyId) {
            let html = '<div class="local-dimensions-tabs-nav" role="tablist">';

            tabs.forEach(function (tab, idx) {
                const isActive = idx === 0;
                html += '<button type="button" class="local-dimensions-tab-btn' + (isActive ? ' active' : '') + '"';
                html += ' role="tab"';
                html += ' id="local-dimensions-tab-' + tab.id + '-' + competencyId + '"';
                html += ' aria-selected="' + (isActive ? 'true' : 'false') + '"';
                html += ' aria-controls="local-dimensions-tabpane-' + tab.id + '-' + competencyId + '"';
                html += ' tabindex="' + (isActive ? '0' : '-1') + '"';
                html += ' data-tab="' + tab.id + '">';
                html += escapeHtml(tab.label);
                html += '</button>';
            });

            html += '</div>';
            return html;
        }

        /**
         * Render the summary tab panes.
         *
         * @param {Object} summaryState Normalized summary state
         * @param {Array} tabs Visible tabs
         * @param {Object} strMap Language strings map
         * @param {number} planId Plan ID
         * @return {string} HTML
         */
        function renderSummaryTabPanes(summaryState, tabs, strMap, planId) {
            let html = '<div class="local-dimensions-tabs-content">';

            if (summaryState.hasStatus) {
                html += renderStatusPane(summaryState, tabs, strMap);
            }
            if (summaryState.hasDesc || summaryState.hasTaxonomyCard || summaryState.hasPath || summaryState.hasRelated) {
                html += renderDescriptionPane(summaryState, tabs, strMap, planId);
            }
            if (summaryState.hasEvidence) {
                html += renderEvidencePane(summaryState, tabs, strMap);
            }
            if (summaryState.hasRules) {
                html += renderRulesPane(summaryState, tabs, strMap, planId);
            }

            html += '</div>';
            return html;
        }

        /**
         * Render the status tab pane.
         *
         * @param {Object} summaryState Normalized summary state
         * @param {Array} tabs Visible tabs
         * @param {Object} strMap Language strings map
         * @return {string} HTML
         */
        function renderStatusPane(summaryState, tabs, strMap) {
            const isFirst = tabs[0].id === 'status';
            let html = '<div class="local-dimensions-tab-pane local-dimensions-tab-pane-status' + (isFirst ? ' active' : '') + '"';
            html += ' id="local-dimensions-tabpane-status-' + summaryState.comp.id + '" data-tab="status"';
            html += ' role="tabpanel" aria-labelledby="local-dimensions-tab-status-' + summaryState.comp.id + '">';
            html += renderStatusSection(summaryState.ucs, strMap);
            html += '</div>';
            return html;
        }

        /**
         * Render the description tab pane.
         *
         * @param {Object} summaryState Normalized summary state
         * @param {Array} tabs Visible tabs
         * @param {Object} strMap Language strings map
         * @param {number} planId Plan ID
         * @return {string} HTML
         */
        function renderDescriptionPane(summaryState, tabs, strMap, planId) {
            const isFirst = tabs[0].id === 'description';
            let html = '<div class="local-dimensions-tab-pane local-dimensions-tab-pane-description' + (isFirst ? ' active' : '') + '"';
            html += ' id="local-dimensions-tabpane-description-' + summaryState.comp.id + '" data-tab="description"';
            html += ' role="tabpanel" aria-labelledby="local-dimensions-tab-description-' + summaryState.comp.id + '">';
            html += '<div class="local-dimensions-desc-layout' + (summaryState.hasTaxonomyCard ? ' local-dimensions-desc-layout-has-taxonomy' : '') + '">';
            html += '<div class="local-dimensions-desc-main">';

            if (summaryState.hasDesc) {
                html += renderDescriptionSection(summaryState.comp.description, strMap);
            }
            if (summaryState.hasPath) {
                html += renderCompetencyPath(summaryState.competencyData, strMap);
            }
            if (summaryState.hasRelated) {
                html += renderRelatedCompetencies(summaryState.competencyData, strMap, planId);
            }

            html += '</div>';

            if (summaryState.hasTaxonomyCard) {
                html += renderTaxonomyCard(summaryState.primaryTaxonomy, strMap);
            }

            html += '</div>';
            html += '</div>';
            return html;
        }

        /**
         * Render the evidence tab pane.
         *
         * @param {Object} summaryState Normalized summary state
         * @param {Array} tabs Visible tabs
         * @param {Object} strMap Language strings map
         * @return {string} HTML
         */
        function renderEvidencePane(summaryState, tabs, strMap) {
            const isFirst = tabs[0].id === 'evidence';
            let html = '<div class="local-dimensions-tab-pane local-dimensions-tab-pane-evidence' + (isFirst ? ' active' : '') + '"';
            html += ' id="local-dimensions-tabpane-evidence-' + summaryState.comp.id + '" data-tab="evidence"';
            html += ' role="tabpanel" aria-labelledby="local-dimensions-tab-evidence-' + summaryState.comp.id + '">';
            html += renderEvidenceSlider(summaryState.ucs.evidence, strMap);

            // Submit evidence button (if enabled by admin + user has capability).
            if (displaySettings.enableevidencesubmitbutton) {
                const uc = summaryState.ucs.usercompetency || summaryState.ucs.usercompetencyplan;
                if (uc && uc.userid) {
                    const evidenceUrl = M.cfg.wwwroot + '/admin/tool/lp/user_evidence_list.php?userid=' + uc.userid;
                    html += '<div class="local-dimensions-evidence-submit-wrapper">';
                    html += '<a href="' + escapeHtml(evidenceUrl) + '" class="local-dimensions-evidence-submit-btn">';
                    html += escapeHtml(strMap.evidenceSubmit);
                    html += '</a>';
                    html += '</div>';
                }
            }

            html += '</div>';
            return html;
        }

        /**
         * Render the lazy-loaded rules tab pane.
         *
         * @param {Object} summaryState Normalized summary state
         * @param {Array} tabs Visible tabs
         * @param {Object} strMap Language strings map
         * @param {number} planId Plan ID
         * @return {string} HTML
         */
        function renderRulesPane(summaryState, tabs, strMap, planId) {
            const isFirst = tabs[0].id === 'rules';
            let html = '<div class="local-dimensions-tab-pane local-dimensions-tab-pane-rules' + (isFirst ? ' active' : '') + '"';
            html += ' id="local-dimensions-tabpane-rules-' + summaryState.comp.id + '" data-tab="rules"';
            html += ' role="tabpanel" aria-labelledby="local-dimensions-tab-rules-' + summaryState.comp.id + '"';
            html += ' data-competency-id="' + summaryState.comp.id + '"';
            html += ' data-plan-id="' + planId + '">';
            html += '<div class="local-dimensions-rules-loading" role="status" aria-live="polite">';
            html += '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>';
            html += '<span class="sr-only">' + escapeHtml(strMap.rulesTab) + '</span>';
            html += '</div>';
            html += '<div class="local-dimensions-rules-content" style="display:none;"></div>';
            html += '</div>';
            return html;
        }

        /**
         * Attach tab click listeners for switching between panes.
         *
         * @param {HTMLElement} contentEl The content container element
         * @param {Object} strMap Language strings map
         */
        function attachTabListeners(contentEl, strMap) {
            const tabBtns = contentEl.querySelectorAll('.local-dimensions-tab-btn');

            /**
             * Activate a specific tab and its corresponding pane.
             *
             * @param {HTMLElement} btn The tab button to activate
             * @param {boolean} setFocus Whether to move focus to the tab
             */
            function activateTab(btn, setFocus) {
                const tabId = btn.dataset.tab;
                const wrapper = btn.closest('.local-dimensions-tabs-wrapper');
                if (!wrapper) {
                    return;
                }

                // Deactivate all tabs in this wrapper.
                wrapper.querySelectorAll('.local-dimensions-tab-btn').forEach(function (b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-selected', 'false');
                    b.setAttribute('tabindex', '-1');
                });

                // Deactivate all panes.
                wrapper.querySelectorAll('.local-dimensions-tab-pane').forEach(function (p) {
                    p.classList.remove('active');
                });

                // Activate clicked tab.
                btn.classList.add('active');
                btn.setAttribute('aria-selected', 'true');
                btn.setAttribute('tabindex', '0');

                if (setFocus) {
                    btn.focus();
                }

                // Activate corresponding pane.
                const pane = wrapper.querySelector('.local-dimensions-tab-pane[data-tab="' + tabId + '"]');
                if (pane) {
                    pane.classList.add('active');
                    refreshScrollableControls(pane);

                    // Lazy-load Rules tab content on first activation.
                    if (tabId === 'rules' && strMap) {
                        loadRulesTabIfNeeded(pane, strMap);
                    }
                }
            }

            tabBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    activateTab(this, false);
                });

                // Keyboard navigation: Arrow Left/Right, Home, End (ARIA Authoring Practices).
                btn.addEventListener('keydown', function (e) {
                    const wrapper = this.closest('.local-dimensions-tabs-wrapper');
                    if (!wrapper) {
                        return;
                    }
                    const tabs = Array.from(wrapper.querySelectorAll('.local-dimensions-tab-btn'));
                    const idx = tabs.indexOf(this);
                    let newIdx = -1;

                    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                        newIdx = (idx + 1) % tabs.length;
                    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                        newIdx = (idx - 1 + tabs.length) % tabs.length;
                    } else if (e.key === 'Home') {
                        newIdx = 0;
                    } else if (e.key === 'End') {
                        newIdx = tabs.length - 1;
                    }

                    if (newIdx >= 0) {
                        e.preventDefault();
                        activateTab(tabs[newIdx], true);
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

            const refresh = function () {
                container.querySelectorAll('.local-dimensions-ev-slider-wrapper, .local-dimensions-courses-scroll-wrapper').forEach(function (wrapper) {
                    if (typeof wrapper._dimsUpdateArrows === 'function') {
                        wrapper._dimsUpdateArrows();
                    }
                });
            };

            if (globalThis.requestAnimationFrame) {
                globalThis.requestAnimationFrame(refresh);
            } else {
                refresh();
            }

            // Secondary pass to catch late layout updates (fonts/images).
            setTimeout(refresh, 120);
        }

        // Cache for loaded Rules tab panes to avoid re-fetching.
        const loadedRulesPanes = new Set();

        /**
         * Load Rules tab data via AJAX if not already loaded.
         *
         * @param {HTMLElement} pane The rules tab pane element
         * @param {Object} strMap Language strings map
         */
        function loadRulesTabIfNeeded(pane, strMap) {
            const competencyId = Number.parseInt(pane.dataset.competencyId, 10);
            const cacheKey = competencyId + '-' + Number.parseInt(pane.dataset.planId, 10);
            if (loadedRulesPanes.has(cacheKey)) {
                return;
            }
            loadedRulesPanes.add(cacheKey);

            const planId = Number.parseInt(pane.dataset.planId, 10);
            const loadingEl = pane.querySelector('.local-dimensions-rules-loading');
            const contentEl = pane.querySelector('.local-dimensions-rules-content');

            if (!competencyId || !planId) {
                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }
                return;
            }

            Ajax.call([{
                methodname: 'local_dimensions_get_competency_rule_data',
                args: {
                    competencyid: competencyId,
                    planid: planId
                }
            }])[0].then(function (response) {
                const data = JSON.parse(response);

                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }
                if (contentEl) {
                    contentEl.innerHTML = renderRulesSection(data, strMap, planId);
                    contentEl.style.display = 'block';
                    initRulesFilters(contentEl);
                }
            }).catch(function (error) {
                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }
                loadedRulesPanes.delete(cacheKey);
                Notification.exception(error);
            });
        }

        /**
         * Render the full Rules tab content.
         *
         * @param {Object} data The rule data from the webservice
         * @param {Object} strMap Language strings map
         * @param {number} planId The plan ID for building child links
         * @return {string} HTML for the rules section
         */
        function renderRulesSection(data, strMap, planId) {
            if (!data?.hasrule) {
                return '';
            }

            const isPoints = data.ruletype === 'points';
            const hasMissingMandatory = !!data.hasmissingmandatory;
            const totalCount = Number.parseInt(data.childcount, 10) || 0;
            const mandatoryCount = Number.parseInt(data.mandatorycount, 10) || 0;
            let html = '<div class="local-dimensions-rules-section">';

            html += renderRuleInfoBox(data, strMap);

            // === Progress header ===
            html += '<div class="local-dimensions-rules-progress-header">';
            html += '<span class="local-dimensions-rules-progress-label-wrap">';
            html += '<span class="local-dimensions-rules-progress-label">' + escapeHtml(strMap.rulesProgress) + '</span>';
            if (hasMissingMandatory) {
                html += '<span class="local-dimensions-rules-progress-alert" aria-label="' + escapeHtml(strMap.rulesSrAlert) + '">';
                html += '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i>';
                html += '</span>';
            }
            html += '</span>';
            if (isPoints) {
                const earnedClass = data.earnedpoints > 0 ? ' local-dimensions-rules-earned-highlight' : '';
                html += '<span class="local-dimensions-rules-progress-score">';
                html += '<span class="local-dimensions-rules-earned' + earnedClass + '">' + data.earnedpoints + '</span>';
                html += ' / ' + data.totalrequired + ' pts';
                html += '</span>';
            } else {
                html += '<span class="local-dimensions-rules-progress-score">';
                html += '<span class="local-dimensions-rules-earned">' + data.earnedpoints + '</span>';
                html += ' / ' + data.totalrequired;
                html += '</span>';
            }
            html += '</div>';

            // === Progress bar ===
            const pct = data.totalrequired > 0
                ? Math.min(100, Math.round((data.earnedpoints / data.totalrequired) * 100))
                : 0;
            const srProgressText = strMap.rulesSrProgress
                .replace('{$a->earned}', data.earnedpoints)
                .replace('{$a->total}', data.totalrequired);
            html += '<div class="local-dimensions-rules-progress-bar">';
            html += '<div class="local-dimensions-rules-progress-track" role="progressbar"';
            html += ' aria-valuenow="' + data.earnedpoints + '"';
            html += ' aria-valuemin="0"';
            html += ' aria-valuemax="' + data.totalrequired + '"';
            html += ' aria-label="' + escapeHtml(srProgressText) + '">';
            html += '<div class="local-dimensions-rules-progress-fill' +
                (hasMissingMandatory ? ' local-dimensions-rules-progress-fill-striped progress-bar-striped' : '') +
                '" style="width: ' + pct + '%;"></div>';
            html += '</div>';
            html += '</div>';

            // === Progress context ===
            const countText = strMap.rulesTotalCompetencies.replace('{$a}', data.childcount || 0);
            html += '<div class="local-dimensions-rules-progress-context' +
                (hasMissingMandatory ? ' local-dimensions-rules-progress-context-alert' : ' text-muted') + '">';
            html += escapeHtml(hasMissingMandatory ? strMap.rulesMissingMandatoryNotice : countText);
            html += '</div>';

            if (mandatoryCount > 0) {
                html += renderRulesFilterTabs(strMap, totalCount, mandatoryCount);
            }

            // === Children list ===
            // Children list as accessible list.
            if (data.children && data.children.length > 0) {
                html += '<ul class="local-dimensions-rules-child-list" role="list">';
                data.children.forEach(function (child) {
                    html += renderRulesChild(child, strMap, planId, isPoints);
                });
                html += '</ul>';
            }

            html += '</div>'; // End local-dimensions-rules-section.
            return html;
        }

        /**
         * Render a single child competency card in the Rules tab.
         *
         * @param {Object} child The child competency data
         * @param {Object} strMap Language strings map
         * @param {number} planId The plan ID
         * @param {boolean} isPoints Whether this is a points-based rule
         * @return {string} HTML for the child card
         */
        function renderRulesChild(child, strMap, planId, isPoints) {
            let cardClasses = 'local-dimensions-rules-child-card';
            if (child.required) {
                cardClasses += ' local-dimensions-rules-child-card-required';
            }
            let html = '<li class="' + cardClasses + '" data-required="' + (child.required ? 'true' : 'false') + '">';

            // Status icon with sr-only label.
            html += '<div class="local-dimensions-rules-child-icon-wrapper">';
            const rulesIconUrls = {
                proficient: M.util.image_url('status/rules-proficient', 'local_dimensions'),
                inprogress: M.util.image_url('status/rules-inprogress', 'local_dimensions'),
                todo: M.util.image_url('status/rules-todo', 'local_dimensions')
            };
            if (child.isproficient) {
                html += '<div class="local-dimensions-rules-child-icon local-dimensions-rules-icon-proficient">';
                html += '<img class="local-dimensions-rules-child-icon-image" src="' + escapeHtml(rulesIconUrls.proficient || '') + '" alt="" aria-hidden="true">';
                html += '<span class="sr-only">' + escapeHtml(strMap.rulesSrProficient) + '</span>';
                html += '</div>';
            } else if (child.hasgrade) {
                html += '<div class="local-dimensions-rules-child-icon local-dimensions-rules-icon-inprogress">';
                html += '<img class="local-dimensions-rules-child-icon-image" src="' + escapeHtml(rulesIconUrls.inprogress || '') + '" alt="" aria-hidden="true">';
                html += '<span class="sr-only">' + escapeHtml(strMap.rulesSrInprogress) + '</span>';
                html += '</div>';
            } else {
                html += '<div class="local-dimensions-rules-child-icon local-dimensions-rules-icon-todo">';
                html += '<img class="local-dimensions-rules-child-icon-image" src="' + escapeHtml(rulesIconUrls.todo || '') + '" alt="" aria-hidden="true">';
                html += '<span class="sr-only">' + escapeHtml(strMap.rulesSrTodo) + '</span>';
                html += '</div>';
            }
            html += '</div>';

            // Content.
            html += '<div class="local-dimensions-rules-child-body">';
            const childUrl = M.cfg.wwwroot + '/local/dimensions/view-plan.php?id=' + planId + '&competencyid=' + child.id;
            html += '<a href="' + escapeHtml(childUrl) + '" class="local-dimensions-rules-child-name">';
            html += escapeHtml(child.shortname);
            html += '</a>';

            // Required tag.
            if (child.required) {
                html += ' <span class="local-dimensions-rules-required-tag">' + escapeHtml(strMap.rulesRequiredTag) + '</span>';
            }

            // Assessment line.
            html += '<div class="local-dimensions-rules-child-assessment text-muted">';
            html += escapeHtml(strMap.rulesAssessmentPrefix) + ' ';
            if (child.hasgrade) {
                html += escapeHtml(child.gradename);
            } else {
                html += escapeHtml(strMap.rulesTodo);
            }
            html += '</div>';
            html += '</div>';

            // Points (only for points-based rules).
            if (isPoints) {
                html += '<div class="local-dimensions-rules-child-points">';
                if (child.isproficient) {
                    html += '<span class="local-dimensions-rules-points-value">' + child.points + '</span>';
                } else {
                    html += '<span class="local-dimensions-rules-points-value local-dimensions-rules-points-pending">' + child.points + '</span>';
                }
                html += ' <span class="local-dimensions-rules-points-unit">' + escapeHtml(strMap.rulesPts) + '</span>';
                html += '</div>';
            }

            html += '</li>'; // End local-dimensions-rules-child-card.
            return html;
        }

        /**
         * Render the rule description info box.
         *
         * @param {Object} data The rule data
         * @param {Object} strMap Language strings map
         * @return {string} HTML for the info box
         */
        function renderRuleInfoBox(data, strMap) {
            const ruleText = data.outcometext || '';

            if (!ruleText) {
                return '';
            }

            let html = '<div class="local-dimensions-rules-info-box" role="note">';
            html += '<div class="local-dimensions-rules-info-icon">';
            html += '<i class="fa fa-info-circle" aria-hidden="true"></i>';
            html += '</div>';
            html += '<div class="local-dimensions-rules-info-text">';
            html += '<div class="local-dimensions-rules-info-title">' + escapeHtml(strMap.rulesInfoTitle) + '</div>';
            html += '<div class="local-dimensions-rules-info-description">' + escapeHtml(ruleText) + '</div>';
            if (data.hasrequired && data.requiredwarningtext) {
                html += '<div class="local-dimensions-rules-info-note"><strong>' + escapeHtml(data.requiredwarningtext) + '</strong></div>';
            }
            html += '</div>';
            html += '</div>';

            return html;
        }

        /**
         * Render local pills to filter rule items.
         *
         * @param {Object} strMap Language strings map
         * @param {number} totalCount Total item count
         * @param {number} mandatoryCount Required item count
         * @return {string} HTML for filter tabs
         */
        function renderRulesFilterTabs(strMap, totalCount, mandatoryCount) {
            let html = '<div class="local-dimensions-rules-filter-wrapper">';
            html += '<div class="local-dimensions-rules-filter-tabs local-dimensions-filter-tabs" role="tablist"';
            html += ' aria-label="' + escapeHtml(strMap.rulesFilterLabel) + '">';
            html += '<button type="button" class="local-dimensions-rules-filter-tab local-dimensions-filter-tab active"';
            html += ' data-filter="all" role="tab" aria-selected="true">';
            html += escapeHtml(strMap.rulesFilterAll);
            html += '<span class="local-dimensions-filter-count">' + totalCount + '</span>';
            html += '</button>';
            html += '<button type="button" class="local-dimensions-rules-filter-tab local-dimensions-filter-tab"';
            html += ' data-filter="required" role="tab" aria-selected="false">';
            html += escapeHtml(strMap.rulesFilterRequired);
            html += '<span class="local-dimensions-filter-count">' + mandatoryCount + '</span>';
            html += '</button>';
            html += '</div>';
            html += '</div>';

            return html;
        }

        /**
         * Attach local filter listeners to a loaded Rules pane.
         *
         * @param {HTMLElement} container Rules pane content container
         */
        function initRulesFilters(container) {
            if (!container) {
                return;
            }

            container.querySelectorAll('.local-dimensions-rules-filter-tabs').forEach(function (tablist) {
                const buttons = Array.from(tablist.querySelectorAll('.local-dimensions-rules-filter-tab'));
                const section = tablist.closest('.local-dimensions-rules-section');
                if (!section || buttons.length === 0) {
                    return;
                }

                const cards = Array.from(section.querySelectorAll('.local-dimensions-rules-child-card'));

                const applyFilter = function (filter, focusButton) {
                    buttons.forEach(function (button) {
                        const isActive = button.dataset.filter === filter;
                        button.classList.toggle('active', isActive);
                        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        if (focusButton && isActive) {
                            button.focus();
                        }
                    });

                    cards.forEach(function (card) {
                        const showCard = filter === 'all' || card.dataset.required === 'true';
                        card.hidden = !showCard;
                    });
                };

                buttons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        applyFilter(this.dataset.filter, false);
                    });

                    button.addEventListener('keydown', function (e) {
                        const index = buttons.indexOf(this);
                        let nextIndex = -1;

                        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                            nextIndex = (index + 1) % buttons.length;
                        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                            nextIndex = (index - 1 + buttons.length) % buttons.length;
                        } else if (e.key === 'Home') {
                            nextIndex = 0;
                        } else if (e.key === 'End') {
                            nextIndex = buttons.length - 1;
                        }

                        if (nextIndex >= 0) {
                            e.preventDefault();
                            applyFilter(buttons[nextIndex].dataset.filter, true);
                        }
                    });
                });

                applyFilter('all', false);
            });
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

            html += '<div class="local-dimensions-ev-slider-wrapper" data-evidence-count="' + evidence.length + '">';

            // Slider track.
            html += '<div class="local-dimensions-ev-slider-track">';

            evidence.forEach(function (ev, index) {
                const typeInfo = getEvidenceTypeInfo(ev, strMap);
                const hasExtraDetails = ev.note || ev.url || (ev.grade && ev.gradename && ev.gradename !== '-');

                html += '<div class="local-dimensions-ev-card" data-evidence-index="' + index + '">';

                // Icon.
                html += '<div class="local-dimensions-ev-icon ' + typeInfo.colorClass + '">';
                html += '<i class="fa ' + typeInfo.icon + '" aria-hidden="true"></i>';
                html += '</div>';

                // Content.
                html += '<div class="local-dimensions-ev-content">';
                html += '<h3 class="local-dimensions-ev-title">' + escapeHtml(typeInfo.label) + '</h3>';

                if (ev.description) {
                    html += '<p class="local-dimensions-ev-desc">' + ev.description + '</p>';
                }

                // Author + date (hidden for manual override evidence — details available in modal).
                const isManualOverride = typeInfo.colorClass === 'local-dimensions-evidence-manual';
                if (!isManualOverride && ev.usermodified && ev.actionuser) {
                    const authorName = escapeHtml(ev.actionuser.fullname || '');
                    const authorProfileUrl = M.cfg.wwwroot + '/user/profile.php?id=' + ev.usermodified;
                    html += '<div class="local-dimensions-ev-meta">';
                    html += '<i class="fa fa-user" aria-hidden="true"></i> ';
                    html += '<a href="' + escapeHtml(authorProfileUrl) + '" target="_blank">';
                    html += authorName + '</a>';
                    if (ev.timecreated) {
                        html += ' <span class="local-dimensions-ev-meta-sep">&middot;</span> ';
                        html += '<span>' + formatTimestamp(ev.timecreated, strMap.dateFormat) + '</span>';
                    }
                    html += '</div>';
                }

                // Detail button — shown when extra info is available.
                if (hasExtraDetails) {
                    html += '<button type="button" class="local-dimensions-ev-detail-btn" data-evidence-index="' + index + '"';
                    html += ' aria-label="' + escapeHtml(strMap.evidenceViewDetails) + ': ' + escapeHtml(typeInfo.label) + '">';
                    html += '<i class="fa fa-expand" aria-hidden="true"></i> ';
                    html += '<span>' + escapeHtml(strMap.evidenceViewDetails) + '</span>';
                    html += '</button>';
                }

                html += '</div>'; // End local-dimensions-ev-content.
                html += '</div>'; // End local-dimensions-ev-card.
            });

            html += '</div>'; // End local-dimensions-ev-slider-track.

            // Controls block (bottom-right).
            html += '<div class="local-dimensions-ev-slider-controls" role="group" aria-label="' + escapeHtml(strMap.evidenceLabel) + '">';
            html += '<button type="button" class="local-dimensions-ev-slider-btn local-dimensions-ev-slider-prev disabled"';
            html += ' aria-label="' + escapeHtml(strMap.sliderPrev) + '">';
            html += '<i class="fa fa-chevron-left" aria-hidden="true"></i>';
            html += '</button>';
            html += '<button type="button" class="local-dimensions-ev-slider-btn local-dimensions-ev-slider-next"';
            html += ' aria-label="' + escapeHtml(strMap.sliderNext) + '">';
            html += '<i class="fa fa-chevron-right" aria-hidden="true"></i>';
            html += '</button>';
            html += '</div>'; // End local-dimensions-ev-slider-controls.

            html += '</div>'; // End local-dimensions-ev-slider-wrapper.

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
                const config = JSON.parse(scaleConfig);
                if (!Array.isArray(config)) {
                    return false;
                }
                // gradeValue is 1-based, array is 0-based.
                const index = Number.parseInt(gradeValue, 10) - 1;
                if (index >= 0 && index < config.length) {
                    return !!(config[index].proficient && Number.parseInt(config[index].proficient, 10) === 1);
                }
            } catch (e) {
                Log.warn('[local_dimensions] Invalid scale configuration JSON.');
                return false;
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
            const typeInfo = getEvidenceTypeInfo(ev, strMap);
            const hasNote = !!ev.note?.trim();
            const hasUrl = !!ev.url;
            const hasGrade = !!(ev.grade && ev.gradename && ev.gradename !== '-');
            const hasActionUser = !!ev.actionuser?.fullname;

            // Determine if the grade value is considered proficient using the scale configuration.
            let gradeProficient = false;
            if (hasGrade && scaleConfig) {
                gradeProficient = isGradeProficient(ev.grade, scaleConfig);
            }

            const context = {
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
                // Prefer server-formatted userdate (already localized by Moodle);
                // fall back to client-side formatting when absent.
                datestring: ev.userdate || formatTimestamp(ev.timecreated, strMap.dateFormat),
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
         * Return whether a horizontal track should expose scroll controls.
         *
         * @param {number} itemCount Number of visible cards in the track
         * @return {boolean}
         */
        function shouldShowScrollableControls(itemCount) {
            const isMobile = !!globalThis.matchMedia?.('(max-width: 575.98px)')?.matches;
            if (itemCount <= 1) {
                return false;
            }
            return itemCount > 2 || isMobile;
        }

        /**
         * Get the scroll offset of a card relative to its track.
         *
         * @param {HTMLElement} track Scroll track element
         * @param {HTMLElement} card Card element
         * @return {number}
         */
        function getTrackCardOffset(track, card) {
            const trackRect = track.getBoundingClientRect();
            const cardRect = card.getBoundingClientRect();
            return (cardRect.left - trackRect.left) + track.scrollLeft;
        }

        /**
         * Return all card offsets for a scroll track.
         *
         * @param {HTMLElement} track Scroll track element
         * @param {string} cardSelector Selector for cards inside the track
         * @return {number[]}
         */
        function getTrackCardOffsets(track, cardSelector) {
            return Array.prototype.map.call(track.querySelectorAll(cardSelector), function (card) {
                return getTrackCardOffset(track, card);
            });
        }

        /**
         * Cubic ease-in-out timing function.
         *
         * @param {number} progress Value between 0 and 1
         * @return {number}
         */
        function easeInOutCubic(progress) {
            if (progress < 0.5) {
                return 4 * progress * progress * progress;
            }
            return 1 - Math.pow(-2 * progress + 2, 3) / 2;
        }

        /**
         * Animate a track to a target scroll position.
         *
         * @param {HTMLElement} track Scroll track element
         * @param {number} targetLeft Target scrollLeft value
         * @param {Function} onComplete Callback after scroll settles
         */
        function animateTrackScroll(track, targetLeft, onComplete) {
            if (globalThis.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches) {
                track.classList.remove('local-dimensions-animating');
                track.scrollLeft = targetLeft;
                onComplete();
                return;
            }

            const startLeft = track.scrollLeft;
            const distance = targetLeft - startLeft;
            if (Math.abs(distance) < 1) {
                track.classList.remove('local-dimensions-animating');
                track.scrollLeft = targetLeft;
                onComplete();
                return;
            }

            track.classList.add('local-dimensions-animating');

            if (track._dimsAnimFrame && globalThis.cancelAnimationFrame) {
                globalThis.cancelAnimationFrame(track._dimsAnimFrame);
            }

            const duration = Math.min(520, Math.max(300, Math.abs(distance) * 1.2));
            let startedAt = null;

            const step = function (timestamp) {
                if (startedAt === null) {
                    startedAt = timestamp;
                }
                const elapsed = timestamp - startedAt;
                const progress = Math.min(1, elapsed / duration);
                const eased = easeInOutCubic(progress);
                track.scrollLeft = startLeft + (distance * eased);

                if (progress < 1) {
                    track._dimsAnimFrame = globalThis.requestAnimationFrame(step);
                    return;
                }

                track.scrollLeft = targetLeft;
                track._dimsAnimFrame = null;
                if (globalThis.requestAnimationFrame) {
                    globalThis.requestAnimationFrame(function () {
                        track.classList.remove('local-dimensions-animating');
                    });
                } else {
                    track.classList.remove('local-dimensions-animating');
                }
                onComplete();
            };

            if (globalThis.requestAnimationFrame) {
                track._dimsAnimFrame = globalThis.requestAnimationFrame(step);
            } else {
                track.scrollLeft = targetLeft;
                track.classList.remove('local-dimensions-animating');
                onComplete();
            }
        }

        /**
         * Scroll one card into view when needed.
         *
         * @param {HTMLElement} track Scroll track element
         * @param {HTMLElement} card Card element
         * @param {number} edgeThreshold Edge tolerance in pixels
         * @param {Function} onComplete Callback after scrolling
         */
        function scrollTrackCardIntoView(track, card, edgeThreshold, onComplete) {
            const cardStart = getTrackCardOffset(track, card);
            const cardEnd = cardStart + card.offsetWidth;
            const viewStart = track.scrollLeft;
            const viewEnd = viewStart + track.clientWidth;

            if (cardStart >= viewStart + edgeThreshold && cardEnd <= viewEnd - edgeThreshold) {
                return;
            }

            const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
            const targetLeft = Math.min(maxScroll, Math.max(0, cardStart));
            animateTrackScroll(track, targetLeft, onComplete);
        }

        /**
         * Scroll to the next or previous card in a track.
         *
         * @param {HTMLElement} track Scroll track element
         * @param {string} cardSelector Selector for cards inside the track
         * @param {number} edgeThreshold Edge tolerance in pixels
         * @param {number} direction Positive for next, negative for previous
         * @param {Function} onComplete Callback after scrolling
         */
        function scrollToTrackAdjacentCard(track, cardSelector, edgeThreshold, direction, onComplete) {
            const offsets = getTrackCardOffsets(track, cardSelector);
            if (!offsets.length) {
                return;
            }

            const current = track.scrollLeft;
            let target = current;

            if (direction > 0) {
                for (const offset of offsets) {
                    if (offset > current + edgeThreshold) {
                        target = offset;
                        break;
                    }
                }
                if (target === current) {
                    target = offsets.at(-1);
                }
            } else {
                target = 0;
                for (let index = offsets.length - 1; index >= 0; index--) {
                    if (offsets[index] < current - edgeThreshold) {
                        target = offsets[index];
                        break;
                    }
                }
            }

            const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
            const targetLeft = Math.min(maxScroll, Math.max(0, target));
            animateTrackScroll(track, targetLeft, onComplete);
        }

        /**
         * Update scroll arrows and wrapper state for a horizontal track.
         *
         * @param {HTMLElement} wrapper Scroll wrapper
         * @param {HTMLElement} track Scroll track
         * @param {HTMLElement|null} prevBtn Previous button
         * @param {HTMLElement|null} nextBtn Next button
         * @param {number} edgeThreshold Edge tolerance in pixels
         * @param {boolean} showControls Whether controls should be shown
         */
        function updateScrollableArrows(wrapper, track, prevBtn, nextBtn, edgeThreshold, showControls) {
            const scrollLeft = track.scrollLeft;
            const maxScroll = track.scrollWidth - track.clientWidth;

            if (!showControls) {
                wrapper.classList.add('local-dimensions-controls-hidden');
                if (prevBtn) {
                    prevBtn.style.display = 'none';
                }
                if (nextBtn) {
                    nextBtn.style.display = 'none';
                }
                return;
            }

            wrapper.classList.remove('local-dimensions-controls-hidden');

            const atStart = scrollLeft <= edgeThreshold;
            const atEnd = scrollLeft >= maxScroll - edgeThreshold;
            const fits = maxScroll <= edgeThreshold;

            if (prevBtn) {
                prevBtn.style.display = '';
                prevBtn.classList.toggle('disabled', fits || atStart);
            }

            if (nextBtn) {
                nextBtn.style.display = '';
                nextBtn.classList.toggle('disabled', fits || atEnd);
            }
        }

        /**
         * Initialize a reusable horizontal scroll track.
         *
         * @param {Object} config Track configuration
         */
        function initScrollableTrack(config) {
            const wrapper = config.wrapper;
            const track = config.track;
            const prevBtn = config.prevBtn;
            const nextBtn = config.nextBtn;
            const cardSelector = config.cardSelector;
            const itemCount = config.itemCount;
            const edgeThreshold = config.edgeThreshold || 2;
            const updateArrows = function () {
                updateScrollableArrows(
                    wrapper,
                    track,
                    prevBtn,
                    nextBtn,
                    edgeThreshold,
                    shouldShowScrollableControls(itemCount)
                );
            };

            wrapper.classList.add('local-dimensions-controls-hidden');
            if (prevBtn) {
                prevBtn.style.display = 'none';
                prevBtn.addEventListener('click', function () {
                    scrollToTrackAdjacentCard(track, cardSelector, edgeThreshold, -1, updateArrows);
                });
            }
            if (nextBtn) {
                nextBtn.style.display = 'none';
                nextBtn.addEventListener('click', function () {
                    scrollToTrackAdjacentCard(track, cardSelector, edgeThreshold, 1, updateArrows);
                });
            }

            track.querySelectorAll(cardSelector).forEach(function (card) {
                card.addEventListener('click', function (event) {
                    if (event.target.closest('a, button')) {
                        return;
                    }
                    scrollTrackCardIntoView(track, card, edgeThreshold, updateArrows);
                });
            });

            track.addEventListener('scroll', updateArrows);
            wrapper._dimsUpdateArrows = updateArrows;
            updateArrows();

            if (globalThis.requestAnimationFrame) {
                globalThis.requestAnimationFrame(updateArrows);
            }
            setTimeout(updateArrows, 120);

            if (typeof ResizeObserver === 'function') {
                const resizeObserver = new ResizeObserver(updateArrows);
                resizeObserver.observe(track);
            }

            enableDragScroll(track);
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
            const sliders = contentEl.querySelectorAll('.local-dimensions-ev-slider-wrapper');

            sliders.forEach(function (wrapper) {
                const track = wrapper.querySelector('.local-dimensions-ev-slider-track');
                const prevBtn = wrapper.querySelector('.local-dimensions-ev-slider-prev');
                const nextBtn = wrapper.querySelector('.local-dimensions-ev-slider-next');

                if (!track) {
                    return;
                }

                const evidenceCount = Number.parseInt(wrapper.dataset.evidenceCount, 10)
                    || track.querySelectorAll('.local-dimensions-ev-card').length;
                initScrollableTrack({
                    wrapper: wrapper,
                    track: track,
                    prevBtn: prevBtn,
                    nextBtn: nextBtn,
                    cardSelector: '.local-dimensions-ev-card',
                    itemCount: evidenceCount
                });

                // Evidence detail button handler — opens modal with full evidence info.
                wrapper.addEventListener('click', function (e) {
                    const btn = e.target.closest('.local-dimensions-ev-detail-btn');
                    if (!btn) {
                        return;
                    }
                    e.stopPropagation();
                    const idx = Number.parseInt(btn.dataset.evidenceIndex, 10);
                    if (!Number.isNaN(idx) && evidenceData?.[idx]) {
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
            let isDown = false;
            let startX;
            let scrollLeft;
            let hasDragged = false;
            let suppressNextClick = false;
            const dragThreshold = 6;

            el.addEventListener('mousedown', function (e) {
                // Ignore clicks on links/buttons.
                if (e.target.closest('a, button')) {
                    return;
                }
                isDown = true;
                hasDragged = false;
                el.classList.add('local-dimensions-dragging');
                startX = e.pageX - el.offsetLeft;
                scrollLeft = el.scrollLeft;
            });

            el.addEventListener('mouseleave', function () {
                if (isDown && hasDragged) {
                    suppressNextClick = true;
                }
                isDown = false;
                hasDragged = false;
                el.classList.remove('local-dimensions-dragging');
            });

            el.addEventListener('mouseup', function () {
                if (isDown && hasDragged) {
                    suppressNextClick = true;
                }
                isDown = false;
                hasDragged = false;
                el.classList.remove('local-dimensions-dragging');
            });

            el.addEventListener('mousemove', function (e) {
                if (!isDown) {
                    return;
                }
                const x = e.pageX - el.offsetLeft;
                const delta = x - startX;
                if (Math.abs(delta) > dragThreshold) {
                    hasDragged = true;
                }
                if (!hasDragged) {
                    return;
                }
                e.preventDefault();
                const walk = delta * 1.5;
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
            let html = '<section class="local-dimensions-section local-dimensions-courses-section">';

            // Section title.
            html += '<h2 class="local-dimensions-section-title">';
            html += escapeHtml(strMap.linkedCourses);
            html += ' <span class="local-dimensions-section-badge">' + courses.length + '</span>';
            html += '</h2>';

            html += '<div class="local-dimensions-courses-scroll-wrapper" data-course-count="' + courses.length + '">';
            html += '<div class="local-dimensions-courses-scroll' + (hasManyCourses ? '' : ' local-dimensions-courses-no-scroll') + '">';

            courses.forEach(function (course) {
                const courseUrl = M.cfg.wwwroot + '/course/view.php?id=' + course.id;
                const courseName = course.fullname || course.shortname || '';
                const progress = Number.parseInt(course.progress, 10) || 0;
                const hasImage = course.courseimage && course.courseimage.trim() !== '';

                html += '<div class="local-dimensions-course-card-lg">';

                // Course image.
                if (hasImage) {
                    html += '<div class="local-dimensions-course-img">';
                    html += '<img src="' + escapeHtml(course.courseimage) + '" alt="" loading="lazy">';
                    html += '</div>';
                } else {
                    // Gradient placeholder with initials.
                    const initials = getInitials(courseName);
                    html += '<div class="local-dimensions-course-img local-dimensions-course-img-placeholder">';
                    html += '<span>' + escapeHtml(initials) + '</span>';
                    html += '</div>';
                }

                // Course body.
                html += '<div class="local-dimensions-course-body">';
                html += '<h3 class="local-dimensions-course-name-lg">' + escapeHtml(courseName) + '</h3>';

                // Progress bar.
                html += '<div class="local-dimensions-course-progress-lg">';
                html += '<div class="local-dimensions-course-progress-track">';
                html += '<div class="local-dimensions-course-progress-fill-lg" style="width: ' + progress + '%;"></div>';
                html += '</div>';
                html += '<span class="local-dimensions-course-progress-pct-lg">' + progress + '%</span>';
                if (progress >= 100) {
                    html += '<i class="fa fa-check-circle local-dimensions-course-check" aria-hidden="true"></i>';
                }
                html += '</div>';

                // Access button (full width).
                html += '<a href="' + escapeHtml(courseUrl) + '" class="btn btn-secondary local-dimensions-course-btn">';
                html += escapeHtml(strMap.accessLabel);
                html += '</a>';

                html += '</div>'; // End local-dimensions-course-body.
                html += '</div>'; // End local-dimensions-course-card-lg.
            });

            html += '</div>'; // End local-dimensions-courses-scroll.
            html += '<div class="local-dimensions-courses-scroll-controls" role="group" aria-label="' + escapeHtml(strMap.linkedCourses) + '">';
            html += '<button type="button" class="local-dimensions-scroll-btn local-dimensions-scroll-prev disabled"';
            html += ' aria-label="' + escapeHtml(strMap.sliderPrev) + '">';
            html += '<i class="fa fa-chevron-left" aria-hidden="true"></i>';
            html += '</button>';
            html += '<button type="button" class="local-dimensions-scroll-btn local-dimensions-scroll-next"';
            html += ' aria-label="' + escapeHtml(strMap.sliderNext) + '">';
            html += '<i class="fa fa-chevron-right" aria-hidden="true"></i>';
            html += '</button>';
            html += '</div>'; // End local-dimensions-courses-scroll-controls.

            html += '</div>'; // End local-dimensions-courses-scroll-wrapper.
            html += '</section>';

            return html;
        }

        /**
         * Initialize course cards scroll navigation.
         *
         * @param {HTMLElement} contentEl The content container element
         */
        function initCourseScroll(contentEl) {
            const wrappers = contentEl.querySelectorAll('.local-dimensions-courses-scroll-wrapper');

            wrappers.forEach(function (wrapper) {
                const track = wrapper.querySelector('.local-dimensions-courses-scroll');
                const prevBtn = wrapper.querySelector('.local-dimensions-scroll-prev');
                const nextBtn = wrapper.querySelector('.local-dimensions-scroll-next');

                if (!track) {
                    return;
                }

                const courseCount = Number.parseInt(wrapper.dataset.courseCount, 10)
                    || track.querySelectorAll('.local-dimensions-course-card-lg').length;
                initScrollableTrack({
                    wrapper: wrapper,
                    track: track,
                    prevBtn: prevBtn,
                    nextBtn: nextBtn,
                    cardSelector: '.local-dimensions-course-card-lg',
                    itemCount: courseCount
                });
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
            if (data.framework?.shortname) {
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
                html += '<nav class="local-dimensions-path-bar" aria-label="' + escapeHtml(strMap.pathBreadcrumbLabel) + '">';
                html += '<span class="local-dimensions-path-label">' + escapeHtml(strMap.pathBreadcrumbLabel) + ':</span>';
                html += '<ol class="local-dimensions-path-breadcrumb">';
                pathParts.forEach(function (part, idx) {
                    html += '<li>';
                    if (idx > 0) {
                        html += '<i class="fa fa-chevron-right local-dimensions-path-bar-sep" aria-hidden="true"></i>';
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
         * @param {number} planId The plan ID (used to build links when showrelatedlink is enabled)
         * @return {string} HTML for related competencies
         */
        function renderRelatedCompetencies(data, strMap, planId) {
            let html = '';

            if (!data.relatedcompetencies || data.relatedcompetencies.length === 0) {
                return html;
            }

            const useLink = displaySettings.showrelatedlink && displaySettings.viewplanurl && planId;

            html += '<section class="local-dimensions-section local-dimensions-related-section">';
            html += '<h3 class="local-dimensions-related-header">';
            html += escapeHtml(strMap.relatedDimensions);
            html += '</h3>';
            html += '<div class="local-dimensions-related-pills">';

            data.relatedcompetencies.forEach(function (related) {
                if (useLink && related.id) {
                    const href = displaySettings.viewplanurl + '?id=' + planId + '&competencyid=' + related.id;
                    html += '<a href="' + escapeHtml(href) + '" class="local-dimensions-related-pill-v2 local-dimensions-related-pill-link">' 
                        + escapeHtml(related.shortname) + '</a>';
                } else {
                    html += '<span class="local-dimensions-related-pill-v2">' + escapeHtml(related.shortname) + '</span>';
                }
            });

            html += '</div>';
            html += '</section>';

            return html;
        }

        /**
         * Return the main taxonomy already present in the payload.
         *
         * @param {Object} competencyData The competency summary data
         * @return {?Object} Taxonomy metadata
         */
        function getPrimaryTaxonomy(competencyData) {
            if (!competencyData?.taxonomy?.current) {
                return null;
            }

            const taxonomy = competencyData.taxonomy.current;
            if (!taxonomy.term) {
                return null;
            }

            return taxonomy;
        }

        /**
         * Return icon metadata for a taxonomy card.
         *
         * @param {string} taxonomyKey Taxonomy key from the payload
         * @return {Object} Icon metadata
         */
        function getTaxonomyCardMeta(taxonomyKey) {
            const key = (taxonomyKey || '').toLowerCase();
            const taxonomyIcons = {
                behaviour: M.util.image_url('taxonomy/behaviour', 'local_dimensions'),
                behavior: M.util.image_url('taxonomy/behaviour', 'local_dimensions'),
                competency: M.util.image_url('taxonomy/competency', 'local_dimensions'),
                concept: M.util.image_url('taxonomy/concept', 'local_dimensions'),
                domain: M.util.image_url('taxonomy/domain', 'local_dimensions'),
                indicator: M.util.image_url('taxonomy/indicator', 'local_dimensions'),
                level: M.util.image_url('taxonomy/level', 'local_dimensions'),
                outcome: M.util.image_url('taxonomy/outcome', 'local_dimensions'),
                practice: M.util.image_url('taxonomy/practice', 'local_dimensions'),
                proficiency: M.util.image_url('taxonomy/proficiency', 'local_dimensions'),
                skill: M.util.image_url('taxonomy/skill', 'local_dimensions'),
                value: M.util.image_url('taxonomy/value', 'local_dimensions')
            };
            const meta = {
                iconurl: taxonomyIcons[key] || '',
                accentClass: 'local-dimensions-taxonomy-card-neutral'
            };

            const mapping = {
                behaviour: { iconurl: taxonomyIcons.behaviour || '', accentClass: 'local-dimensions-taxonomy-card-behaviour' },
                behavior: { iconurl: taxonomyIcons.behavior || taxonomyIcons.behaviour || '', accentClass: 'local-dimensions-taxonomy-card-behaviour' },
                competency: { iconurl: taxonomyIcons.competency || '', accentClass: 'local-dimensions-taxonomy-card-competency' },
                concept: { iconurl: taxonomyIcons.concept || '', accentClass: 'local-dimensions-taxonomy-card-concept' },
                domain: { iconurl: taxonomyIcons.domain || '', accentClass: 'local-dimensions-taxonomy-card-domain' },
                indicator: { iconurl: taxonomyIcons.indicator || '', accentClass: 'local-dimensions-taxonomy-card-indicator' },
                level: { iconurl: taxonomyIcons.level || '', accentClass: 'local-dimensions-taxonomy-card-level' },
                outcome: { iconurl: taxonomyIcons.outcome || '', accentClass: 'local-dimensions-taxonomy-card-outcome' },
                practice: { iconurl: taxonomyIcons.practice || '', accentClass: 'local-dimensions-taxonomy-card-practice' },
                proficiency: { iconurl: taxonomyIcons.proficiency || '', accentClass: 'local-dimensions-taxonomy-card-proficiency' },
                skill: { iconurl: taxonomyIcons.skill || '', accentClass: 'local-dimensions-taxonomy-card-skill' },
                value: { iconurl: taxonomyIcons.value || '', accentClass: 'local-dimensions-taxonomy-card-value' }
            };

            return mapping[key] || meta;
        }

        /**
         * Render the main taxonomy card.
         *
         * @param {Object} taxonomy Taxonomy metadata from the payload
         * @param {Object} strMap Language strings map
         * @return {string} HTML for taxonomy card
         */
        function renderTaxonomyCard(taxonomy, strMap) {
            const meta = getTaxonomyCardMeta(taxonomy.key);
            let html = '<aside class="local-dimensions-taxonomy-card ' + meta.accentClass + '" aria-label="' +
                escapeHtml(taxonomy.term) + '">';

            html += '<div class="local-dimensions-taxonomy-card-label">' + escapeHtml(strMap.taxonomyCardLabel) + '</div>';
            html += '<div class="local-dimensions-taxonomy-card-icon">';
            if (meta.iconurl) {
                html += '<img class="local-dimensions-taxonomy-card-icon-image" src="' + escapeHtml(meta.iconurl) + '" alt="" aria-hidden="true">';
            }
            html += '</div>';
            html += '<div class="local-dimensions-taxonomy-card-title">' + escapeHtml(taxonomy.term) + '</div>';
            html += '</aside>';

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
            // Use descidentifier (exported by core_competency evidence_exporter) as the primary
            // type selector. This field directly maps to the Moodle evidence type string identifiers
            // and is reliable across all Moodle versions.
            const descidentifier = evidence.descidentifier || '';

            if (descidentifier === 'evidence_coursemodulecompleted') {
                return {
                    icon: 'fa-check-circle',
                    label: strMap.evidenceTypeActivity,
                    colorClass: 'local-dimensions-evidence-activity'
                };
            }

            if (descidentifier === 'evidence_coursecompleted') {
                return {
                    icon: 'fa-graduation-cap',
                    label: strMap.evidenceTypeCoursegrade,
                    colorClass: 'local-dimensions-evidence-grade'
                };
            }

            if (descidentifier === 'evidence_manualoverride' || descidentifier === 'evidence_manualoverrideinplan') {
                return {
                    icon: 'fa-pencil',
                    label: strMap.evidenceTypeManual,
                    colorClass: 'local-dimensions-evidence-manual'
                };
            }

            if (descidentifier === 'evidence_evidenceofpriorlearninglinked') {
                // Sub-check: if desca references a file, use file icon; otherwise prior learning.
                if (evidence.desca?.includes('file')) {
                    return {
                        icon: 'fa-paperclip',
                        label: strMap.evidenceTypeFile,
                        colorClass: 'local-dimensions-evidence-file'
                    };
                }
                return {
                    icon: 'fa-trophy',
                    label: strMap.evidenceTypePrior,
                    colorClass: 'local-dimensions-evidence-prior'
                };
            }

            // Fallback heuristics for backward compatibility when descidentifier is absent.
            // Evidence action constants from Moodle core_competency:
            // 0 = EVIDENCE_ACTION_LOG, 1 = EVIDENCE_ACTION_SUGGEST,
            // 2 = EVIDENCE_ACTION_COMPLETE, 3 = EVIDENCE_ACTION_OVERRIDE.
            // JSON-encoded responses return numeric fields as strings; coerce to integer.
            const action = Number.parseInt(evidence.action, 10) || 0;

            if (evidence.url?.includes('/mod/')) {
                return {
                    icon: 'fa-check-circle',
                    label: strMap.evidenceTypeActivity,
                    colorClass: 'local-dimensions-evidence-activity'
                };
            }

            if (evidence.url?.includes('/grade/')) {
                return {
                    icon: 'fa-graduation-cap',
                    label: strMap.evidenceTypeCoursegrade,
                    colorClass: 'local-dimensions-evidence-grade'
                };
            }

            if (action === 3) { // OVERRIDE - typically manual rating.
                return {
                    icon: 'fa-pencil',
                    label: strMap.evidenceTypeManual,
                    colorClass: 'local-dimensions-evidence-manual'
                };
            }

            if (evidence.desca?.includes('file')) {
                return {
                    icon: 'fa-paperclip',
                    label: strMap.evidenceTypeFile,
                    colorClass: 'local-dimensions-evidence-file'
                };
            }

            if (action === 2) { // COMPLETE.
                return {
                    icon: 'fa-trophy',
                    label: strMap.evidenceTypePrior,
                    colorClass: 'local-dimensions-evidence-prior'
                };
            }

            // Default.
            return {
                icon: 'fa-info-circle',
                label: strMap.evidenceTypeOther,
                colorClass: 'local-dimensions-evidence-other'
            };
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
            const uc = ucs.usercompetency || ucs.usercompetencyplan;
            if (!uc) {
                return '';
            }

            let html = '<div class="local-dimensions-status-tab-content">';

            // 2-column grid.
            html += '<div class="local-dimensions-status-grid">';

            // Rating cell.
            html += '<div class="local-dimensions-status-cell">';
            html += '<p class="local-dimensions-status-label">' + escapeHtml(strMap.ratingLabel) + '</p>';
            // JSON-encoded responses return numeric fields as strings; coerce to integer for safe comparison.
            const isProficient = Number.parseInt(uc.proficiency, 10) === 1;

            if (uc.grade && uc.gradename) {
                if (isProficient) {
                    html += '<span class="local-dimensions-status-badge">';
                    html += '<i class="fa fa-check-circle" aria-hidden="true"></i> ';
                } else {
                    html += '<span class="local-dimensions-status-badge local-dimensions-status-badge-muted">';
                }
                html += escapeHtml(uc.gradename);
                html += '</span>';
            } else {
                html += '<span class="local-dimensions-status-value-muted">-</span>';
            }
            html += '</div>';

            // Proficiency cell.
            html += '<div class="local-dimensions-status-cell">';
            html += '<p class="local-dimensions-status-label">' + escapeHtml(strMap.proficiencyLabel) + '</p>';
            if (isProficient) {
                html += '<div class="local-dimensions-status-value local-dimensions-status-success">';
                html += '<i class="fa fa-check-circle" aria-hidden="true"></i> ';
                html += escapeHtml(strMap.yesStr);
                html += '</div>';
            } else {
                html += '<div class="local-dimensions-status-value local-dimensions-status-pending">';
                html += escapeHtml(strMap.noStr);
                html += '</div>';
            }
            html += '</div>';

            html += '</div>'; // End local-dimensions-status-grid.
            html += '</div>'; // End local-dimensions-status-tab-content.

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
            let html = '<div class="local-dimensions-desc-tab-content">';

            // Description text (always start collapsed, JS will check if truncation is needed).
            html += '<div class="local-dimensions-desc-content local-dimensions-desc-collapsed">';
            html += description;
            html += '</div>';

            // Toggle button (always rendered, JS will hide if content fits).
            html += '<button type="button" class="local-dimensions-show-more" data-collapsed="true">';
            html += escapeHtml(strMap.showMore);
            html += ' <i class="fa fa-chevron-right" aria-hidden="true"></i>';
            html += '</button>';

            html += '</div>'; // End local-dimensions-desc-tab-content.

            return html;
        }

        /**
         * Attach event listeners for "Ver mais" toggle buttons.
         *
         * @param {HTMLElement} contentEl The content container element
         * @param {Object} strMap Language strings map
         */
        function attachShowMoreListeners(contentEl, strMap) {
            const toggleBtns = contentEl.querySelectorAll('.local-dimensions-show-more');

            toggleBtns.forEach(function (btn) {
                const descContent = btn.previousElementSibling;

                // Check if the content is actually truncated by comparing heights.
                if (descContent && descContent.scrollHeight <= descContent.clientHeight) {
                    // Content fits without truncation - remove collapsed class and hide button.
                    descContent.classList.remove('local-dimensions-desc-collapsed');
                    btn.style.display = 'none';
                    return;
                }

                btn.addEventListener('click', function () {
                    const isCollapsed = this.dataset.collapsed === 'true';

                    if (isCollapsed) {
                        // Expand.
                        descContent.classList.remove('local-dimensions-desc-collapsed');
                        this.dataset.collapsed = 'false';
                        this.innerHTML = escapeHtml(strMap.showLess)
                            + ' <i class="fa fa-chevron-down" aria-hidden="true"></i>';
                    } else {
                        // Collapse.
                        descContent.classList.add('local-dimensions-desc-collapsed');
                        this.dataset.collapsed = 'true';
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
            const date = new Date(timestamp * 1000);
            let lang = document.documentElement.lang || 'en';
            lang = lang.replace('_', '-');

            try {
                const day = date.getDate();
                const monthLong = date.toLocaleDateString(lang, { month: 'long' });
                const monthShort = date.toLocaleDateString(lang, { month: 'short' });
                const year = date.getFullYear();
                const weekdayLong = date.toLocaleDateString(lang, { weekday: 'long' });
                const weekdayShort = date.toLocaleDateString(lang, { weekday: 'short' });

                return formatStr
                    .replace('%A', weekdayLong)
                    .replace('%a', weekdayShort)
                    .replace('%d', day)
                    .replace('%B', monthLong)
                    .replace('%b', monthShort)
                    .replace('%Y', year)
                    .replace('%m', ('0' + (date.getMonth() + 1)).slice(-2));
            } catch (e) {
                Log.warn('[local_dimensions] Falling back to default locale date formatting.');
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

            const summaryContainer = document.querySelector('.local-dimensions-plan-summary');
            if (!summaryContainer) {
                return;
            }

            // Get plan ID from data attribute.
            const planId = Number.parseInt(summaryContainer.dataset.planid, 10);

            const toggleButtons = document.querySelectorAll('.local-dimensions-accordion-toggle');

            toggleButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const expanded = this.getAttribute('aria-expanded') === 'true';
                    const contentId = this.getAttribute('aria-controls');
                    const content = document.getElementById(contentId);
                    const accordionItem = this.closest('.local-dimensions-accordion-item');
                    const competencyId = accordionItem ? Number.parseInt(accordionItem.dataset.competencyId, 10) : null;

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
                                const otherId = otherBtn.getAttribute('aria-controls');
                                const otherContent = document.getElementById(otherId);
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
            const accordionContainer = document.querySelector('.local-dimensions-competency-accordion');
            if (accordionContainer) {
                accordionContainer.classList.add('local-dimensions-filter-initialized');
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
            return str.normalize('NFD').replaceAll(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        /**
         * Return the currently active tab filter value ('incomplete' or 'all').
         *
         * @return {string}
         */
        function getActiveFilter() {
            const active = document.querySelector('.local-dimensions-filter-tab.active');
            return active ? active.dataset.filter : 'incomplete';
        }

        /**
         * Return the current search query (normalized).
         *
         * @return {string}
         */
        function getSearchQuery() {
            const input = document.querySelector('.local-dimensions-search-input');
            return input ? normalizeText(input.value.trim()) : '';
        }

        /**
         * Initialize filter tabs click handlers.
         */
        function initFilterTabs() {
            const filterTabs = document.querySelectorAll('.local-dimensions-filter-tab');

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
            const input = document.querySelector('.local-dimensions-search-input');
            const clearBtn = document.querySelector('.local-dimensions-search-clear');
            if (!input) {
                return;
            }

            let debounceTimer = null;

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
            const filter = getActiveFilter();
            const query = getSearchQuery();
            const accordionItems = document.querySelectorAll('.local-dimensions-accordion-item');

            accordionItems.forEach(function (item) {
                const isCompleted = item.classList.contains('completed');

                // Tab filter.
                let passesTab = true;
                if (filter === 'incomplete' && isCompleted) {
                    passesTab = false;
                }

                // Search filter.
                let passesSearch = true;
                if (query.length > 0) {
                    const title = item.querySelector('.local-dimensions-accordion-title');
                    if (title) {
                        passesSearch = normalizeText(title.textContent).includes(query);
                    }
                }

                if (passesTab && passesSearch) {
                    item.style.display = '';
                    item.classList.remove('local-dimensions-hidden');
                } else {
                    item.style.display = 'none';
                    item.classList.add('local-dimensions-hidden');
                }
            });
        }

        return {
            init: init
        };
    });
