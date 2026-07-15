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
 * rule outcome. Each linked course renders as a bordered card (course link, short name, linked-activity
 * count and a completion-rule badge); its activities expand inside the card border and load lazily on
 * first expand. Activities are added through a client-side search over the course's available modules
 * (name + localised module type) and listed as removable two-line rows: name (clamped, full name on
 * hover) with the module type, then the outcome select, completion-rule badge and shared-competency
 * warning. Outcome selects save on change. Rows are built in JS to avoid a template render per row.
 * Closing the modal triggers the caller's onClose so the Structure tree count refreshes.
 *
 * @module     local_dimensions/central/competency_links
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import {notifyError} from 'local_dimensions/central/errors';
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
    addInput: '[data-fieldtype="autocomplete"]',
    activitySearch: '[data-role="activity-search"]',
    activitySearchList: '[data-role="activity-search-list"]',
};

/**
 * Fold a string for search matching: lower-case and accent-stripped.
 *
 * @param {String} text The text to fold.
 * @return {String}
 */
const fold = (text) => text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

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
 * Build a small module-type tag (e.g. "Assignment", "Forum").
 *
 * @param {String} text Localised module type name.
 * @return {HTMLSpanElement}
 */
const mtypeTag = (text) => {
    const tag = document.createElement('span');
    tag.className = 'local-dimensions-central-links-mtype ms-2';
    tag.textContent = text;
    return tag;
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
 * Refresh a course card's linked-activity count and its "whole course" note.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} courseEl The course card.
 * @param {Number} linked Number of linked activities.
 * @return {void}
 */
const updateCourseMeta = (state, courseEl, linked) => {
    const count = courseEl.querySelector('[data-role="modcount"]');
    const note = courseEl.querySelector('[data-role="wholecoursenote"]');
    if (linked === 1) {
        count.textContent = state.modulecountonelabel;
        note.hidden = true;
    } else if (linked > 1) {
        count.textContent = state.modulecounttemplate.replace('{count}', linked);
        note.hidden = true;
    } else {
        count.textContent = state.wholecourselabel;
        note.hidden = false;
    }
};

/**
 * Build one linked-activity row: the name on its own clamped line (full name on hover) with the
 * module type and a remove button, then the outcome select and completion badge on a second line,
 * and — when other competencies share the activity — a warning that the rule affects them all.
 *
 * @param {Object} state Modal state.
 * @param {Object} module {cmid, name, modtype, ruleoutcome, hascompletion, sharedcount, canmanage,
 *                        editurl, competenciesurl}.
 * @param {String} sharedtext Localised shared-competency warning ('' when not shared).
 * @return {HTMLElement}
 */
const makeModuleRow = (state, module, sharedtext) => {
    const row = document.createElement('div');
    row.className = 'local-dimensions-central-links-act';
    row.dataset.cmid = String(module.cmid);
    row.dataset.name = module.name;

    const nameline = document.createElement('div');
    nameline.className = 'd-flex align-items-start';
    const name = document.createElement('span');
    name.className = 'local-dimensions-central-links-actname flex-grow-1';
    name.title = module.name;
    name.textContent = module.name;
    nameline.appendChild(name);
    if (module.modtype) {
        nameline.appendChild(mtypeTag(module.modtype));
    }
    if (module.canmanage) {
        nameline.appendChild(iconButton('remove-module', 'times', state.removeactivitylabel));
    }
    row.appendChild(nameline);

    const controls = document.createElement('div');
    controls.className = 'd-flex align-items-center flex-wrap mt-1';
    const select = outcomeSelect(state.moduleoutcomes, module.ruleoutcome, state.outcomelabel, !module.canmanage);
    select.dataset.role = 'module-outcome';
    select.name = 'module-outcome';
    controls.appendChild(select);
    controls.appendChild(makeCompletionBadge(state, Boolean(module.hascompletion), module.editurl || ''));
    row.appendChild(controls);

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
 * Build the add-activity search: a text input filtering the course's available modules client-side
 * (fold-matched by name); each result shows the name plus the module type and adds the link on click.
 *
 * @param {Object} state Modal state.
 * @param {Number} courseid The course id (for a unique input id).
 * @param {Array} available List of {cmid, name, modtype}.
 * @return {HTMLElement}
 */
const makeActivitySearch = (state, courseid, available) => {
    const wrap = document.createElement('div');
    wrap.className = 'mb-2';
    wrap.dataset.role = 'activity-search';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm';
    input.id = `local-dimensions-links-actsearch-${courseid}`;
    input.name = 'activity-search';
    input.autocomplete = 'off';
    input.placeholder = state.addactivityplaceholder;
    input.setAttribute('aria-label', state.addactivitylabel);

    const list = document.createElement('div');
    list.className = 'local-dimensions-central-links-actsearch-list';
    list.dataset.role = 'activity-search-list';
    list.hidden = true;

    const render = () => {
        const query = fold(input.value.trim());
        const matches = available.filter((module) => !query || fold(module.name).includes(query));
        list.textContent = '';
        if (!matches.length) {
            const none = document.createElement('div');
            none.className = 'text-muted small px-2 py-1';
            none.textContent = state.nomatcheslabel;
            list.appendChild(none);
        } else {
            matches.forEach((module) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'local-dimensions-central-links-actsearch-item';
                item.dataset.action = 'add-module';
                item.dataset.cmid = String(module.cmid);
                const name = document.createElement('span');
                name.className = 'flex-grow-1 text-truncate';
                name.textContent = module.name;
                item.appendChild(name);
                if (module.modtype) {
                    item.appendChild(mtypeTag(module.modtype));
                }
                list.appendChild(item);
            });
        }
        list.hidden = false;
    };

    input.addEventListener('input', render);
    input.addEventListener('focus', render);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            list.hidden = true;
        }
    });

    wrap.appendChild(input);
    wrap.appendChild(list);
    return wrap;
};

