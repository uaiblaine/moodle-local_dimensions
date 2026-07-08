@local @local_dimensions @javascript
Feature: Manage competency structures from the Competency hub
  In order to maintain competency structures without leaving the hub
  As an administrator
  I need a Structures tab that lists structures with management actions

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "core_competency > frameworks" exist:
      | shortname        | idnumber |
      | Behat framework  | BF1      |
    And I log in as "admin"

  Scenario: The Structures tab lists structures with management actions
    When I visit "/local/dimensions/central.php"
    And I click on "Structures" "link"
    Then I should see "Behat framework"
    And I should see "New structure"
    When I click on "Behat framework" "button"
    Then I should see "Edit" in the "#sticky-footer" "css_element"

  Scenario: Import and export open their modals from the Structures tab
    When I visit "/local/dimensions/central.php"
    And I click on "Structures" "link"
    Then I should see "Import"
    And I should see "Export"
    When I click on "Import" "button"
    Then I should see "CSV file" in the "Import structure from CSV" "dialogue"
    And I should see "Update the existing structure with the same ID number" in the "Import structure from CSV" "dialogue"
    And I click on "Cancel" "button" in the "Import structure from CSV" "dialogue"
    When I click on "Export" "button"
    Then I should see "Structure to export" in the "Export structure to CSV" "dialogue"
