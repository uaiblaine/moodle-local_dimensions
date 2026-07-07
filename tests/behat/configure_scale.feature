@local @local_dimensions @javascript
Feature: Configure a competency scale from the Competency hub edit modal
  In order to set up competency scales without leaving the Competency hub
  As an administrator
  I need the "Configure scale" button in the competency edit modal to open the scale dialogue

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And a competency scale "Behat scale" with values "Bad,Good" exists
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname        | idnumber | competencyframework |
      | Behat competency | BC1      | BF1                 |
    And I log in as "admin"

  Scenario: The Configure scale button opens the scale configuration dialogue inside the modal
    When I visit "/local/dimensions/central.php"
    And I click on "Competencies" "link"
    Then I should see "Behat competency"
    When I click on "Behat competency" "button"
    And I click on "[data-action='edit']" "css_element"
    Then I should see "Edit competency"
    When I set the field "Scale" to "Behat scale"
    And I click on "Configure scale" "button"
    Then I should see "Scale value"
    And I should see "Good"
