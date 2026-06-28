@local @local_dimensions @javascript
Feature: Manage competency frameworks from the Competency hub
  In order to maintain competency frameworks without leaving the hub
  As an administrator
  I need a Frameworks tab with edit and visibility actions

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "core_competency > frameworks" exist:
      | shortname        | idnumber |
      | Behat framework  | BF1      |
    And I log in as "admin"

  Scenario: Open the Frameworks tab and rename a framework
    When I visit "/local/dimensions/central.php"
    And I click on "Frameworks" "link"
    And I click on "Edit" "button"
    And I set the field "shortname" to "Renamed framework"
    And I click on "Save changes" "button"
    Then I should see "Renamed framework"
    And I should not see "Behat framework"
