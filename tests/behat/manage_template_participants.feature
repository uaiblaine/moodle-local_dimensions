@local @local_dimensions @javascript
Feature: Manage a learning plan template's participants in the Competency hub
  In order to assign a learning plan to specific people
  As an administrator
  I need an individual-user participants grid on a template

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "users" exist:
      | username | firstname | lastname |
      | learner1 | Dana      | Scully   |
    And the following "core_competency > templates" exist:
      | shortname   |
      | Skills plan |
    And I log in as "admin"

  Scenario: Assign a template to an individual user
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "Skills plan" "button"
    And I press "Manage participants"
    And I click on "Users" "button"
    And I set the field "Assign to user" to "Dana Scully"
    Then I should see "Dana Scully"
