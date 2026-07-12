@local @local_dimensions @javascript
Feature: Bulk-configure enrolment methods from the Competency hub
  In order to enrol a plan's cohort into the linked courses
  As an administrator
  I need to review and queue enrolment methods from the participants modal

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname        | idnumber | competencyframework |
      | Alpha competency | AC1      | BF1                 |
    And the following "core_competency > templates" exist:
      | shortname   |
      | Skills plan |
    And the following "core_competency > template_competencies" exist:
      | template    | competency |
      | Skills plan | AC1        |
    And the following "courses" exist:
      | fullname      | shortname |
      | Linked course | LINK1     |
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | LINK1  | AC1        |
    And I log in as "admin"

  Scenario: The tab asks for a cohort when the plan has none
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "Skills plan" "button"
    And I press "Manage participants"
    And I click on "Enrolment methods" "button" in the "Manage participants" "dialogue"
    Then I should see "No cohort is linked to this plan" in the "Manage participants" "dialogue"
    And I should not see "are both disabled on this site" in the "Manage participants" "dialogue"

  Scenario: See the linked course and its status for the plan cohort
    Given the following "cohorts" exist:
      | name      | idnumber |
      | Marketing | C1       |
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "Skills plan" "button"
    And I press "Manage participants"
    And I set the field "Add cohort" to "Marketing"
    And I should see "Marketing" in the "Manage participants" "dialogue"
    And I click on "Enrolment methods" "button" in the "Manage participants" "dialogue"
    Then I should see "1 course" in the "Manage participants" "dialogue"
    And I should not see "No cohort is linked to this plan" in the "Manage participants" "dialogue"
    And I should not see "are both disabled on this site" in the "Manage participants" "dialogue"
    When I click on "Alpha competency" "button" in the "Manage participants" "dialogue"
    Then I should see "LINK1" in the "Manage participants" "dialogue"
    And I should see "Not configured" in the "Manage participants" "dialogue"
