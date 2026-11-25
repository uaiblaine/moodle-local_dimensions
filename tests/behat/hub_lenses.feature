@local @local_dimensions @javascript
Feature: Read a framework through the Competency hub's lenses and check its structure
  In order to see how a competency framework is used, ruled and built
  As an administrator
  I need lenses that answer one question in words on the map and in the panel, and a checker that reveals each finding

  # These scenarios need local_dimensions_get_framework_insights, which the lenses' branch does not carry:
  # they run once the service's branch is merged with it.

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "courses" exist:
      | fullname      | shortname |
      | Linked course | LC1       |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname | idnumber | competencyframework | ruletype                            | ruleoutcome |
      | Domain A  | DA       | BF1                 | core_competency\competency_rule_all | 1           |
    And the following "core_competency > competencies" exist:
      | shortname   | idnumber | competencyframework | parent |
      | Leaf A1     | LA1      | BF1                 | DA     |
      | Linked leaf | LL       | BF1                 | DA     |
    And the following "core_competency > competencies" exist:
      | shortname   | idnumber | competencyframework |
      | Unused leaf | UL       | BF1                 |
    # A description of markup alone says nothing: the checker counts it as empty.
    And the following "core_competency > competencies" exist:
      | shortname | idnumber | competencyframework | description |
      | Bare leaf | BL       | BF1                 | <p></p>     |
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | LC1    | LL         |
    And I log in as "admin"
    And I visit "/local/dimensions/central.php"
    And I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"

  Scenario: The Usage lens says "Not used" on a competency no course uses, and the count on one a course uses
    When I click on "Usage" "button" in the ".local-dimensions-hubv2-lensswitch" "css_element"
    Then the "aria-pressed" attribute of "//*[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-lensswitch ')]//button[.='Usage']" "xpath_element" should contain "true"
    And I should see "Not used" in the "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Unused leaf,')]" "xpath_element"
    And the "aria-label" attribute of "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Unused leaf,')]" "xpath_element" should contain "not used"
    # The panel lists the unused competencies in words.
    And I should see "Unused leaf" in the "[data-region='lens']" "css_element"
    # A competency a course uses shows its count, once its branch is open.
    And I click on "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Domain A']]//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-otoggle-on ')]" "xpath_element"
    And I should see "1 course" in the "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Linked leaf,')]" "xpath_element"
    # The lens is kept in the address.
    And I reload the page
    And I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"
    And the "aria-pressed" attribute of "//*[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-lensswitch ')]//button[.='Usage']" "xpath_element" should contain "true"

  Scenario: A saved lens survives a later display choice and a visit whose address names no lens
    When I click on "Usage" "button" in the ".local-dimensions-hubv2-lensswitch" "css_element"
    Then the "aria-pressed" attribute of "//*[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-lensswitch ')]//button[.='Usage']" "xpath_element" should contain "true"
    # A display save that carries no lens: the server keeps the stored one, and must not refuse the save.
    When I click on "Outline" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    Then the "aria-pressed" attribute of ".local-dimensions-central-viewswitch [data-view='outline']" "css_element" should contain "true"
    And I should not see "Connection lost"
    When I visit "/local/dimensions/central.php"
    Then I should see "Domain A" in the ".local-dimensions-hubv2-tree" "css_element"
    And the "aria-pressed" attribute of ".local-dimensions-central-viewswitch [data-view='outline']" "css_element" should contain "true"
    When I click on "Both" "button" in the ".local-dimensions-central-viewswitch" "css_element"
    Then the "aria-pressed" attribute of "//*[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-lensswitch ')]//button[.='Usage']" "xpath_element" should contain "true"

  Scenario: The Rules lens names the rule on a parent, on the map and in the panel
    When I click on "Rules" "button" in the ".local-dimensions-hubv2-lensswitch" "css_element"
    Then I should see "All children are complete" in the "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Domain A,')]" "xpath_element"
    And I should see "Domain A" in the "[data-region='lens']" "css_element"
    And I should see "All children are complete" in the "[data-region='lens']" "css_element"
    And "Set the rule…" "button" should exist in the "[data-region='lens']" "css_element"

  Scenario: "Check" lists a competency with no description, and "Reveal" selects it in both panes
    When I click on "Check" "button" in the ".local-dimensions-hubv2-bar" "css_element"
    Then I should see "No description" in the "[data-region='checker']" "css_element"
    And I should see "Bare leaf" in the "[data-region='checker']" "css_element"
    When I click on "Reveal" "button" in the "//li[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-finding ')][contains(., 'No description')][contains(., 'Bare leaf')]" "xpath_element"
    Then the "aria-current" attribute of "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Bare leaf']]" "xpath_element" should contain "true"
    And the "aria-current" attribute of "//div[contains(concat(' ', @class, ' '), ' react-flow__node ')][starts-with(@aria-label, 'Bare leaf,')]" "xpath_element" should contain "true"
    # The checker stays open on the finding it revealed, marked in its list.
    And the "aria-current" attribute of "//li[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-finding ')][contains(., 'No description')][contains(., 'Bare leaf')]" "xpath_element" should contain "true"
    # Its close button gives the panel back.
    And I click on "Close the check" "button" in the "[data-region='checker']" "css_element"
    And "[data-region='checker']" "css_element" should not exist
    And I should see "Bare leaf" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
