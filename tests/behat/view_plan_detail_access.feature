@local @local_dimensions
Feature: Withhold competency detail from a viewer who cannot read it
  In order not to offer a control that can only fail
  As a reviewer allowed to read draft plans but not user competencies
  I see the plan's competencies without a way to open their detail

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "users" exist:
      | username  | firstname | lastname |
      | student1  | Sam       | Student  |
      | reviewer1 | Rae       | Reviewer |
    And the following "roles" exist:
      | shortname     | name           |
      | draftreviewer | Draft reviewer |
    And the following "permission overrides" exist:
      | capability                      | permission | role          | contextlevel | reference |
      | moodle/competency:planviewdraft | Allow      | draftreviewer | System       |           |
    And the following "system role assigns" exist:
      | user      | role          |
      | reviewer1 | draftreviewer |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname        | idnumber | competencyframework |
      | Alpha competency | AC1      | BF1                 |
    And the following "core_competency > plans" exist:
      | name        | user     | status | competencies |
      | Draft plan  | student1 | draft  | AC1          |

  Scenario: A draft-only reviewer sees the list and a notice, with nothing to expand
    When I am on the "Draft plan" "local_dimensions > View plan" page logged in as "reviewer1"
    Then I should see "Alpha competency"
    And I should see "Competency details are not available to you."
    And "Alpha competency" "button" should not exist

  @javascript
  Scenario: A draft-only reviewer's grid card opens no detail dialogue
    When I am on the "Draft plan" "local_dimensions > View plan" page logged in as "reviewer1"
    And I click on "Grid view" "button"
    And I click on "Alpha competency" "text"
    Then "Alpha competency" "dialogue" should not exist
    And I should see "Competency details are not available to you."

  Scenario: A viewer who can read user competencies keeps the expandable detail
    When I am on the "Draft plan" "local_dimensions > View plan" page logged in as "admin"
    Then "Alpha competency" "button" should exist
    And I should not see "Competency details are not available to you."
