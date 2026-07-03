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
 * rule outcome. Each linked course renders as a bordered card (course link, short name, linked/total
 * activity count and a completion-rule badge); its activities expand inside the card border and load
 * lazily on first expand (one read returns linked + available, both rendered as checkbox rows that
 * link/unlink on toggle). Activity rows carry their own outcome select, completion-rule badge and a
 * shared-competency warning. Outcome selects save on change. Rows are built in JS to avoid a template
 * render per row. Closing the modal triggers the caller's onClose so the Structure tree count refreshes.
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
 * Append an external-link glyph plus a screen-reader "new window" note to a link.
 *
 * @param {HTMLAnchorElement} link The link to decorate.
 * @param {String} newwindowlabel Localised "opens in new window" text.
 */
const decorateExternalLink = (link, newwindowlabel) => {
    const ext = document.createElement('i');
    ext.className = 'fa fa-external-link ms-1';
    ext.setAttribute('aria-hidden', 'true');
    const sr = document.createElement('span');
    sr.className = 'sr-only';
    sr.textContent = newwindowlabel;
    link.appendChild(ext);
    link.appendChild(sr);
};

/**
 * Build a completion-rule badge: green when a rule exists, amber "create one" otherwise.
 * Rendered as a link into the matching settings page when the user may edit it.
 *
 * @param {Object} state Modal state.
 * @param {Boolean} hascompletion Whether a completion rule is configured.
 * @param {String} url Settings URL, or empty when the user may not edit.
 * @return {HTMLElement}
 */
const makeCompletionBadge = (state, hascompletion, url) => {
    const variant = hascompletion ? 'ok' : 'warn';
    const badge = document.createElement(url ? 'a' : 'span');
    badge.className = `local-dimensions-central-links-badge local-dimensions-central-links-badge-${variant} ms-2`;
    const glyph = document.createElement('i');
    glyph.className = hascompletion ? 'fa fa-check' : 'fa fa-exclamation-triangle';
    glyph.setAttribute('aria-hidden', 'true');
    badge.appendChild(glyph);
    badge.appendChild(document.createTextNode(
        hascompletion ? state.completionoklabel : state.completionmissinglabel
    ));
    if (url) {
        badge.href = url;
        badge.target = '_blank';
        badge.rel = 'noopener noreferrer';
        decorateExternalLink(badge, state.newwindowlabel);
    }
    return badge;
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
 * Refresh a course card's linked/total activity count and its "whole course" note.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} courseEl The course card.
 * @param {Number} linked Number of linked activities.
 * @param {Number} total Total number of activities in the course.
 * @return {Promise<void>}
 */
const updateCourseMeta = async(state, courseEl, linked, total) => {
    const count = courseEl.querySelector('[data-role="modcount"]');
    const note = courseEl.querySelector('[data-role="wholecoursenote"]');
    if (linked > 0) {
        count.textContent = await getString('central_links_modulecount', 'local_dimensions', {linked: linked, total: total});
        note.hidden = true;
    } else {
        count.textContent = state.wholecourselabel;
        note.hidden = false;
    }
};

/**
 * Build one linked-activity row: checkbox (unticking unlinks), outcome select, completion badge
 * and, when other competencies share the activity, a warning that the rule affects them all.
 *
 * @param {Object} state Modal state.
 * @param {Object} module {cmid, name, ruleoutcome, hascompletion, sharedcount, canmanage, editurl, competenciesurl}.
 * @param {String} sharedtext Localised shared-competency warning ('' when not shared).
 * @return {HTMLElement}
 */
const makeModuleRow = (state, module, sharedtext) => {
    const row = document.createElement('div');
    row.className = 'py-1';
    row.dataset.cmid = String(module.cmid);
    row.dataset.name = module.name;

    const line = document.createElement('div');
    line.className = 'd-flex align-items-center';

    const label = document.createElement('label');
    label.className = 'd-flex align-items-center flex-grow-1 mb-0 me-2';
    const box = document.createElement('input');
    box.type = 'checkbox';
    box.className = 'me-2';
    box.checked = true;
    box.dataset.action = 'toggle-module';
    box.disabled = !module.canmanage;
    const name = document.createElement('span');
    name.className = 'text-truncate';
    name.textContent = module.name;
    label.appendChild(box);
    label.appendChild(name);

    const select = outcomeSelect(state.moduleoutcomes, module.ruleoutcome, state.outcomelabel, !module.canmanage);
    select.dataset.role = 'module-outcome';
    select.name = 'module-outcome';

    line.appendChild(label);
    line.appendChild(select);
    line.appendChild(makeCompletionBadge(state, Boolean(module.hascompletion), module.editurl || ''));
    row.appendChild(line);

    if (module.sharedcount > 0 && sharedtext) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-warning d-flex align-items-center justify-content-between py-1 px-2 small mt-1 mb-0';
        const text = document.createElement('span');
        text.textContent = sharedtext;
        alert.appendChild(text);
        if (module.competenciesurl) {
            const link = document.createElement('a');
            link.href = module.competenciesurl;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'text-nowrap ms-2';
            link.textContent = state.opencompetencieslabel;
            decorateExternalLink(link, state.newwindowlabel);
            alert.appendChild(link);
        }
        row.appendChild(alert);
    }
    return row;
};

