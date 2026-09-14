@local @local_dimensions @javascript
Feature: Reuse fetched competency detail on the plan overview
  In order to page through my competencies without waiting for each one again
  As a learner
  My plan overview fetches each competency's detail once per page, and again only after I change it

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course one | C1        |
      | Course two | C2        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student1 | C2     | student |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname        | idnumber | competencyframework | ruletype                            | ruleoutcome |
      | Alpha competency | AC1      | BF1                 | core_competency\competency_rule_all | 1           |
    And the following "core_competency > competencies" exist:
      | shortname        | idnumber | competencyframework |
      | Bravo competency | BC1      | BF1                 |
      | Alpha child      | AC1A     | BF1                 |
    And the competency "AC1A" is a child of "AC1"
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | C1     | AC1        |
      | C2     | BC1        |
    And the following "core_competency > plans" exist:
      | name       | user     | status | competencies |
      | Behat plan | student1 | active | AC1, BC1     |
    And "student1" has an evidence note "Portfolio reviewed" on competency "AC1"
    And the rule of competency "AC1" was met by "student1"

  Scenario: A competency shown again renders from fetched data, and a review request fetches it again
    When I am on the "Behat plan" "local_dimensions > View plan" page logged in as "student1"
    And I click on "Grid view" "button"
    And I click on "Alpha competency" "button"
    Then I should see "Course one" in the "Alpha competency" "dialogue"
    When I click on "Rules" "button" in the "Alpha competency" "dialogue"
    Then I should see "Alpha child" in the "Alpha competency" "dialogue"
    # Step away and back: the second Alpha pane is new, its data is not.
    When I click on "Next" "button" in the ".local-dimensions-modal-pager" "css_element"
    Then I should see "Course two" in the "Bravo competency" "dialogue"
    When I click on "Previous" "button" in the ".local-dimensions-modal-pager" "css_element"
    Then I should see "Course one" in the "Alpha competency" "dialogue"
    And the page should have called the "local_dimensions_get_user_competency_summary_in_plan" web service "2" times
    And the page should have called the "local_dimensions_get_competency_courses" web service "2" times
    # Every part of the re-rendered detail still works.
    When I click on "Rules" "button" in the "Alpha competency" "dialogue"
    Then I should see "Alpha child" in the "Alpha competency" "dialogue"
    And the page should have called the "local_dimensions_get_competency_rule_data" web service "1" time
    When I click on "Progress" "button" in the "Alpha competency" "dialogue"
    And I click on ".local-dimensions-ev-row-clickable" "css_element" in the "Alpha competency" "dialogue"
    Then I should see "Portfolio reviewed" in the "Evidence details" "dialogue"
    When I click on "Close" "button" in the "Evidence details" "dialogue"
    # A review request changes the data, so the next Alpha pane must not offer it again.
    And I click on "Send for review" "button" in the "Alpha competency" "dialogue"
    Then I should see "Sent for review" in the "Alpha competency" "dialogue"
    When I click on "Next" "button" in the ".local-dimensions-modal-pager" "css_element"
    Then I should see "Course two" in the "Bravo competency" "dialogue"
    When I click on "Previous" "button" in the ".local-dimensions-modal-pager" "css_element"
    And I click on "Progress" "button" in the "Alpha competency" "dialogue"
    Then I should see "Sent for review" in the "Alpha competency" "dialogue"
    And "Send for review" "button" should not exist in the "Alpha competency" "dialogue"
    And the page should have called the "local_dimensions_get_user_competency_summary_in_plan" web service "3" times

  Scenario: The list pane rebuilt by a layout switch fills its rules tab from fetched data
    When I am on the "Behat plan" "local_dimensions > View plan" page logged in as "student1"
    And I click on "Alpha competency" "button"
    And I click on "Rules" "button"
    Then I should see "Alpha child"
    When I click on "Grid view" "button"
    And I click on "List view" "button"
    And I click on "Alpha competency" "button"
    And I click on "Rules" "button"
    Then I should see "Alpha child"
    And the page should have called the "local_dimensions_get_user_competency_summary_in_plan" web service "1" time
    And the page should have called the "local_dimensions_get_competency_rule_data" web service "1" time