/**
 * Build one course card (header + outcome row + collapsed activities container, all inside one border).
 *
 * @param {Object} state Modal state.
 * @param {Object} course {courseid, fullname, shortname, ruleoutcome, modulecount, hascompletion,
 *                        canmanage, courseurl, completionurl}.
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
    children.className = 'local-dimensions-central-links-acts';
    children.dataset.role = 'activities';
    children.dataset.loaded = '0';
    children.hidden = true;

    wrap.appendChild(header);
    wrap.appendChild(outcomerow);
    wrap.appendChild(note);
    wrap.appendChild(children);
    updateCourseMeta(state, wrap, Number(course.modulecount));
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
        const row = makeCourseRow(state, course);
        // Only the rows this cursor fetched count towards it; the picker appends its own.
        row.dataset.paged = '1';
        state.rowsEl.appendChild(row);
        state.excluded.add(String(course.courseid));
    });
    state.addsel.dataset.exclude = Array.from(state.excluded).join(',');
    state.offset += response.items.length;

    state.emptyEl.hidden = !(state.offset === 0 && response.total === 0);
    state.loadMoreEl.hidden = state.offset >= response.total;
};

/**
 * Lazily load a course's activities into its container (search first, then the linked rows),
 * refreshing the card's linked count from the fresh data.
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
    const header = document.createElement('div');
    header.className = 'small text-muted mb-1';
    header.textContent = state.activitieshdrlabel;
    container.appendChild(header);
    if (response.canmanage && response.available.length) {
        container.appendChild(makeActivitySearch(state, courseid, response.available));
    }
    if (response.linked.length) {
        response.linked.forEach((module, index) => {
            container.appendChild(makeModuleRow(state, module, sharedtexts[index]));
        });
    } else {
        const none = document.createElement('div');
        none.className = 'text-muted small py-1';
        none.textContent = state.noactivitieslabel;
        container.appendChild(none);
    }
    container.dataset.loaded = '1';
    updateCourseMeta(state, courseEl, response.linked.length);
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
 * Give keyboard focus a useful home when the element holding it was removed (focus falls to
 * <body>, forcing keyboard users to re-traverse the dialog).
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement|null} preferred Element to focus first, falling back to whatever the
 *     region still offers.
 * @return {void}
 */
const restoreFocus = (state, preferred) => {
    if (document.activeElement !== document.body) {
        return;
    }
    /* The fallback is the enhanced autocomplete input, never state.addsel: enhance() hides the
       original select, so it is not focusable. Load more comes first while it is still on
       screen — with a page pending it, and not the picker, is the way on. */
    const container = state.addsel ? state.addsel.parentElement : null;
    const loadmore = state.loadMoreEl && !state.loadMoreEl.hidden
        ? state.loadMoreEl.querySelector('[data-action="loadmore"]')
        : null;
    const target = preferred || loadmore || (container && container.querySelector(SELECTORS.addInput));
    if (target) {
        target.focus();
    }
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
    /* Removing a fetched row shifts every later one down, so the cursor has to come back with
       it or Load more skips the course that took its place. Guarded twice over: the picker
       appends rows this cursor never counted, and the trash button stays live across the
       confirm and the unlink, so a re-click can reach the same row again — the second unlink
       resolves with success false rather than throwing, and would decrement a second time.
       Consuming the tag makes the step idempotent per row. */
    if (courseEl.dataset.paged) {
        delete courseEl.dataset.paged;
        state.offset -= 1;
    }
    /* The list is paginated, so an empty rows container only means "no courses linked" once
       there is nothing left to load: with a page still pending the message would be a lie. */
    state.emptyEl.hidden = state.rowsEl.children.length > 0 || !state.loadMoreEl.hidden;
    /* The confirm dialog handed focus back to the trash button of the card just detached. The
       toggle is preferred over the next trash because only the toggle is always rendered — the
       trash is capability-gated per course. */
    restoreFocus(state, state.rowsEl.querySelector('[data-action="toggle-course"]'));
    addToast(state.courseremovedlabel);
};

