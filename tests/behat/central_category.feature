@local @local_dimensions @javascript
Feature: Reach the Competency hub from a course category
  In order to manage the competencies of my own course category
  As a manager holding the competency capabilities in that category only
  I need the hub in the category's menu, locked to the category

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "categories" exist:
      | name        | idnumber |
      | Engineering | ENG      |
      | Elsewhere   | ELSE     |
    And the following "users" exist:
      | username   | firstname | lastname |
      | catmanager | Cat       | Manager  |
    And the following "role assigns" exist:
      | user       | role    | contextlevel | reference |
      | catmanager | manager | Category     | ENG       |
    And the following "local_dimensions > frameworks" exist:
      | shortname             | idnumber | category |
      | Engineering framework | ENGFW    | ENG      |
      | Elsewhere framework   | ELSEFW   | ELSE     |
    And the following "local_dimensions > templates" exist:
      | shortname            | category |
      | Engineering template | ENG      |

  Scenario: A category manager opens the hub from the category page, locked to that category
    Given I am on the "ENG" "category" page logged in as "catmanager"
    When I navigate to "Competency hub" in current page administration
    Then I should see "Engineering framework"
    And I should not see "Elsewhere framework"
    And "System" "button" should not exist
    And I should see "Engineering" in the "[data-region='locked-context']" "css_element"
    When I click on "Learning plans" "link"
    Then I should see "Engineering template"
