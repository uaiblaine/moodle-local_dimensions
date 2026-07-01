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
 * "Courses & activities" modal: manage a competency's course and activity links, each with its own
 * rule outcome. Courses load paginated; a course's activities load lazily on expand (one read returns
 * linked + available). Outcome selects save on change. Rows are built in JS to avoid a template render
 * per row. Closing the modal triggers the caller's onClose so the Structure tree's course count refreshes.
 *
 * @module     local_dimensions/central/competency_links
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {enhance} from 'core/form-autocomplete';
import {getString} from 'core/str';
import {add as addToast, addToastRegion} from 'core/toast';

const DATASOURCE = 'local_dimensions/central/course_datasource';
const PAGE_SIZE = 25;

const SELECTORS = {
    region: '[data-region="competency-links"]',
    hiddenFramework: '[data-region="hiddenframework"]',
    courseAdd: '[data-region="course-add"]',
    courseRows: '[data-region="course-rows"]',
    courseEmpty: '[data-region="course-empty"]',
    loadMoreWrap: '[data-region="loadmore-wrap"]',
};

/**
 * Build a labelled <select> of outcome options.
 *
 * @param {Array} options List of {value, label}.
 * @param {Number} selected The selected value.
 * @param {String} arialabel Accessible label for the select.
 * @param {Boolean} disabled Whether the select is disabled.
 * @return {HTMLSelectElement}
 */
const outcomeSelect = (options, selected, arialabel, disabled) => {
    const select = document.createElement('select');
    select.className = 'form-select form-select-sm w-auto d-inline-block';
    select.setAttribute('aria-label', arialabel);
    select.disabled = disabled;
    options.forEach((option) => {
        const node = document.createElement('option');
        node.value = String(option.value);
        node.textContent = option.label;
        if (Number(option.value) === Number(selected)) {
            node.selected = true;
        }
        select.appendChild(node);
    });
    return select;
};

/**
 * Build an icon-only action button.
 *
 * @param {String} action The data-action value.
 * @param {String} icon FontAwesome class suffix (e.g. "trash").
 * @param {String} label Accessible label.
 * @return {HTMLButtonElement}
 */
const iconButton = (action, icon, label) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-link text-danger p-0 ms-2';
    button.dataset.action = action;
    button.title = label;
    const glyph = document.createElement('i');
    glyph.className = `fa fa-${icon}`;
    glyph.setAttribute('aria-hidden', 'true');
    const sr = document.createElement('span');
    sr.className = 'sr-only';
    sr.textContent = label;
    button.appendChild(glyph);
    button.appendChild(sr);
    return button;
};

/**
 * Briefly highlight an element so an in-place change is visible without leaving the modal.
 *
 * @param {HTMLElement} el The element to flash.
 */
const flash = (el) => {
    if (!el || typeof el.animate !== 'function') {
        return;
    }
    el.animate(
        [{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}],
        {duration: 1500, easing: 'ease-out'}
    );
};

/**
 * Build one activity row.
 *
 * @param {Object} state Modal state.
 * @param {Object} module {cmid, name, modname, iconurl, ruleoutcome, canmanage}.
 * @return {HTMLElement}
 */
const makeModuleRow = (state, module) => {
    const row = document.createElement('div');
    row.className = 'd-flex align-items-center py-1 ps-4';
    row.dataset.cmid = String(module.cmid);

    const name = document.createElement('span');
    name.className = 'flex-grow-1 text-truncate';
    name.textContent = module.name;

    const select = outcomeSelect(state.moduleoutcomes, module.ruleoutcome, state.outcomelabel, !module.canmanage);
    select.dataset.role = 'module-outcome';
    select.name = 'module-outcome';

    row.appendChild(name);
    row.appendChild(select);
    if (module.canmanage) {
        row.appendChild(iconButton('remove-module', 'trash', state.removeactivitylabel));
    }
    return row;
};

/**
 * Build the "add activity" inline control from a course's available modules.
 *
 * @param {Object} state Modal state.
 * @param {Array} available List of {cmid, name, modname}.
 * @return {HTMLElement}
 */
const makeAddActivity = (state, available) => {
    const wrap = document.createElement('div');
    wrap.className = 'd-flex align-items-center py-1 ps-4';
    if (!available.length) {
        return wrap;
    }
    const select = document.createElement('select');
    select.className = 'form-select form-select-sm w-auto d-inline-block';
    select.dataset.role = 'activity-add';
    select.name = 'activity-add';
    select.setAttribute('aria-label', state.addactivitylabel);
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = state.addactivitylabel;
    select.appendChild(placeholder);
    available.forEach((module) => {
        const node = document.createElement('option');
        node.value = String(module.cmid);
        node.textContent = module.name;
        select.appendChild(node);
    });
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-link ms-2';
    button.dataset.action = 'add-activity';
    button.textContent = state.addactivitylabel;
    wrap.appendChild(select);
    wrap.appendChild(button);
    return wrap;
};

