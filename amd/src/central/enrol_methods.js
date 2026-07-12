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
 * "Enrolment methods" tab of the Manage participants modal: bulk-configure cohort sync or
 * cohort-restricted self enrolment on the courses linked to the template's competencies.
 * Every action queues one background task per (course, method, cohort) combination; rows in
 * that state show Processing (checkbox swapped for a spinner) and a queue poll flips them to
 * their final status. Each row carries BOTH methods' status in its data attributes, so
 * switching the method segment only repaints client-side.
 *
 * @module     local_dimensions/central/enrol_methods
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';
import {notifyError} from 'local_dimensions/central/errors';
import Templates from 'core/templates';
import {getString, getStrings} from 'core/str';
import {add as addToast} from 'core/toast';

const PAGE_COMPETENCIES = 20;
const PAGE_COURSES = 25;
const POLL_MS = 5000;

const SELECTORS = {
    empty: '[data-region="enrol-empty"]',
    disabled: '[data-region="enrol-disabled"]',
    main: '[data-region="enrol-main"]',
    cohort: '[data-region="enrol-cohort"]',
    methodgroup: '[data-region="enrol-method"]',
    role: '[data-region="enrol-role"]',
    hint: '[data-region="enrol-hint"]',
    category: '[data-region="enrol-category"]',
    hiddentoggle: '[data-region="enrol-hidden"]',
    viscount: '[data-region="enrol-viscount"]',
    tree: '[data-region="enrol-tree"]',
    selcount: '[data-region="enrol-selcount"]',
    proccount: '[data-region="enrol-proccount"]',
    proccounttext: '[data-region="enrol-proccount-text"]',
    apply: '[data-action="enrol-apply"]',
    remove: '[data-action="enrol-remove"]',
    rowcheck: 'input[data-rowcheck]',
    row: '.local-dimensions-enrol-row',
    status: '[data-region="row-status"]',
    rolenote: '[data-region="row-role"]',
    spinner: '[data-region="row-spinner"]',
};

const STATUS_BADGES = {
    configured: 'badge bg-success',
    processing: 'badge bg-info',
    notconfigured: 'badge bg-secondary',
};

/**
 * Fetch every static label the tab uses.
 *
 * @return {Promise<Object>} Map of label name -> localised string.
 */
const loadLabels = async() => {
    const [configured, processing, notconfigured, info, loadmore, hintcohort, hintself, methodcohort,
        methodself, competency, opencourse, categoryall, selfcohortonly, category, visible, yes, no,
        inactive, role] = await getStrings([
        {key: 'central_enrol_status_configured', component: 'local_dimensions'},
        {key: 'central_enrol_status_processing', component: 'local_dimensions'},
        {key: 'central_enrol_status_notconfigured', component: 'local_dimensions'},
        {key: 'central_enrol_info', component: 'local_dimensions'},
        {key: 'central_enrol_loadmore', component: 'local_dimensions'},
        {key: 'central_enrol_hint_cohort', component: 'local_dimensions'},
        {key: 'central_enrol_hint_self', component: 'local_dimensions'},
        {key: 'central_enrol_method_cohort', component: 'local_dimensions'},
        {key: 'central_enrol_method_self', component: 'local_dimensions'},
        {key: 'central_enrol_detail_competency', component: 'local_dimensions'},
        {key: 'central_enrol_detail_opencourse', component: 'local_dimensions'},
        {key: 'central_enrol_categoryall', component: 'local_dimensions'},
        {key: 'central_enrol_selfcohortonly', component: 'local_dimensions'},
        {key: 'category', component: 'moodle'},
        {key: 'visible', component: 'moodle'},
        {key: 'yes', component: 'moodle'},
        {key: 'no', component: 'moodle'},
        {key: 'inactive', component: 'moodle'},
        {key: 'role', component: 'moodle'},
    ]);
    return {
        'status_configured': configured,
        'status_processing': processing,
        'status_notconfigured': notconfigured,
        'info': info,
        'loadmore': loadmore,
        'hintcohort': hintcohort,
        'hintself': hintself,
        'methodcohort': methodcohort,
        'methodself': methodself,
        'competency': competency,
        'opencourse': opencourse,
        'categoryall': categoryall,
        'selfcohortonly': selfcohortonly,
        'category': category,
        'visible': visible,
        'yes': yes,
        'no': no,
        'inactive': inactive,
        'role': role,
    };
};

