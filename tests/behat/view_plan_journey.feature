@local @local_dimensions @javascript
Feature: Read a plan's journey on the plan overview
  In order to see what happened in my plan over time
  As a learner
  My plan overview offers a Journey view beside the list and the cards

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
    And the following "core_competency > plans" exist:
      | name       | user     | status | competencies |
      | Behat plan | student1 | active | AC1, BC1     |
    And the rule of competency "AC1" was met by "student1"

  Scenario: The owner switches to the Journey, reads a rated record and returns to the list
    When I am on the "Behat plan" "local_dimensions > View plan" page logged in as "student1"
    And I click on "Journey" "button" in the "[data-region='local-dimensions-line-view']" "css_element"
    Then I should see "Alpha competency" in the "[data-region='local-dimensions-journey']" "css_element"
    And I should see "The rule of the competency was met." in the "[data-region='local-dimensions-journey']" "css_element"
    And I should not see "Bravo competency" in the "[data-region='local-dimensions-journey']" "css_element"
    And "#local-dimensions-viewplan-accordion" "css_element" should not be visible
    When I click on "List" "button" in the "[data-region='local-dimensions-line-view']" "css_element"
    Then "#local-dimensions-viewplan-accordion" "css_element" should be visible
    And "[data-region='local-dimensions-journey']" "css_element" should not be visible
