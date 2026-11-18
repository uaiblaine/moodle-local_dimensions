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
 * Competency hub v2: a framework's tree as a canvas of nodes, with a detail panel beside it.
 *
 * The entry of the bundle (js/esm/bundle.json), mounted by templates/hubv2.mustache with the props
 * classes/output/hubv2_page.php builds. The tree is lazy: the first page of roots arrives as props,
 * each branch is loaded when it is first opened (local_dimensions_browse_structure), and a node is
 * refreshed from the server after every change. Nothing is persisted: the layout is recomputed from
 * the tree on every change (layout.ts), and nodes cannot be dragged. Every action reuses the hub's
 * own dialogues and core's competency services, called as the list view (amd/src/central/structure.js)
 * calls them.
 *
 * @module     local_dimensions/hubv2/Hub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import type {ReactNode} from 'react';
import {
    Background, BackgroundVariant, Controls, MiniMap, ReactFlow, ReactFlowProvider, useReactFlow,
} from '../../vendor/xyflow/xyflow.js';
import type {AriaLabelConfig, NodeChange} from '../../vendor/xyflow/xyflow.js';
import {load, notifyError} from './amd';
import type {RuleConfig} from './amd';
import {CARD, FRAMEWORK_ID, buildGraph, competencyKey, competencyOf} from './graph';
import type {CanvasEdge, CanvasNode} from './graph';
import {blockedBody, confirmBody} from './deletion';
import {escapeHtml} from './html';
import {CanvasContext, CompetencyNode, FrameworkNode, MoreNode, RuleEdge} from './Nodes';
import type {CanvasActions} from './Nodes';
import Panel from './Panel';
import type {PanelActions} from './Panel';
import {track} from './pending';
import {
    allChildren, browse, countCompetencies, deleteCheck, deleteCompetency, getNode, moveOne, selectionData, setParent,
    updateRule,
} from './repository';
import type {NodeAnswer} from './repository';
import Search from './Search';
import {fill, fillObject, loadLabels} from './strings';
import type {Labels} from './strings';
import {
    ROOT, ancestors, branchHas, createTree, expandPath, forgetBranch, hasBranch, insertChildren, isExpanded, isWithin,
    removeNode, replaceNode, setExpanded,
} from './tree';
import type {Tree} from './tree';
import type {DeleteCheck, HubProps, PathItem, StructureNode, Usage} from './types';

/** The competency form the list view edits and adds with. */
const FORM_CLASS = 'local_dimensions\\form\\competency_dynamic_form';

/** The jQuery event, namespaced, through which the canvas hears a dialogue close. */
const MODAL_HIDDEN = 'modal:hidden.localdimensionshubv2';

/** Card components by node type; module-level so React Flow sees the same object on every render. */
const NODE_TYPES = {framework: FrameworkNode, competency: CompetencyNode, more: MoreNode};

/** The keys that turn the mouse wheel into zoom: the wheel alone scrolls the page. */
const ZOOM_KEYS = ['Control', 'Meta'];

/** The overview map's size, in pixels; FIT reserves the corner it covers. */
const MINIMAP = {width: 140, height: 96};

/**
 * How the tree is framed: a margin all round, and below it room for the overview map and the
 * controls so that no fitted node sits under them.
 */
const FIT = {padding: {top: '32px', right: '32px', bottom: '140px', left: '32px'}, maxZoom: 1};

/** Edge components by edge type. */
const EDGE_TYPES = {rule: RuleEdge};

/**
 * The canvas, the search and the panel, inside the provider useReactFlow() needs.
 *
 * @param {object} props The page's props and the loaded labels.
 * @returns {ReactNode} The canvas.
 */