/**
 * Build one course row (header + collapsed activities container).
 *
 * @param {Object} state Modal state.
 * @param {Object} course {courseid, fullname, ruleoutcome, modulecount, canmanage}.
 * @return {HTMLElement}
 */
const makeCourseRow = (state, course) => {
    const wrap = document.createElement('div');
    wrap.className = 'border-bottom py-1';
    wrap.dataset.courseid = String(course.courseid);

    const header = document.createElement('div');
    header.className = 'd-flex align-items-center';

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'btn btn-sm btn-link p-0 me-1';
    toggle.dataset.action = 'toggle-course';
    toggle.setAttribute('aria-expanded', 'false');
    const chevron = document.createElement('i');
    chevron.className = 'fa fa-chevron-right';
    chevron.setAttribute('aria-hidden', 'true');
    toggle.appendChild(chevron);

    const name = document.createElement('span');
    name.className = 'flex-grow-1 text-truncate';
    name.textContent = course.fullname;

    const select = outcomeSelect(state.courseoutcomes, course.ruleoutcome, state.outcomelabel, !course.canmanage);
    select.dataset.role = 'course-outcome';
    select.name = 'course-outcome';

    header.appendChild(toggle);
    header.appendChild(name);
    header.appendChild(select);
    if (course.canmanage) {
        header.appendChild(iconButton('remove-course', 'trash', state.removecourselabel));
    }

    const children = document.createElement('div');
    children.dataset.role = 'activities';
    children.dataset.loaded = '0';
    children.hidden = true;

    wrap.appendChild(header);
    wrap.appendChild(children);
    return wrap;
};

/**
 * Fetch a page of linked courses and append them.
 *
 * @param {Object} state Modal state.
 * @return {Promise<void>}
 */
const loadCourses = async(state) => {
    const response = await Ajax.call([{
        methodname: 'local_dimensions_get_competency_links',
        args: {competencyid: state.competencyid, query: '', limitfrom: state.offset, limitnum: PAGE_SIZE},
    }])[0];

    state.hiddenframeworkEl.hidden = response.canlink;
    state.addsel.disabled = !response.canlink;

    response.items.forEach((course) => {
        state.rowsEl.appendChild(makeCourseRow(state, course));
        state.excluded.add(String(course.courseid));
    });
    state.addsel.dataset.exclude = Array.from(state.excluded).join(',');
    state.offset += response.items.length;

    state.emptyEl.hidden = !(state.offset === 0 && response.total === 0);
    state.loadMoreEl.hidden = state.offset >= response.total;
};

/**
 * Lazily load a course's activities into its container.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} courseEl The course row.
 * @return {Promise<void>}
 */
const loadActivities = async(state, courseEl) => {
    const container = courseEl.querySelector('[data-role="activities"]');
    const courseid = Number(courseEl.dataset.courseid);
    const response = await Ajax.call([{
        methodname: 'local_dimensions_get_competency_module_links',
        args: {competencyid: state.competencyid, courseid: courseid},
    }])[0];

    container.textContent = '';
    if (!response.linked.length) {
        const none = document.createElement('div');
        none.className = 'text-muted small ps-4 py-1';
        none.textContent = state.noactivitieslabel;
        container.appendChild(none);
    } else {
        response.linked.forEach((module) => container.appendChild(makeModuleRow(state, module)));
    }
    if (response.canmanage) {
        container.appendChild(makeAddActivity(state, response.available));
    }
    container.dataset.loaded = '1';
};

/**
 * Toggle a course row's activities, loading them on first open.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} courseEl The course row.
 * @param {HTMLElement} toggle The toggle button.
 * @return {Promise<void>}
 */
const toggleCourse = async(state, courseEl, toggle) => {
    const container = courseEl.querySelector('[data-role="activities"]');
    const icon = toggle.querySelector('i');
    if (!container.hidden) {
        container.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        icon.className = 'fa fa-chevron-right';
        return;
    }
    if (container.dataset.loaded !== '1') {
        await loadActivities(state, courseEl);
    }
    container.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    icon.className = 'fa fa-chevron-down';
};

/**
 * Remove a course link after a confirm.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} courseEl The course row.
 * @return {Promise<void>}
 */
