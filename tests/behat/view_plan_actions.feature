@local @local_dimensions @javascript
Feature: Act on the plan and open a competency from the plan overview
  In order to send my draft plan for review and land on the competency I came for
  As a learner
  My plan overview offers core's review actions and opens the competency its address names

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "permission overrides" exist:
      | capability                             | permission | role | contextlevel | reference |
      | moodle/competency:planviewowndraft     | Allow      | user | System       |           |
      | moodle/competency:planrequestreviewown | Allow      | user | System       |           |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname        | idnumber | competencyframework |
      | Alpha competency | AC1      | BF1                 |
      | Bravo competency | BC1      | BF1                 |

  Scenario: The owner of a draft plan requests a review and cancels the request
    Given the following "core_competency > plans" exist:
      | name       | user     | status | competencies |
      | Draft plan | student1 | draft  | AC1          |
    When I am on the "Draft plan" "local_dimensions > View plan" page logged in as "student1"
    Then I should see "Draft" in the "[data-region='local-dimensions-plan-actions']" "css_element"
    And I should see "Plan page in Moodle" in the "[data-region='local-dimensions-plan-actions']" "css_element"
    When I press "Request review"
    Then I should see "Waiting for review" in the "[data-region='local-dimensions-plan-actions']" "css_element"
    And "Request review" "button" should not exist
    When I press "Cancel review request"
    Then I should see "Draft" in the "[data-region='local-dimensions-plan-actions']" "css_element"
    And "Request review" "button" should exist

  Scenario: The address opens the competency it names
    Given the following "core_competency > plans" exist:
      | name       | user     | status | competencies |
      | Behat plan | student1 | active | AC1, BC1     |
    When I am on the "Behat plan > BC1" "local_dimensions > View plan competency" page logged in as "student1"
    Then the "aria-expanded" attribute of "Bravo competency" "button" should contain "true"
    And the "aria-expanded" attribute of "Alpha competency" "button" should contain "false"
    And I should see "Progress" in the "#local-dimensions-viewplan-accordion" "css_element"
    And the view of competency "BC1" in plan "Behat plan" should be logged "1" time
    And the view of competency "AC1" in plan "Behat plan" should be logged "0" times
    And the view of plan "Behat plan" should be logged "1" time
