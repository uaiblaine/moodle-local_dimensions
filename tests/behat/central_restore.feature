@local @local_dimensions @javascript
Feature: The Competency hub remembers the last visited tab
  In order to resume where I left off
  As a competency manager
  I need the hub to reopen on the tab I last used

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And I log in as "admin"

  Scenario: Reloading the hub restores the last active tab
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    Then I should see "New template"
    And I wait "1" seconds
    And I reload the page
    Then I should see "New template"