const removeCourse = async(state, courseEl) => {
    const courseid = Number(courseEl.dataset.courseid);
    const name = courseEl.querySelector('span').textContent;
    const [title, body] = await Promise.all([
        getString('central_links_removecourse', 'local_dimensions'),
        getString('central_links_removecourse_confirm', 'local_dimensions', name),
    ]);
    try {
        await Notification.deleteCancelPromise(title, body);
    } catch (e) {
        return;
    }
    await Ajax.call([{
        methodname: 'local_dimensions_unlink_competency_course',
        args: {competencyid: state.competencyid, courseid: courseid},
    }])[0];
    state.excluded.delete(String(courseid));
    state.addsel.dataset.exclude = Array.from(state.excluded).join(',');
    courseEl.remove();
};

/**
 * Remove an activity link after a confirm.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} moduleEl The activity row.
 * @return {Promise<void>}
 */
const removeModule = async(state, moduleEl) => {
    const cmid = Number(moduleEl.dataset.cmid);
    const name = moduleEl.querySelector('span').textContent;
    const [title, body] = await Promise.all([
        getString('central_links_removeactivity', 'local_dimensions'),
        getString('central_links_removeactivity_confirm', 'local_dimensions', name),
    ]);
    try {
        await Notification.deleteCancelPromise(title, body);
    } catch (e) {
        return;
    }
    await Ajax.call([{
        methodname: 'local_dimensions_unlink_competency_module',
        args: {competencyid: state.competencyid, cmid: cmid},
    }])[0];
    const courseEl = moduleEl.closest('[data-courseid]');
    moduleEl.remove();
    const container = courseEl.querySelector('[data-role="activities"]');
    container.dataset.loaded = '0';
    await loadActivities(state, courseEl);
};

/**
 * Add the selected activity to its course.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} courseEl The course row.
 * @return {Promise<void>}
 */
const addActivity = async(state, courseEl) => {
    const select = courseEl.querySelector('[data-role="activity-add"]');
    const cmid = Number(select.value);
    if (!cmid) {
        return;
    }
    await Ajax.call([{
        methodname: 'local_dimensions_link_competency_module',
        args: {competencyid: state.competencyid, cmid: cmid},
    }])[0];
    const container = courseEl.querySelector('[data-role="activities"]');
    container.dataset.loaded = '0';
    await loadActivities(state, courseEl);
    flash(container.querySelector('[data-cmid="' + cmid + '"]'));
    addToast(state.activityaddedlabel);
};

/**
 * Save a changed outcome select (course or activity level).
 *
 * @param {Object} state Modal state.
 * @param {HTMLSelectElement} select The changed select.
 * @return {Promise<void>}
 */
const saveOutcome = (state, select) => {
    const value = Number(select.value);
    if (select.dataset.role === 'course-outcome') {
        const courseid = Number(select.closest('[data-courseid]').dataset.courseid);
        return Ajax.call([{
            methodname: 'local_dimensions_set_course_link_outcome',
            args: {competencyid: state.competencyid, courseid: courseid, ruleoutcome: value},
        }])[0];
    }
    const cmid = Number(select.closest('[data-cmid]').dataset.cmid);
    return Ajax.call([{
        methodname: 'local_dimensions_set_module_link_outcome',
        args: {competencyid: state.competencyid, cmid: cmid, ruleoutcome: value},
    }])[0];
};

/**
 * Add the selected course (from the autocomplete), then re-render the body to reset the picker.
 *
 * @param {Object} state Modal state.
 * @return {void}
 */
const onAddCourse = (state) => {
    const courseid = Number(state.addsel.value);
    if (!courseid) {
        return;
    }
    Ajax.call([{
        methodname: 'local_dimensions_link_competency_course',
        args: {competencyid: state.competencyid, courseid: courseid},
    }])[0]
        .then((course) => {
            const row = makeCourseRow(state, course);
            state.rowsEl.appendChild(row);
            row.scrollIntoView({block: 'nearest'});
            flash(row);
            state.excluded.add(String(course.courseid));
            state.emptyEl.hidden = true;
            addToast(state.courseaddedlabel);
            return Templates.replaceNodeContents(state.addsel.parentElement, state.addshtml, '');
        })
        .then(() => bindPicker(state))
        .catch(Notification.exception);
};

/**
 * Route a click inside the body to its handler.
 *
 * @param {Object} state Modal state.
 * @param {Event} event The click event.
 * @return {void}
 */
