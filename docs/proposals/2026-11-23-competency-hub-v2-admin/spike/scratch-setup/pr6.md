## What

Stage S4 of the competency hub v2 plan (owner's decisions H2 and H3): on 5.3 the workspace is the hub's default, and the framework lives on the tree's root.

- **The framework on the root**: its facts (context, scale, taxonomies as column headings, counts, visibility) and its actions (edit, duplicate, show or hide, export, delete, manage scales) are on the root node and in the workspace's Structures menu (switch framework, create, import), calling the functions the Structures tab's module already exports. Each action is still checked by its own service or form in the framework's own context.
- **The workspace as the default**: the tab strip is Competencies and Learning plans, and the Competencies tab opens on Both. The Structures tab and the classic list stay for one release behind a quiet **Classic view** link in the context bar (`classes/local/hub_mode.php`); **Workspace view** switches back. Display only: each tab is still admitted by its own `is_available()`.
- The mode is one member of the hub's display preference with **one writer**: the request that carries the session key. A display save from the client can no longer change it (the preference's cleaner keeps the stored value), so a tab left open in the other mode does not write it back.

Version `2026100538`.

## Security

Written with `secure-by-design`, read with `security-diff-read` before the push: no blocking finding. The mode is saved only on a request with a valid session key and admits two values; a guest or a logged-out visitor cannot reach the write; the redirect after it goes to the page's own address with four cleaned parameters (tab, context type, category, framework), no return address. The Structures menu and the root's facts come from the list the Competencies tab already filters (unlisted categories, `can_read_context`). Fixed from the read: the single writer above. Left as found (parity with the Structures tab): `can_all_be_deleted` over every competency of the selected framework on a manager's render.

Not requested here: an MDL Shield review (the owner posts that command).

## Gate

Tier 3 (a page features drive, a preference write, JavaScript), on the head `c7d87fa`:

`mdl ci wt-local_dimensions-hubv2-s4 --matrix --behat --remote --keep-logs` (Mac mini): **4 of 4 legs passed**.

| Leg | PHPUnit | Behat |
|---|---|---|
| 5.03 · PHP 8.4 · pgsql (statics, build, Jest 215 of 215) | 1,096 tests, 26,399 assertions, 8 skipped | 46 scenarios, 805 steps, all passed |
| 5.03 · PHP 8.3 · pgsql | 1,096 tests, 26,399 assertions, 8 skipped | 46 scenarios, 805 steps |
| 5.03 · PHP 8.3 · mariadb | 1,096 tests, 26,388 assertions, 11 skipped | 46 scenarios, 805 steps |
| 5.03 · PHP 8.4 · mariadb | 1,096 tests, 26,388 assertions, 11 skipped | 46 scenarios, 805 steps |

Two earlier runs were red and are fixed in this head: a cast of core's taxonomy list (an array) to a string in `framework_facts()`, which raised a PHP warning on every hub page (18 scenarios) and counted one level; and the redirect after the mode is saved, which went to the bare page and so left Competencies for the Structures tab (1 scenario).

Not checked: a screen reader, phone width, right-to-left and dark mode in a browser; nobody has used this head in a browser yet (it is installed on the stacks after the merge).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
