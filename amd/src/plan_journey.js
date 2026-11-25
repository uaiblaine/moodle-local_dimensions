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
 * The plan overview's third view, Journey: the plan's evidence over time, newest first, by month.
 *
 * The server draws the frame (templates/plan_journey.mustache) for a viewer who gets the competencies'
 * detail; local_dimensions/accordion arms this module, switches to it and back, and tells it when the
 * line's filters change. The first page is asked for when the view is first shown, the next ones by
 * "Load more" with the cursor the previous page returned. Each answer is painted only when it is the
 * latest request's, every async gap holds a pending token, and every record is written as text.
 *
 * A record names its competency with a button that opens the competency's detail the way a press on its
 * row does (the accordion's dialogue). The line's filters apply here as they apply to the list: a record
 * of a competency the list would hide is hidden. Another component's logs come counted, one quiet line per
 * month, and the plan's own start is the last marker once the oldest page is reached.
 *
 * @module     local_dimensions/plan_journey
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Log from 'core/log';
import Pending from 'core/pending';
import {getString} from 'core/str';

/** @type {Object} The frame's hooks, written by templates/plan_journey.mustache. */
const SELECTORS = {
    region: '[data-region="local-dimensions-journey"]',
    list: '[data-region="local-dimensions-journey-list"]',
    loading: '[data-region="local-dimensions-journey-loading"]',
    empty: '[data-region="local-dimensions-journey-empty"]',
    filtered: '[data-region="local-dimensions-journey-filtered"]',
    error: '[data-region="local-dimensions-journey-error"]',
    retry: '[data-action="local-dimensions-journey-retry"]',
    more: '[data-action="local-dimensions-journey-more"]',
};

/** @type {Object|null} The armed frame and what it was armed with; null until arm() finds a frame. */
let frame = null;

/** @type {Number} The latest request's ticket: an answer to an older one is dropped. */
let seq = 0;

/** @type {Object} What has been loaded: the next cursor, whether more exists, the months drawn. */
const loaded = {
    started: false,
    hasmore: false,
    nexttime: 0,
    nextid: 0,
    records: 0,
    planstart: null,
    startplaced: false,
    months: new Map(),
};

/**
 * Create an element with a class and, optionally, its text.
 *
 * @param {String} tag The tag name.
 * @param {String} className The class.
 * @param {String} [text] Its text, written as text.
 * @return {HTMLElement}
 */
const element = (tag, className, text) => {
    const node = document.createElement(tag);
    node.className = className;
    if (text !== undefined) {
        node.textContent = text;
    }
    return node;
};

/**
 * An address the page may link to: http or https, else null.
 *
 * The server sends only such addresses; parsing it again without a base keeps a relative or scripted value out
 * whatever arrives.
 *
 * @param {String} value The address.
 * @return {String|null}
 */
const webHref = (value) => {
    try {
        const url = new URL(value);
        return (url.protocol === 'http:' || url.protocol === 'https:') ? url.href : null;
    } catch (e) {
        return null;
    }
};

/**
 * The month's group, created at the end of the list on first use: pages arrive newest first.
 *
 * @param {String} key The month, YYYY-MM.
 * @param {String} label The month, named.
 * @return {Object} heading, list (the ordered list), fold (its quiet line, or null).
 */
const monthGroup = (key, label) => {
    if (loaded.months.has(key)) {
        return loaded.months.get(key);
    }
    const heading = element('h3', 'local-dimensions-journey-month', label);
    const list = element('ol', 'local-dimensions-journey-entries');
    frame.list.appendChild(heading);
    frame.list.appendChild(list);
    const group = {heading, list, fold: null, foldcount: 0};
    loaded.months.set(key, group);
    return group;
};

/**
 * Put an item into its month, before the month's quiet line, which stays last.
 *
 * @param {Object} group The month's group.
 * @param {HTMLElement} item The list item.
 */
const place = (group, item) => {
    if (group.fold) {
        group.list.insertBefore(item, group.fold);
    } else {
        group.list.appendChild(item);
    }
};

/**
 * The shell of a marker: its day, the rail with its dot, and the body the caller fills.
 *
 * @param {String} className The item's classes: the entry's, and a modifier's for the start and a quiet line.
 * @param {String|null} datetime The datetime attribute; null for a marker without a day (a month's quiet line).
 * @param {String} day The day, named.
 * @return {Object} item, body.
 */
const marker = (className, datetime, day) => {
    const item = element('li', className);
    const when = element(datetime === null ? 'span' : 'time', 'local-dimensions-journey-when', day);
    if (datetime !== null) {
        when.setAttribute('datetime', datetime);
    }
    const rail = element('span', 'local-dimensions-journey-rail');
    rail.setAttribute('aria-hidden', 'true');
    rail.appendChild(element('span', 'local-dimensions-journey-dot'));
    const body = element('div', 'local-dimensions-journey-body');
    item.append(when, rail, body);
    return {item, body};
};

/**
 * Draw the plan's start as the oldest marker, once.
 */
const placeStart = () => {
    const start = loaded.planstart;
    if (!start || loaded.startplaced) {
        return;
    }
    loaded.startplaced = true;
    const {item, body} = marker(
        'local-dimensions-journey-entry local-dimensions-journey-entry--start',
        start.datetime,
        start.daylabel
    );
    body.appendChild(element('p', 'local-dimensions-journey-quiet', start.label));
    place(monthGroup(start.monthkey, start.monthlabel), item);
};

/**
 * Draw one record: its day, its competency (a button opening the detail), its rating, what happened, who, the note.
 *
 * @param {Object} entry A record of the service's page.
 */
const drawEntry = (entry) => {
    if (loaded.planstart && !loaded.startplaced && entry.timecreated < loaded.planstart.timecreated) {
        placeStart();
    }
    const {item, body} = marker('local-dimensions-journey-entry', entry.datetime, entry.daylabel);
    item.dataset.competencyId = String(entry.competencyid);

    const top = element('div', 'local-dimensions-journey-top');
    const name = element('button', 'local-dimensions-journey-competency', entry.competencyname);
    name.type = 'button';
    name.dataset.competencyId = String(entry.competencyid);
    name.setAttribute('aria-haspopup', 'dialog');
    top.appendChild(name);
    if (entry.hasrating) {
        top.appendChild(element('span', 'local-dimensions-journey-rating', entry.rating));
    }
    body.appendChild(top);

    if (entry.description) {
        body.appendChild(element('p', 'local-dimensions-journey-what', entry.description));
    }
    if (entry.hasactor) {
        body.appendChild(element('p', 'local-dimensions-journey-by', entry.actor));
    }
    if (entry.hasnote) {
        body.appendChild(element('blockquote', 'local-dimensions-journey-note', entry.note));
    }
    const href = entry.hasurl ? webHref(entry.url) : null;
    if (href) {
        const link = element('a', 'local-dimensions-journey-link', frame.region.dataset.labelLink || href);
        link.href = href;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        body.appendChild(link);
    }
    place(monthGroup(entry.monthkey, entry.monthlabel), item);
};

/**
 * Write a month's count of another component's logs on its quiet line, the line created at the month's end.
 *
 * @param {Object} fold A fold of the service's page.
 * @param {Number} total The month's count so far, this page's included.
 * @param {String} text The line's words for that count.
 */
const drawFold = (fold, total, text) => {
    const group = monthGroup(fold.monthkey, fold.monthlabel);
    group.foldcount = total;
    if (!group.fold) {
        const {item, body} = marker('local-dimensions-journey-entry local-dimensions-journey-entry--fold', null, '');
        body.appendChild(element('p', 'local-dimensions-journey-quiet'));
        group.list.appendChild(item);
        group.fold = item;
    }
    group.fold.querySelector('.local-dimensions-journey-quiet').textContent = text;
};

/**
 * Show the records whose competency the line's filters leave in the list, and the line that says when none is left.
 */
const applyFilter = () => {
    if (!frame) {
        return;
    }
    let shown = 0;
    loaded.months.forEach((group) => {
        let visible = 0;
        group.list.querySelectorAll('li[data-competency-id]').forEach((item) => {
            const keep = frame.isShown(Number(item.dataset.competencyId));
            item.hidden = !keep;
            visible += keep ? 1 : 0;
        });
        shown += visible;
        const other = group.list.querySelectorAll('li:not([data-competency-id])').length;
        const empty = visible === 0 && other === 0;
        group.heading.hidden = empty;
        group.list.hidden = empty;
    });
    frame.filtered.hidden = !(loaded.records > 0 && shown === 0);
};

/**
 * Show or hide the loading skeleton, and mark the frame busy while it shows.
 *
 * @param {Boolean} busy Whether a page is loading.
 */
const setBusy = (busy) => {
    frame.loading.hidden = !busy;
    frame.more.disabled = busy;
    if (busy) {
        frame.region.setAttribute('aria-busy', 'true');
    } else {
        frame.region.removeAttribute('aria-busy');
    }
};

/**
 * Paint a page: its records, its folds, the start marker on the last page, "Load more" and the empty state.
 *
 * The words of the quiet lines are fetched first, and nothing is drawn unless the request is still the latest
 * once they arrive, so a dropped answer leaves no record behind.
 *
 * @param {Object} page The service's answer.
 * @param {Number} ticket The request's ticket.
 * @return {Promise<Boolean>} Whether the page was painted (the request was still the latest).
 */
const paint = async(page, ticket) => {
    const totals = page.folds.map((fold) => {
        const group = loaded.months.get(fold.monthkey);
        return (group ? group.foldcount : 0) + fold.count;
    });
    const texts = await Promise.all(totals.map((total) => getString('journey_fold', 'local_dimensions', total)));
    if (ticket !== seq) {
        return false;
    }

    loaded.planstart = page.planstart;
    page.entries.forEach(drawEntry);
    page.folds.forEach((fold, index) => drawFold(fold, totals[index], texts[index]));
    loaded.records += page.entries.length + page.folds.length;
    loaded.hasmore = page.hasmore;
    loaded.nexttime = page.nexttime;
    loaded.nextid = page.nextid;
    if (!page.hasmore && loaded.records > 0) {
        placeStart();
    }
    frame.more.hidden = !page.hasmore;
    frame.empty.hidden = loaded.records > 0 || page.hasmore;
    applyFilter();
    return true;
};

/**
 * Ask for the next page (the first when nothing is loaded) and paint it if it is still the latest request.
 *
 * @return {Promise<void>}
 */
const load = async() => {
    const ticket = ++seq;
    const pending = new Pending('local_dimensions/plan_journey:load');
    frame.error.hidden = true;
    setBusy(true);
    try {
        const page = await Ajax.call([{
            methodname: 'local_dimensions_get_plan_journey',
            args: {planid: frame.planid, beforetime: loaded.nexttime, beforeid: loaded.nextid},
        }])[0];
        if (ticket !== seq) {
            return;
        }
        if (!await paint(page, ticket)) {
            return;
        }
        if (ticket !== seq) {
            return;
        }
        setBusy(false);
    } catch (error) {
        if (ticket === seq) {
            setBusy(false);
            frame.error.hidden = false;
        }
        Log.debug('local_dimensions/plan_journey: page not loaded: ' + (error && error.message));
    } finally {
        pending.resolve();
    }
};

/**
 * Arm the Journey on the plan overview: find its frame and wire its buttons.
 *
 * @param {HTMLElement} root The overview's container (.local-dimensions-plan-summary).
 * @param {Object} options openCompetency(id) opens a competency's detail; isShown(id) says whether the line's
 *     filters leave it in the list.
 * @return {Boolean} Whether the view can be offered: false without a frame (a viewer without the detail).
 */
export const arm = (root, options) => {
    const region = root ? root.querySelector(SELECTORS.region) : null;
    const planid = region ? Number.parseInt(region.dataset.planid, 10) : 0;
    if (!region || !(planid > 0) || !options || typeof options.openCompetency !== 'function') {
        return false;
    }
    frame = {
        region,
        planid,
        list: region.querySelector(SELECTORS.list),
        loading: region.querySelector(SELECTORS.loading),
        empty: region.querySelector(SELECTORS.empty),
        filtered: region.querySelector(SELECTORS.filtered),
        error: region.querySelector(SELECTORS.error),
        more: region.querySelector(SELECTORS.more),
        openCompetency: options.openCompetency,
        isShown: typeof options.isShown === 'function' ? options.isShown : () => true,
    };
    if (Object.values(frame).some((part) => part === null)) {
        frame = null;
        return false;
    }

    frame.more.addEventListener('click', () => {
        load();
    });
    region.querySelector(SELECTORS.retry).addEventListener('click', () => {
        load();
    });
    frame.list.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('button[data-competency-id]') : null;
        if (button) {
            frame.openCompetency(Number(button.dataset.competencyId));
        }
    });
    return true;
};

/**
 * Show the Journey, asking for its first page the first time.
 */
export const show = () => {
    if (!frame) {
        return;
    }
    frame.region.hidden = false;
    if (!loaded.started) {
        loaded.started = true;
        load();
    }
};

/**
 * Hide the Journey; what it loaded stays for the next time it is shown.
 */
export const hide = () => {
    if (frame) {
        frame.region.hidden = true;
    }
};

/**
 * The line's filters changed: show the records of the competencies they leave in the list.
 */
export const refilter = () => {
    applyFilter();
};
