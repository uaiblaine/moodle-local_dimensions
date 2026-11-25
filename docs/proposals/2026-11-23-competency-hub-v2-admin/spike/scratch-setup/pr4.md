## What

Stage S3 of the competency hub v2 plan: every action of the workspace (Outline, Map, Both) comes from one registry, and four surfaces draw from it.

- **A node's menu**: a "more" button on the selected row and map card, and Shift+F10 or the context-menu key on the focused item. ARIA menu keys, Delete last, focus returns to the item.
- **A command palette** (`Commands` in the workspace's bar, Ctrl+K / Cmd+K while the focus is in the workspace): the selected competency's actions, "Go to" (the tree's search) and the structure and view commands.
- **Move to…**: a dialogue with a search field and the possible parents; the competency's own subtree is not offered, and the confirmation names the completion rules core resets on both parents.
- **Typing in the outline**: F2 renames, Ctrl/Cmd+Enter (or Insert) adds a sibling, Ctrl/Cmd+Shift+Enter a child, with an inline editor of two fields (name, ID number) that reopens for the next sibling.
- The panel keeps three controls: Edit, Add child, More actions.

No web service is added: the writes are core's `core_competency_create_competency`, `_update_competency`, `_move_up_competency`, `_move_down_competency` and `_set_parent_competency`, which check the competency capabilities in the framework's context themselves. A reader is offered no command (the registry decides once; the server refuses regardless).

Version `2026100536`.

## Gate

Tier 3 (a page a feature drives, JavaScript, strings), on the head `5b76f23`:

`mdl ci wt-local_dimensions-hubv2-s3 --matrix --behat --remote --keep-logs` (Mac mini): **4 of 4 legs passed**.

| Leg | PHPUnit | Behat |
|---|---|---|
| 5.03 · PHP 8.4 · pgsql (statics, build, Jest 199 of 199) | 1,023 tests, 25,413 assertions, 8 skipped | 41 scenarios, 701 steps, all passed |
| 5.03 · PHP 8.3 · pgsql | 1,023 tests, 25,413 assertions, 8 skipped | 41 scenarios, 701 steps |
| 5.03 · PHP 8.3 · mariadb | 1,023 tests, 25,402 assertions, 11 skipped | 41 scenarios, 701 steps |
| 5.03 · PHP 8.4 · mariadb | 1,023 tests, 25,402 assertions, 11 skipped | 41 scenarios, 701 steps |

A first run of the matrix had 4 of 41 scenarios red (the menu took its first focus before it was placed; three scenario wordings); fixed and re-run in full.

Not checked: a screen reader, phone width, right-to-left and dark mode in a browser.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
