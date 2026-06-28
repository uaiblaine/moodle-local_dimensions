@local @local_dimensions @javascript
Feature: Manage competency frameworks from the Competency hub
  In order to maintain competency frameworks without leaving the hub
  As an administrator
  I need a Frameworks tab that lists frameworks with management actions

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "core_competency > frameworks" exist:
      | shortname        | idnumber |
      | Behat framework  | BF1      |
    And I log in as "admin"

  Scenario: The Frameworks tab lists frameworks with management actions
    When I visit "/local/dimensions/central.php"
    And I click on "Frameworks" "link"
    Then I should see "Behat framework"
    And I should see "New framework"
    And I should see "Edit" in the ".local-dimensions-central-framework" "css_element"
