@local @local_dimensions @javascript
Feature: Manage a learning plan template's cohorts in the Competency hub
  In order to assign a learning plan to groups of users
  As an administrator
  I need to attach and detach cohorts on a template

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "cohorts" exist:
      | name      | idnumber |
      | Marketing | C1       |
    And the following "core_competency > templates" exist:
      | shortname   |
      | Skills plan |
    And I log in as "admin"

  Scenario: Attach and detach a cohort
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "Skills plan" "button"
    And I press "Manage cohorts"
    And I set the field "Add cohort" to "Marketing"
    Then I should see "Marketing"
    And I click on "Remove cohort" "button" in the "Marketing" "table_row"
    And I click on "Remove" "button" in the "Remove cohort" "dialogue"
    Then I should not see "Marketing"
