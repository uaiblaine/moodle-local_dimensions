@local @local_dimensions @javascript
Feature: Walk the Competency hub's outline and map with the keyboard
  In order to manage a competency framework without a pointer
  As an administrator
  I need the outline and the map of the Competencies tab to be one Tab stop each, walked by the arrow keys, on one selection

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname | idnumber | competencyframework | ruletype                            | ruleoutcome |
      | Domain A  | DA       | BF1                 | core_competency\competency_rule_all | 1           |
    And the following "core_competency > competencies" exist:
      | shortname | idnumber | competencyframework |
      | Domain B  | DB       | BF1                 |
    And the following "core_competency > competencies" exist:
      | shortname | idnumber | competencyframework | parent |
      | Leaf A1   | LA1      | BF1                 | DA     |
      | Leaf A2   | LA2      | BF1                 | DA     |
    And I log in as "admin"
    And I visit "/local/dimensions/central.php"
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"

  Scenario: In the Both view the outline is one Tab stop, its arrow keys walk the tree and Enter selects in both panes
    Given I click on "Both" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"
    # A competency with a rule keeps its name on screen: the rule is a mark in the row and words in its description.
    And "//li[@role='treeitem']//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain A']" "xpath_element" should be visible
    And I should see "rule: All children are complete" in the "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain A']]" "xpath_element"
    And I set the focus on the "Collapse all" "button"
    When I press the tab key
    # With nothing selected, Tab enters at the framework, the tree's root.
    Then the focused element is "//li[@role='treeitem'][@aria-level='1']" "xpath_element"
    # One Tab stop: the next Tab leaves the tree for the splitter, and Shift+Tab comes back to the same row.
    And I press the tab key
    And the focused element is ".local-dimensions-hubv2-splitter" "css_element"
    And I press the shift tab key
    And the focused element is "//li[@role='treeitem'][@aria-level='1']" "xpath_element"
    # Right on the framework (always open) goes to its first child.
    And I press the right key
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain A']]" "xpath_element"
    And I should not see "Leaf A1" in the ".local-dimensions-hubv2-tree" "css_element"
    # Right on a closed parent opens it and keeps the focus there; Right again moves into it.
    And I press the right key
    And I should see "Leaf A1" in the ".local-dimensions-hubv2-tree" "css_element"
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain A']]" "xpath_element"
    And I press the right key
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Leaf A1']]" "xpath_element"
    # Down moves to the next sibling.
    And I press the down key
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Leaf A2']]" "xpath_element"
    # Enter selects: the panel shows that competency, the row is the selected one, and so is its node on the map.
    And I press the enter key
    And I should see "Leaf A2" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
    And the "aria-selected" attribute of "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Leaf A2']]" "xpath_element" should contain "true"
    And the "aria-current" attribute of "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Leaf A2,')]" "xpath_element" should contain "true"
    # The focus stays in the outline after the selection; a letter jumps to the next name it starts.
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Leaf A2']]" "xpath_element"
    And I press the d key
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain B']]" "xpath_element"

  Scenario: In the Map view the map is one Tab stop, its arrow keys walk the tree and Enter selects
    Given I click on "Map" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I should see "Domain A" in the ".local-dimensions-hubv2-canvas" "css_element"
    And ".local-dimensions-hubv2-tree" "css_element" should not exist
    And I set the focus on the "Search the tree" "field"
    When I press the tab key
    # With nothing selected, Tab enters at the framework node.
    Then the focused element is "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Behat framework,')]" "xpath_element"
    # One Tab stop: the next Tab leaves the nodes for the map's controls, and Shift+Tab comes back.
    And I press the tab key
    And the focused element is "Zoom in" "button"
    And I press the shift tab key
    And the focused element is "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Behat framework,')]" "xpath_element"
    # Right on the framework (always open) goes to its first child.
    And I press the right key
    And the focused element is "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Domain A,')]" "xpath_element"
    And I should not see "Leaf A1" in the ".local-dimensions-hubv2-canvas" "css_element"
    # Right on a closed parent opens it and keeps the focus there; Right again moves into it.
    And I press the right key
    And I should see "Leaf A1" in the ".local-dimensions-hubv2-canvas" "css_element"
    And the focused element is "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Domain A,')]" "xpath_element"
    And I press the right key
    And the focused element is "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Leaf A1,')]" "xpath_element"
    # Down moves to the next sibling.
    And I press the down key
    And the focused element is "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Leaf A2,')]" "xpath_element"
    # Enter selects: the panel shows that competency.
    And I press the enter key
    And I should see "Leaf A2" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
    # Left goes back to the parent; the selection stays.
    And I press the left key
    And the focused element is "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Domain A,')]" "xpath_element"
    And I should see "Leaf A2" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