/**
 * Link the activity picked in the search, then reload the course's activities.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} courseEl The course card.
 * @param {Number} cmid The course module id.
 * @return {Promise<void>}
 */
const addModule = async(state, courseEl, cmid) => {
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
 * Remove an activity link after a confirm, then reload the course's activities.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} moduleEl The activity row.
 * @return {Promise<void>}
 */
const removeModule = async(state, moduleEl) => {
    const cmid = Number(moduleEl.dataset.cmid);
    const name = moduleEl.dataset.name || '';
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
    const container = courseEl.querySelector('[data-role="activities"]');
    container.dataset.loaded = '0';
    await loadActivities(state, courseEl);
    /* Queried after the reload, since it empties and refills the container. Focus stays inside
       the card the user was working in: its toggle is always rendered, so it is the home when
       the last activity goes. */
    restoreFocus(state, container.querySelector('[data-action="remove-module"]')
        || courseEl.querySelector('[data-action="toggle-course"]'));
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
        .catch(notifyError);
};

/**
 * Route a click inside the body to its handler. Also closes any open activity-search dropdown
 * the click landed outside of.
 *
 * @param {Object} state Modal state.
 * @param {Event} event The click event.
 * @return {void}
 */
const onClick = (state, event) => {
    state.root.querySelectorAll(SELECTORS.activitySearch).forEach((search) => {
        if (!search.contains(event.target)) {
            const list = search.querySelector(SELECTORS.activitySearchList);
            if (list) {
                list.hidden = true;
            }
        }
    });
    const toggle = event.target.closest('[data-action="toggle-course"]');
    if (toggle) {
        toggleCourse(state, toggle.closest('[data-courseid]'), toggle).catch(notifyError);
        return;
    }
    if (event.target.closest('[data-action="remove-course"]')) {
        removeCourse(state, event.target.closest('[data-courseid]')).catch(notifyError);
        return;
    }
    const additem = event.target.closest('[data-action="add-module"]');
    if (additem) {
        addModule(state, additem.closest('[data-courseid]'), Number(additem.dataset.cmid)).catch(notifyError);
        return;
    }
    if (event.target.closest('[data-action="remove-module"]')) {
        removeModule(state, event.target.closest('[data-cmid]')).catch(notifyError);
        return;
    }
    if (event.target.closest('[data-action="loadmore"]')) {
        loadCourses(state).catch(notifyError);
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
    enhance(SELECTORS.courseAdd, false, DATASOURCE, state.addcourseplaceholder).catch(notifyError);
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
            getString('central_links_addactivity', 'local_dimensions'),
            getString('central_links_addactivity_placeholder', 'local_dimensions'),
            getString('central_links_outcome', 'local_dimensions'),
            getString('central_links_outcomeprefix', 'local_dimensions'),
            getString('central_links_removecourse', 'local_dimensions'),
            getString('central_links_removeactivity', 'local_dimensions'),
            getString('central_links_noactivities', 'local_dimensions'),
            getString('central_links_activitieshdr', 'local_dimensions'),
            getString('central_links_nomatches', 'local_dimensions'),
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
            getString('central_links_modulecountone', 'local_dimensions'),
            getString('central_links_modulecount', 'local_dimensions', '{count}'),
            getString('central_links_courseremoved', 'local_dimensions'),
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
        addactivitylabel: labels[1],
        addactivityplaceholder: labels[2],
        outcomelabel: labels[3],
        outcomeprefixlabel: labels[4],
        removecourselabel: labels[5],
        removeactivitylabel: labels[6],
        noactivitieslabel: labels[7],
        activitieshdrlabel: labels[8],
        nomatcheslabel: labels[9],
        courseaddedlabel: labels[10],
        activityaddedlabel: labels[11],
        activityremovedlabel: labels[12],
        savedlabel: labels[13],
        wholecourselabel: labels[14],
        wholecoursenotelabel: labels[15],
        completionoklabel: labels[16],
        completionmissinglabel: labels[17],
        opencompetencieslabel: labels[18],
        newwindowlabel: labels[19],
        modulecountonelabel: labels[20],
        modulecounttemplate: labels[21],
        courseremovedlabel: labels[22],
    };

    modal.getRoot().on(ModalEvents.shown, () => {
        // Host a toast region inside the modal body so success toasts render above the dialog,
        // not behind it (the page-level region sits below the modal). Core removes it on close.
        addToastRegion(modal.getBody()[0]).catch(notifyError);
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
                    .catch(notifyError);
            }
        });
        bindPicker(state);
        loadCourses(state).catch(notifyError);
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
