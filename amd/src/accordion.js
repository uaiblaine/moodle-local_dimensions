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
    ['core/ajax', 'core/templates', 'core/notification', 'core/str', 'core/modal', 'core/log',
        'local_dimensions/collapsible_description', 'local_dimensions/chip_filters',
        'local_dimensions/learner_prefs', 'local_dimensions/central/modal_expander'],
    function(Ajax, Templates, Notification, Str, Modal, Log, CollapsibleDescription, ChipFilters,
        LearnerPrefs, ModalExpander) {
        'use strict';

        // Cache for loaded competency summaries to avoid reloading.
        const loadedCompetencies = new Set();

        /* The plan's completion tabs, scoped to the filter bar. The chip filters reuse the
           .local-dimensions-filter-tab class (chip_filters.mustache), so an unscoped selector
           binds this handler to every chip as well. */
        const FILTER_TAB_SELECTOR = '.local-dimensions-filter-bar .local-dimensions-filter-tab';

        /* The two ruleoutcome values that conclude or advance a competency
           (core_competency\course_competency OUTCOME_RECOMMEND / OUTCOME_COMPLETE). The other
           two stay unbadged: OUTCOME_EVIDENCE is core's DB default, so badging it would mark
           nearly every card instead of the few that decide anything. */
        const OUTCOME_RECOMMEND = 2;
        const OUTCOME_COMPLETE = 3;

        /* Panels need document-unique ids for aria-controls, and one course can hang off
           several competencies in the same plan, so the course id alone would collide. */
        let activitiesPanelSeq = 0;

        // Display settings (loaded from page).
        let displaySettings = {
            showdescription: true,
            showtaxonomycard: false,
            showpath: false,
            showrelated: false,
            showrelatedlink: false,
            viewcompetencyurl: '',
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
            }])[0].then(function(response) {
                return JSON.parse(response);
            });

            // Always use the plugin WS: it resolves the enrolment-filter cascade
            // (competency -> plan -> global) server-side and returns richer course cards.
            const coursesPromise = Ajax.call([{
                methodname: 'local_dimensions_get_competency_courses',
                args: {competencyid: competencyId, planid: planId}
            }])[0];

            // Wait for both to complete.
            Promise.all([summaryPromise, coursesPromise]).then(function(results) {
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
                return null;
            }).catch(function(error) {
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
                {key: 'rating_label', component: 'local_dimensions'},
                {key: 'evidence_label', component: 'local_dimensions'},
                {key: 'yes', component: 'local_dimensions'},
                {key: 'no', component: 'local_dimensions'},
                {key: 'related_dimensions', component: 'local_dimensions'},
                {key: 'evidence_type_file', component: 'local_dimensions'},
                {key: 'evidence_type_manual', component: 'local_dimensions'},
                {key: 'evidence_type_activity', component: 'local_dimensions'},
                {key: 'evidence_type_coursegrade', component: 'local_dimensions'},
                {key: 'evidence_type_prior', component: 'local_dimensions'},
                {key: 'evidence_type_other', component: 'local_dimensions'},
                {key: 'no_evidence', component: 'local_dimensions'},
                {key: 'related_content', component: 'local_dimensions'},
                {key: 'assessment_status', component: 'local_dimensions'},
                {key: 'description_label', component: 'local_dimensions'},
                {key: 'taxonomy_whatis', component: 'local_dimensions'},
                {key: 'show_more', component: 'local_dimensions'},
                {key: 'show_less', component: 'local_dimensions'},
                {key: 'proficiency', component: 'local_dimensions'},
                {key: 'outcome_complete', component: 'local_dimensions'},
                {key: 'strftimedaydate', component: 'core_langconfig'},
                {key: 'evidence_slider_prev', component: 'local_dimensions'},
                {key: 'evidence_slider_next', component: 'local_dimensions'},
                {key: 'competency_path', component: 'local_dimensions'},
                {key: 'evidence_details', component: 'local_dimensions'},
                {key: 'evidence_note', component: 'local_dimensions'},
                {key: 'evidence_link', component: 'local_dimensions'},
                {key: 'evidence_grade', component: 'local_dimensions'},
                {key: 'evidence_author', component: 'local_dimensions'},
                {key: 'evidence_date', component: 'local_dimensions'},
                {key: 'evidence_view_details', component: 'local_dimensions'},
                {key: 'evidence_open_link', component: 'local_dimensions'},
                {key: 'rules_tab', component: 'local_dimensions'},
                {key: 'rules_progress', component: 'local_dimensions'},
                {key: 'rules_total_competencies', component: 'local_dimensions'},
                {key: 'rules_required_tag', component: 'local_dimensions'},
                {key: 'rules_assessment_prefix', component: 'local_dimensions'},
                {key: 'rules_pts', component: 'local_dimensions'},
                {key: 'evidence_submit', component: 'local_dimensions'},
                {key: 'rules_todo', component: 'local_dimensions'},
                {key: 'rules_info_title', component: 'local_dimensions'},
                {key: 'rules_missing_mandatory_notice', component: 'local_dimensions'},
                {key: 'rules_filter_label', component: 'local_dimensions'},
                {key: 'rules_filter_all', component: 'local_dimensions'},
                {key: 'rules_filter_required', component: 'local_dimensions'},
                {key: 'rules_sr_alert', component: 'local_dimensions'},
                {key: 'rules_sr_proficient', component: 'local_dimensions'},
                {key: 'rules_sr_inprogress', component: 'local_dimensions'},
                {key: 'rules_sr_todo', component: 'local_dimensions'},
                {key: 'rules_sr_progress', component: 'local_dimensions'},
                {key: 'proficient', component: 'local_dimensions'},
                {key: 'status_notyetrated', component: 'local_dimensions'},
                {key: 'status_notyetproficient', component: 'local_dimensions'},
                {key: 'evidence_type_rule', component: 'local_dimensions'},
                {key: 'evidence_rule_completed', component: 'local_dimensions'},
                {key: 'evidence_rule_viewrule', component: 'local_dimensions'},
                {key: 'evidence_rule_stale', component: 'local_dimensions'},
                {key: 'evidence_rule_sendreview', component: 'local_dimensions'},
                {key: 'evidence_rule_reviewsent', component: 'local_dimensions'},
                {key: 'scale_about', component: 'local_dimensions'},
                {key: 'evidence_position', component: 'local_dimensions'},
                {key: 'taxonomy_def_behaviour', component: 'local_dimensions'},
                {key: 'taxonomy_def_competency', component: 'local_dimensions'},
                {key: 'taxonomy_def_concept', component: 'local_dimensions'},
                {key: 'taxonomy_def_domain', component: 'local_dimensions'},
                {key: 'taxonomy_def_indicator', component: 'local_dimensions'},
                {key: 'taxonomy_def_level', component: 'local_dimensions'},
                {key: 'taxonomy_def_outcome', component: 'local_dimensions'},
                {key: 'taxonomy_def_practice', component: 'local_dimensions'},
                {key: 'taxonomy_def_proficiency', component: 'local_dimensions'},
                {key: 'taxonomy_def_skill', component: 'local_dimensions'},
                {key: 'taxonomy_def_value', component: 'local_dimensions'},
                {key: 'outcome_recommend', component: 'local_dimensions'},
                {key: 'activities_count', component: 'local_dimensions'},
                {key: 'activities_count_one', component: 'local_dimensions'}
            ]).then(function(strings) {
                const strMap = {
                    ratingLabel: strings[0],
                    evidenceLabel: strings[1],
                    yesStr: strings[2],
                    noStr: strings[3],
                    relatedDimensions: strings[4],
                    evidenceTypeFile: strings[5],
                    evidenceTypeManual: strings[6],
                    evidenceTypeActivity: strings[7],
                    evidenceTypeCoursegrade: strings[8],
                    evidenceTypePrior: strings[9],
                    evidenceTypeOther: strings[10],
                    noEvidence: strings[11],
                    relatedContent: strings[12],
                    assessmentStatus: strings[13],
                    descriptionLabel: strings[14],
                    taxonomyWhatIs: strings[15],
                    showMore: strings[16],
                    showLess: strings[17],
                    proficiencyLabel: strings[18],
                    outcomeComplete: strings[19],
                    dateFormat: strings[20],
                    sliderPrev: strings[21],
                    sliderNext: strings[22],
                    pathBreadcrumbLabel: strings[23],
                    evidenceDetails: strings[24],
                    evidenceNote: strings[25],
                    evidenceLink: strings[26],
                    evidenceGrade: strings[27],
                    evidenceAuthor: strings[28],
                    evidenceDate: strings[29],
                    evidenceViewDetails: strings[30],
                    evidenceOpenLink: strings[31],
                    rulesTab: strings[32],
                    rulesProgress: strings[33],
                    rulesTotalCompetencies: strings[34],
                    rulesRequiredTag: strings[35],
                    rulesAssessmentPrefix: strings[36],
                    rulesPts: strings[37],
                    evidenceSubmit: strings[38],
                    rulesTodo: strings[39],
                    rulesInfoTitle: strings[40],
                    rulesMissingMandatoryNotice: strings[41],
                    rulesFilterLabel: strings[42],
                    rulesFilterAll: strings[43],
                    rulesFilterRequired: strings[44],
                    rulesSrAlert: strings[45],
                    rulesSrProficient: strings[46],
                    rulesSrInprogress: strings[47],
                    rulesSrTodo: strings[48],
                    rulesSrProgress: strings[49],
                    proficientLabel: strings[50],
                    statusNotYetRated: strings[51],
                    statusNotYetProficient: strings[52],
                    evidenceTypeRule: strings[53],
                    evidenceRuleCompleted: strings[54],
                    evidenceRuleViewRule: strings[55],
                    evidenceRuleStale: strings[56],
                    evidenceRuleSendReview: strings[57],
                    evidenceRuleReviewSent: strings[58],
                    scaleAbout: strings[59],
                    evidencePosition: strings[60],
                    taxonomyDefinitions: {
                        behaviour: strings[61],
                        competency: strings[62],
                        concept: strings[63],
                        domain: strings[64],
                        indicator: strings[65],
                        level: strings[66],
                        outcome: strings[67],
                        practice: strings[68],
                        proficiency: strings[69],
                        skill: strings[70],
                        value: strings[71],
                        behavior: strings[61]
                    },
                    outcomeRecommend: strings[72],
                    activitiesCount: strings[73],
                    activitiesCountOne: strings[74]
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

                // Activate collapsible description wrappers (30vh max-height + toggle).
                CollapsibleDescription.refresh(contentEl);


                // Initialize evidence slider(s) — pass evidence data, strings, and scale config for modal.
                // Competency-level scaleconfiguration is null when it inherits from the framework.
                // Fall back to the resolved scaleconfiguration on the competency tree data object.
                const scaleConfig = summaryState.comp?.scaleconfiguration
                    || summaryState.competencyData?.scaleconfiguration
                    || null;
                initEvidenceList(contentEl, summaryState.ucs ? summaryState.ucs.evidence : [], strMap, scaleConfig,
                    summaryState.ucs);
                initScaleAbout(contentEl, strMap, summaryState.scaleDescription);
                initTaxonomyDefinition(contentEl, strMap);

                // Initialize course scroll navigation and the per-card activity disclosures.
                initCourseScroll(contentEl);
                initActivityDisclosures(contentEl);

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
            const visibleCourses = (courses || []).filter(function(course) {
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
                // Injected by the plugin's WS wrapper onto usercompetencysummary.competency.
                scaleDescription: (competencyData && competencyData.scaledescription) || '',
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
                tabs.push({id: 'status', label: strMap.assessmentStatus, icon: 'fa-star'});
            }
            if (summaryState.hasDesc || summaryState.hasTaxonomyCard || summaryState.hasPath || summaryState.hasRelated) {
                tabs.push({id: 'description', label: strMap.descriptionLabel, icon: 'fa-file-text-o'});
            }
            if (summaryState.hasEvidence) {
                tabs.push({id: 'evidence', label: strMap.evidenceLabel, icon: 'fa-check-square-o'});
            }
            if (summaryState.hasRules) {
                tabs.push({id: 'rules', label: strMap.rulesTab, icon: 'fa-gavel'});
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

            tabs.forEach(function(tab, idx) {
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
            html += renderStatusSection(summaryState.ucs, strMap, summaryState.scaleDescription);
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
            let html = '<div class="local-dimensions-tab-pane local-dimensions-tab-pane-description' +
                (isFirst ? ' active' : '') + '"';
            html += ' id="local-dimensions-tabpane-description-' + summaryState.comp.id + '" data-tab="description"';
            html += ' role="tabpanel" aria-labelledby="local-dimensions-tab-description-' + summaryState.comp.id + '">';
            html += '<div class="local-dimensions-desc-layout">';

            if (summaryState.hasDesc) {
                html += renderDescriptionSection(summaryState.comp.description, strMap, summaryState.comp.id);
            }
            if (summaryState.hasRelated) {
                html += renderRelatedCompetencies(summaryState.competencyData, strMap, planId);
            }
            html += renderDescriptionFootnote(
                summaryState.competencyData,
                summaryState.hasTaxonomyCard ? summaryState.primaryTaxonomy : null,
                summaryState.hasPath,
                strMap
            );

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
            const scaleConfig = summaryState.comp?.scaleconfiguration
                || summaryState.competencyData?.scaleconfiguration
                || null;
            const isFirst = tabs[0].id === 'evidence';
            let html = '<div class="local-dimensions-tab-pane local-dimensions-tab-pane-evidence' +
                (isFirst ? ' active' : '') + '"';
            html += ' id="local-dimensions-tabpane-evidence-' + summaryState.comp.id + '" data-tab="evidence"';
            html += ' role="tabpanel" aria-labelledby="local-dimensions-tab-evidence-' + summaryState.comp.id + '">';
            html += renderEvidenceList(summaryState.ucs, strMap, scaleConfig);

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
                wrapper.querySelectorAll('.local-dimensions-tab-btn').forEach(function(b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-selected', 'false');
                    b.setAttribute('tabindex', '-1');
                });

                // Deactivate all panes.
                wrapper.querySelectorAll('.local-dimensions-tab-pane').forEach(function(p) {
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

            tabBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    activateTab(this, false);
                });

                // Keyboard navigation: Arrow Left/Right, Home, End (ARIA Authoring Practices).
                btn.addEventListener('keydown', function(e) {
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

            const refresh = function() {
                container
                    .querySelectorAll('.local-dimensions-courses-scroll-wrapper')
                    .forEach(function(wrapper) {
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
            }])[0].then(function(response) {
                const data = JSON.parse(response);

                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }
                if (contentEl) {
                    contentEl.innerHTML = renderRulesSection(data, strMap, planId);
                    contentEl.style.display = 'block';
                    initRulesFilters(contentEl);
                }
                return null;
            }).catch(function(error) {
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

            html += renderRuleHeadline(data, strMap);

            /* Progress reads as a quiet status line, not a scoreboard. The warning triangle
               that used to sit here is dropped: the missing-mandatory notice below says the
               same thing in words, and two alarms for one condition read as two problems. */
            html += '<div class="local-dimensions-rules-progress-header">';
            html += '<span class="local-dimensions-rules-progress-label">' + escapeHtml(strMap.rulesProgress) + '</span>';
            html += '<span class="local-dimensions-rules-progress-score">';
            html += data.earnedpoints + ' / ' + data.totalrequired + (isPoints ? ' pts' : '');
            html += '</span>';
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
            html += '<div class="local-dimensions-rules-progress-fill" style="width: ' + pct + '%;"></div>';
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
                data.children.forEach(function(child) {
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
                html += '<img class="local-dimensions-rules-child-icon-image" src="' +
                    escapeHtml(rulesIconUrls.proficient || '') + '" alt="" aria-hidden="true">';
                html += '<span class="sr-only">' + escapeHtml(strMap.rulesSrProficient) + '</span>';
                html += '</div>';
            } else if (child.hasgrade) {
                html += '<div class="local-dimensions-rules-child-icon local-dimensions-rules-icon-inprogress">';
                html += '<img class="local-dimensions-rules-child-icon-image" src="' +
                    escapeHtml(rulesIconUrls.inprogress || '') + '" alt="" aria-hidden="true">';
                html += '<span class="sr-only">' + escapeHtml(strMap.rulesSrInprogress) + '</span>';
                html += '</div>';
            } else {
                html += '<div class="local-dimensions-rules-child-icon local-dimensions-rules-icon-todo">';
                html += '<img class="local-dimensions-rules-child-icon-image" src="' +
                    escapeHtml(rulesIconUrls.todo || '') + '" alt="" aria-hidden="true">';
                html += '<span class="sr-only">' + escapeHtml(strMap.rulesSrTodo) + '</span>';
                html += '</div>';
            }
            html += '</div>';

            // Content.
            html += '<div class="local-dimensions-rules-child-body">';
            const baseUrl = displaySettings.viewcompetencyurl || (M.cfg.wwwroot + '/local/dimensions/view-competency.php');
            const childUrl = baseUrl + '?id=' + planId + '&competencyid=' + child.id;
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
                    html += '<span class="local-dimensions-rules-points-value local-dimensions-rules-points-pending">' +
                        child.points + '</span>';
                }
                html += ' <span class="local-dimensions-rules-points-unit">' + escapeHtml(strMap.rulesPts) + '</span>';
                html += '</div>';
            }

            html += '</li>'; // End local-dimensions-rules-child-card.
            return html;
        }

        /**
         * Render the rule headline: how this competency is earned.
         *
         * This is the answer the learner actually came for, so it leads the pane as plain
         * prose rather than sitting in a boxed aside with an info icon.
         *
         * @param {Object} data The rule data
         * @param {Object} strMap Language strings map
         * @return {string} HTML for the headline
         */
        function renderRuleHeadline(data, strMap) {
            const ruleText = data.outcometext || '';

            if (!ruleText) {
                return '';
            }

            let html = '<div class="local-dimensions-rules-headline">';
            html += '<div class="local-dimensions-rules-eyebrow">' + escapeHtml(strMap.rulesInfoTitle) + '</div>';
            html += '<p class="local-dimensions-rules-sentence">' + escapeHtml(ruleText) + '</p>';
            if (data.hasrequired && data.requiredwarningtext) {
                html += '<p class="local-dimensions-rules-note">' + escapeHtml(data.requiredwarningtext) + '</p>';
            }
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

            container.querySelectorAll('.local-dimensions-rules-filter-tabs').forEach(function(tablist) {
                const buttons = Array.from(tablist.querySelectorAll('.local-dimensions-rules-filter-tab'));
                const section = tablist.closest('.local-dimensions-rules-section');
                if (!section || buttons.length === 0) {
                    return;
                }

                const cards = Array.from(section.querySelectorAll('.local-dimensions-rules-child-card'));

                const applyFilter = function(filter, focusButton) {
                    buttons.forEach(function(button) {
                        const isActive = button.dataset.filter === filter;
                        button.classList.toggle('active', isActive);
                        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        if (focusButton && isActive) {
                            button.focus();
                        }
                    });

                    cards.forEach(function(card) {
                        const showCard = filter === 'all' || card.dataset.required === 'true';
                        card.hidden = !showCard;
                    });
                };

                buttons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        applyFilter(this.dataset.filter, false);
                    });

                    button.addEventListener('keydown', function(e) {
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
         * Decide whether an evidence row is a competency-rule completion.
         *
         * Core writes this exact pair only when a rule concludes the competency
         * (OUTCOME_COMPLETE); the evidence and recommend outcomes are logged with ACTION_LOG.
         * So the test is a direct read of the payload, never an inference.
         *
         * @param {Object} ev An evidence row
         * @return {boolean}
         */
        function isRuleCompletion(ev) {
            return ev.descidentifier === 'evidence_competencyrule' && Number.parseInt(ev.action, 10) === 2;
        }

        /**
         * Render one evidence row in the journey list.
         *
         * @param {Object} ev The evidence row
         * @param {number} index Its index in the payload array
         * @param {Object} strMap Language strings map
         * @param {string|null} scaleConfig The scale configuration JSON string
         * @return {string} HTML for the row
         */
        function renderEvidenceRow(ev, index, strMap, scaleConfig) {
            const typeInfo = getEvidenceTypeInfo(ev, strMap);
            const hasGrade = !!(ev.grade && ev.gradename && ev.gradename !== '-');
            const hasExtraDetails = ev.note || ev.url || hasGrade;

            let html = '<div class="local-dimensions-ev-row' + (hasExtraDetails ? ' local-dimensions-ev-row-clickable' : '') +
                '" data-evidence-index="' + index + '"';
            if (hasExtraDetails) {
                html += ' role="button" tabindex="0"';
                html += ' aria-label="' + escapeHtml(strMap.evidenceViewDetails) + ': ' + escapeHtml(typeInfo.label) + '"';
            }
            html += '>';

            html += '<span class="local-dimensions-ev-row-icon ' + typeInfo.colorClass + '">';
            html += '<i class="fa ' + typeInfo.icon + '" aria-hidden="true"></i>';
            html += '</span>';

            html += '<span class="local-dimensions-ev-row-body">';
            if (ev.description) {
                html += '<span class="local-dimensions-ev-row-desc">' + ev.description + '</span>';
            }
            html += '<span class="local-dimensions-ev-row-type">' + escapeHtml(typeInfo.label) + '</span>';
            html += '</span>';

            if (hasGrade) {
                const proficient = isGradeProficient(ev.grade, scaleConfig);
                html += '<span class="local-dimensions-pill local-dimensions-pill-' +
                    (proficient ? 'success' : 'warning') + '">' + escapeHtml(ev.gradename) + '</span>';
            }

            html += '<span class="local-dimensions-ev-row-date">';
            if (ev.timecreated) {
                html += escapeHtml(formatTimestamp(ev.timecreated, strMap.dateFormat));
            }
            html += '</span>';

            html += '</div>';
            return html;
        }

        /**
         * Render the evidence tab: what settled the competency, then how it got there.
         *
         * The old slider gave every row the same weight and made the TYPE the headline, so a
         * rule completion looked exactly like a note. Here a decisive rule completion is
         * lifted out into a result strip and the rest stay as a plain chronological list.
         *
         * @param {Object} ucs The user competency summary
         * @param {Object} strMap Language strings map
         * @param {string|null} scaleConfig The scale configuration JSON string
         * @return {string} HTML for the evidence tab
         */
        function renderEvidenceList(ucs, strMap, scaleConfig) {
            const evidence = ucs ? ucs.evidence : [];
            const uc = (ucs && (ucs.usercompetency || ucs.usercompetencyplan)) || {};

            if (!evidence || evidence.length === 0) {
                return '<p class="local-dimensions-ev-empty">' + escapeHtml(strMap.noEvidence) + '</p>';
            }

            let html = '<div class="local-dimensions-ev-list">';

            /* The decisive row leads and is then omitted from the journey below, so the same
               fact is not stated twice. Only the LAST rule completion is decisive - an earlier
               one was superseded. */
            let decisiveIndex = -1;
            evidence.forEach(function(ev, index) {
                if (isRuleCompletion(ev)) {
                    decisiveIndex = index;
                }
            });

            if (decisiveIndex >= 0) {
                const decisive = evidence[decisiveIndex];
                html += '<div class="local-dimensions-ev-result">';
                html += '<span class="local-dimensions-ev-result-icon">';
                html += '<i class="fa fa-gavel" aria-hidden="true"></i>';
                html += '</span>';
                html += '<span class="local-dimensions-ev-result-body">';
                html += '<span class="local-dimensions-ev-result-title">' +
                    escapeHtml(strMap.evidenceRuleCompleted) + '</span>';
                if (decisive.description) {
                    html += '<span class="local-dimensions-ev-result-desc">' + decisive.description + '</span>';
                }
                html += '</span>';
                html += '<button type="button" class="local-dimensions-ev-result-action" data-goto-rules>' +
                    escapeHtml(strMap.evidenceRuleViewRule) + '</button>';
                html += '</div>';

                /* Core does not let a rule overwrite a grade that is already set, and a rule has
                   no override option of its own, so a rating made BEFORE the rule fired still
                   stands. That leaves the learner reading "the rule was met" beside a status that
                   never moved. Say so, and offer the review request that gets a human to look. */
                if (Number.parseInt(uc.proficiency, 10) !== 1) {
                    html += '<div class="local-dimensions-ev-stale" role="note">';
                    html += '<p class="local-dimensions-ev-stale-text">' + escapeHtml(strMap.evidenceRuleStale) + '</p>';
                    if (uc.isrequestreviewallowed) {
                        html += '<button type="button" class="local-dimensions-ev-stale-action" data-request-review>' +
                            escapeHtml(strMap.evidenceRuleSendReview) + '</button>';
                    } else if (uc.isstatuswaitingforreview) {
                        html += '<p class="local-dimensions-ev-stale-sent">' +
                            escapeHtml(strMap.evidenceRuleReviewSent) + '</p>';
                    }
                    html += '</div>';
                }
            }

            evidence.forEach(function(ev, index) {
                if (index === decisiveIndex) {
                    return;
                }
                html += renderEvidenceRow(ev, index, strMap, scaleConfig);
            });

            html += '</div>';
            return html;
        }

        /**
         * Check if a grade value is considered proficient according to the scale configuration.
         *
         * The scaleconfiguration is a JSON string whose FIRST element is a header carrying the
         * scale id, followed by one entry per configured scale value:
         * [{"scaleid":7},{"id":3,"scaledefault":1,"proficient":1},{"id":4,"scaledefault":0,"proficient":1}]
         *
         * Entries are keyed by their "id" (the grade) - position is meaningless, because core
         * omits any scale value that is neither the default nor proficient, so the array is
         * often sparse. A grade with no matching entry is not proficient. This mirrors
         * core_competency\competency_framework::get_proficiency_of_grade_from_scale_configuration().
         *
         * @param {number} gradeValue The grade (a scale item id)
         * @param {string} scaleConfig The JSON-encoded scale configuration string
         * @return {boolean} True if the grade is considered proficient
         */
        function isGradeProficient(gradeValue, scaleConfig) {
            try {
                const config = JSON.parse(scaleConfig);
                if (!Array.isArray(config)) {
                    return false;
                }
                const grade = Number.parseInt(gradeValue, 10);
                // Drop the scale-id header, then match on the entry's own id.
                return config.slice(1).some(function(part) {
                    return Number.parseInt(part.id, 10) === grade && Number.parseInt(part.proficient, 10) === 1;
                });
            } catch (e) {
                Log.warn('[local_dimensions] Invalid scale configuration JSON.');
                return false;
            }
        }

        /**
         * Wire the "About this scale" button to a modal showing the scale's own description.
         *
         * @param {HTMLElement} contentEl The content container element
         * @param {Object} strMap Language strings map
         * @param {string} scaleDescription Formatted description HTML from the web service
         */
        function initScaleAbout(contentEl, strMap, scaleDescription) {
            const button = contentEl.querySelector('[data-about-scale]');
            if (!button || !scaleDescription) {
                return;
            }

            button.addEventListener('click', function() {
                Modal.create({
                    title: strMap.scaleAbout,
                    /* Server-formatted through format_text with the competency's context, so it
                       is trusted HTML by the same contract the description panes already use. */
                    body: scaleDescription,
                    show: true,
                    removeOnClose: true
                }).catch(Notification.exception);
            });
        }

        /**
         * Wire the taxonomy-type button in the description footnote to its definition modal.
         *
         * @param {HTMLElement} contentEl The content container element
         * @param {Object} strMap Language strings map
         */
        function initTaxonomyDefinition(contentEl, strMap) {
            const button = contentEl.querySelector('[data-tax-key]');
            if (!button) {
                return;
            }
            button.addEventListener('click', function() {
                openTaxonomyDefinitionModal(button.dataset.taxKey, button.dataset.taxTerm, strMap);
            });
        }

        /**
         * Build the evidence-modal template context for one evidence row.
         *
         * @param {Object} ev The evidence object from the API
         * @param {number} index Its index in the evidence array
         * @param {number} total The evidence array length
         * @param {Object} strMap Language strings map
         * @param {string|null} scaleConfig The scale configuration JSON string
         * @return {Object} Template context
         */
        function buildEvidenceModalContext(ev, index, total, strMap, scaleConfig) {
            const typeInfo = getEvidenceTypeInfo(ev, strMap);
            const hasNote = !!ev.note?.trim();
            const hasUrl = !!ev.url;
            const hasGrade = !!(ev.grade && ev.gradename && ev.gradename !== '-');
            const hasActionUser = !!ev.actionuser?.fullname;
            const gradeProficient = hasGrade && scaleConfig ? isGradeProficient(ev.grade, scaleConfig) : false;

            return {
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
                stropenlink: strMap.evidenceOpenLink,
                haspager: total > 1,
                pagerlabel: strMap.evidencePosition
                    .replace('{$a->index}', index + 1)
                    .replace('{$a->total}', total),
                prevdisabled: index === 0,
                nextdisabled: index === total - 1,
                strprev: strMap.sliderPrev,
                strnext: strMap.sliderNext
            };
        }

        /**
         * Open a modal with full evidence details, paging across the competency's evidence.
         *
         * @param {Array} evidenceData The full evidence array from the API
         * @param {number} startIndex The evidence to open first
         * @param {Object} strMap Language strings map
         * @param {string|null} scaleConfig The scale configuration JSON string from the competency
         */
        function openEvidenceDetailModal(evidenceData, startIndex, strMap, scaleConfig) {
            const total = evidenceData.length;
            let current = startIndex;

            Modal.create({
                title: strMap.evidenceDetails,
                body: Templates.render('local_dimensions/evidence_detail_modal',
                    buildEvidenceModalContext(evidenceData[current], current, total, strMap, scaleConfig)),
                large: false,
                removeOnClose: true
            }).then(function(modal) {
                const root = modal.getRoot();

                // Delegated on the root, so the handler survives each body re-render.
                root.on('click', '[data-ev-prev], [data-ev-next]', function(e) {
                    const step = e.currentTarget.hasAttribute('data-ev-next') ? 1 : -1;
                    const next = current + step;
                    if (next < 0 || next >= total) {
                        return;
                    }
                    current = next;
                    modal.setBody(Templates.render('local_dimensions/evidence_detail_modal',
                        buildEvidenceModalContext(evidenceData[current], current, total, strMap, scaleConfig)));
                });

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
            return Array.prototype.map.call(track.querySelectorAll(cardSelector), function(card) {
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

            const step = function(timestamp) {
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
                    globalThis.requestAnimationFrame(function() {
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
            const updateArrows = function() {
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
                prevBtn.addEventListener('click', function() {
                    scrollToTrackAdjacentCard(track, cardSelector, edgeThreshold, -1, updateArrows);
                });
            }
            if (nextBtn) {
                nextBtn.style.display = 'none';
                nextBtn.addEventListener('click', function() {
                    scrollToTrackAdjacentCard(track, cardSelector, edgeThreshold, 1, updateArrows);
                });
            }

            track.querySelectorAll(cardSelector).forEach(function(card) {
                card.addEventListener('click', function(event) {
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
         * Wire the evidence list: open the modal from a row, jump to the Rules tab.
         *
         * @param {HTMLElement} contentEl The content container element
         * @param {Array} evidenceData The evidence array from the API
         * @param {Object} strMap Language strings map
         * @param {string|null} scaleConfig The scale configuration JSON string from the competency
         * @param {Object} ucs The user competency summary, for the review request
         */
        function initEvidenceList(contentEl, evidenceData, strMap, scaleConfig, ucs) {
            const list = contentEl.querySelector('.local-dimensions-ev-list');
            if (!list) {
                return;
            }

            const requestReview = function(button) {
                const uc = (ucs && (ucs.usercompetency || ucs.usercompetencyplan)) || {};
                if (!uc.userid || !uc.competencyid) {
                    return;
                }
                button.disabled = true;
                Ajax.call([{
                    methodname: 'core_competency_user_competency_request_review',
                    args: {userid: uc.userid, competencyid: uc.competencyid}
                }])[0].then(function() {
                    // Swap the control for the sent state; core rejects a second request anyway.
                    const sent = document.createElement('p');
                    sent.className = 'local-dimensions-ev-stale-sent';
                    sent.textContent = strMap.evidenceRuleReviewSent;
                    button.replaceWith(sent);
                    return null;
                }).catch(function(e) {
                    button.disabled = false;
                    Notification.exception(e);
                });
            };

            const openRow = function(row) {
                const idx = Number.parseInt(row.dataset.evidenceIndex, 10);
                if (!Number.isNaN(idx) && evidenceData?.[idx]) {
                    openEvidenceDetailModal(evidenceData, idx, strMap, scaleConfig);
                }
            };

            list.addEventListener('click', function(e) {
                const review = e.target.closest('[data-request-review]');
                if (review) {
                    requestReview(review);
                    return;
                }
                const gotoRules = e.target.closest('[data-goto-rules]');
                if (gotoRules) {
                    /* The tab activator is a closure with no exported handle, so reach the
                       Rules tab the same way the user would - by clicking its button. */
                    const rulesTab = contentEl.querySelector('.local-dimensions-tab-btn[data-tab="rules"]');
                    if (rulesTab) {
                        rulesTab.click();
                    }
                    return;
                }
                const row = e.target.closest('.local-dimensions-ev-row-clickable');
                if (row) {
                    openRow(row);
                }
            });

            list.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                const row = e.target.closest('.local-dimensions-ev-row-clickable');
                if (row) {
                    e.preventDefault();
                    openRow(row);
                }
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

            el.addEventListener('mousedown', function(e) {
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

            el.addEventListener('mouseleave', function() {
                if (isDown && hasDragged) {
                    suppressNextClick = true;
                }
                isDown = false;
                hasDragged = false;
                el.classList.remove('local-dimensions-dragging');
            });

            el.addEventListener('mouseup', function() {
                if (isDown && hasDragged) {
                    suppressNextClick = true;
                }
                isDown = false;
                hasDragged = false;
                el.classList.remove('local-dimensions-dragging');
            });

            el.addEventListener('mousemove', function(e) {
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
            el.addEventListener('click', function(e) {
                if (!suppressNextClick) {
                    return;
                }
                suppressNextClick = false;
                e.preventDefault();
                e.stopPropagation();
            }, true);
        }

        /**
         * Render the badge saying what completing an item does to the competency.
         *
         * @param {number|string} outcome The link's ruleoutcome value
         * @param {Object} strMap Language strings map
         * @return {string} HTML for the badge, empty unless the outcome is a decisive one
         */
        function renderOutcomeBadge(outcome, strMap) {
            const value = Number.parseInt(outcome, 10);
            let label = '';
            let icon = '';

            if (value === OUTCOME_COMPLETE) {
                label = strMap.outcomeComplete;
                icon = 'fa-trophy';
            } else if (value === OUTCOME_RECOMMEND) {
                label = strMap.outcomeRecommend;
                icon = 'fa-paper-plane';
            } else {
                return '';
            }

            return '<span class="local-dimensions-outcome-badge">' +
                '<i class="fa ' + icon + '" aria-hidden="true"></i>' +
                escapeHtml(label) + '</span>';
        }

        /**
         * Render one linked activity as a compact row.
         *
         * A restricted activity keeps its row but carries the course URL, because that is the
         * page where core explains the restriction; the server has already resolved that.
         *
         * @param {Object} activity One entry of a course's activities array
         * @param {Object} strMap Language strings map
         * @return {string} HTML for the row
         */
        function renderActivityRow(activity, strMap) {
            const name = activity.name || '';
            const hasLink = !!activity.url;
            const label = activity.modtype ? activity.modtype + ': ' + name : name;
            let marker = '';

            if (activity.locked) {
                marker = '<i class="fa fa-lock" aria-hidden="true"></i>';
            } else if (activity.has_completion && activity.is_completed) {
                marker = '<i class="fa fa-check-circle local-dimensions-act-done" aria-hidden="true"></i>';
            } else if (activity.has_completion) {
                marker = '<i class="fa fa-circle-o local-dimensions-act-todo" aria-hidden="true"></i>';
            }

            let html = '<li class="local-dimensions-course-act' +
                (activity.locked ? ' local-dimensions-course-act-locked' : '') + '">';
            html += hasLink
                ? '<a class="local-dimensions-course-act-row" href="' + escapeHtml(activity.url) +
                    '" aria-label="' + escapeHtml(label) + '">'
                : '<span class="local-dimensions-course-act-row">';
            html += '<span class="local-dimensions-course-act-icon activityiconcontainer smaller ' +
                escapeHtml(activity.purpose || '') + '">' +
                '<img class="activityicon" src="' + escapeHtml(activity.iconurl) + '" alt="" loading="lazy">' +
                '</span>';
            html += '<span class="local-dimensions-course-act-main">';
            html += '<span class="local-dimensions-course-act-name">' + escapeHtml(name) + '</span>';
            html += renderOutcomeBadge(activity.ruleoutcome, strMap);
            html += '</span>';
            html += '<span class="local-dimensions-course-act-state">' + marker + '</span>';
            html += hasLink ? '</a>' : '</span>';
            html += '</li>';

            return html;
        }

        /**
         * Render the related content: linked courses as a scrollable horizontal section, each
         * card disclosing the competency's activities inside that course.
         *
         * @param {Array} courses Visible courses array
         * @param {Object} strMap Language strings map
         * @return {string} HTML for the related-content section
         */
        function renderCourseCardsScrollable(courses, strMap) {
            const hasManyCourses = courses.length > 2;
            let html = '<section class="local-dimensions-section local-dimensions-courses-section">';

            html += '<div class="local-dimensions-courses-scroll-wrapper" data-course-count="' + courses.length + '">';

            /* The nav pill lives in the header rather than in a reserved lane under the track,
               so it stays put when a card's activity list expands. It has to remain inside the
               wrapper: initCourseScroll looks the buttons up from there. */
            html += '<div class="local-dimensions-courses-head">';
            html += '<h2 class="local-dimensions-section-title">';
            html += escapeHtml(strMap.relatedContent);
            html += ' <span class="local-dimensions-section-badge">' + courses.length + '</span>';
            html += '</h2>';
            html += '<span class="local-dimensions-courses-scroll-controls" role="group" aria-label="' +
                escapeHtml(strMap.relatedContent) + '">';
            html += '<button type="button" class="local-dimensions-scroll-btn local-dimensions-scroll-prev disabled"';
            html += ' aria-label="' + escapeHtml(strMap.sliderPrev) + '">';
            html += '<i class="fa fa-chevron-left" aria-hidden="true"></i>';
            html += '</button>';
            html += '<button type="button" class="local-dimensions-scroll-btn local-dimensions-scroll-next"';
            html += ' aria-label="' + escapeHtml(strMap.sliderNext) + '">';
            html += '<i class="fa fa-chevron-right" aria-hidden="true"></i>';
            html += '</button>';
            html += '</span>';
            html += '</div>';

            html += '<div class="local-dimensions-courses-scroll' +
                (hasManyCourses ? '' : ' local-dimensions-courses-no-scroll') + '">';

            courses.forEach(function(course) {
                const courseUrl = M.cfg.wwwroot + '/course/view.php?id=' + course.id;
                const courseName = course.fullname || course.shortname || '';
                const progress = Number.parseInt(course.progress, 10) || 0;
                const hasImage = course.courseimage && course.courseimage.trim() !== '';
                const activities = course.activities || [];

                html += '<div class="local-dimensions-course-card-lg">';

                // The whole card is the link to the course; the disclosure below sits outside it.
                html += '<a href="' + escapeHtml(courseUrl) + '" class="local-dimensions-course-link">';

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
                html += renderOutcomeBadge(course.ruleoutcome, strMap);

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

                html += '</div>'; // End local-dimensions-course-body.
                html += '</a>'; // End local-dimensions-course-link.

                if (activities.length > 0) {
                    const panelId = 'local-dimensions-course-acts-' + (++activitiesPanelSeq);
                    const countLabel = activities.length === 1
                        ? strMap.activitiesCountOne
                        : strMap.activitiesCount.replace('{$a}', activities.length);

                    html += '<button type="button" class="local-dimensions-course-acts-toggle"';
                    html += ' aria-expanded="false" aria-controls="' + panelId + '">';
                    html += '<i class="fa fa-list-ul" aria-hidden="true"></i>';
                    html += '<span>' + escapeHtml(countLabel) + '</span>';
                    html += '<i class="fa fa-chevron-down local-dimensions-course-acts-chevron" aria-hidden="true"></i>';
                    html += '</button>';

                    html += '<ul class="local-dimensions-course-acts" id="' + panelId + '" hidden>';
                    activities.forEach(function(activity) {
                        html += renderActivityRow(activity, strMap);
                    });
                    html += '</ul>';
                }

                html += '</div>'; // End local-dimensions-course-card-lg.
            });

            html += '</div>'; // End local-dimensions-courses-scroll.
            html += '</div>'; // End local-dimensions-courses-scroll-wrapper.
            html += '</section>';

            return html;
        }

        /**
         * Wire the per-card activity disclosures.
         *
         * A plain hidden toggle rather than a Bootstrap collapse: the data-API attribute
         * differs between Bootstrap 4 (4.5) and 5 (5.x) and is not bridged.
         *
         * @param {HTMLElement} contentEl The content container element
         */
        function initActivityDisclosures(contentEl) {
            contentEl.querySelectorAll('.local-dimensions-course-acts-toggle').forEach(function(toggle) {
                toggle.addEventListener('click', function() {
                    const panel = contentEl.querySelector('#' + toggle.getAttribute('aria-controls'));
                    if (!panel) {
                        return;
                    }
                    const expanded = toggle.getAttribute('aria-expanded') === 'true';
                    toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    panel.hidden = expanded;
                });
            });
        }

        /**
         * Initialize course cards scroll navigation.
         *
         * @param {HTMLElement} contentEl The content container element
         */
        function initCourseScroll(contentEl) {
            const wrappers = contentEl.querySelectorAll('.local-dimensions-courses-scroll-wrapper');

            wrappers.forEach(function(wrapper) {
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
         * Render the description-pane footnote: the competency's place in the framework, and
         * an accented button that explains its taxonomy type.
         *
         * Path and taxonomy share one low-contrast line at the foot of the pane. Either half
         * may be absent (gated independently by showpath / showtaxonomycard), so the row renders
         * whenever at least one is present, with the taxonomy button pushed to the right.
         *
         * @param {Object} data The competency data
         * @param {Object} taxonomy The primary taxonomy ({key, term}) or null
         * @param {boolean} showPath Whether the path half is enabled
         * @param {Object} strMap Language strings map
         * @return {string} HTML for the footnote
         */
        function renderDescriptionFootnote(data, taxonomy, showPath, strMap) {
            let pathHtml = '';
            if (showPath && data.competency) {
                const pathParts = [];
                if (data.framework?.shortname) {
                    pathParts.push(escapeHtml(data.framework.shortname));
                }
                if (Array.isArray(data.compparents)) {
                    data.compparents.forEach(function(parent) {
                        if (parent.shortname) {
                            pathParts.push(escapeHtml(parent.shortname));
                        }
                    });
                }
                if (pathParts.length > 0) {
                    pathHtml = '<span class="local-dimensions-path-trail">' +
                        '<i class="fa fa-sitemap" aria-hidden="true"></i> ' +
                        pathParts.join(' <span class="local-dimensions-path-sep">&rsaquo;</span> ') +
                        '</span>';
                }
            }

            let taxHtml = '';
            if (taxonomy && taxonomy.term && strMap.taxonomyDefinitions[(taxonomy.key || '').toLowerCase()]) {
                taxHtml = '<button type="button" class="local-dimensions-tax-link" data-tax-key="' +
                    escapeHtml((taxonomy.key || '').toLowerCase()) + '" data-tax-term="' + escapeHtml(taxonomy.term) + '">' +
                    escapeHtml(taxonomy.term) +
                    ' <i class="fa fa-question-circle" aria-hidden="true"></i></button>';
            }

            if (!pathHtml && !taxHtml) {
                return '';
            }

            return '<div class="local-dimensions-desc-footnote">' + pathHtml +
                '<span class="local-dimensions-desc-footnote-spacer"></span>' + taxHtml + '</div>';
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

            const useLink = displaySettings.showrelatedlink && displaySettings.viewcompetencyurl && planId;

            html += '<section class="local-dimensions-section local-dimensions-related-section">';
            html += '<h3 class="local-dimensions-related-header">';
            html += escapeHtml(strMap.relatedDimensions);
            html += '</h3>';
            html += '<div class="local-dimensions-related-pills">';

            data.relatedcompetencies.forEach(function(related) {
                if (useLink && related.id) {
                    const href = displaySettings.viewcompetencyurl + '?id=' + planId + '&competencyid=' + related.id;
                    html += '<a href="' + escapeHtml(href) +
                        '" target="_blank" rel="noopener"' +
                        ' class="local-dimensions-related-pill-v2 local-dimensions-related-pill-link">'
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
        /**
         * Open a modal explaining what a taxonomy type means.
         *
         * @param {string} key The core taxonomy key (behaviour, skill, ...)
         * @param {string} term The localised taxonomy term, for the title
         * @param {Object} strMap Language strings map
         */
        function openTaxonomyDefinitionModal(key, term, strMap) {
            const definition = strMap.taxonomyDefinitions[key];
            if (!definition) {
                return;
            }
            Modal.create({
                title: strMap.taxonomyWhatIs.replace('{$a}', term),
                body: definition,
                show: true,
                removeOnClose: true
            }).catch(Notification.exception);
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

            if (descidentifier === 'evidence_competencyrule') {
                return {
                    icon: 'fa-gavel',
                    label: strMap.evidenceTypeRule,
                    colorClass: 'local-dimensions-evidence-rule'
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
         * @param {string} scaleDescription Formatted scale description, or '' to hide the button
         * @return {string} HTML for the status section
         */
        function renderStatusSection(ucs, strMap, scaleDescription) {
            const uc = ucs.usercompetency || ucs.usercompetencyplan;
            if (!uc) {
                return '';
            }

            // JSON-encoded responses return numeric fields as strings; coerce to integer for safe comparison.
            const isProficient = Number.parseInt(uc.proficiency, 10) === 1;
            const hasGrade = !!(uc.grade && uc.gradename);

            /* The rating is the fact the learner wants; proficiency only qualifies it. So the
               scale level leads as plain strong text and proficiency follows as a pill - and
               with no grade there is nothing to qualify, so the pill is dropped entirely
               rather than saying "No". */
            let html = '<div class="local-dimensions-status-tab-content">';
            html += '<div class="local-dimensions-status-headline">';
            html += '<span class="local-dimensions-status-rating' +
                (hasGrade ? '' : ' local-dimensions-status-rating-empty') + '">';
            html += escapeHtml(hasGrade ? uc.gradename : strMap.statusNotYetRated);
            html += '</span>';

            if (hasGrade) {
                html += '<span class="local-dimensions-pill local-dimensions-pill-' +
                    (isProficient ? 'success' : 'warning') + '">';
                if (isProficient) {
                    html += '<i class="fa fa-check-circle" aria-hidden="true"></i> ';
                }
                html += escapeHtml(isProficient ? strMap.proficientLabel : strMap.statusNotYetProficient);
                html += '</span>';
            }

            html += '</div>';

            /* Rendered only when the scale actually has a description - most do not, so the
               button would otherwise open an empty modal. */
            if (scaleDescription) {
                html += '<button type="button" class="local-dimensions-status-scale" data-about-scale>';
                html += '<i class="fa fa-info-circle" aria-hidden="true"></i> ';
                html += escapeHtml(strMap.scaleAbout);
                html += '</button>';
            }

            html += '</div>'; // End local-dimensions-status-tab-content.

            return html;
        }

        /**
         * Render description section with "Ver mais" truncation.
         * Now rendered inside a tab pane, no shadow card wrapper needed.
         *
         * @param {string} description The competency description HTML
         * @param {Object} strMap Language strings map
         * @param {number} competencyId The competency identifier (used to build unique DOM ids)
         * @return {string} HTML for the description section
         */
        function renderDescriptionSection(description, strMap, competencyId) {
            const descId = 'local-dimensions-acc-desc-' + competencyId;
            let html = '<div class="local-dimensions-desc-tab-content">';

            // Reusable collapsible wrapper (max-height 30vh) — matches the
            // local_dimensions/collapsible_description Mustache partial. The
            // local_dimensions/collapsible_description AMD module activates
            // it after this markup is inserted into the DOM.
            html += '<div class="local-dimensions-collapsible" data-collapsible-description>';
            html += '<div id="' + descId + '-content" class="local-dimensions-collapsible-content" aria-hidden="false">';
            html += description;
            html += '</div>';
            html += '<div class="local-dimensions-collapsible-fade" aria-hidden="true"></div>';
            html += '<button type="button" class="local-dimensions-collapsible-toggle" aria-expanded="false"';
            html += ' aria-controls="' + descId + '-content"';
            html += ' data-label-show="' + escapeHtml(strMap.showMore) + '"';
            html += ' data-label-hide="' + escapeHtml(strMap.showLess) + '" hidden>';
            html += '<span class="local-dimensions-collapsible-toggle-label">' + escapeHtml(strMap.showMore) + '</span>';
            html += ' <i class="fa fa-chevron-down" aria-hidden="true"></i>';
            html += '</button>';
            html += '</div>';

            html += '</div>'; // End local-dimensions-desc-tab-content.

            return html;
        }

        /**
         * Build initials from a course name for image placeholders.
         *
         * @param {string} name Course name
         * @return {string} Initials
         */
        function getInitials(name) {
            if (!name || typeof name !== 'string') {
                return '';
            }

            const parts = name.trim().split(/\s+/).filter(Boolean);
            if (parts.length === 0) {
                return '';
            }

            if (parts.length === 1) {
                return parts[0].slice(0, 2).toUpperCase();
            }

            return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
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
                const monthLong = date.toLocaleDateString(lang, {month: 'long'});
                const monthShort = date.toLocaleDateString(lang, {month: 'short'});
                const year = date.getFullYear();
                const weekdayLong = date.toLocaleDateString(lang, {weekday: 'long'});
                const weekdayShort = date.toLocaleDateString(lang, {weekday: 'short'});

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

            toggleButtons.forEach(function(button) {
                button.addEventListener('click', function() {
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
                        toggleButtons.forEach(function(otherBtn) {
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
                button.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            });

            /* Seed the preference store from what the server already rendered, before any
               control can fire a save - otherwise the first click would persist a state
               assembled from defaults instead of from the learner's own choices. */
            const summary = document.querySelector('.local-dimensions-plan-summary');
            LearnerPrefs.init({
                sort: (summary && summary.dataset.sortmode) || 'planorder',
                filter: getActiveFilter(),
            });

            // Initialize filter tabs functionality.
            initFilterTabs();
            initSearch();
            initSort();
            initFavourites();
            initViewToggle(planId);

            // Wire up custom-field chip filters (no-op when none rendered).
            ChipFilters.init('local-dimensions-viewplan-chip-filters', function(selection) {
                activeChipSelection = selection || {};
                applyFilter();
            });
            ChipFilters.initPanel('local-dimensions-viewplan-chip-filters');
            initNoResultsClear();

            // Apply initial filter (show incomplete only by default).
            applyFilter();

            // Mark as initialized to enable CSS transitions (prevents flickering).
            const accordionContainer = document.querySelector('.local-dimensions-competency-accordion');
            if (accordionContainer) {
                accordionContainer.classList.add('local-dimensions-filter-initialized');
            }
        }

        // Active chip filter selection (shortname => string[]).
        let activeChipSelection = {};

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
            const active = document.querySelector(FILTER_TAB_SELECTOR + '.active');
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
            const filterTabs = document.querySelectorAll(FILTER_TAB_SELECTOR);

            filterTabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    // Update active state on tabs.
                    filterTabs.forEach(function(t) {
                        t.classList.remove('active');
                        t.setAttribute('aria-selected', 'false');
                    });
                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');

                    // Apply the filter (combined with search).
                    applyFilter();
                    LearnerPrefs.save({filter: this.dataset.filter});
                });

                // Keyboard support.
                tab.addEventListener('keydown', function(e) {
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

            input.addEventListener('input', function() {
                // Show/hide clear button.
                if (clearBtn) {
                    clearBtn.style.display = input.value.length > 0 ? 'flex' : 'none';
                }
                // Debounce filtering (100ms).
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    applyFilter();
                }, 100);
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
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
            const favToggle = document.querySelector('[data-fav-toggle]');
            const favonly = !!favToggle && favToggle.getAttribute('aria-pressed') === 'true';
            const accordionItems = document.querySelectorAll('.local-dimensions-accordion-item');
            let visiblecount = 0;
            let nonfavcount = 0;

            accordionItems.forEach(function(item) {
                const isCompleted = item.classList.contains('completed');
                if (!item.classList.contains('local-dimensions-favourite')) {
                    nonfavcount++;
                }

                // Tab filter.
                let passesTab = true;
                if (filter === 'incomplete' && isCompleted) {
                    passesTab = false;
                }

                // Favourites-only filter.
                const passesFav = !favonly || item.classList.contains('local-dimensions-favourite');

                // Search filter.
                let passesSearch = true;
                if (query.length > 0) {
                    const title = item.querySelector('.local-dimensions-accordion-title');
                    if (title) {
                        passesSearch = normalizeText(title.textContent).includes(query);
                    }
                }

                // Chip filter (custom-field driven).
                let passesChips = true;
                if (item.dataset.filtervalues) {
                    let parsed = null;
                    try {
                        parsed = JSON.parse(item.dataset.filtervalues);
                    } catch (e) {
                        parsed = null;
                    }
                    passesChips = ChipFilters.matchesSelection(activeChipSelection, parsed || {});
                }

                if (passesTab && passesFav && passesSearch && passesChips) {
                    item.style.display = '';
                    item.classList.remove('local-dimensions-hidden');
                    visiblecount++;
                } else {
                    item.style.display = 'none';
                    item.classList.add('local-dimensions-hidden');
                }
            });

            /* The ghost card and the no-results block are both empty-state messages, so only
               one may show: in favourites-only mode the ghost is the more useful of the two,
               because it names the filter that is hiding everything. */
            const showghost = favonly && nonfavcount > 0;
            updateGhost(showghost, nonfavcount);

            const noresults = document.getElementById('local-dimensions-viewplan-noresults');
            if (noresults) {
                noresults.hidden = visiblecount > 0 || showghost;
            }
        }

        /**
         * Show or hide the ghost card and refresh its count.
         *
         * @param {boolean} show Whether the card belongs on screen at all
         * @param {number} count How many competencies are not favourited
         */
        function updateGhost(show, count) {
            const slot = document.querySelector('[data-ghost-slot]');
            if (!slot) {
                return;
            }
            slot.hidden = !show;
            if (!show) {
                return;
            }
            const label = slot.querySelector('[data-ghost-count]');
            if (!label) {
                return;
            }
            Str.get_string('fav_ghost', 'local_dimensions', count).then(function(text) {
                label.textContent = text;
                return null;
            }).catch(Notification.exception);
        }

        /**
         * Show the favourites filter only once there is a favourite to filter to.
         *
         * The same rule the companion block applies (its whole favourites pill group renders
         * only when the count is above zero): a filter that can only ever return nothing is
         * not a control, it is a dead end. Unstarring the last one also releases the filter,
         * or the learner would be left looking at an empty plan with the control gone.
         */
        function syncFavouriteToggle() {
            const favToggle = document.querySelector('[data-fav-toggle]');
            const group = document.querySelector('.local-dimensions-fav-group');
            if (!favToggle || !group) {
                return;
            }
            const count = document.querySelectorAll(
                '.local-dimensions-accordion-item.local-dimensions-favourite'
            ).length;
            if (!count && favToggle.getAttribute('aria-pressed') === 'true') {
                favToggle.setAttribute('aria-pressed', 'false');
                favToggle.classList.remove('active');
                LearnerPrefs.save({favonly: false});
            }
            const label = favToggle.querySelector('[data-fav-count]');
            if (label) {
                label.textContent = count;
            }
            group.hidden = !count;
        }

        /**
         * Wire the favourites controls: the per-row stars, the toolbar toggle and the ghost.
         *
         * A star toggle deliberately does NOT re-sort, even under "Favourites first": rows
         * leaping out from under the pointer at the moment of clicking is worse than an order
         * that settles on the next visit.
         */
        function initFavourites() {
            const summary = document.querySelector('.local-dimensions-plan-summary');
            if (!summary) {
                return;
            }

            let map = {};
            try {
                map = JSON.parse(summary.dataset.favourites || '{}');
            } catch (e) {
                map = {};
            }
            LearnerPrefs.initFavourites(summary.dataset.planid, map);

            document.querySelectorAll('[data-fav-star]').forEach(function(star) {
                star.addEventListener('click', function() {
                    const on = LearnerPrefs.toggleFavourite(star.dataset.competencyId);
                    star.setAttribute('aria-pressed', on ? 'true' : 'false');
                    const item = star.closest('.local-dimensions-accordion-item');
                    if (item) {
                        item.classList.toggle('local-dimensions-favourite', on);
                    }
                    syncFavouriteToggle();
                    applyFilter();
                });
            });

            const favToggle = document.querySelector('[data-fav-toggle]');
            if (favToggle) {
                favToggle.addEventListener('click', function() {
                    const on = favToggle.getAttribute('aria-pressed') !== 'true';
                    favToggle.setAttribute('aria-pressed', on ? 'true' : 'false');
                    favToggle.classList.toggle('active', on);
                    LearnerPrefs.save({favonly: on});
                    applyFilter();
                });
            }
            syncFavouriteToggle();

            /* Narrow screens fold the filters behind an adjustments button. A class rather
               than the hidden attribute, so the media query alone decides whether the
               wrappers are folded at all - on a wide screen they are always in the bar. */
            const adjust = document.querySelector('[data-toolbar-adjust]');
            const bar = document.querySelector('.local-dimensions-toolbar');
            if (adjust && bar) {
                adjust.addEventListener('click', function() {
                    const open = adjust.getAttribute('aria-expanded') !== 'true';
                    adjust.setAttribute('aria-expanded', open ? 'true' : 'false');
                    bar.classList.toggle('local-dimensions-toolbar-open', open);
                });
            }

            const ghost = document.querySelector('[data-ghost]');
            if (ghost && favToggle) {
                // Activating the ghost clears the filter it is there to explain.
                ghost.addEventListener('click', function() {
                    favToggle.click();
                });
            }
        }

        /**
         * Reorder the loaded rows.
         *
         * Client-side over rows the server already ordered for the first paint, so a change
         * costs no round trip and every lazily-loaded detail pane stays attached to its row.
         * Each mode is computed from the plan order rather than from the current DOM order,
         * so the result never depends on which sort ran before it.
         *
         * @param {string} mode One of planorder, name, completed
         */
        function applySort(mode) {
            const container = document.getElementById('local-dimensions-viewplan-accordion');
            if (!container) {
                return;
            }

            const byPlan = Array.prototype.slice
                .call(container.querySelectorAll('.local-dimensions-accordion-item'))
                .sort(function(a, b) {
                    return Number(a.dataset.planorder) - Number(b.dataset.planorder);
                });

            const titleOf = function(item) {
                const title = item.querySelector('.local-dimensions-accordion-title');
                return title ? title.textContent.trim() : '';
            };
            const hasClass = function(name) {
                return function(item) {
                    return item.classList.contains(name);
                };
            };
            const partition = function(name) {
                return byPlan.filter(hasClass(name)).concat(byPlan.filter(function(item) {
                    return !item.classList.contains(name);
                }));
            };

            let sorted = byPlan;
            if (mode === 'name') {
                sorted = byPlan.slice().sort(function(a, b) {
                    return titleOf(a).localeCompare(titleOf(b));
                });
            } else if (mode === 'completed') {
                sorted = partition('completed');
            } else if (mode === 'favourites') {
                sorted = partition('local-dimensions-favourite');
            }

            sorted.forEach(function(item) {
                container.append(item);
            });

            // Re-appending the rows moved them all past the ghost; it belongs after them.
            const slot = container.querySelector('[data-ghost-slot]');
            if (slot) {
                container.append(slot);
            }
        }

        /**
         * Wire the sort menu.
         *
         * A plain block toggled by the hidden attribute, like the chip-filter panel: a
         * Bootstrap dropdown would need both data-toggle and data-bs-toggle to work on 4.5
         * and 5.x alike.
         */
        function initSort() {
            const toggle = document.querySelector('[data-sort-toggle]');
            const menu = document.getElementById('local-dimensions-viewplan-sort-menu');
            if (!toggle || !menu) {
                return;
            }

            const closeMenu = function() {
                menu.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            };

            toggle.addEventListener('click', function(event) {
                event.stopPropagation();
                const expanded = toggle.getAttribute('aria-expanded') === 'true';
                menu.hidden = expanded;
                toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            });

            document.addEventListener('click', function(event) {
                if (!menu.hidden && !menu.contains(event.target)) {
                    closeMenu();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && !menu.hidden) {
                    closeMenu();
                    toggle.focus();
                }
            });

            const options = menu.querySelectorAll('[data-sort]');
            options.forEach(function(option) {
                option.addEventListener('click', function() {
                    options.forEach(function(other) {
                        other.setAttribute('aria-checked', other === option ? 'true' : 'false');
                    });
                    widenFilterForSort(option.dataset.sort);
                    applySort(option.dataset.sort);
                    closeMenu();
                    toggle.focus();
                    LearnerPrefs.save({sort: option.dataset.sort});
                });
            });
        }

        /**
         * Let a sort widen the filter that would otherwise make it a no-op.
         *
         * Ordering by "completed first" while only the not-completed rows are shown sorts an
         * empty set, and so does "favourites first" while only favourites are shown. Rather
         * than hide or disable the option - which leaves the learner holding a control that
         * does nothing, with no way to make it work - the choice is read as the intent it
         * expresses and the filter opens far enough to honour it.
         *
         * @param {string} mode The sort the learner just picked
         */
        function widenFilterForSort(mode) {
            if (mode === 'completed') {
                const allTab = document.querySelector(FILTER_TAB_SELECTOR + '[data-filter="all"]');
                if (allTab && !allTab.classList.contains('active')) {
                    allTab.click();
                }
                return;
            }

            if (mode === 'favourites') {
                const favToggle = document.querySelector('[data-fav-toggle]');
                if (favToggle && favToggle.getAttribute('aria-pressed') === 'true') {
                    favToggle.click();
                }
            }
        }

        /**
         * Empty every accordion detail pane and forget it was ever loaded.
         *
         * This is the invariant that lets the grid modal exist at all. A rendered detail
         * carries ids of the form local-dimensions-tab-{tabid}-{competencyId}, with no
         * instance suffix, and getElementById returns the FIRST document-order match - so a
         * pane and a modal holding the same competency would make the tab handler act on the
         * wrong element. Exactly one surface may hold rendered detail at a time, and switching
         * layout tears the outgoing one down before the incoming one is built.
         *
         * If a future change ever renders both at once, the instance-suffix work returns.
         */
        function tearDownAccordionPanes() {
            document.querySelectorAll('.local-dimensions-accordion-content').forEach(function(content) {
                const toggle = document.getElementById(content.getAttribute('aria-labelledby'));
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
                content.hidden = true;

                const rendered = content.querySelector('.local-dimensions-competency-summary-content');
                if (rendered) {
                    rendered.innerHTML = '';
                    rendered.style.display = 'none';
                }
                const error = content.querySelector('.local-dimensions-competency-summary-error');
                if (error) {
                    error.style.display = 'none';
                }
            });
            loadedCompetencies.clear();
        }

        /**
         * Switch between the accordion list and the card grid.
         *
         * The same rows serve both layouts - only their presentation and their detail surface
         * change - so nothing is re-fetched to change mode, and the filters, the sort and the
         * favourites all keep working untouched.
         *
         * @param {string} mode Either list or grid
         */
        function applyViewMode(mode) {
            const container = document.getElementById('local-dimensions-viewplan-accordion');
            if (!container) {
                return;
            }
            tearDownAccordionPanes();
            container.classList.toggle('local-dimensions-grid-mode', mode === 'grid');
        }

        /**
         * Open one competency's detail in a modal, with a pager over the visible rows.
         *
         * @param {HTMLElement} item The accordion item whose detail to show
         * @param {number} planId The plan id
         */
        function openDetailModal(item, planId) {
            const competencyId = Number.parseInt(item.dataset.competencyId, 10);
            const content = document.getElementById('competency-content-' + competencyId);
            const shell = content && content.querySelector('.local-dimensions-accordion-body');
            const title = item.querySelector('.local-dimensions-accordion-title');
            if (!shell) {
                return;
            }

            Modal.create({
                title: title ? title.textContent.trim() : '',
                body: '',
                large: true,
                show: true,
                removeOnClose: true
            }).then(function(modal) {
                const root = modal.getRoot()[0];

                // R4: a modal is appended to document.body, outside the percentagemode wrapper.
                const summary = document.querySelector('.local-dimensions-plan-summary');
                root.querySelector('.modal-body').classList.add(
                    'percentagemode-' + ((summary && summary.dataset.percentagemode) || 'hover')
                );

                const dialog = root.querySelector('.modal-dialog');
                if (dialog) {
                    // The expander only decorates the dialog; nothing below waits on its strings.
                    // eslint-disable-next-line promise/no-nesting
                    ModalExpander.attach(dialog, {
                        get: () => Boolean(LearnerPrefs.get().expanded),
                        set: (expanded) => LearnerPrefs.save({expanded: expanded}),
                    }).catch(Notification.exception);
                }

                showCompetencyInModal(modal, item, planId);
                return null;
            }).catch(Notification.exception);
        }

        /**
         * Fill an already-open modal with one competency's detail.
         *
         * Paging replaces the contents of the SAME modal rather than destroying it and building
         * the next: a destroy/create pair plays the close and open animations back to back,
         * which reads as the dialog flashing on every step.
         *
         * @param {Object} modal The open modal
         * @param {HTMLElement} item The accordion item whose detail to show
         * @param {number} planId The plan id
         */
        function showCompetencyInModal(modal, item, planId) {
            const competencyId = Number.parseInt(item.dataset.competencyId, 10);
            const content = document.getElementById('competency-content-' + competencyId);
            const shell = content && content.querySelector('.local-dimensions-accordion-body');
            const title = item.querySelector('.local-dimensions-accordion-title');
            const modalbody = modal.getRoot()[0].querySelector('.modal-body');
            if (!shell || !modalbody) {
                return;
            }

            modal.setTitle(title ? title.textContent.trim() : '');

            /* Appended as nodes, never as an HTML string: the shell is cloned from a torn-down
               pane, so the copy carries the loading placeholder and its already-translated
               strings, and no rendered ids come with it. The pager has to be live too, or its
               asynchronous labels would land on a detached copy nobody sees. */
            modalbody.textContent = '';
            modalbody.appendChild(buildModalPager(item, modal, planId));
            modalbody.appendChild(shell.cloneNode(true));

            /* The pane cache would otherwise short-circuit a second look at the same
               competency, leaving the fresh modal body on its loading placeholder. */
            loadedCompetencies.delete(competencyId);
            loadCompetencySummary(modalbody.querySelector('.local-dimensions-accordion-body'), competencyId, planId);
        }

        /**
         * The rows the learner can currently see, in their current order.
         *
         * @return {HTMLElement[]}
         */
        function visibleItems() {
            return Array.prototype.slice
                .call(document.querySelectorAll('.local-dimensions-accordion-item'))
                .filter(function(item) {
                    return !item.classList.contains('local-dimensions-hidden');
                });
        }

        /**
         * Build the modal's prev / position / next row, live so its labels can arrive late.
         *
         * @param {HTMLElement} item The item the modal is opening for
         * @param {Object} modal The open modal, replaced when the learner steps
         * @param {number} planId The plan id
         * @return {HTMLElement}
         */
        function buildModalPager(item, modal, planId) {
            const items = visibleItems();
            const index = items.indexOf(item);
            const pager = document.createElement('div');
            pager.className = 'local-dimensions-modal-pager';
            if (items.length < 2 || index === -1) {
                return pager;
            }

            const step = function(offset, icon, key) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'local-dimensions-modal-step';
                const glyph = document.createElement('i');
                glyph.className = 'fa ' + icon;
                glyph.setAttribute('aria-hidden', 'true');
                button.appendChild(glyph);

                const target = items[index + offset];
                if (!target) {
                    button.disabled = true;
                } else {
                    button.addEventListener('click', function() {
                        showCompetencyInModal(modal, target, planId);
                    });
                }
                Str.get_string(key, 'local_dimensions').then(function(label) {
                    button.setAttribute('aria-label', label);
                    return null;
                }).catch(Notification.exception);
                return button;
            };

            const position = document.createElement('span');
            position.className = 'local-dimensions-modal-position';
            Str.get_string('modal_position', 'local_dimensions', {
                index: index + 1,
                total: items.length
            }).then(function(text) {
                position.textContent = text;
                return null;
            }).catch(Notification.exception);

            pager.appendChild(step(-1, 'fa-chevron-left', 'evidence_slider_prev'));
            pager.appendChild(position);
            pager.appendChild(step(1, 'fa-chevron-right', 'evidence_slider_next'));
            return pager;
        }

        /**
         * Wire the list/grid toggle and the grid's card clicks.
         *
         * @param {number} planId The plan id
         */
        function initViewToggle(planId) {
            const container = document.getElementById('local-dimensions-viewplan-accordion');
            if (!container) {
                return;
            }

            document.querySelectorAll('[data-view]').forEach(function(button) {
                button.addEventListener('click', function() {
                    document.querySelectorAll('[data-view]').forEach(function(other) {
                        other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
                    });
                    applyViewMode(button.dataset.view);
                    LearnerPrefs.save({view: button.dataset.view});
                });
            });

            /* One delegated handler rather than one per card: in grid mode the header button
               opens the modal instead of its own pane, and the star keeps working. */
            container.addEventListener('click', function(event) {
                if (!container.classList.contains('local-dimensions-grid-mode')) {
                    return;
                }
                if (event.target.closest('[data-fav-star]')) {
                    return;
                }
                const item = event.target.closest('.local-dimensions-accordion-item');
                if (!item) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                openDetailModal(item, planId);
            }, true);
        }

        /**
         * Wire the no-results "Clear filters" button.
         *
         * Any of the three inputs can produce the empty list, so the reset has to cover all
         * of them: the chip selection, the search box, and the completion tab.
         */
        function initNoResultsClear() {
            const button = document.querySelector('[data-noresults-clear]');
            if (!button) {
                return;
            }
            button.addEventListener('click', function() {
                const chipClear = document.querySelector('[data-chip-clear]');
                if (chipClear) {
                    // Runs the component's own clear, which re-fires the host callback.
                    chipClear.click();
                }

                const input = document.querySelector('.local-dimensions-search-input');
                if (input) {
                    input.value = '';
                }

                const allTab = document.querySelector(FILTER_TAB_SELECTOR + '[data-filter="all"]');
                if (allTab && !allTab.classList.contains('active')) {
                    allTab.click();
                    return;
                }

                applyFilter();
            });
        }

        return {
            init: init
        };
    });
