@local @local_dimensions @javascript
Feature: Filter learning plans by competency in the Competency hub
  In order to find which plans use a competency
  As an administrator
  I need to search a competency and see only the plans that contain it

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
      | shortname    |
      | Plan with    |
      | Plan without |
    And the following "core_competency > template_competencies" exist:
      | template  | competency |
      | Plan with | AC1        |
    And I log in as "admin"

  Scenario: Searching a competency filters the plan list
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I should see "Plan without"
    And I click on "Add to filter" "button"
    And I set the field "Filter plans by competency" to "Alpha competency"
    Then I should see "Plan with"
    And I should not see "Plan without"
    And I click on "Clear competency filter" "button"
    And I should see "Plan without"
