## What

The client half of stage S5 of the competency hub v2 plan: three lenses and a structure checker in the workspace, drawn from `local_dimensions_get_framework_insights` (merged in #176), one answer per framework. No write path is added: every fix a finding offers is an existing action of the registry.

- **Lens switch** in the workspace's bar and the palette: None, Usage, Rules, Relations; saved as a member of the hub's display preference and kept in the address as `lens=`.
- **Usage**: each card's ground steps by the chosen measure (courses, activities, plan templates, learning plans), an unused competency has a dashed edge, the count is in words under the name and in the accessible names; the panel holds the measure switch, the legend with counts and the unused list. Under the `presence` level only used and unused are shown and the panel says why.
- **Rules**: a ruled parent carries what its rule asks, its children their points and "required"; a rule that can never fire is a dashed warning with words. **Relations**: related pairs as dotted edges, "+N elsewhere" for an end not drawn.
- **Check**: a button with the findings' count opens the checker in the panel: the codes with counts, each finding with **Reveal** and the action that fixes it; F8 and Shift+F8 walk the findings and announce them.
- **Impact preview**: while a delete's confirmation or "Move to" is open, the competencies it touches are outlined in both panes.
- Every lens has a text form (a legend with counts and a list in the panel); a lens never says something by colour alone.

A competency a finding names is never shown as a bare id: a name the tree has not loaded is fetched in one batched read, and **Reveal** loads its path. The saved lens has one writer per choice: the client sends it only in the write that follows the choice, and the preference's cleaner keeps the stored one otherwise, so a tab left open does not write an old lens back.

Version `2026100539`.

## Gate

Tier 3 (pages features drive, a preference, JavaScript), on the head `b13c69a`:

`mdl ci wt-local_dimensions-hubv2-s5 --matrix --behat --remote --keep-logs` (Mac mini): **4 of 4 legs passed**.

| Leg | PHPUnit | Behat |
|---|---|---|
| 5.03 · PHP 8.4 · pgsql (statics, build, Jest 265 of 265) | 1,102 tests, 27,870 assertions, 8 skipped | 49 scenarios, 863 steps, all passed |
| 5.03 · PHP 8.3 · pgsql | 1,102 tests, 27,870 assertions, 8 skipped | 49 scenarios, 863 steps |
| 5.03 · PHP 8.3 · mariadb | 1,102 tests, 27,859 assertions, 11 skipped | 49 scenarios, 863 steps |
| 5.03 · PHP 8.4 · mariadb | 1,102 tests, 27,859 assertions, 11 skipped | 49 scenarios, 863 steps |

A first run was red on one contract test (a function placed between the root's classes and the root element hid the outline width from the test's window) and one keyboard scenario (the new "Check" button sat after "Commands", between the bar and the map in the Tab order); both fixed in the code, the tests unchanged.

Not checked: a screen reader, phone width, right-to-left and dark mode in a browser; the lens colours were not looked at in a browser on this head.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
