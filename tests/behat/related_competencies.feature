@local @local_dimensions @javascript
Feature: See a competency's related competencies in the Competency hub
  In order to connect competencies within a framework
  As an administrator
  I need to open the Related competencies modal and see the current relations

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname        | idnumber | competencyframework |
      | Alpha competency | AC1      | BF1                 |
      | Bravo competency | BC1      | BF1                 |
    And the following "core_competency > related_competencies" exist:
      | competency | relatedcompetency |
      | AC1        | BC1               |
    And I log in as "admin"

  Scenario: Open the Related competencies modal and see a related competency
    When I visit "/local/dimensions/central.php"
    And I click on "Alpha competency" "button"
    And I click on "Related competencies" "button"
    Then I should see "Bravo competency"