/**
 * Build one available-activity row: an unticked checkbox that links the activity on toggle.
 *
 * @param {Object} state Modal state.
 * @param {Object} module {cmid, name, modname}.
 * @return {HTMLElement}
 */
const makeAvailableRow = (state, module) => {
    const row = document.createElement('div');
    row.className = 'py-1';
    row.dataset.cmid = String(module.cmid);
    row.dataset.name = module.name;

    const label = document.createElement('label');
    label.className = 'd-flex align-items-center mb-0';
    const box = document.createElement('input');
    box.type = 'checkbox';
    box.className = 'me-2';
    box.dataset.action = 'toggle-module';
    const name = document.createElement('span');
    name.className = 'text-truncate text-muted';
    name.textContent = module.name;
    label.appendChild(box);
    label.appendChild(name);
    row.appendChild(label);
    return row;
};

/**
 * Build one course card (header + outcome row + collapsed activities container, all inside one border).
 *
 * @param {Object} state Modal state.
 * @param {Object} course {courseid, fullname, shortname, ruleoutcome, modulecount, totalmodules,
 *                        hascompletion, canmanage, courseurl, completionurl}.
 * @return {HTMLElement}
 */
const makeCourseRow = (state, course) => {
    const wrap = document.createElement('div');
    wrap.className = 'local-dimensions-central-links-card';
    wrap.dataset.courseid = String(course.courseid);
    wrap.dataset.fullname = course.fullname;

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

    const grad = document.createElement('i');
    grad.className = 'fa fa-graduation-cap text-muted me-1';
    grad.setAttribute('aria-hidden', 'true');

    let name;
    if (course.courseurl) {
        name = document.createElement('a');
        name.href = course.courseurl;
        name.target = '_blank';
        name.rel = 'noopener noreferrer';
        name.className = 'text-truncate local-dimensions-central-links-name';
        name.textContent = course.fullname;
        decorateExternalLink(name, state.newwindowlabel);
    } else {
        name = document.createElement('span');
        name.className = 'text-truncate local-dimensions-central-links-name';
        name.textContent = course.fullname;
    }

    const shortname = document.createElement('span');
    shortname.className = 'font-monospace small text-muted ms-1';
    shortname.textContent = course.shortname || '';

    const count = document.createElement('span');
    count.className = 'small text-muted text-nowrap ms-auto';
    count.dataset.role = 'modcount';

    header.appendChild(toggle);
    header.appendChild(grad);
    header.appendChild(name);
    header.appendChild(shortname);
    header.appendChild(count);
    if (course.canmanage) {
        header.appendChild(iconButton('remove-course', 'trash', state.removecourselabel));
    }

    const outcomerow = document.createElement('div');
    outcomerow.className = 'd-flex align-items-center flex-wrap mt-1 ps-4';
    const outcomeprefix = document.createElement('span');
    outcomeprefix.className = 'small text-muted me-2';
    outcomeprefix.textContent = state.outcomeprefixlabel;
    const select = outcomeSelect(state.courseoutcomes, course.ruleoutcome, state.outcomelabel, !course.canmanage);
    select.dataset.role = 'course-outcome';
    select.name = 'course-outcome';
    outcomerow.appendChild(outcomeprefix);
    outcomerow.appendChild(select);
    outcomerow.appendChild(makeCompletionBadge(state, Boolean(course.hascompletion), course.completionurl || ''));

    const note = document.createElement('div');
    note.className = 'small text-muted ps-4 mt-1';
    note.dataset.role = 'wholecoursenote';
    note.textContent = state.wholecoursenotelabel;
    note.hidden = true;

    const children = document.createElement('div');
    children.className = 'local-dimensions-central-links-acts ps-4';
    children.dataset.role = 'activities';
    children.dataset.loaded = '0';
    children.hidden = true;

    wrap.appendChild(header);
    wrap.appendChild(outcomerow);
    wrap.appendChild(note);
    wrap.appendChild(children);
    updateCourseMeta(state, wrap, Number(course.modulecount), Number(course.totalmodules)).catch(Notification.exception);
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
 * Lazily load a course's activities into its container (linked first, then available), refreshing
 * the card's linked/total count from the fresh data.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} courseEl The course card.
 * @return {Promise<void>}
 */
const loadActivities = async(state, courseEl) => {
    const container = courseEl.querySelector('[data-role="activities"]');
    const courseid = Number(courseEl.dataset.courseid);
    const response = await Ajax.call([{
        methodname: 'local_dimensions_get_competency_module_links',
        args: {competencyid: state.competencyid, courseid: courseid},
    }])[0];

    const sharedtexts = await Promise.all(response.linked.map((module) => {
        if (!module.sharedcount) {
            return Promise.resolve('');
        }
        const key = module.sharedcount === 1 ? 'central_links_sharedwarningone' : 'central_links_sharedwarning';
        return getString(key, 'local_dimensions', module.sharedcount);
    }));

    container.textContent = '';
    const showavailable = response.canmanage && response.available.length > 0;
    if (!response.linked.length && !showavailable) {
        const none = document.createElement('div');
        none.className = 'text-muted small py-1';
        none.textContent = state.noactivitieslabel;
        container.appendChild(none);
    } else {
        const header = document.createElement('div');
        header.className = 'small text-muted mb-1';
        header.textContent = state.activitieshdrlabel;
        container.appendChild(header);
        response.linked.forEach((module, index) => {
            container.appendChild(makeModuleRow(state, module, sharedtexts[index]));
        });
        if (showavailable) {
            response.available.forEach((module) => container.appendChild(makeAvailableRow(state, module)));
        }
    }
    container.dataset.loaded = '1';
    await updateCourseMeta(state, courseEl, response.linked.length, response.linked.length + response.available.length);
};

/**
 * Toggle a course card's activities, loading them on first open.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} courseEl The course card.
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
 * @param {HTMLElement} courseEl The course card.
 * @return {Promise<void>}
 */
const removeCourse = async(state, courseEl) => {
    const courseid = Number(courseEl.dataset.courseid);
    const name = courseEl.dataset.fullname || '';
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
 * Link or unlink an activity from its toggled checkbox, then reload the course's activities.
 * Unlinking asks for a confirm first; cancelling re-ticks the box.
 *
 * @param {Object} state Modal state.
 * @param {HTMLInputElement} box The toggled checkbox.
 * @return {Promise<void>}
 */
const toggleModule = async(state, box) => {
    const moduleEl = box.closest('[data-cmid]');
    const courseEl = box.closest('[data-courseid]');
    const container = courseEl.querySelector('[data-role="activities"]');
    const cmid = Number(moduleEl.dataset.cmid);

    if (box.checked) {
        box.disabled = true;
        try {
            await Ajax.call([{
                methodname: 'local_dimensions_link_competency_module',
                args: {competencyid: state.competencyid, cmid: cmid},
            }])[0];
        } catch (error) {
            // Restore the untouched state so the row still reflects the server and can be retried.
            box.checked = false;
            box.disabled = false;
            throw error;
        }
        container.dataset.loaded = '0';
        await loadActivities(state, courseEl);
        flash(container.querySelector('[data-cmid="' + cmid + '"]'));
        addToast(state.activityaddedlabel);
        return;
    }

    const name = moduleEl.dataset.name || '';
    const [title, body] = await Promise.all([
        getString('central_links_removeactivity', 'local_dimensions'),
        getString('central_links_removeactivity_confirm', 'local_dimensions', name),
    ]);
    try {
        await Notification.deleteCancelPromise(title, body);
    } catch (e) {
        box.checked = true;
        return;
    }
    box.disabled = true;
    try {
        await Ajax.call([{
            methodname: 'local_dimensions_unlink_competency_module',
            args: {competencyid: state.competencyid, cmid: cmid},
        }])[0];
    } catch (error) {
        // Restore the untouched state so the row still reflects the server and can be retried.
        box.checked = true;
        box.disabled = false;
        throw error;
    }
    container.dataset.loaded = '0';
    await loadActivities(state, courseEl);
    addToast(state.activityremovedlabel);
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
    enhance(SELECTORS.courseAdd, false, DATASOURCE, state.addcourseplaceholder).catch(Notification.exception);
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
            getString('central_links_addcourse_placeholder', 'local_dimensions'),
            getString('central_links_outcome', 'local_dimensions'),
            getString('central_links_outcomeprefix', 'local_dimensions'),
            getString('central_links_removecourse', 'local_dimensions'),
            getString('central_links_noactivities', 'local_dimensions'),
            getString('central_links_activitieshdr', 'local_dimensions'),
            getString('central_links_courseadded', 'local_dimensions'),
            getString('central_links_activityadded', 'local_dimensions'),
            getString('central_links_activityremoved', 'local_dimensions'),
            getString('central_links_saved', 'local_dimensions'),
            getString('central_links_wholecourse', 'local_dimensions'),
            getString('central_links_wholecoursenote', 'local_dimensions'),
            getString('central_links_completionrule_ok', 'local_dimensions'),
            getString('central_links_completionrule_missing', 'local_dimensions'),
            getString('central_links_opencompetencies', 'local_dimensions'),
            getString('opensinnewwindow', 'core'),
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
        addcourseplaceholder: labels[0],
        outcomelabel: labels[1],
        outcomeprefixlabel: labels[2],
        removecourselabel: labels[3],
        noactivitieslabel: labels[4],
        activitieshdrlabel: labels[5],
        courseaddedlabel: labels[6],
        activityaddedlabel: labels[7],
        activityremovedlabel: labels[8],
        savedlabel: labels[9],
        wholecourselabel: labels[10],
        wholecoursenotelabel: labels[11],
        completionoklabel: labels[12],
        completionmissinglabel: labels[13],
        opencompetencieslabel: labels[14],
        newwindowlabel: labels[15],
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
            if (event.target.matches('[data-action="toggle-module"]')) {
                toggleModule(state, event.target).catch(Notification.exception);
                return;
            }
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
            // (each child of the rows container is one linked-course card).
            const count = state.rowsEl ? state.rowsEl.children.length : null;
            opts.onClose(count);
        }
    });
    modal.show();
};
