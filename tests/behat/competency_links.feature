@local @local_dimensions @javascript
Feature: Link a competency to courses and activities in the Competency hub
  In order to connect competencies to where they are assessed
  As an administrator
  I need to open the Courses & activities modal and see a competency's linked courses

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname        | idnumber | competencyframework |
      | Alpha competency | AC1      | BF1                 |
    And the following "courses" exist:
      | fullname      | shortname |
      | Linked course | LINK1     |
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | LINK1  | AC1        |
    And I log in as "admin"

  Scenario: Open the Courses & activities modal and see a linked course
    When I visit "/local/dimensions/central.php"
    And I click on "Alpha competency" "button"
    And I click on "Courses & activities" "button"
    Then I should see "Linked course"
