// Measuring helpers of the hub v2 spike (S0), imported into the hub v2 page from the console:
// await import('http://localhost:8896/s0-helpers.js'). A development tool, never shipped.
//
// The numbers do not depend on frames or timers, so they hold while the Browser pane is hidden:
// long tasks are main-thread work, and the probes time synchronous work between MessageChannel turns.

const count = () => document.querySelectorAll('.react-flow__node').length;
const turn = () => new Promise((resolve) => {
    const channel = new MessageChannel();
    channel.port1.onmessage = () => resolve();
    channel.port2.postMessage(0);
});
const state = {longtasks: [], phases: [], running: false, err: null};
new PerformanceObserver((list) => list.getEntries().forEach((e) => state.longtasks.push(Math.round(e.duration))))
    .observe({entryTypes: ['longtask']});

/** Mount another build of the hub over the page's own, with the same props. */
export const sideload = async(url) => {
    const el = document.querySelector('.local-dimensions-hubv2 [data-react-component]');
    const props = JSON.parse(el.getAttribute('data-react-props'));
    const parent = el.parentElement;
    el.remove();
    for (let i = 0; i < 200; i++) {
        await turn();
    }
    const host = document.createElement('div');
    parent.appendChild(host);
    const mod = await import(url);
    const {mountReactApp} = await import('@moodle/lms/core/mount');
    mountReactApp(host, mod.default, props);
};

/** Open every branch, one expand button at a time. Returns when nothing is left to open. */
export const openAll = async() => {
    state.running = true;
    state.phases = [];
    state.longtasks.length = 0;
    try {
        for (;;) {
            const button = document.querySelector('.local-dimensions-hubv2-toggle[aria-expanded="false"]')
                || document.querySelector('.local-dimensions-hubv2-card-more button');
            if (!button) {
                break;
            }
            const before = count();
            const t0 = performance.now();
            button.click();
            while (count() === before && performance.now() - t0 < 10000) {
                await turn();
            }
            state.phases.push({n: count(), ms: Math.round(performance.now() - t0)});
        }
    } catch (e) {
        state.err = String(e);
    }
    state.running = false;
};

const summary = () => ({
    running: state.running,
    err: state.err,
    clicks: state.phases.length,
    nodes: count(),
    edges: document.querySelectorAll('.react-flow__edge').length,
    dom: document.querySelectorAll('.local-dimensions-hubv2 *').length,
    longtasks: {
        count: state.longtasks.length,
        max: Math.max(0, ...state.longtasks),
        over200: state.longtasks.filter((x) => x > 200).length,
        sum: state.longtasks.reduce((a, b) => a + b, 0),
    },
    heapMB: performance.memory ? Math.round(performance.memory.usedJSHeapSize / 1048576) : null,
});

/** Collapse and reopen three loaded branches, then move the viewport sixty steps. */
export const probe = async() => {
    const viewport = document.querySelector('.react-flow__viewport');
    const force = () => viewport.getBoundingClientRect().width;
    const until = async(action) => {
        const before = count();
        const t0 = performance.now();
        action();
        while (count() === before && performance.now() - t0 < 6000) {
            await turn();
        }
        force();
        return {ms: +(performance.now() - t0).toFixed(1), n: count()};
    };
    const out = {...summary(), toggles: [], pan: null};
    const opened = [...document.querySelectorAll('.local-dimensions-hubv2-toggle[aria-expanded="true"]')];
    const ids = [opened[0], opened[Math.floor(opened.length / 2)], opened[opened.length - 1]]
        .map((button) => button.closest('.react-flow__node').dataset.id);
    const toggle = (id) => document.querySelector(`.react-flow__node[data-id="${id}"] .local-dimensions-hubv2-toggle`);
    for (const id of ids) {
        const collapse = await until(() => toggle(id).click());
        const expand = await until(() => toggle(id).click());
        out.toggles.push({id, collapse, expand});
    }
    const pane = document.querySelector('.react-flow__pane');
    const rect = pane.getBoundingClientRect();
    let x = rect.left + rect.width / 2;
    let y = rect.top + rect.height / 2;
    const fire = (type, target) => target.dispatchEvent(new MouseEvent(type, {
        bubbles: true, cancelable: true, view: window, clientX: x, clientY: y, button: 0, buttons: type === 'mouseup' ? 0 : 1,
    }));
    fire('mousedown', pane);
    const before = viewport.style.transform;
    const steps = [];
    for (let i = 0; i < 60; i++) {
        x -= 4;
        y -= 2;
        const t0 = performance.now();
        fire('mousemove', window);
        force();
        steps.push(performance.now() - t0);
    }
    const moved = viewport.style.transform !== before;
    fire('mouseup', window);
    steps.sort((a, b) => a - b);
    out.pan = {moved, avg: +(steps.reduce((a, b) => a + b, 0) / steps.length).toFixed(2), max: +steps[59].toFixed(2)};
    out.nodesAfter = count();
    return out;
};

export {state, summary};
