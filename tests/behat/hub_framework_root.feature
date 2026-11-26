@local @local_dimensions @javascript
Feature: Manage a competency framework from the root of the Competency hub's workspace
  In order to manage a framework where its competencies are
  As a manager of competencies
  I need the framework's own actions on the workspace's root, and the classic hub behind a link

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "categories" exist:
      | name        | idnumber |
      | Engineering | ENG      |
    And the following "users" exist:
      | username   | firstname | lastname |
      | catmanager | Cat       | Manager  |
    And the following "role assigns" exist:
      | user       | role    | contextlevel | reference |
      | catmanager | manager | Category     | ENG       |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "local_dimensions > frameworks" exist:
      | shortname             | idnumber | category |
      | Engineering framework | ENGFW    | ENG      |
    And the following "core_competency > competencies" exist:
      | shortname | idnumber | competencyframework |
      | Domain A  | DA       | BF1                 |
      | Circuits  | CIRC     | ENGFW               |

  Scenario: A manager renames the structure through "Edit structure" on the root, and the outline and the select follow
    Given I log in as "admin"
    When I visit "/local/dimensions/central.php"
    Then I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"
    And I click on "//li[@role='treeitem'][@aria-level='1']" "xpath_element"
    And I should see "Behat framework" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
    And I should see "BF1" in the "aside.local-dimensions-hubv2-panel" "css_element"
    # The controls of "A reader sees the structure's facts on the root and none of its actions".
    And "More actions" "button" should exist in the "aside.local-dimensions-hubv2-panel" "css_element"
    And "Add competency" "button" should exist in the "aside.local-dimensions-hubv2-panel" "css_element"
    When I click on "Edit structure" "button" in the "aside.local-dimensions-hubv2-panel" "css_element"
    And I set the field "Name" in the "Edit structure" "dialogue" to "Renamed framework"
    And I click on "Save changes" "button" in the "Edit structure" "dialogue"
    Then I should see "Renamed framework" in the "//li[@role='treeitem'][@aria-level='1']" "xpath_element"
    And I should see "Renamed framework" in the "[data-region='framework-select']" "css_element"
    And I should see "Renamed framework" in the "aside.local-dimensions-hubv2-panel h3" "css_element"

  Scenario: A manager hides the structure from the root's menu and the root says it is hidden
    Given I log in as "admin"
    When I visit "/local/dimensions/central.php"
    And I should see "Visible" in the "[data-region='framework-visibility']" "css_element"
    And I click on "More actions" "button" in the "aside.local-dimensions-hubv2-panel" "css_element"
    And I click on "//*[@role='menu']//button[@role='menuitem'][starts-with(normalize-space(.), 'Hide structure')]" "xpath_element"
    Then I should see "Hidden" in the "[data-region='framework-visibility']" "css_element"
    And I should see "Behat framework" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
    # The root's menu now offers to show it again, and no longer to hide it.
    And I click on "More actions" "button" in the "aside.local-dimensions-hubv2-panel" "css_element"
    And "//*[@role='menu']//button[@role='menuitem'][starts-with(normalize-space(.), 'Show structure')]" "xpath_element" should exist
    And "//*[@role='menu']//button[@role='menuitem'][starts-with(normalize-space(.), 'Hide structure')]" "xpath_element" should not exist

  Scenario: "New structure" in the Structures menu creates a structure and the tab shows it
    Given I log in as "admin"
    When I visit "/local/dimensions/central.php"
    And I click on "Structures" "button" in the "[data-region='structures-menu']" "css_element"
    And I click on "New structure" "button" in the "[data-region='structures-menu']" "css_element"
    And I set the field "Name" in the "New structure" "dialogue" to "Fresh framework"
    And I set the field "ID number" in the "New structure" "dialogue" to "FRESH"
    And I set the field "Scale" in the "New structure" "dialogue" to "Default competence scale"
    And I click on "Configure scale" "button" in the "New structure" "dialogue"
    And I click on "[data-value='2'] [data-role='default']" "css_element" in the "Configure scale" "dialogue"
    And I click on "[data-value='2'] [data-role='proficient']" "css_element" in the "Configure scale" "dialogue"
    And I click on "Save changes" "button" in the "Configure scale" "dialogue"
    And I click on "Save changes" "button" in the "New structure" "dialogue"
    Then I should see "Fresh framework" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
    And I should see "Fresh framework" in the "[data-region='framework-select']" "css_element"

  Scenario: The tab strip has no Structures tab by default, and has it after "Classic view", which is remembered
    Given I log in as "admin"
    When I visit "/local/dimensions/central.php"
    Then "Structures" "link" should not exist in the "#dynamictabs-tabs" "css_element"
    And ".local-dimensions-central-viewswitch [data-view='list']" "css_element" should not exist
    And the "aria-pressed" attribute of ".local-dimensions-central-viewswitch [data-view='both']" "css_element" should contain "true"
    When I click on "Classic view" "link" in the "[data-region='contextbar']" "css_element"
    Then "Structures" "link" should exist in the "#dynamictabs-tabs" "css_element"
    And ".local-dimensions-central-viewswitch [data-view='list']" "css_element" should exist
    And I should see "Workspace view" in the "[data-region='contextbar']" "css_element"
    When I visit "/local/dimensions/central.php"
    Then "Structures" "link" should exist in the "#dynamictabs-tabs" "css_element"
    When I click on "Workspace view" "link" in the "[data-region='contextbar']" "css_element"
    Then "Structures" "link" should not exist in the "#dynamictabs-tabs" "css_element"
    And I should see "Classic view" in the "[data-region='contextbar']" "css_element"

  Scenario: The map heads each level with its number and term, and offers the level names once
    Given the following "core_competency > competencies" exist:
      | shortname | idnumber | competencyframework | parent |
      | Leaf A1   | LA1      | BF1                 | DA     |
    And I log in as "admin"
    When I visit "/local/dimensions/central.php"
    And I click on "Map" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I click on "Expand Domain A" "button"
    # Both levels use core's term: the number tells the two headings apart.
    Then I should see "Level 1: Competency" in the ".local-dimensions-hubv2-colheads" "css_element"
    And I should see "Level 2: Competency" in the ".local-dimensions-hubv2-colheads" "css_element"
    # One control however many levels are drawn, named for what it does and not after a level's term.
    And "(//div[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-colheads ')]//button)[2]" "xpath_element" should not exist
    When I click on "Edit the level names" "button" in the ".local-dimensions-hubv2-colheads" "css_element"
    Then "Edit structure" "dialogue" should exist

  Scenario: A reader sees the structure's facts on the root and none of its actions
    Given the following "users" exist:
      | username | firstname | lastname |
      | reader   | Rea       | Der      |
    And I am on the "ENG" "local_dimensions > Competency hub" page logged in as "reader"
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    And I click on "Outline" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    And I should see "Circuits" in the ".local-dimensions-hubv2-tree" "css_element"
    When I click on "//li[@role='treeitem'][@aria-level='1']" "xpath_element"
    Then I should see "Engineering framework" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
    And I should see "ENGFW" in the "aside.local-dimensions-hubv2-panel" "css_element"
    And "Edit structure" "button" should not exist
    And "More actions" "button" should not exist
    And "Add competency" "button" should not exist
    # Nothing to create, import or reveal: the Structures menu is not drawn.
    And "[data-region='structures-menu']" "css_element" should not exist

  Scenario: A category manager on the locked entry gets the workspace by default, on the category's framework
    Given I am on the "ENG" "local_dimensions > Competency hub" page logged in as "catmanager"
    Then "Structures" "link" should not exist in the "#dynamictabs-tabs" "css_element"
    And I should see "Engineering framework" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
    And I should see "Circuits" in the ".local-dimensions-hubv2-tree" "css_element"
    And I should not see "Behat framework"
    And the "aria-pressed" attribute of ".local-dimensions-central-viewswitch [data-view='both']" "css_element" should contain "true"
