@local @local_dimensions @javascript
Feature: Run the Competency hub's commands from the keyboard
  In order to restructure a competency framework without a pointer
  As a manager of the framework's competencies
  I need every action of the workspace in a node's menu, behind keys in the outline, and nothing of it for a reader

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "categories" exist:
      | name        | idnumber |
      | Engineering | ENG      |
    And the following "users" exist:
      | username | firstname | lastname |
      | reader   | Rea       | Der      |
    And the following "local_dimensions > frameworks" exist:
      | shortname       | idnumber | category |
      | Behat framework | BF1      | ENG      |
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

  Scenario: The node menu, opened from the keyboard, moves a competency under another parent through "Move to"
    Given I am on the "ENG" "local_dimensions > Competency hub" page logged in as "admin"
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    And I click on "Outline" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"
    And I set the focus on the "Display options" "button"
    And I press the tab key
    And the focused element is "//li[@role='treeitem'][@aria-level='1']" "xpath_element"
    And I press the right key
    And I press the right key
    And I press the right key
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Leaf A1']]" "xpath_element"
    And I press the enter key
    # A menu opened with the pointer gives the focus back to the row, or to the panel's button, on Escape.
    And I click on "Actions of Leaf A1" "button"
    And I press the escape key
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Leaf A1']]" "xpath_element"
    And I click on "More actions" "button" in the "aside.local-dimensions-hubv2-panel" "css_element"
    And "//*[@role='menu']//button[@role='menuitem'][starts-with(normalize-space(.), 'Delete')]" "xpath_element" should exist
    And I press the escape key
    And the focused element is "More actions" "button" in the "aside.local-dimensions-hubv2-panel" "css_element"
    # The selected row carries the menu's button; Enter on it opens the menu on its first action.
    When I set the focus on the "Actions of Leaf A1" "button"
    And I press the enter key
    Then the focused element is "//*[@role='menu']//button[@role='menuitem'][starts-with(normalize-space(.), 'Edit')]" "xpath_element"
    # Type-ahead in the menu, then Enter runs the action.
    And I press the m key
    And the focused element is "//*[@role='menu']//button[@role='menuitem'][starts-with(normalize-space(.), 'Move to')]" "xpath_element"
    And I press the enter key
    # The workspace's own dialogue, by its class: the page may carry Moodle's own role="dialog" elements.
    And I should see "Move Leaf A1" in the ".local-dimensions-hubv2-movedialog[role='dialog']" "css_element"
    And I should see "Top level of Behat framework" in the ".local-dimensions-hubv2-movedialog [role='tree']" "css_element"
    And I should see "Current parent" in the "//*[contains(@class, 'local-dimensions-hubv2-movedialog')]//li[@role='treeitem'][contains(., 'Domain A')]" "xpath_element"
    And the "aria-disabled" attribute of "//*[contains(@class, 'local-dimensions-hubv2-movedialog')]//li[@role='treeitem'][contains(., 'Domain A')]" "xpath_element" should contain "true"
    # The tree of possible parents is one Tab stop after the search field: Down twice reaches Domain B.
    And I press the tab key
    And I press the down key
    And I press the down key
    And I press the enter key
    And the "aria-selected" attribute of "//*[contains(@class, 'local-dimensions-hubv2-movedialog')]//li[@role='treeitem'][contains(., 'Domain B')]" "xpath_element" should contain "true"
    # Domain A has a rule, which core resets when it loses a child: the dialogue says so before its buttons.
    And I should see "The rule of \"Domain A\", the current parent, will be reset." in the ".local-dimensions-hubv2-movedialog" "css_element"
    And I press "Move under Domain B"
    And ".local-dimensions-hubv2-movedialog" "css_element" should not exist
    And I should see "Domain B" in the "aside.local-dimensions-hubv2-panel" "css_element"
    And "//li[@role='treeitem'][.//span[.='Domain B']]/following-sibling::li[1][@aria-level='3'][.//span[.='Leaf A1']]" "xpath_element" should exist

  Scenario: Alt+Down moves the focused competency below its next sibling and keeps the focus on it
    Given I am on the "ENG" "local_dimensions > Competency hub" page logged in as "admin"
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    And I click on "Outline" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"
    And I set the focus on the "Display options" "button"
    And I press the tab key
    And I press the right key
    And I press the right key
    And I press the right key
    And "//li[@role='treeitem'][.//span[.='Leaf A1']]/following-sibling::li[1][.//span[.='Leaf A2']]" "xpath_element" should exist
    When I press the alt down key
    Then "//li[@role='treeitem'][.//span[.='Leaf A2']]/following-sibling::li[1][.//span[.='Leaf A1']]" "xpath_element" should exist
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Leaf A1']]" "xpath_element"
    And I should see "\"Leaf A1\" moved down, now 2 of 2." in the ".local-dimensions-hubv2-layout > [aria-live='polite']" "css_element"
    # At the end nothing moves, and the live region says so.
    And I press the alt down key
    And I should see "\"Leaf A1\" is already the last." in the ".local-dimensions-hubv2-layout > [aria-live='polite']" "css_element"
    And "//li[@role='treeitem'][.//span[.='Leaf A2']]/following-sibling::li[1][.//span[.='Leaf A1']]" "xpath_element" should exist

  Scenario: Ctrl+Enter on a row types a new sibling right below it, with the next ID number offered
    Given I am on the "ENG" "local_dimensions > Competency hub" page logged in as "admin"
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    And I click on "Outline" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"
    And I set the focus on the "Display options" "button"
    And I press the tab key
    And I press the right key
    And I press the right key
    And I press the right key
    And I press the down key
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Leaf A2']]" "xpath_element"
    When I press the ctrl enter key
    Then the focused element is "Name" "field"
    And the field "ID number" matches value "LA3"
    And I type "Leaf A3"
    And I press the enter key
    # Saved: the editor opens again for the next sibling, under the new one.
    And I should see "Leaf A3" in the ".local-dimensions-hubv2-tree" "css_element"
    And the field "ID number" matches value "LA4"
    And I press the escape key
    And ".local-dimensions-hubv2-oeditor" "css_element" should not exist
    And "//li[@role='treeitem'][.//span[.='Leaf A2']]/following-sibling::li[1][.//span[.='Leaf A3']]" "xpath_element" should exist
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Leaf A3']]" "xpath_element"

  Scenario: A reader sees no menu button, and the command keys change nothing
    Given I am on the "ENG" "local_dimensions > Competency hub" page logged in as "reader"
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    And I click on "Outline" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"
    And I set the focus on the "Display options" "button"
    And I press the tab key
    And I press the right key
    And I press the enter key
    And I should see "Domain A" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
    Then "Actions of Domain A" "button" should not exist
    And "More actions" "button" should not exist in the "aside.local-dimensions-hubv2-panel" "css_element"
    And "Edit" "button" should not exist in the "aside.local-dimensions-hubv2-panel" "css_element"
    When I press the alt down key
    Then "//li[@role='treeitem'][.//span[.='Domain A']]/following-sibling::li[1][.//span[.='Domain B']]" "xpath_element" should exist
    And I press the ctrl enter key
    And ".local-dimensions-hubv2-oeditor" "css_element" should not exist
    # Delete runs nothing for a reader: no check, no confirmation (core's or the workspace's), the row stays.
    And I press the delete key
    And ".local-dimensions-hubv2-dialog" "css_element" should not exist
    And ".modal.show" "css_element" should not exist
    And "//li[@role='treeitem'][.//span[.='Domain A']]" "xpath_element" should exist
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain A']]" "xpath_element"
    # The palette opens for a reader too: it lists the view commands a reader may use and no action that writes.
    And I press the ctrl k key
    And I should see "Structure and view" in the ".local-dimensions-hubv2-palette" "css_element"
    And I should see "Show the map" in the ".local-dimensions-hubv2-palette" "css_element"
    And I should not see "On Domain A" in the ".local-dimensions-hubv2-palette" "css_element"
    And I should not see "Move to" in the ".local-dimensions-hubv2-palette" "css_element"
    And I should not see "Delete" in the ".local-dimensions-hubv2-palette" "css_element"
