@local @local_dimensions @javascript
Feature: The plugin's colours follow the host page and nothing else
  In order that a learner never sees a dark panel on a light page
  As a site administrator
  The plugin's surfaces must track the host, and only the host

  # The first three scenarios assert a relative invariant (the plugin surface equals the page
  # surface), so they hold on every supported branch with no skip and no branch tag. Only the last
  # needs a real dark palette, and it detects one at runtime rather than from the version number.
  #
  # These scenarios do not prove that the prefers-color-scheme block in styles.css is inert:
  # headless Chrome reports light, so such a scenario would pass vacuously.
  # colour_tokens_test::test_media_fallback_is_written_and_unreachable covers it instead: every
  # selector in that block carries a gate attribute that no runtime file writes.

  # themedesignermode is on because Behat saves the compiled theme CSS when the site is initialised
  # and restores it before every JavaScript scenario (behat_util::restore_saved_themes()), so
  # without it these scenarios would read styles.css as it was at Behat init rather than the file
  # in the tree. Designer mode compiles the CSS per request.
  Background:
    Given the following config values are set as admin:
      | enabled          | 1 | core_competency |
      | themedesignermode | 1 |                 |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And I log in as "admin"

  Scenario: The hub stays light when the host says nothing
    When I visit "/local/dimensions/central.php"
    And I click on "Structures" "link"
    Then I should see "Behat framework"
    And I remember the page background colour
    And the ".local-dimensions-central-fwcard" element background should still match the page
    And the "local-dimensions-shadow" colour token should resolve to "rgb(0 0 0 / 10%)"

  Scenario: The hub follows the host into dark mode and never diverges from it
    When I visit "/local/dimensions/central.php"
    And I click on "Structures" "link"
    Then I should see "Behat framework"
    And I remember the page background colour
    When the page colour mode is "dark"
    Then the ".local-dimensions-central-fwcard" element background should still match the page

  Scenario: A hub dialogue follows the page into dark mode
    When I visit "/local/dimensions/central.php"
    And I click on "Structures" "link"
    Then I should see "Behat framework"
    And I remember the page background colour
    When the page colour mode is "dark"
    And I click on "Import" "button"
    Then I should see "CSV file" in the "Import structure from CSV" "dialogue"
    And the ".modal.show .modal-content" element background should still match the page

  Scenario: The plugin's own decorative tokens flip with the host
    Given the site has a host colour mode
    When I visit "/local/dimensions/central.php"
    And I click on "Structures" "link"
    Then I should see "Behat framework"
    When the page colour mode is "dark"
    Then the "local-dimensions-shadow" colour token should resolve to "rgb(0 0 0 / 55%)"
    And the "local-dimensions-scrim" colour token should resolve to "rgb(29 33 37 / 72%)"
    And the "local-dimensions-favourite" colour token should resolve to "#fd7e14"