const onClick = (state, event) => {
    const toggle = event.target.closest('[data-action="toggle-course"]');
    if (toggle) {
        toggleCourse(state, toggle.closest('[data-courseid]'), toggle).catch(Notification.exception);
        return;
    }
    if (event.target.closest('[data-action="remove-course"]')) {
        removeCourse(state, event.target.closest('[data-courseid]')).catch(Notification.exception);
        return;
    }
    if (event.target.closest('[data-action="remove-module"]')) {
        removeModule(state, event.target.closest('[data-cmid]')).catch(Notification.exception);
        return;
    }
    if (event.target.closest('[data-action="add-activity"]')) {
        addActivity(state, event.target.closest('[data-courseid]')).catch(Notification.exception);
        return;
    }
    if (event.target.closest('[data-action="loadmore"]')) {
        loadCourses(state).catch(Notification.exception);
    }
};

/**
 * (Re-)bind the add-course picker: refresh the cached element and enhance the autocomplete.
 *
 * @param {Object} state Modal state.
 * @return {void}
 */
const bindPicker = (state) => {
    state.addsel = state.root.querySelector(SELECTORS.courseAdd);
    state.addsel.dataset.exclude = Array.from(state.excluded).join(',');
    state.addsel.addEventListener('change', () => onAddCourse(state));
    enhance(SELECTORS.courseAdd, false, DATASOURCE, state.addcourselabel).catch(Notification.exception);
};

/**
 * Open the Courses & activities modal.
 *
 * @param {Object} opts {competencyid, competencyname, courseoutcomes, moduleoutcomes, onClose}.
 * @return {Promise<void>}
 */
export const open = async(opts) => {
    const [title, labels] = await Promise.all([
        getString('central_links_title', 'local_dimensions', opts.competencyname),
        Promise.all([
            getString('central_links_addcourse', 'local_dimensions'),
            getString('central_links_addactivity', 'local_dimensions'),
            getString('central_links_outcome', 'local_dimensions'),
            getString('central_links_removecourse', 'local_dimensions'),
            getString('central_links_removeactivity', 'local_dimensions'),
            getString('central_links_noactivities', 'local_dimensions'),
            getString('central_links_courseadded', 'local_dimensions'),
            getString('central_links_activityadded', 'local_dimensions'),
            getString('central_links_saved', 'local_dimensions'),
        ]),
    ]);
    const {html} = await Templates.renderForPromise('local_dimensions/central/competency_links', {
        competencyid: Number(opts.competencyid),
    });
    const modal = await Modal.create({title, body: html, large: true});
    modal.setRemoveOnClose(true);

    const root = modal.getRoot()[0];
    const state = {
        competencyid: Number(opts.competencyid),
        courseoutcomes: opts.courseoutcomes,
        moduleoutcomes: opts.moduleoutcomes,
        root: root,
        rowsEl: null,
        emptyEl: null,
        loadMoreEl: null,
        hiddenframeworkEl: null,
        addsel: null,
        addshtml: '',
        offset: 0,
        excluded: new Set(),
        addcourselabel: labels[0],
        addactivitylabel: labels[1],
        outcomelabel: labels[2],
        removecourselabel: labels[3],
        removeactivitylabel: labels[4],
        noactivitieslabel: labels[5],
        courseaddedlabel: labels[6],
        activityaddedlabel: labels[7],
        savedlabel: labels[8],
    };

    modal.getRoot().on(ModalEvents.shown, () => {
        // Host a toast region inside the modal body so success toasts render above the dialog,
        // not behind it (the page-level region sits below the modal). Core removes it on close.
        addToastRegion(modal.getBody()[0]).catch(Notification.exception);
        const region = root.querySelector(SELECTORS.region);
        state.rowsEl = region.querySelector(SELECTORS.courseRows);
        state.emptyEl = region.querySelector(SELECTORS.courseEmpty);
        state.loadMoreEl = region.querySelector(SELECTORS.loadMoreWrap);
        state.hiddenframeworkEl = region.querySelector(SELECTORS.hiddenFramework);
        state.addshtml = region.querySelector(SELECTORS.courseAdd).parentElement.innerHTML;
        region.addEventListener('click', (event) => onClick(state, event));
        region.addEventListener('change', (event) => {
            const select = event.target.closest('[data-role="course-outcome"], [data-role="module-outcome"]');
            if (select) {
                saveOutcome(state, select)
                    .then(() => {
                        addToast(state.savedlabel);
                        flash(select.closest('[data-cmid]') || select.closest('[data-courseid]'));
                        return null;
                    })
                    .catch(Notification.exception);
            }
        });
        bindPicker(state);
        loadCourses(state).catch(Notification.exception);
    });
    modal.getRoot().on(ModalEvents.hidden, () => {
        if (typeof opts.onClose === 'function') {
            // Report the current linked-course count so the caller can refresh in place
            // (each child of the rows container is one linked-course row).
            const count = state.rowsEl ? state.rowsEl.children.length : null;
            opts.onClose(count);
        }
    });
    modal.show();
};