/**
 * Replace a select's options from a list of {value, label}.
 *
 * @param {HTMLSelectElement} select
 * @param {Array} options
 * @return {void}
 */
const fillSelect = (select, options) => {
    select.textContent = '';
    options.forEach((opt) => {
        const el = document.createElement('option');
        el.value = String(opt.value);
        el.textContent = opt.label;
        select.appendChild(el);
    });
};

/**
 * The row's status for the currently selected method.
 *
 * @param {Object} state Tab state.
 * @param {HTMLElement} row Course row.
 * @return {String} configured|processing|notconfigured.
 */
const rowStatus = (state, row) => {
    return state.method === 'cohort' ? row.dataset.cohortStatus : row.dataset.selfStatus;
};

/**
 * Store the row's status for the currently selected method.
 *
 * @param {Object} state Tab state.
 * @param {HTMLElement} row Course row.
 * @param {String} status New status.
 * @return {void}
 */
const setRowStatus = (state, row, status) => {
    if (state.method === 'cohort') {
        row.dataset.cohortStatus = status;
    } else {
        row.dataset.selfStatus = status;
    }
};

/**
 * Paint a row's status pill, the assigned-role note and the checkbox/spinner swap.
 *
 * @param {Object} state Tab state.
 * @param {HTMLElement} row Course row.
 * @return {void}
 */
const paintRow = (state, row) => {
    const status = rowStatus(state, row) || 'notconfigured';
    const pill = row.querySelector(SELECTORS.status);
    pill.className = STATUS_BADGES[status] || STATUS_BADGES.notconfigured;
    pill.textContent = state.labels['status_' + status] || '';
    const rolename = (state.method === 'cohort' ? row.dataset.cohortRole : row.dataset.selfRole) || '';
    const rolenote = row.querySelector(SELECTORS.rolenote);
    rolenote.hidden = !(status === 'configured' && rolename);
    rolenote.textContent = rolenote.hidden ? '' : rolename;
    const processing = status === 'processing';
    const check = row.querySelector(SELECTORS.rowcheck);
    check.hidden = processing;
    row.querySelector(SELECTORS.spinner).hidden = !processing;
    if (processing) {
        check.checked = false;
    }
};

/**
 * Select or unselect a course, mirroring the checkbox on its twin rows (a course can appear
 * under more than one competency).
 *
 * @param {Object} state Tab state.
 * @param {Number} courseid Course id.
 * @param {Boolean} selected Whether the course is selected.
 * @return {void}
 */
const setCourseSelected = (state, courseid, selected) => {
    if (selected) {
        state.selected.add(courseid);
    } else {
        state.selected.delete(courseid);
    }
    state.root.querySelectorAll(`${SELECTORS.row}[data-courseid="${courseid}"] ${SELECTORS.rowcheck}`)
        .forEach((check) => {
            check.checked = selected;
        });
};

/**
 * Refresh the footer counters and the action buttons' disabled state.
 *
 * @param {Object} state Tab state.
 * @return {void}
 */
const updateFooter = (state) => {
    getString('central_enrol_selcount', 'local_dimensions', state.selected.size)
        .then((text) => {
            state.root.querySelector(SELECTORS.selcount).textContent = text;
            return null;
        })
        .catch(notifyError);
    const proc = state.pending.size;
    state.root.querySelector(SELECTORS.proccount).hidden = proc === 0;
    if (proc > 0) {
        getString('central_enrol_proccount', 'local_dimensions', proc)
            .then((text) => {
                state.root.querySelector(SELECTORS.proccounttext).textContent = text;
                return null;
            })
            .catch(notifyError);
    }
    const disabled = state.selected.size === 0;
    state.root.querySelector(SELECTORS.apply).disabled = disabled;
    state.root.querySelector(SELECTORS.remove).disabled = disabled;
};

/**
 * Build a "Load more" link-button.
 *
 * @param {Object} state Tab state.
 * @param {String} action Value for data-action.
 * @param {Object} dataset Extra data attributes.
 * @return {HTMLButtonElement}
 */
