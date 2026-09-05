@local @local_dimensions @javascript
Feature: The plugin's colours follow the host page and nothing else
  In order that a learner never sees a dark panel on a light page
  As a site administrator
  The plugin's surfaces must track the host, and only the host

  # The first three scenarios assert a RELATIVE invariant - the plugin surface equals the page
  # surface - so they are true on every supported branch with no skip and no branch tag, including
  # one whose compiled stylesheet nobody has measured. Only the last needs a real dark palette, and
  # it detects one at runtime rather than guessing from a version number.
  #
  # What these scenarios do NOT prove is that the OS-preference block in styles.css is inert:
  # emulating prefers-color-scheme needs Emulation.setEmulatedMedia over the DevTools protocol, and
  # headless Chrome reports light, so a scenario asserting the light value would pass vacuously.
  # That gap is closed by colour_tokens_test::test_media_fallback_is_written_and_unreachable, which
  # is strictly stronger: it proves nothing CAN set the gate, not merely that nothing did on one run.

  # themedesignermode is on for one reason: Behat saves the compiled theme CSS when the site is
  # initialised and restores it around every run (lib/behat/classes/util.php::restore_saved_themes),
  # so a scenario about this stylesheet would otherwise read whatever styles.css said at
  # behat-init time. Measured: with the theme cache in play, deleting the whole dark activation
  # block left all four scenarios green - a suite that certifies nothing. Designer mode compiles
  # the same CSS per request, which is what makes these assertions about the file in the tree.
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
