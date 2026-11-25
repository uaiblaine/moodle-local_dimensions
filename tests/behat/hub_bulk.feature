@local @local_dimensions @javascript
Feature: Act on several competencies at once from the Competency hub's bulk dock
  In order to restyle and tidy a competency framework without opening each competency
  As a manager of the framework's competencies
  I need to pick competencies in the outline and act on them from a dock, and a reader needs none of it

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "categories" exist:
      | name        | idnumber |
      | Engineering | ENG      |
    And the following "users" exist:
      | username | firstname | lastname |
      | reader   | Rea       | Der      |
    And the following "courses" exist:
      | fullname      | shortname |
      | Linked course | LINK1     |
    And the following "local_dimensions > frameworks" exist:
      | shortname       | idnumber | category |
      | Behat framework | BF1      | ENG      |
    And the following "core_competency > competencies" exist:
      | shortname | idnumber | competencyframework |
      | Domain A  | DA       | BF1                 |
      | Domain B  | DB       | BF1                 |
      | Domain C  | DC       | BF1                 |
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | LINK1  | DB         |

  Scenario: A manager picks two competencies with the keyboard and sets their tag 1 from the dock
    Given I am on the "ENG" "local_dimensions > Competency hub" page logged in as "admin"
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    And I click on "Outline" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"
    And "[data-region='bulk-dock']" "css_element" should not exist
    And I set the focus on the "Display options" "button"
    And I press the tab key
    And I press the down key
    # Space picks the focused row; Shift+Down extends the picks from it.
    When I press the space key
    And I press the shift down key
    Then I should see "2 selected" in the "[data-region='bulk-dock']" "css_element"
    And the "aria-selected" attribute of "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain A']]" "xpath_element" should contain "true"
    And the "aria-selected" attribute of "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain B']]" "xpath_element" should contain "true"
    And the "aria-selected" attribute of "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain C']]" "xpath_element" should contain "false"
    And the focused element is "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain B']]" "xpath_element"
    When I click on "Set appearance…" "button" in the "[data-region='bulk-dock']" "css_element"
    # The bulk dock's dialogue, by its class: the page may carry Moodle's own role="dialog" elements.
    And I should see "Set the appearance of 2 competencies" in the ".local-dimensions-hubv2-bulkdialog[role='dialog']" "css_element"
    And I set the field "Tag 1" to "Edit me 2"
    And I press "Apply to 2 competencies"
    Then "[role='dialog'].local-dimensions-hubv2-bulkdialog" "css_element" should not exist
    And "[data-region='bulk-dock']" "css_element" should not exist
    When I click on "//li[@role='treeitem']//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain A']" "xpath_element"
    Then I should see "Edit me 2" in the "aside.local-dimensions-hubv2-panel" "css_element"
    When I click on "//li[@role='treeitem']//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain B']" "xpath_element"
    Then I should see "Domain B" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
    And I should see "Edit me 2" in the "aside.local-dimensions-hubv2-panel" "css_element"
    When I click on "//li[@role='treeitem']//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain C']" "xpath_element"
    Then I should not see "Edit me 2" in the "aside.local-dimensions-hubv2-panel" "css_element"

  Scenario: Cancelling "Move under…" hands the focus back to the dock's button
    Given I am on the "ENG" "local_dimensions > Competency hub" page logged in as "admin"
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    And I click on "Outline" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"
    When I click on "Select Domain A" "checkbox"
    And I click on "Select Domain C" "checkbox"
    And I click on "Move under…" "button" in the "[data-region='bulk-dock']" "css_element"
    Then I should see "Move 2 competencies" in the ".local-dimensions-hubv2-movedialog[role='dialog']" "css_element"
    When I click on "Cancel" "button" in the ".local-dimensions-hubv2-movedialog[role='dialog']" "css_element"
    Then "[role='dialog'].local-dimensions-hubv2-movedialog" "css_element" should not exist
    And I should see "2 selected" in the "[data-region='bulk-dock']" "css_element"
    And the focused element is "Move under…" "button" in the "[data-region='bulk-dock']" "css_element"

  Scenario: Deleting two competencies deletes the one core allows and names the one it refuses
    Given I am on the "ENG" "local_dimensions > Competency hub" page logged in as "admin"
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    And I click on "Outline" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I should see "Domain B" in the ".local-dimensions-hubv2-tree" "css_element"
    When I click on "Select Domain B" "checkbox"
    And I click on "Select Domain C" "checkbox"
    Then I should see "2 selected" in the "[data-region='bulk-dock']" "css_element"
    When I click on "Delete…" "button" in the "[data-region='bulk-dock']" "css_element"
    Then I should see "Delete 2 competencies" in the ".local-dimensions-hubv2-bulkdialog[role='dialog']" "css_element"
    And I should see "1 competency can be deleted." in the ".local-dimensions-hubv2-bulkdialog[role='dialog']" "css_element"
    And I should see "Core will refuse 1 of them:" in the ".local-dimensions-hubv2-bulkdialog[role='dialog']" "css_element"
    And I should see "Domain B: Linked to 1 course." in the ".local-dimensions-hubv2-bulkdialog[role='dialog']" "css_element"
    When I press "Delete 1"
    Then "[role='dialog'].local-dimensions-hubv2-bulkdialog" "css_element" should not exist
    And I should not see "Domain C" in the ".local-dimensions-hubv2-tree" "css_element"
    And I should see "Domain B" in the ".local-dimensions-hubv2-tree" "css_element"
    And I should see "Deleted 1 competency. Not deleted: Domain B." in the ".local-dimensions-hubv2-layout > [aria-live='polite']" "css_element"
    # What core refused stays picked.
    And I should see "1 selected" in the "[data-region='bulk-dock']" "css_element"

  Scenario: A reader gets no box, no dock and no picking key
    Given I am on the "ENG" "local_dimensions > Competency hub" page logged in as "reader"
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    And I click on "Outline" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"
    Then "Select Domain A" "checkbox" should not exist
    And "//ul[@role='tree'][@aria-multiselectable]" "xpath_element" should not exist
    And I set the focus on the "Display options" "button"
    And I press the tab key
    And I press the down key
    And I press the space key
    And "[data-region='bulk-dock']" "css_element" should not exist
    # Space still chooses the row for the panel, as in a single-select tree.
    And I should see "Domain A" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