const makeLoadMore = (state, action, dataset) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-link btn-sm p-0 d-block';
    button.dataset.action = action;
    Object.keys(dataset).forEach((key) => {
        button.dataset[key] = String(dataset[key]);
    });
    button.textContent = state.labels.loadmore;
    return button;
};

/**
 * Render one competency group's HTML.
 *
 * @param {Object} state Tab state.
 * @param {Object} item Competency item from the web service.
 * @return {Promise<String>}
 */
const renderGroupHtml = async(state, item) => {
    const [countlabel, selectalllabel] = await Promise.all([
        item.coursecount === 1
            ? getString('central_enrol_coursesone', 'local_dimensions')
            : getString('central_enrol_courses', 'local_dimensions', item.coursecount),
        getString('central_enrol_selectall', 'local_dimensions', item.shortname),
    ]);
    const {html} = await Templates.renderForPromise('local_dimensions/central/enrol_group', {
        competencyid: item.competencyid,
        name: item.shortname,
        countlabel: countlabel,
        selectalllabel: selectalllabel,
    });
    return html;
};

/**
 * Render one course row's HTML.
 *
 * @param {Object} state Tab state.
 * @param {Object} item Course item from the web service.
 * @param {String} competencyname Owning competency name (for the details modal).
 * @return {Promise<String>}
 */
const renderRowHtml = async(state, item, competencyname) => {
    const selectlabel = await getString('central_enrol_selectcourse', 'local_dimensions', item.shortname);
    const context = Object.assign({}, item, {
        competencyname: competencyname,
        selectlabel: selectlabel,
        infolabel: state.labels.info,
    });
    const {html} = await Templates.renderForPromise('local_dimensions/central/enrol_row', context);
    return html;
};

/**
 * Fetch and append one page of competency groups, plus a "Load more" when needed.
 *
 * @param {Object} state Tab state.
 * @param {Number} offset Pagination offset.
 * @param {Object|null} preloaded Response already fetched by mount (first page only).
 * @return {Promise<void>}
 */
const loadCompetencies = async(state, offset, preloaded = null) => {
    const data = preloaded || await Ajax.call([{
        methodname: 'local_dimensions_list_enrol_competencies',
        args: {
            templateid: state.templateid,
            categoryid: state.categoryid,
            includehidden: state.showhidden,
            limitfrom: offset,
            limitnum: PAGE_COMPETENCIES,
        },
    }])[0];
    const tree = state.root.querySelector(SELECTORS.tree);
    const parts = await Promise.all(data.items.map((item) => renderGroupHtml(state, item)));
    await Templates.appendNodeContents(tree, parts.join(''), '');
    const shown = offset + data.items.length;
    if (shown < data.total) {
        tree.appendChild(makeLoadMore(state, 'enrol-morecomps', {offset: shown}));
    }
    getString('central_enrol_viscount', 'local_dimensions', data.totalcourses)
        .then((text) => {
            state.root.querySelector(SELECTORS.viscount).textContent = text;
            return null;
        })
        .catch(notifyError);
};

/**
 * Fetch and append one page of course rows into a competency group.
 *
 * @param {Object} state Tab state.
 * @param {Number} competencyid Competency id.
 * @param {Number} offset Pagination offset.
 * @return {Promise<void>}
 */
const loadCourses = async(state, competencyid, offset) => {
    const data = await Ajax.call([{
        methodname: 'local_dimensions_list_enrol_courses',
        args: {
            templateid: state.templateid,
            competencyid: competencyid,
            cohortid: state.cohortid,
            categoryid: state.categoryid,
            includehidden: state.showhidden,
            limitfrom: offset,
            limitnum: PAGE_COURSES,
        },
    }])[0];
    const group = state.root.querySelector(`[data-group="${competencyid}"]`);
    const children = group.querySelector(`[data-children="${competencyid}"]`);
    const name = group.dataset.name;
    const parts = await Promise.all(data.items.map((item) => renderRowHtml(state, item, name)));
    await Templates.appendNodeContents(children, parts.join(''), '');
    children.querySelectorAll(SELECTORS.row).forEach((row) => {
        paintRow(state, row);
        const courseid = Number(row.dataset.courseid);
        if (rowStatus(state, row) === 'processing') {
            state.pending.add(courseid);
        } else {
            row.querySelector(SELECTORS.rowcheck).checked = state.selected.has(courseid);
        }
    });
    const shown = offset + data.items.length;
    if (shown < data.total) {
        children.appendChild(makeLoadMore(state, 'enrol-morecourses', {competencyid: competencyid, offset: shown}));
    }
    updateFooter(state);
    ensurePolling(state);
};

