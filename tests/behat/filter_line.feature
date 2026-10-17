@local @local_dimensions @local_dimensions_filter_line
Feature: The filter line of the learner views
  In order to find my way through a plan and its courses
  As a learner
  I narrow the list from one line of controls: the scope, the search, and the filters

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "courses" exist:
      | fullname     | shortname |
      | Gamma course | C1        |
      | Delta course | C2        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student1 | C2     | student |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname        | idnumber | competencyframework |
      | Alpha competency | AC1      | BF1                 |
      | Bravo competency | BC1      | BF1                 |
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | C1     | AC1        |
      | C2     | AC1        |
    And the following "core_competency > plans" exist:
      | name       | user     | status | competencies |
      | Behat plan | student1 | active | AC1, BC1     |
    And the following "core_competency > user_competency" exist:
      | user     | competency | proficiency | grade |
      | student1 | BC1        | 1           | D     |

  @javascript
  Scenario: The plan's line opens on "To do", searches as I type and remembers the scope
    When I am on the "Behat plan" "local_dimensions > View plan" page logged in as "student1"
    Then "[data-region='local-dimensions-line-scope'] [data-filter='incomplete'][aria-checked='true']" "css_element" should exist
    And I should see "Alpha competency" in the "#local-dimensions-viewplan-accordion" "css_element"
    And I should not see "Bravo competency" in the "#local-dimensions-viewplan-accordion" "css_element"
    And "[data-toolbar-adjust]" "css_element" should not exist
    When I click on "All" "button" in the "[data-region='local-dimensions-line-scope']" "css_element"
    Then I should see "Bravo competency" in the "#local-dimensions-viewplan-accordion" "css_element"
    When I set the field "Search competencies" to "brav"
    Then I should see "Bravo competency" in the "#local-dimensions-viewplan-accordion" "css_element"
    And I should not see "Alpha competency" in the "#local-dimensions-viewplan-accordion" "css_element"
    And I should see "Bravo competency" in the "[data-region='local-dimensions-line-suggest']" "css_element"
    When I press the escape key
    Then "[data-region='local-dimensions-line-suggest']" "css_element" should not be visible
    And I wait "1" seconds
    When I reload the page
    Then "[data-region='local-dimensions-line-scope'] [data-filter='all'][aria-checked='true']" "css_element" should exist
    And the field "Search competencies" matches value ""
    And I should see "Alpha competency" in the "#local-dimensions-viewplan-accordion" "css_element"

  @javascript
  Scenario: The tracker's line opens on "To do" and its search says when nothing matches
    When I am on the "Behat plan > AC1" "local_dimensions > View competency" page logged in as "student1"
    Then "[data-region='local-dimensions-line-scope'] [data-filter='incomplete'][aria-checked='true']" "css_element" should exist
    And "[data-region='local-dimensions-line-scope']" "css_element" should be visible
    And I should see "Gamma course" in the "#local-dimensions-viewcompetency-grid" "css_element"
    And I should see "Delta course" in the "#local-dimensions-viewcompetency-grid" "css_element"
    When I set the field "Search courses" to "gam"
    Then I should see "Gamma course" in the "#local-dimensions-viewcompetency-grid" "css_element"
    And I should not see "Delta course" in the "#local-dimensions-viewcompetency-grid" "css_element"
    When I set the field "Search courses" to "zzz"
    Then I should see "No courses match your filters"
    And I should not see "Gamma course" in the "#local-dimensions-viewcompetency-grid" "css_element"
    When I click on "Clear filters" "button" in the "#local-dimensions-viewcompetency-noresults" "css_element"
    Then I should see "Gamma course" in the "#local-dimensions-viewcompetency-grid" "css_element"
    And I should not see "No courses match your filters"
