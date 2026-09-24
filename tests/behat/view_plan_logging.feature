@local @local_dimensions @javascript
Feature: Log competency views from the learner pages
  In order to find in the logs what a learner looked at
  As a learner
  My plan overview and competency tracker log the same view events as core's pages

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname        | idnumber | competencyframework |
      | Alpha competency | AC1      | BF1                 |
      | Bravo competency | BC1      | BF1                 |

  Scenario: Each competency's view is logged once per page, in the list and in the grid
    Given the following "core_competency > plans" exist:
      | name       | user     | status | competencies |
      | Behat plan | student1 | active | AC1, BC1     |
    When I am on the "Behat plan" "local_dimensions > View plan" page logged in as "student1"
    Then the view of plan "Behat plan" should be logged "1" time
    When I click on "Alpha competency" "button"
    Then the view of competency "AC1" in plan "Behat plan" should be logged "1" time
    # Collapse, expand again, then reopen the same competency from the grid: still one view logged.
    When I click on "Alpha competency" "button"
    And I click on "Alpha competency" "button"
    And I click on "Grid view" "button"
    And I click on "Alpha competency" "button"
    # The Progress tab exists only once the detail has rendered, which is when the view is logged.
    Then I should see "Progress" in the "Alpha competency" "dialogue"
    When I click on "Next" "button" in the ".local-dimensions-modal-pager" "css_element"
    Then I should see "Progress" in the "Bravo competency" "dialogue"
    And the view of competency "BC1" in plan "Behat plan" should be logged "1" time
    And the view of competency "AC1" in plan "Behat plan" should be logged "1" time

  Scenario: A competency of a completed plan logs the completed-plan view
    Given the following "core_competency > plans" exist:
      | name          | user     | status   | competencies |
      | Finished plan | student1 | complete | AC1          |
    And the following "core_competency > user_competency_plans" exist:
      | plan          | competency | user     |
      | Finished plan | AC1        | student1 |
    When I am on the "Finished plan" "local_dimensions > View plan" page logged in as "student1"
    And I click on "Alpha competency" "button"
    Then the completed-plan view of competency "AC1" in plan "Finished plan" should be logged "1" time
    And the view of competency "AC1" in plan "Finished plan" should be logged "0" times

  Scenario: The competency tracker logs the competency view
    Given the following "core_competency > plans" exist:
      | name       | user     | status | competencies |
      | Behat plan | student1 | active | AC1, BC1     |
    When I am on the "Behat plan > AC1" "local_dimensions > View competency" page logged in as "student1"
    Then the view of competency "AC1" in plan "Behat plan" should be logged "1" time
    And the view of competency "BC1" in plan "Behat plan" should be logged "0" times