const Canvas = (props: HubProps & {labels: Labels}): ReactNode => {
    const {labels, framework, pagesize} = props;
    const flow = useReactFlow();

    const [tree, setTree] = useState<Tree>(() => createTree(props.roots));
    const treeRef = useRef<Tree>(tree);
    const [selected, setSelected] = useState<number | null>(null);
    const selectedRef = useRef<number | null>(null);
    const [usage, setUsage] = useState<Usage | null>(null);
    const [related, setRelated] = useState<PathItem[]>([]);
    const [moving, setMoving] = useState<number | null>(null);
    const movingRef = useRef<number | null>(null);
    const [count, setCount] = useState(framework.competencycount);
    const [announcement, setAnnouncement] = useState('');
    const [measured, setMeasured] = useState<Record<string, {width: number, height: number}>>({});
    const centreOn = useRef<string | null>(null);
    const selectionAsk = useRef(0);
    // The competency a confirmed deletion is removing: its panel lists are not asked for again.
    const removing = useRef<number | null>(null);

    /**
     * Change the tree; the ref is what asynchronous work reads between renders.
     *
     * @param {Function} update From the current tree to the next.
     * @returns {void}
     */
    const commit = useCallback((update: (current: Tree) => Tree): void => {
        treeRef.current = update(treeRef.current);
        setTree(treeRef.current);
    }, []);

    /**
     * Load a parent's first page, or the page after the loaded ones.
     *
     * @param {number} parent The parent.
     * @param {boolean} append Whether to load the next page.
     * @returns {Promise} Resolves once in the tree.
     */
    const loadPage = useCallback((parent: number, append: boolean): Promise<void> => {
        const loaded = append ? (treeRef.current.branches[parent]?.ids.length ?? 0) : 0;
        return browse(framework.id, parent, loaded, pagesize)
            .then((page) => commit((current) => insertChildren(current, parent, page, append)));
    }, [commit, framework.id, pagesize]);

    /**
     * Reload a parent's children, as many as were loaded (one page at least), after a change among them.
     *
     * @param {number} parent The parent.
     * @returns {Promise} Resolves once in the tree.
     */
    const reloadBranch = useCallback(async(parent: number): Promise<void> => {
        const wanted = Math.max(treeRef.current.branches[parent]?.ids.length ?? 0, 1);
        const items: StructureNode[] = [];
        let total = 0;
        do {
            const page = await browse(framework.id, parent, items.length, pagesize);
            items.push(...page.items);
            total = page.total;
            if (page.items.length === 0) {
                break;
            }
        } while (items.length < Math.min(wanted, total));
        commit((current) => insertChildren(current, parent, {items, total}));
    }, [commit, framework.id, pagesize]);

    /**
     * Refresh a parent's own fields (whether it still has children), unless it is the framework.
     *
     * @param {number} parent The parent.
     * @returns {Promise} Resolves once in the tree.
     */
    const refreshParent = useCallback(async(parent: number): Promise<void> => {
        if (parent === ROOT) {
            return;
        }
        const answer = await getNode(parent);
        if (answer.found && answer.node) {
            const fresh = answer.node;
            commit((current) => replaceNode(current, fresh));
        }
    }, [commit]);

    /**
     * Make a competency drawable: load every branch along its path (further pages too, until it
     * is among them) and open its ancestors.
     *
     * @param {number} id The competency.
     * @param {Array} pathids Its ancestors, root first.
     * @returns {Promise} Resolves once it is on the canvas (or its branch is exhausted).
     */
    const ensureVisible = useCallback(async(id: number, pathids: number[]): Promise<void> => {
        const chain = [ROOT, ...pathids];
        for (let index = 0; index < chain.length; index++) {
            const parent = chain[index];
            const child = index + 1 < chain.length ? chain[index + 1] : id;
            if (!hasBranch(treeRef.current, parent)) {
                await loadPage(parent, false);
            }
            while (!branchHas(treeRef.current, parent, child)) {
                const branch = treeRef.current.branches[parent];
                if (!branch || branch.ids.length >= branch.total) {
                    break;
                }
                await loadPage(parent, true);
            }
        }
        commit((current) => expandPath(current, pathids));
    }, [commit, loadPage]);

    /**
     * Load what the panel shows beyond the node; a later selection discards an earlier answer.
     *
     * @param {number} id The competency.
     * @returns {void}
     */
    const loadSelection = useCallback((id: number): void => {
        const ask = ++selectionAsk.current;
        track('selection', selectionData(id))
            .then(([answer, relations]) => {
                if (ask === selectionAsk.current) {
                    setUsage(answer);
                    setRelated(relations);
                }
                return null;
            })
            .catch(notifyError);
    }, []);

    /**
     * Select a competency, or the framework with null.
     *
     * @param {number|null} id The competency.
     * @returns {void}
     */
    const choose = useCallback((id: number | null): void => {
        selectedRef.current = id;
        setSelected(id);
        setUsage(null);
        setRelated([]);
        if (id === null) {
            selectionAsk.current++;
            return;
        }
        loadSelection(id);
    }, [loadSelection]);

    /**
     * Reveal a competency, select it and centre the canvas on it.
     *
     * @param {number} id The competency.
     * @param {Array} pathids Its ancestors, root first.
     * @returns {Promise} Resolves once revealed.
     */
    const reveal = useCallback((id: number, pathids: number[]): Promise<void> =>
        ensureVisible(id, pathids).then(() => {
            centreOn.current = competencyKey(id);
            choose(id);
            return undefined;
        }), [choose, ensureVisible]);

    /**
     * Place a competency again after it changed parent: reload both branches, refresh both parents
     * and reveal it where it now is. Its own children are forgotten: their depth, and so their
     * taxonomy, changed.
     *
     * @param {number} id The competency.
     * @param {number} oldparent Its parent before.
     * @param {object} answer Its fresh node and path.
     * @returns {Promise} Resolves once placed.
     */
    const relocate = useCallback(async(id: number, oldparent: number, answer: NodeAnswer): Promise<void> => {
        const fresh = answer.node as StructureNode;
        commit((current) => forgetBranch(current, id));
        await reloadBranch(oldparent);
        await refreshParent(oldparent);
        if (hasBranch(treeRef.current, fresh.parentid)) {
            await reloadBranch(fresh.parentid);
        }
        await refreshParent(fresh.parentid);
        await reveal(id, answer.pathids ?? []);
    }, [commit, refreshParent, reloadBranch, reveal]);

    /**
     * Refresh one competency after a dialogue changed it; one the form moved is placed again, one
     * that is gone is removed.
     *
     * @param {number} id The competency.
     * @returns {Promise} Resolves once refreshed.
     */
    const refreshNode = useCallback(async(id: number): Promise<void> => {
        const answer = await getNode(id);
        const known = treeRef.current.nodes[id];
        if (!answer.found || !answer.node) {
            commit((current) => removeNode(current, id));
            if (selectedRef.current === id) {
                choose(null);
            }
            return;
        }
        if (known && known.parentid !== answer.node.parentid) {
            await relocate(id, known.parentid, answer);
            return;
        }
        const fresh = answer.node;
        commit((current) => replaceNode(current, fresh));
        if (selectedRef.current === id) {
            loadSelection(id);
        }
    }, [choose, commit, loadSelection, relocate]);

    /**
     * Open or close a competency's children, loading them the first time.
     *
     * @param {number} id The competency.
     * @returns {void}
     */
    const toggle = useCallback((id: number): void => {
        if (isExpanded(treeRef.current, id)) {
            commit((current) => setExpanded(current, id, false));
            return;
        }
        const loading = hasBranch(treeRef.current, id) ? Promise.resolve() : loadPage(id, false);
        track('children', loading.then(() => commit((current) => setExpanded(current, id, true)))).catch(notifyError);
    }, [commit, loadPage]);

    /**
     * Load a parent's next page of children.
     *
     * @param {number} parent The parent.
     * @returns {void}
     */
    const loadMore = useCallback((parent: number): void => {
        track('children', loadPage(parent, true)).catch(notifyError);
    }, [loadPage]);

    /**
     * Say something to assistive technology through the canvas's live region.
     *
     * @param {string} message The message.
     * @returns {void}
     */
    const announce = (message: string): void => setAnnouncement(message);

    /**
     * Leave the move mode.
     *
     * @param {boolean} cancelled Whether to say it was cancelled.
     * @returns {void}
     */
    const endMove = useCallback((cancelled: boolean): void => {
        movingRef.current = null;
        setMoving(null);
        if (cancelled) {
            setAnnouncement(labels.hubv2_movecancelled);
        }
    }, [labels]);

    /**
     * Focus a node on the canvas once it is drawn.
     *
     * @param {string} key The node id.
     * @returns {void}
     */
    const focusNode = (key: string): void => {
        window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
            document.querySelector<HTMLElement>(`.local-dimensions-hubv2 .react-flow__node[data-id="${key}"]`)?.focus();
        }));
    };

    /**
     * Give the competency being moved its new parent, once confirmed.
     *
     * @param {number} target The new parent, ROOT for the top level.
     * @returns {void}
     */
    const pickParent = (target: number): void => {
        const source = movingRef.current !== null ? treeRef.current.nodes[movingRef.current] : undefined;
        if (!source) {
            endMove(false);
            return;
        }
        if (target !== ROOT && isWithin(treeRef.current, target, source.id)) {
            announce(labels.hubv2_moveinvalid);
            return;
        }
        if (target === source.parentid) {
            endMove(true);
            return;
        }
        const name = escapeHtml(source.shortname);
        const question = target === ROOT
            ? fill(labels.hubv2_moveconfirmroot, name)
            : fillObject(labels.hubv2_moveconfirm, {name, parent: escapeHtml(treeRef.current.nodes[target]?.shortname ?? '')});
        (async() => {
            const notification = await load('core/notification');
            const confirmed = await notification
                .saveCancelPromise(labels.hubv2_movetitle, question, labels.hubv2_move)
                .then(() => true, () => false);
            if (!confirmed) {
                return;
            }
            endMove(false);
            await track('move', (async() => {
                await setParent(source.id, target);
                const answer = await getNode(source.id);
                if (answer.found) {
                    await relocate(source.id, source.parentid, answer);
                }
                announce(fill(labels.hubv2_moved, source.shortname));
            })());
        })().catch(notifyError);
    };

    /**
     * React to what React Flow reports: a node's size, and a selection by click or keyboard,
     * which in the move mode picks the new parent instead.
     *
     * @param {Array} changes The changes.
     * @returns {void}
     */
    const onNodesChange = (changes: NodeChange[]): void => {
        const sizes: Record<string, {width: number, height: number}> = {};
        changes.forEach((change) => {
            if (change.type === 'dimensions' && change.dimensions) {
                sizes[change.id] = change.dimensions;
            }
        });
        if (Object.keys(sizes).length > 0) {
            setMeasured((current) => ({...current, ...sizes}));
        }
        const picked = changes.find((change) => change.type === 'select' && change.selected);
        if (picked && 'id' in picked) {
            const id = picked.id === FRAMEWORK_ID ? ROOT : competencyOf(picked.id);
            if (id === null) {
                return;
            }
            if (movingRef.current !== null) {
                pickParent(id);
            } else {
                choose(id === ROOT ? null : id);
            }
            return;
        }
        const unselected = changes.some((change) => change.type === 'select' && !change.selected);
        if (unselected && movingRef.current === null) {
            choose(null);
        }
    };

    /**
     * Open the competency form: an edit of a competency, or a new child (of the framework with null).
     *
     * @param {object|null} node The competency to edit, or null.
     * @param {object|null} parent The new competency's parent, or null for the top level.
     * @returns {void}
     */
    const openForm = (node: StructureNode | null, parent: StructureNode | null): void => {
        const args = node
            ? {competencyframeworkid: framework.id, id: node.id, parentid: node.parentid}
            : {competencyframeworkid: framework.id, id: 0, parentid: parent ? parent.id : ROOT};
        const title = labels[node ? 'tool_lp:editcompetency' : 'tool_lp:addcompetency'];
        (async() => {
            const ModalForm = await load('core_form/modalform');
            const form = new ModalForm({formClass: FORM_CLASS, args, modalConfig: {title}});
            form.addEventListener(form.events.FORM_SUBMITTED, (event) => {
                if (node) {
                    track('refresh', refreshNode(node.id)).catch(notifyError);
                    return;
                }
                const newid = Number((event as CustomEvent).detail?.competencyid) || 0;
                const parentid = parent ? parent.id : ROOT;
                track('refresh', (async() => {
                    await refreshParent(parentid);
                    if (hasBranch(treeRef.current, parentid)) {
                        await reloadBranch(parentid);
                    }
                    setCount(await countCompetencies(framework.id));
                    const answer = newid ? await getNode(newid) : {found: false};
                    if (answer.found) {
                        await reveal(newid, answer.pathids ?? []);
                    }
                })()).catch(notifyError);
            });
            await form.show();
        })().catch(notifyError);
    };

    /**
     * Store a rule chosen in the hub's rule dialogue, then refresh the competency.
     *
     * @param {object} node The competency.
     * @param {object} config The rule.
     * @returns {Promise} Resolves once stored and refreshed.
     */
    const storeRule = (node: StructureNode, config: RuleConfig): Promise<void> =>
        track('refresh', updateRule(node.id, config).then(() => refreshNode(node.id)))
            .then(() => announce(labels['core:changessaved']));

    /**
     * Open the hub's rule dialogue over the competency's children, then store what it returns.
     *
     * @param {object} node The competency.
     * @returns {void}
     */
    const editRule = (node: StructureNode): void => {
        // Only the children's fetch is tracked: the dialogue then waits on the viewer.
        (async() => {
            const children = await track('rule', allChildren(framework.id, node.id, pagesize));
            const module = await load('local_dimensions/central/rule_config');
            const config = await module.show({
                id: node.id,
                ruletype: node.ruletype ?? '',
                ruleoutcome: node.ruleoutcome,
                ruleconfig: node.ruleconfig ?? '',
            }, children, props.rulesmodules);
            if (config) {
                await storeRule(node, config);
            }
        })().catch(notifyError);
    };

    /**
     * Open the hub's courses and activities dialogue; its counts are refreshed when it closes.
     *
     * @param {object} node The competency.
     * @returns {void}
     */
    const openLinks = (node: StructureNode): void => {
        /**
         * Refresh the competency's counts.
         *
         * @returns {void}
         */
        const onClose = (): void => {
            track('refresh', refreshNode(node.id)).catch(notifyError);
        };
        load('local_dimensions/central/competency_links')
            .then((module) => module.open({
                competencyid: node.id,
                competencyname: node.shortname,
                courseoutcomes: props.courseoutcomes,
                moduleoutcomes: props.moduleoutcomes,
                onClose,
            }))
            .catch(notifyError);
    };

    /**
     * Open the hub's related competencies dialogue. The relations are read again when it closes,
     * through the modal-hidden listener below.
     *
     * @param {object} node The competency.
     * @returns {void}
     */
    const openRelated = (node: StructureNode): void => {
        load('local_dimensions/central/related_competencies')
            .then((module) => module.open({competencyid: node.id, competencyname: node.shortname, frameworkid: framework.id}))
            .catch(notifyError);
    };

    /**
     * Enter the move mode: the next node picked becomes the new parent.
     *
     * @param {object} node The competency to move.
     * @returns {void}
     */
    const startMove = (node: StructureNode): void => {
        movingRef.current = node.id;
        setMoving(node.id);
        announce(fill(labels.hubv2_movepick, node.shortname));
        focusNode(competencyKey(node.id));
    };

    /**
     * Move a competency one place among its siblings, then reload them.
     *
     * @param {object} node The competency.
     * @param {boolean} up Up when true.
     * @returns {void}
     */
    const stepMove = (node: StructureNode, up: boolean): void => {
        track('refresh', moveOne(node.id, up).then(() => reloadBranch(node.parentid))).catch(notifyError);
    };

    /**
     * Delete a competency: ask the server first, say why when core would refuse it, and otherwise name
     * what will be destroyed before asking for the confirmation.
     *
     * @param {object} node The competency.
     * @returns {void}
     */
    const remove = (node: StructureNode): void => {
        (async() => {
            const notification = await load('core/notification');
            // Set before any dialogue opens: the dialogue reports its closing before its promise settles, and
            // that closing would otherwise ask for the lists of a competency that is going away.
            removing.current = node.id;
            /**
             *
             * @param check
             */
            /**
             * Tell why core would refuse the deletion.
             *
             * @param {object} check The server's answer.
             * @returns {Promise} Resolves when the dialogue closes.
             */
            const showBlocked = async(check: DeleteCheck): Promise<void> => {
                removing.current = null;
                await notification.alert(labels.hubv2_delete_blocked_title, blockedBody(labels, node.shortname, check));
            };
            const check = await track('refresh', deleteCheck(node.id)).catch((error: unknown) => {
                removing.current = null;
                throw error;
            });
            if (!check.candelete) {
                await showBlocked(check);
                return;
            }
            const confirmed = await notification
                .deleteCancelPromise(labels.hubv2_delete, confirmBody(labels, node.shortname, check))
                .then(() => true, () => false);
            if (!confirmed) {
                removing.current = null;
                return;
            }
            await track('refresh', (async() => {
                const done = await deleteCompetency(node.id).catch((error: unknown) => {
                    removing.current = null;
                    throw error;
                });
                if (done === false) {
                    // Something changed since the check: ask again for the reasons.
                    const again = await deleteCheck(node.id).catch(() => null);
                    if (again && !again.candelete) {
                        await showBlocked(again);
                    } else {
                        removing.current = null;
                        await notification.alert(
                            null,
                            fill(labels['tool_lp:competencycannotbedeleted'], escapeHtml(node.shortname))
                        );
                    }
                    return;
                }
                commit((current) => removeNode(current, node.id));
                choose(null);
                await reloadBranch(node.parentid);
                await refreshParent(node.parentid);
                setCount(await countCompetencies(framework.id));
            })());
        })().catch(notifyError);
    };

    /**
     * Reveal, select and centre a competency named by a search hit or a relation.
     *
     * @param {object} item The competency and its path.
     * @returns {void}
     */
    const revealItem = (item: PathItem): void => {
        track('reveal', reveal(item.id, item.pathids)).catch(notifyError);
    };

    /**
     * Edit a competency.
     *
     * @param {object} node The competency.
     * @returns {void}
     */
    const edit = (node: StructureNode): void => openForm(node, null);

    /**
     * Add a competency under another, or at the top level with null.
     *
     * @param {object|null} node The parent.
     * @returns {void}
     */
    const addChild = (node: StructureNode | null): void => openForm(null, node);

    /**
     * Make the competency being moved a top-level one.
     *
     * @returns {void}
     */
    const moveRoot = (): void => pickParent(ROOT);

    /**
     * Leave the move mode without moving.
     *
     * @returns {void}
     */
    const cancelMove = (): void => endMove(true);

    const actions: PanelActions = {
        edit, addChild, rule: editRule, links: openLinks, relate: openRelated, move: startMove, moveRoot, cancelMove,
        moveOne: stepMove, remove, reveal: revealItem,
    };

    // Escape leaves the move mode, wherever the focus is.
    useEffect(() => {
        if (moving === null) {
            return undefined;
        }
        /**
         * Leave the move mode on Escape.
         *
         * @param {object} event The key event.
         * @returns {void}
         */
        const onKey = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                endMove(true);
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [moving, endMove]);

    // Any dialogue closing (the related competencies', the links', a form) refreshes the panel's lists.
    useEffect(() => {
        /**
         * Unbind the listener; replaced once jQuery has loaded.
         *
         * @returns {void}
         */
        let off = (): void => undefined;
        load('jquery')
            .then(($) => {
                /**
                 * Read the selected competency's lists again, unless it is being deleted.
                 *
                 * @returns {void}
                 */
                const onHidden = (): void => {
                    if (selectedRef.current !== null && selectedRef.current !== removing.current) {
                        loadSelection(selectedRef.current);
                    }
                };
                $(document).on(MODAL_HIDDEN, onHidden);
                /**
                 * Unbind the listener.
                 *
                 * @returns {void}
                 */
                off = (): void => {
                    $(document).off(MODAL_HIDDEN);
                };
                return null;
            })
            .catch(notifyError);
        return () => off();
    }, [loadSelection]);

    /**
     * The name of a "load more" node.
     *
     * @param {number} remaining How many children are still to load.
     * @returns {string} The name.
     */
    const moreLabel = useCallback((remaining: number): string =>
        fill(labels.hubv2_loadmore, String(Math.min(remaining, pagesize))), [labels, pagesize]);

    const relatedids = useMemo(() => related.map((item) => item.id), [related]);
    const graph = useMemo(() => buildGraph({
        tree,
        framework: {...framework, competencycount: count},
        selected,
        related: relatedids,
        moving,
        labels: {roledescription: labels.hubv2_noderole, more: moreLabel},
    }), [tree, framework, count, selected, relatedids, moving, labels, moreLabel]);

    const nodes = useMemo((): CanvasNode[] => graph.nodes.map((node) =>
        (measured[node.id] ? {...node, measured: measured[node.id]} : node)), [graph, measured]);

    const oncanvas = useMemo(() => new Set(graph.nodes.map((node) => node.id)), [graph]);

    // Centre on a competency just revealed, once the layout has placed it.
    useEffect(() => {
        const key = centreOn.current;
        const position = key ? graph.positions[key] : undefined;
        if (!key || !position) {
            return;
        }
        centreOn.current = null;
        const zoom = Math.max(flow.getZoom(), 0.8);
        flow.setCenter(position.x + CARD.width / 2, position.y + CARD.height / 2, {zoom, duration: 300}).catch(() => null);
        focusNode(key);
    }, [graph, flow]);

    const canvasActions: CanvasActions = useMemo(
        () => ({labels, pagesize, toggle, loadMore}),
        [labels, pagesize, toggle, loadMore]
    );

    /**
     * Nothing to say.
     *
     * @returns {string} The empty string.
     */
    const silent = (): string => '';

    const aria: Partial<AriaLabelConfig> = useMemo(() => ({
        'node.a11yDescription.default': labels.hubv2_a11y_node,
        'node.a11yDescription.keyboardDisabled': labels.hubv2_a11y_node,
        // Spoken when a node is moved by the arrow keys, which a node that cannot be dragged never is.
        'node.a11yDescription.ariaLiveMessage': silent,
        'controls.ariaLabel': labels.hubv2_a11y_controls,
        'controls.zoomIn.ariaLabel': labels.hubv2_zoomin,
        'controls.zoomOut.ariaLabel': labels.hubv2_zoomout,
        'controls.fitView.ariaLabel': labels.hubv2_fitview,
        'controls.interactive.ariaLabel': labels.hubv2_a11y_controls,
        'minimap.ariaLabel': labels.hubv2_minimap,
        'handle.ariaLabel': labels.hubv2_a11y_handle,
    }), [labels]);

    const node = selected !== null ? tree.nodes[selected] ?? null : null;
    const siblings = node ? tree.branches[node.parentid] : undefined;
    const position = node && siblings ? siblings.ids.indexOf(node.id) : -1;
    const islast = !!siblings && position === siblings.ids.length - 1 && siblings.ids.length >= siblings.total;
    const movingnode = moving !== null ? tree.nodes[moving] ?? null : null;

    return (
        <div className={`local-dimensions-hubv2-layout${moving !== null ? ' local-dimensions-hubv2-picking' : ''}`}>
            <div className="local-dimensions-hubv2-main">
                <div className="local-dimensions-hubv2-bar">
                    <Search labels={labels} frameworkid={framework.id} onChoose={actions.reveal} />
                    <span className="local-dimensions-hubv2-hint">
                        <i className="fa fa-search-plus me-1" aria-hidden="true" />{labels.hubv2_zoomhint}
                    </span>
                </div>
                <div className="local-dimensions-hubv2-canvas" role="region" aria-label={labels.hubv2_canvas}>
                    <CanvasContext.Provider value={canvasActions}>
                        <ReactFlow<CanvasNode, CanvasEdge>
                            nodes={nodes}
                            edges={graph.edges}
                            nodeTypes={NODE_TYPES}
                            edgeTypes={EDGE_TYPES}
                            onNodesChange={onNodesChange}
                            nodesDraggable={false}
                            nodesConnectable={false}
                            edgesFocusable={false}
                            selectNodesOnDrag={false}
                            deleteKeyCode={null}
                            selectionKeyCode={null}
                            multiSelectionKeyCode={null}
                            zoomOnDoubleClick={false}
                            zoomOnScroll={false}
                            zoomOnPinch
                            preventScrolling={false}
                            zoomActivationKeyCode={ZOOM_KEYS}
                            fitView
                            fitViewOptions={FIT}
                            minZoom={0.2}
                            maxZoom={1.5}
                            ariaLabelConfig={aria}
                            proOptions={{hideAttribution: true}}
                        >
                            <Background variant={BackgroundVariant.Dots} gap={18} size={1}
                                className="local-dimensions-hubv2-dots" />
                            <Controls showInteractive={false} fitViewOptions={FIT} />
                            <MiniMap pannable zoomable ariaLabel={labels.hubv2_minimap} style={MINIMAP} />
                        </ReactFlow>
                    </CanvasContext.Provider>
                </div>
            </div>
            <Panel
                labels={labels}
                framework={framework}
                count={count}
                node={node}
                path={node ? ancestors(tree, node.id).map((id) => tree.nodes[id].shortname) : []}
                usage={usage}
                related={related}
                onCanvas={(id) => oncanvas.has(competencyKey(id))}
                moving={movingnode}
                canUp={position > 0}
                canDown={position >= 0 && !islast}
                actions={actions}
            />
            <div className="visually-hidden" aria-live="polite">{announcement}</div>
        </div>
    );
};

/**
 * Competency hub v2's canvas: the strings first, read in one call from the page's own cache.
 *
 * @param {object} props The page's props.
 * @returns {ReactNode} The component.
 */
const Hub = (props: HubProps): ReactNode => {
    const [labels, setLabels] = useState<Labels | null>(null);
    useEffect(() => {
        track('strings', loadLabels()).then(setLabels).catch(notifyError);
    }, []);
    if (!labels) {
        return <div className="local-dimensions-hubv2-layout" aria-busy="true" />;
    }
    return (
        <div className="local-dimensions-hubv2-root">
            <ReactFlowProvider>
                <Canvas {...props} labels={labels} />
            </ReactFlowProvider>
        </div>
    );
};

export default Hub;
