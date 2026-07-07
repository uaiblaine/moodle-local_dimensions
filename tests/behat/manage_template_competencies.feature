@local @local_dimensions @javascript
Feature: Manage a learning plan template's competencies in the Competency hub
  In order to curate what a learning plan bundles
  As an administrator
  I need to add, remove and reorder a template's competencies across frameworks

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "core_competency > frameworks" exist:
      | shortname        | idnumber |
      | Behat framework  | BF1      |
      | Other framework  | OF2      |
    And the following "core_competency > competencies" exist:
      | shortname         | idnumber | competencyframework |
      | Alpha competency  | AC1      | BF1                 |
      | Beta competency   | BC2      | BF1                 |
      | Gamma competency  | GC3      | OF2                 |
    And the following "core_competency > templates" exist:
      | shortname    |
      | Skills plan  |
    And the following "core_competency > template_competencies" exist:
      | template    | competency |
      | Skills plan | AC1        |
    And I log in as "admin"

  Scenario: Add competencies across frameworks, reorder and remove them
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "Skills plan" "button"
    Then I should see "Alpha competency"
    And I click on "Add competency" "button"
    And I set the field "Add competency" to "Beta competency"
    Then I should see "Beta competency"
    And I click on "Add competency" "button"
    And I set the field "Add competency" to "Gamma competency"
    Then I should see "Gamma competency"
    And I should see "OF2"
    And I click on "Actions" "button" in the "Beta competency" "list_item"
    And I click on "Remove competency" "button" in the "Beta competency" "list_item"
    And I click on "Remove" "button" in the "Remove competency" "dialogue"
    Then I should not see "Beta competency"

  Scenario: Browse a framework's competencies in the modal
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "Skills plan" "button"
    And I click on "Add competency" "button"
    And I press "Browse structures"
    Then I should see "Alpha competency"
    And I should see "Beta competency"