/**
 * Expand or collapse a competency group, lazily loading its courses on first expansion.
 *
 * @param {Object} state Tab state.
 * @param {HTMLElement} button The group toggle button.
 * @return {Promise<void>}
 */
const toggleGroup = async(state, button) => {
    const competencyid = Number(button.dataset.competencyid);
    const children = state.root.querySelector(`[data-children="${competencyid}"]`);
    // The chevron rotation and the reveal animation are pure CSS, keyed on aria-expanded.
    if (button.getAttribute('aria-expanded') === 'true') {
        children.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        return;
    }
    button.setAttribute('aria-expanded', 'true');
    children.hidden = false;
    if (children.dataset.loaded !== '1') {
        children.dataset.loaded = '1';
        try {
            await loadCourses(state, competencyid, 0);
        } catch (error) {
            children.dataset.loaded = '0';
            throw error;
        }
    }
};

/**
 * Mark a course's rows as processing for the current method and drop it from the selection.
 *
 * @param {Object} state Tab state.
 * @param {Number} courseid Course id.
 * @return {void}
 */
const markProcessing = (state, courseid) => {
    state.pending.add(courseid);
    state.selected.delete(courseid);
    state.root.querySelectorAll(`${SELECTORS.row}[data-courseid="${courseid}"]`).forEach((row) => {
        setRowStatus(state, row, 'processing');
        paintRow(state, row);
    });
};

/**
 * Queue the apply/remove action for the selected courses (remove asks for confirmation).
 *
 * @param {Object} state Tab state.
 * @param {String} action 'apply' or 'remove'.
 * @return {Promise<void>}
 */
const queueAction = async(state, action) => {
    const courseids = [...state.selected];
    if (!courseids.length) {
        return;
    }
    if (action === 'remove') {
        const [title, body, label] = await Promise.all([
            getString('central_enrol_confirm_remove_title', 'local_dimensions'),
            getString('central_enrol_confirm_remove', 'local_dimensions', courseids.length),
            getString('remove', 'moodle'),
        ]);
        try {
            await Notification.saveCancelPromise(title, body, label);
        } catch (e) {
            return;
        }
    }
    const data = await Ajax.call([{
        methodname: 'local_dimensions_queue_enrol_action',
        args: {
            templateid: state.templateid,
            cohortid: state.cohortid,
            method: state.method,
            roleid: state.roleid,
            action: action,
            courseids: courseids,
        },
    }])[0];
    data.results.forEach((result) => {
        if (result.status !== 'skipped') {
            markProcessing(state, Number(result.courseid));
        }
    });
    updateFooter(state);
    ensurePolling(state);
    addToast(await getString('central_enrol_apply_queued', 'local_dimensions', data.queued));
};

/**
 * Poll the task queue once and flip finished rows to their fresh status.
 *
 * @param {Object} state Tab state.
 * @return {Promise<void>}
 */
const poll = async(state) => {
    if (!state.root.isConnected || !state.pending.size) {
        stopPolling(state);
        return;
    }
    const tracked = [...state.pending];
    const data = await Ajax.call([{
        methodname: 'local_dimensions_get_enrol_queue_status',
        args: {templateid: state.templateid, cohortid: state.cohortid, method: state.method, courseids: tracked},
    }])[0];
    const stillpending = new Set(data.pendingcourseids.map(Number));
    const configured = new Map(data.items.map((item) => [Number(item.courseid), Boolean(item.configured)]));
    tracked.forEach((courseid) => {
        if (stillpending.has(courseid)) {
            return;
        }
        state.pending.delete(courseid);
        const status = configured.get(courseid) ? 'configured' : 'notconfigured';
        state.root.querySelectorAll(`${SELECTORS.row}[data-courseid="${courseid}"]`).forEach((row) => {
            setRowStatus(state, row, status);
            paintRow(state, row);
            row.animate([{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}], {duration: 1500});
        });
    });
    updateFooter(state);
    if (!state.pending.size) {
        stopPolling(state);
    }
};

