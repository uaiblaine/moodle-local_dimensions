@local @local_dimensions @javascript
Feature: Manage learning plans from the Competency hub
  In order to maintain learning plan templates without leaving the hub
  As an administrator
  I need to create, edit and delete templates in a modal

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And I log in as "admin"

  Scenario: Create a new learning plan template
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "New template" "button"
    And I set the field "shortname" to "Induction plan"
    And I click on "Save changes" "button"
    Then I should see "Induction plan"

  Scenario: Edit a learning plan template
    Given the following "core_competency > templates" exist:
      | shortname |
      | Old name  |
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "Old name" "button"
    And I click on "Edit" "button"
    And I set the field "shortname" to "New name"
    And I click on "Save changes" "button"
    Then I should see "New name"
    And I should not see "Old name"

  Scenario: Delete a template that has no plans
    Given the following "core_competency > templates" exist:
      | shortname  |
      | Disposable |
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "Disposable" "button"
    And I click on "Delete template" "button"
    And I click on "Delete" "button" in the "Delete" "dialogue"
    Then I should not see "Disposable"
