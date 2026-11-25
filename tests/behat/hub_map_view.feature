@local @local_dimensions @javascript
Feature: Switch the Competency hub's Competencies tab between the classic list and the map
  In order to see a framework's competencies as a tree I can explore
  As a manager of competencies
  I need a Map view beside the Classic view of the Competencies tab, and the hub to remember it

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "categories" exist:
      | name        | idnumber |
      | Engineering | ENG      |
    And the following "users" exist:
      | username   | firstname | lastname |
      | catmanager | Cat       | Manager  |
    And the following "role assigns" exist:
      | user       | role    | contextlevel | reference |
      | catmanager | manager | Category     | ENG       |
    And the following "local_dimensions > frameworks" exist:
      | shortname             | idnumber | category |
      | Engineering framework | ENGFW    | ENG      |
    And the following "core_competency > frameworks" exist:
      | shortname      | idnumber |
      | Site framework | SITEFW   |
    And the following "core_competency > competencies" exist:
      | shortname       | idnumber | competencyframework |
      | Circuit design  | CIRC     | ENGFW               |
      | Site competency | SITEC    | SITEFW              |
    # The classic hub (the Structures tab and the classic list), kept one release behind the context bar's link.
    And the following "user preferences" exist:
      | user       | preference                       | value            |
      | catmanager | local_dimensions_central_display | {"classic":true} |

  Scenario: A category manager switches the Competencies tab to the map, and the map survives a reload
    Given I am on the "ENG" "category" page logged in as "catmanager"
    And I navigate to "Competency hub" in current page administration
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    And I should see "Circuit design" in the "[data-region='competency-tree']" "css_element"
    And the "aria-pressed" attribute of ".local-dimensions-central-viewswitch [data-view='list']" "css_element" should contain "true"
    When I click on "Map" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    Then I should see "Circuit design" in the "[data-region='hubv2']" "css_element"
    And "[data-region='competency-tree']" "css_element" should not exist
    And the "aria-pressed" attribute of ".local-dimensions-central-viewswitch [data-view='map']" "css_element" should contain "true"
    When I reload the page
    Then I should see "Circuit design" in the "[data-region='hubv2']" "css_element"
    And the "aria-pressed" attribute of ".local-dimensions-central-viewswitch [data-view='map']" "css_element" should contain "true"
    When I click on "Classic" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    Then I should see "Circuit design" in the "[data-region='competency-tree']" "css_element"
    And "[data-region='hubv2']" "css_element" should not exist

  Scenario: In the classic hub the chosen view is saved, so a visit whose address names none opens it
    Given I am on the "ENG" "local_dimensions > Competency hub" page logged in as "catmanager"
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    And the "aria-pressed" attribute of ".local-dimensions-central-viewswitch [data-view='list']" "css_element" should contain "true"
    When I click on "Map" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    Then I should see "Circuit design" in the "[data-region='hubv2']" "css_element"
    # A save the server refuses shows as a lost connection.
    And I should not see "Connection lost"
    # This address names no view: only the saved preference can say "map".
    When I am on the "ENG" "local_dimensions > Competency hub" page
    And I click on "Competencies" "link" in the "#dynamictabs-tabs" "css_element"
    Then I should see "Circuit design" in the "[data-region='hubv2']" "css_element"
    And the "aria-pressed" attribute of ".local-dimensions-central-viewswitch [data-view='map']" "css_element" should contain "true"
    # The save did not write over the stored mode: the classic hub's Structures tab is still there.
    And "Structures" "link" should exist in the "#dynamictabs-tabs" "css_element"

  Scenario: A kept link to the former map page opens the hub's map view
    Given I log in as "admin"
    When I visit "/local/dimensions/hubv2.php?contexttype=system"
    Then I should see "Site competency" in the "[data-region='hubv2']" "css_element"
    And the "aria-pressed" attribute of ".local-dimensions-central-viewswitch [data-view='map']" "css_element" should contain "true"