/**
 * Start the queue poll when there are pending combinations and no timer yet.
 *
 * @param {Object} state Tab state.
 * @return {void}
 */
const ensurePolling = (state) => {
    if (state.polltimer || !state.pending.size) {
        return;
    }
    state.polltimer = setInterval(() => {
        poll(state).catch((error) => {
            stopPolling(state);
            notifyError(error);
        });
    }, POLL_MS);
};

/**
 * Stop the queue poll.
 *
 * @param {Object} state Tab state.
 * @return {void}
 */
const stopPolling = (state) => {
    if (state.polltimer) {
        clearInterval(state.polltimer);
        state.polltimer = null;
    }
};

/**
 * Switch the selected method: repaint every row from its data attributes (no refetch) and
 * rebuild the pending set for the new method.
 *
 * @param {Object} state Tab state.
 * @param {String} method 'cohort' or 'self'.
 * @return {void}
 */
const applyMethodChange = (state, method) => {
    state.method = method;
    state.root.querySelectorAll(`${SELECTORS.methodgroup} button`).forEach((button) => {
        const on = button.dataset.method === method;
        button.classList.toggle('active', on);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    state.root.querySelector(SELECTORS.hint).textContent =
        method === 'cohort' ? state.labels.hintcohort : state.labels.hintself;
    state.pending.clear();
    state.root.querySelectorAll(SELECTORS.row).forEach((row) => {
        paintRow(state, row);
        if (rowStatus(state, row) === 'processing') {
            const courseid = Number(row.dataset.courseid);
            state.pending.add(courseid);
            state.selected.delete(courseid);
        }
    });
    updateFooter(state);
    ensurePolling(state);
};

/**
 * Clear the tree and reload the first competency page (cohort or filter changed).
 *
 * @param {Object} state Tab state.
 * @return {Promise<void>}
 */
const reload = async(state) => {
    stopPolling(state);
    state.pending.clear();
    state.selected.clear();
    state.root.querySelector(SELECTORS.tree).textContent = '';
    updateFooter(state);
    await loadCompetencies(state, 0);
};

/**
 * A status line for the details modal: for self enrolment it leads with the cohort-only
 * nature of the instance; when configured it appends the date, an Inactive marker when the
 * instance is disabled, and the assigned role.
 *
 * @param {Object} state Tab state.
 * @param {HTMLElement} row Course row.
 * @param {String} method 'cohort' or 'self'.
 * @return {String}
 */
const statusLine = (state, row, method) => {
    const data = row.dataset;
    const cohort = method === 'cohort';
    const status = (cohort ? data.cohortStatus : data.selfStatus) || 'notconfigured';
    const since = cohort ? data.cohortSince : data.selfSince;
    const active = (cohort ? data.cohortActive : data.selfActive) === '1';
    const rolename = cohort ? data.cohortRole : data.selfRole;
    const parts = [];
    if (!cohort) {
        parts.push(state.labels.selfcohortonly);
    }
    let statustext = state.labels['status_' + status] || '';
    if (status === 'configured' && since) {
        statustext += ' ' + since;
    }
    parts.push(statustext);
    if (status === 'configured' && !active) {
        parts.push(state.labels.inactive);
    }
    if (status === 'configured' && rolename) {
        parts.push(state.labels.role + ': ' + rolename);
    }
    return parts.join(' · ');
};

/**
 * Open the read-only course details modal for a row.
 *
 * @param {Object} state Tab state.
 * @param {HTMLElement} row Course row.
 * @return {Promise<void>}
 */
const showDetail = async(state, row) => {
    const data = row.dataset;
    const cohortline = statusLine(state, row, 'cohort');
    const selfline = statusLine(state, row, 'self');
    const {html} = await Templates.renderForPromise('local_dimensions/central/enrol_detail', {
        fullname: data.fullname,
        shortname: data.shortname,
        categorylabel: state.labels.category,
        categoryname: data.categoryname,
        visiblelabel: state.labels.visible,
        visiblevalue: data.visible === '1' ? state.labels.yes : state.labels.no,
        competencylabel: state.labels.competency,
        competencyname: data.competencyname,
        cohortlabel: state.labels.methodcohort,
        cohortline: cohortline,
        selflabel: state.labels.methodself,
        selfline: selfline,
        courseurl: data.courseurl,
        opencourselabel: state.labels.opencourse,
    });
    const modal = await Modal.create({title: data.fullname, body: html, large: true});
    modal.setRemoveOnClose(true);
    modal.show();
};

/**
 * Apply the bootstrap data to the config bar: role and category selects, method availability.
 *
 * @param {Object} state Tab state.
 * @param {Object} bootstrap Bootstrap payload from list_enrol_competencies.
 * @return {void}
 */
const applyBootstrap = (state, bootstrap) => {
    const roleselect = state.root.querySelector(SELECTORS.role);
    fillSelect(roleselect, bootstrap.roles.map((role) => ({value: role.id, label: role.name})));
    const wantedrole = state.roleid || Number(bootstrap.defaultroleid);
    if (wantedrole && bootstrap.roles.some((role) => Number(role.id) === wantedrole)) {
        roleselect.value = String(wantedrole);
    } else if (bootstrap.defaultroleid) {
        roleselect.value = String(bootstrap.defaultroleid);
    }
    state.roleid = Number(roleselect.value) || 0;

    const options = [{value: 0, label: state.labels.categoryall}];
    bootstrap.categories.forEach((cat) => options.push({value: cat.id, label: cat.name}));
    const categoryselect = state.root.querySelector(SELECTORS.category);
    fillSelect(categoryselect, options);
    if (state.categoryid && bootstrap.categories.some((cat) => Number(cat.id) === state.categoryid)) {
        categoryselect.value = String(state.categoryid);
    }
    state.categoryid = Number(categoryselect.value) || 0;

    const buttons = state.root.querySelectorAll(`${SELECTORS.methodgroup} button`);
    buttons.forEach((button) => {
        const enabled = button.dataset.method === 'cohort' ? bootstrap.cohortenabled : bootstrap.selfenabled;
        button.disabled = !enabled;
    });
    if (!bootstrap.cohortenabled && bootstrap.selfenabled && state.method === 'cohort') {
        applyMethodChange(state, 'self');
    }
};

/**
 * Fetch the cohorts and the first competency page, then populate the whole pane. Runs on
 * mount and again on the Refresh action, so a cohort attached meanwhile on the Cohorts tab
 * appears without closing the modal. Keeps the current cohort/role/category when possible.
 *
 * @param {Object} state Tab state.
 * @return {Promise<void>}
 */
const init = async(state) => {
    stopPolling(state);
    state.pending.clear();
    state.selected.clear();
    state.root.querySelector(SELECTORS.tree).textContent = '';
    const [cohortdata, compdata] = await Promise.all(Ajax.call([
        {
            methodname: 'local_dimensions_list_template_cohorts',
            args: {templateid: state.templateid},
        },
        {
            methodname: 'local_dimensions_list_enrol_competencies',
            args: {
                templateid: state.templateid,
                categoryid: state.categoryid,
                includehidden: state.showhidden,
                includebootstrap: true,
                limitnum: PAGE_COMPETENCIES,
            },
        },
    ]));
    const empty = state.root.querySelector(SELECTORS.empty);
    const disabled = state.root.querySelector(SELECTORS.disabled);
    const main = state.root.querySelector(SELECTORS.main);
    const bootstrap = compdata.bootstrap || {
        roles: [], defaultroleid: 0, categories: [], cohortenabled: true, selfenabled: true,
    };
    // Neither method is enabled sitewide: the whole tab is inert, warn instead. The header
    // link to the enrol-plugins admin page (site admins only) is where this gets fixed.
    if (!bootstrap.cohortenabled && !bootstrap.selfenabled) {
        disabled.hidden = false;
        empty.hidden = true;
        main.hidden = true;
        return;
    }
    disabled.hidden = true;
    if (!cohortdata.cohorts.length) {
        empty.hidden = false;
        main.hidden = true;
        return;
    }
    empty.hidden = true;
    main.hidden = false;

    const cohortselect = state.root.querySelector(SELECTORS.cohort);
    fillSelect(cohortselect, cohortdata.cohorts.map((cohort) => ({value: cohort.cohortid, label: cohort.name})));
    if (state.cohortid && cohortdata.cohorts.some((cohort) => Number(cohort.cohortid) === state.cohortid)) {
        cohortselect.value = String(state.cohortid);
    }
    state.cohortid = Number(cohortselect.value) || 0;
    applyBootstrap(state, bootstrap);
    state.root.querySelector(SELECTORS.hint).textContent =
        state.method === 'cohort' ? state.labels.hintcohort : state.labels.hintself;
    updateFooter(state);
    await loadCompetencies(state, 0, compdata);
};

/**
 * Wire the pane's delegated click and change handlers.
 *
 * @param {Object} state Tab state.
 * @return {void}
 */
const wireEvents = (state) => {
    state.root.addEventListener('click', (event) => {
        if (event.target.closest('[data-action="enrol-refresh"]')) {
            init(state).catch(notifyError);
            return;
        }
        const toggle = event.target.closest('[data-action="enrol-toggle"]');
        if (toggle) {
            toggleGroup(state, toggle).catch(notifyError);
            return;
        }
        const info = event.target.closest('[data-action="enrol-info"]');
        if (info) {
            showDetail(state, info.closest(SELECTORS.row)).catch(notifyError);
            return;
        }
        const morecomps = event.target.closest('[data-action="enrol-morecomps"]');
        if (morecomps) {
            morecomps.remove();
            loadCompetencies(state, Number(morecomps.dataset.offset)).catch(notifyError);
            return;
        }
        const morecourses = event.target.closest('[data-action="enrol-morecourses"]');
        if (morecourses) {
            morecourses.remove();
            loadCourses(state, Number(morecourses.dataset.competencyid), Number(morecourses.dataset.offset))
                .catch(notifyError);
            return;
        }
        const methodbutton = event.target.closest(`${SELECTORS.methodgroup} button[data-method]`);
        if (methodbutton && !methodbutton.disabled) {
            applyMethodChange(state, methodbutton.dataset.method);
            return;
        }
        if (event.target.closest(SELECTORS.apply)) {
            queueAction(state, 'apply').catch(notifyError);
            return;
        }
        if (event.target.closest(SELECTORS.remove)) {
            queueAction(state, 'remove').catch(notifyError);
        }
    });

    state.root.addEventListener('change', (event) => {
        const target = event.target;
        if (target.matches(SELECTORS.cohort)) {
            state.cohortid = Number(target.value) || 0;
            reload(state).catch(notifyError);
        } else if (target.matches(SELECTORS.role)) {
            state.roleid = Number(target.value) || 0;
        } else if (target.matches(SELECTORS.category)) {
            state.categoryid = Number(target.value) || 0;
            reload(state).catch(notifyError);
        } else if (target.matches(SELECTORS.hiddentoggle)) {
            state.showhidden = target.checked;
            reload(state).catch(notifyError);
        } else if (target.matches(SELECTORS.rowcheck)) {
            const row = target.closest(SELECTORS.row);
            setCourseSelected(state, Number(row.dataset.courseid), target.checked);
            updateFooter(state);
        } else if (target.matches('input[data-groupcheck]')) {
            const children = state.root.querySelector(`[data-children="${target.dataset.groupcheck}"]`);
            children.querySelectorAll(SELECTORS.row).forEach((row) => {
                if (rowStatus(state, row) !== 'processing') {
                    setCourseSelected(state, Number(row.dataset.courseid), target.checked);
                }
            });
            updateFooter(state);
        }
    });
};

/**
 * Mount the enrolment methods manager into its tab pane.
 *
 * @param {HTMLElement} container The pane element.
 * @param {Object} opts Options: templateid, contextid.
 * @return {Promise<void>}
 */
export const mount = async(container, opts) => {
    const labels = await loadLabels();
    const {html} = await Templates.renderForPromise('local_dimensions/central/enrol_methods', {});
    await Templates.replaceNodeContents(container, html, '');

    const state = {
        templateid: Number(opts.templateid),
        root: container,
        labels: labels,
        method: 'cohort',
        cohortid: 0,
        roleid: 0,
        categoryid: 0,
        showhidden: false,
        selected: new Set(),
        pending: new Set(),
        polltimer: null,
    };

    wireEvents(state);
    await init(state);
};
