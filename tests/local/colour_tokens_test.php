<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dimensions\local;

/**
 * The whole colour contract, as a build gate.
 *
 * Nothing else in the pipeline reads a colour role. phpcs reads PHP, phpdoc reads docblocks, the
 * mustache lint reads markup structure, stylelint reads CSS syntax; not one of them can tell a
 * focus ring drawn with a box-shadow from one drawn with an outline, a token chain that terminates
 * in core's value from one that terminates in a frozen literal, a dark rule that assigns a
 * decorative shadow from one that assigns a whole surface, or an activation selector anchored at
 * the html element from one that fires off a navbar.
 *
 * That is why every rule of the design is a method here rather than a paragraph somewhere. The
 * defect class this file exists for has shipped three times in this plugin with CI fully green,
 * was correctly root-caused each time, and recurred anyway. Prose is not a gate.
 *
 * Each test names the mutation that must redden it, because a test that passes against the
 * mutation it was written to catch is worse than no test: it certifies safety that is not there.
 * Two drafts of the companion bootstrap_compat_test did exactly that in this repository.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\colour_mode
 */
final class colour_tokens_test extends \basic_testcase {
    /** @var string This plugin's token namespace. The sibling's differs only in the frankenstyle part. */
    private const PREFIX = '--local-dimensions-';

    /** @var string The sentinel both plugins' prefixes are rewritten to before the blocks are compared. */
    private const SENTINEL = '--DIMENSIONS-';

    /** @var string The family sibling whose token block must stay byte-identical to this one. */
    private const SIBLING = 'block_dimensions';

    /** @var string That sibling's own token namespace. */
    private const SIBLING_PREFIX = '--block-dimensions-';

    /**
     * @var array The 34 token suffixes, in declaration order.
     *
     * This array is byte-identical in both plugins' copies of this file and is the parity floor:
     * it needs no sibling installed, so a rename in one plugin reddens that plugin's own build.
     */
    private const SUFFIXES = [
        'surface', 'surface-alt', 'surface-inset', 'line',
        'ink', 'ink-muted', 'ink-faint', 'ink-strong',
        'accent', 'accent-hover',
        'brand-ink', 'brand-tint', 'brand-edge',
        'success-ink', 'success-tint', 'success-edge',
        'warning-ink', 'warning-tint', 'warning-edge',
        'danger-ink', 'danger-tint', 'danger-edge',
        'info-ink', 'info-tint', 'info-edge',
        'neutral-ink', 'neutral-tint', 'neutral-edge',
        'brand-fill', 'on-brand-fill',
        'focus-ring', 'shadow', 'scrim', 'favourite',
    ];

    /**
     * @var array Suffix => the EXACT declaration text the token block must carry.
     *
     * Equality, not a shape regex. A pattern match cannot prove a chain terminates in a literal,
     * and the terminating literal is the whole of the plugin's Moodle 4.5 behaviour.
     */
    private const LIGHT = [
        'surface' => 'var(--bs-body-bg, var(--white, #fff))',
        'surface-alt' => 'var(--bs-tertiary-bg, var(--light, #f8f9fa))',
        'surface-inset' => 'var(--bs-secondary-bg, #e9ecef)',
        'line' => 'var(--bs-border-color, #dee2e6)',
        'ink' => 'var(--bs-body-color, #1d2125)',
        'ink-muted' => 'var(--bs-secondary-color, #495057)',
        'ink-faint' => 'var(--bs-tertiary-color, #6a737b)',
        'ink-strong' => 'var(--bs-emphasis-color, var(--gray-dark, #343a40))',
        'accent' => 'var(--bs-link-color, var(--primary, #0f6cbf))',
        'accent-hover' => 'var(--bs-link-hover-color, #0c5699)',
        'brand-ink' => 'var(--bs-primary-text-emphasis, #062b4c)',
        'brand-tint' => 'var(--bs-primary-bg-subtle, #cfe2f2)',
        'brand-edge' => 'var(--bs-primary-border-subtle, #9fc4e5)',
        'success-ink' => 'var(--bs-success-text-emphasis, #153114)',
        'success-tint' => 'var(--bs-success-bg-subtle, #d7e4d6)',
        'success-edge' => 'var(--bs-success-border-subtle, #aecaad)',
        'warning-ink' => 'var(--bs-warning-text-emphasis, #60451f)',
        'warning-tint' => 'var(--bs-warning-bg-subtle, #fcefdc)',
        'warning-edge' => 'var(--bs-warning-border-subtle, #f9deb8)',
        'danger-ink' => 'var(--bs-danger-text-emphasis, #51140d)',
        'danger-tint' => 'var(--bs-danger-bg-subtle, #f4d6d2)',
        'danger-edge' => 'var(--bs-danger-border-subtle, #eaada6)',
        'info-ink' => 'var(--bs-info-text-emphasis, #00343c)',
        'info-tint' => 'var(--bs-info-bg-subtle, #cce6ea)',
        'info-edge' => 'var(--bs-info-border-subtle, #99cdd5)',
        'neutral-ink' => 'var(--bs-secondary-text-emphasis, #525557)',
        'neutral-tint' => 'var(--bs-secondary-bg-subtle, #f5f6f8)',
        'neutral-edge' => 'var(--bs-secondary-border-subtle, #ebeef0)',
        'brand-fill' => 'var(--bs-primary, var(--primary, #0f6cbf))',
        'on-brand-fill' => '#fff',
        'focus-ring' => 'var(--bs-emphasis-color, var(--gray-dark, #343a40))',
        'shadow' => 'rgb(0 0 0 / 10%)',
        'scrim' => 'rgb(255 255 255 / 72%)',
        'favourite' => '#e8590c',
    ];

    /** @var array The only three tokens the mode layer may assign, and nothing else may join them. */
    private const DARK_OWNED = ['shadow', 'scrim', 'favourite'];

    /**
     * @var array Core's own --bs-* values on the LIGHT page.
     *
     * Measured, not assumed: read out of the compiled Boost stylesheet of the running m502 stack
     * (http://localhost:8502/theme/styles.php/boost/1/all) on 2026-09-05, from the
     * ":root,[data-bs-theme=\"light\"]" block Bootstrap 5.3 compiles.
     */
    private const CORE_LIGHT = [
        '--bs-body-bg' => '#ffffff',
        '--bs-tertiary-bg' => '#f8f9fa',
        '--bs-secondary-bg' => '#e9ecef',
        '--bs-border-color' => '#dee2e6',
        '--bs-body-color' => '#1d2125',
        '--bs-secondary-color' => 'rgba(29, 33, 37, 0.75)',
        '--bs-tertiary-color' => 'rgba(29, 33, 37, 0.5)',
        '--bs-emphasis-color' => '#000000',
        '--bs-link-color' => '#0f6cbf',
        '--bs-link-hover-color' => '#0c5699',
        '--bs-focus-ring-color' => 'rgba(15, 108, 191, 0.25)',
        '--bs-primary' => '#0f6cbf',
        '--bs-primary-text-emphasis' => '#062b4c',
        '--bs-primary-bg-subtle' => '#cfe2f2',
        '--bs-primary-border-subtle' => '#9fc4e5',
        '--bs-success-text-emphasis' => '#153114',
        '--bs-success-bg-subtle' => '#d7e4d6',
        '--bs-success-border-subtle' => '#aecaad',
        '--bs-warning-text-emphasis' => '#60451f',
        '--bs-warning-bg-subtle' => '#fcefdc',
        '--bs-warning-border-subtle' => '#f9deb8',
        '--bs-danger-text-emphasis' => '#51140d',
        '--bs-danger-bg-subtle' => '#f4d6d2',
        '--bs-danger-border-subtle' => '#eaada6',
        '--bs-info-text-emphasis' => '#00343c',
        '--bs-info-bg-subtle' => '#cce6ea',
        '--bs-info-border-subtle' => '#99cdd5',
        '--bs-secondary-text-emphasis' => '#525557',
        '--bs-secondary-bg-subtle' => '#f5f6f8',
        '--bs-secondary-border-subtle' => '#ebeef0',
    ];

    /**
     * @var array Core's own --bs-* values on the DARK page, from the same measurement.
     *
     * A name absent here is a name core does not redefine under the dark attribute, and the light
     * value stands. --bs-primary is the one that matters: it does NOT flip, which is why the brand
     * is approved only as a solid fill under on-brand-fill and never as an ink.
     * --bs-focus-ring-color is present and identical to its light value, which is core's own bug
     * and the reason the plugin's ring chains --bs-emphasis-color instead.
     */
    private const CORE_DARK = [
        '--bs-body-bg' => '#1d2125',
        '--bs-tertiary-bg' => '#292e33',
        '--bs-secondary-bg' => '#343a40',
        '--bs-border-color' => '#495057',
        '--bs-body-color' => '#dee2e6',
        '--bs-secondary-color' => 'rgba(222, 226, 230, 0.75)',
        '--bs-tertiary-color' => 'rgba(222, 226, 230, 0.5)',
        '--bs-emphasis-color' => '#ffffff',
        '--bs-link-color' => '#6fa7d9',
        '--bs-link-hover-color' => '#8cb9e1',
        '--bs-focus-ring-color' => 'rgba(15, 108, 191, 0.25)',
        '--bs-primary-text-emphasis' => '#6fa7d9',
        '--bs-primary-bg-subtle' => '#031626',
        '--bs-primary-border-subtle' => '#094173',
        '--bs-success-text-emphasis' => '#86af84',
        '--bs-success-bg-subtle' => '#0b180a',
        '--bs-success-border-subtle' => '#20491e',
        '--bs-warning-text-emphasis' => '#f6ce95',
        '--bs-warning-bg-subtle' => '#302310',
        '--bs-warning-border-subtle' => '#90682f',
        '--bs-danger-text-emphasis' => '#df8379',
        '--bs-danger-bg-subtle' => '#280a06',
        '--bs-danger-border-subtle' => '#791d13',
        '--bs-info-text-emphasis' => '#66b3c0',
        '--bs-info-bg-subtle' => '#001a1e',
        '--bs-info-border-subtle' => '#004d5a',
        '--bs-secondary-text-emphasis' => '#e2e5e9',
        '--bs-secondary-bg-subtle' => '#292a2c',
        '--bs-secondary-border-subtle' => '#7c7f83',
    ];

    /**
     * @var array Foreground token, background token, WCAG floor.
     *
     * Every row is checked in three resolutions - 5.x light, 5.x dark and the Moodle 4.5 fallback
     * literal - so a chain that is right on one branch and wrong on another cannot pass.
     *
     * Three pairings are deliberately ABSENT and their absence is the design, not an oversight:
     * accent, brand-ink and danger-ink as normal text on surface-inset measure 4.50, 4.50 and 4.20
     * in dark. Those are core's own values failing against core's own surface - the same arithmetic
     * applies to every core component on the page - so they are banned by rule instead of being
     * asserted here, and test_no_low_contrast_ink_on_the_inset_surface is the ban. ink-faint is
     * absent for the same kind of reason: 2.70:1 on surface-inset in light, which WCAG 1.4.3's
     * incidental exception covers only for inactive controls.
     */
    private const PAIRS = [
        ['ink', 'surface', 4.5],
        ['ink', 'surface-alt', 4.5],
        ['ink', 'surface-inset', 4.5],
        ['ink-muted', 'surface', 4.5],
        ['ink-muted', 'surface-alt', 4.5],
        ['ink-muted', 'surface-inset', 4.5],
        ['ink-strong', 'surface', 4.5],
        ['ink-strong', 'surface-alt', 4.5],
        ['ink-strong', 'surface-inset', 4.5],
        ['accent', 'surface', 4.5],
        ['accent', 'surface-alt', 4.5],
        ['accent-hover', 'surface', 4.5],
        ['accent-hover', 'surface-alt', 4.5],
        ['accent-hover', 'surface-inset', 4.5],
        ['on-brand-fill', 'brand-fill', 4.5],
        ['focus-ring', 'surface', 3.0],
        ['focus-ring', 'surface-alt', 3.0],
        ['focus-ring', 'surface-inset', 3.0],
        ['favourite', 'surface', 3.0],
        ['favourite', 'surface-alt', 3.0],
        ['favourite', 'surface-inset', 3.0],
        ['brand-ink', 'brand-tint', 4.5],
        ['success-ink', 'success-tint', 4.5],
        ['warning-ink', 'warning-tint', 4.5],
        ['danger-ink', 'danger-tint', 4.5],
        ['info-ink', 'info-tint', 4.5],
        ['neutral-ink', 'neutral-tint', 4.5],
    ];

    /** @var array Tokens that may not be normal-size text on surface-inset (dark: 4.50, 4.50, 4.20). */
    private const DENIED_ON_INSET = ['accent', 'brand-ink', 'danger-ink'];

    /**
     * @var int Coloured-ink rules whose effective background the scanner cannot resolve.
     *
     * A ratchet, asserted for EQUALITY: raising it reddens because the actual count is then below
     * it, and adding an unresolvable pairing reddens because the count is above it. It may only
     * ever be edited downwards, and only together with the rule that made a pairing resolvable.
     */
    private const UNRESOLVED_BUDGET = 19;

    /**
     * @var array Rules painted with an admin-chosen colour, inside which no mode token may appear.
     *
     * The boundary is the element carrying the admin colour. Inside it, "adapt" means relative to
     * that colour, not relative to the page, so a mode token there would be measuring against the
     * wrong ground - and the island does not go dark when the page does, correctly, because the
     * admin's colour did not change. The glass chips are on the list for the same reason: they are
     * alpha over the admin's own fill, not over the page.
     */
    private const ISLAND_ROOTS = [
        '.local-dimensions-hero',
        '.local-dimensions-central-plans-detail-header',
        '.local-dimensions-central-plans-optpanel',
        '.local-dimensions-central-plans-chip-glass',
        '.local-dimensions-central-plans-gear-onhead',
        '.local-dimensions-plans-switch-dark',
        '.local-dimensions-related-modal-close',
        '.local-dimensions-return-fab',
        '.local-dimensions-learnmore-btn',
    ];

    /**
     * @var array Custom properties that carry admin instance data, which the mode layer may not own.
     *
     * The sibling's own transport names are listed too: the ban is what stops one arriving here by
     * copy-paste, and a ban that only names what already exists is a ban that arrives one commit
     * late.
     */
    private const ADMIN_COLOUR_NAMES = [
        '--dimension-custombgcolor',
        '--dimension-customtextcolor',
        '--hero-bg-image',
        '--hero-overlay-color',
        '--ld-plans-hdr-0',
        '--ld-plans-hdr-48',
        '--ld-plans-hdr-100',
        '--local-dimensions-fab-color',
    ];

    /**
     * @var array Custom properties the stylesheet reads but deliberately never declares.
     *
     * Two shapes, and both are set on the element rather than in the sheet: admin instance data
     * emitted inline by a template, and a measurement written by an AMD module at runtime. Neither
     * is a design token, so neither belongs in the token block - but each still has to be named
     * somewhere, or test_every_token_read_is_declared could not tell one from a typo.
     */
    private const INLINE_DECLARED = [
        '--local-dimensions-fab-color',
        '--local-dimensions-plans-master-width',
        '--local-dimensions-structure-master-width',
    ];

    /**
     * @var array The named literal exemptions, each of which must match at least one live literal.
     *
     * "Add it to the allow-list" is the standard way an enforcement suite dies, so the list is
     * checked in both directions: an unexempted literal fails, and an exemption matching nothing
     * fails too. Keys are a selector substring, the property and the literal itself - a
     * family-level entry would let a second literal ride in beside the first.
     */
    private const LITERAL_EXEMPTIONS = [
        [
            'selector' => '.local-dimensions-hero-title',
            'property' => 'color',
            'value' => 'rgb(255 255 255 / 100%)',
            'why' => 'Island ink: the fallback when the admin set no text colour, over the admin\'s own fill.',
        ],
        [
            'selector' => '.local-dimensions-hero-description',
            'property' => 'color',
            'value' => 'rgb(255 255 255 / 85%)',
            'why' => 'Island ink: the fallback when the admin set no text colour, over the admin\'s own fill.',
        ],
        [
            'selector' => '.local-dimensions-duedate-card',
            'property' => 'background',
            'value' => 'rgb(255 255 255 / 15%)',
            'why' => 'Glass over the admin\'s own fill or photograph; relative to the island, not to the page.',
        ],
        [
            'selector' => '.local-dimensions-duedate-card',
            'property' => 'border',
            'value' => 'rgb(255 255 255 / 25%)',
            'why' => 'Glass over the admin\'s own fill or photograph; relative to the island, not to the page.',
        ],
        [
            'selector' => '.local-dimensions-duedate-label',
            'property' => 'color',
            'value' => 'rgb(255 255 255 / 70%)',
            'why' => 'Island ink: the fallback when the admin set no text colour, over the admin\'s own fill.',
        ],
        [
            'selector' => '.local-dimensions-duedate-value',
            'property' => 'color',
            'value' => 'rgb(255 255 255 / 100%)',
            'why' => 'Island ink: the fallback when the admin set no text colour, over the admin\'s own fill.',
        ],
        [
            'selector' => '.local-dimensions-hero-collapse',
            'property' => 'background',
            'value' => 'rgb(255 255 255 / 14%)',
            'why' => 'Glass over the admin\'s own fill or photograph; relative to the island, not to the page.',
        ],
        [
            'selector' => '.local-dimensions-hero-collapse',
            'property' => 'border',
            'value' => 'rgb(255 255 255 / 40%)',
            'why' => 'Glass over the admin\'s own fill or photograph; relative to the island, not to the page.',
        ],
        [
            'selector' => '.local-dimensions-hero-collapse',
            'property' => 'color',
            'value' => 'rgb(255 255 255 / 100%)',
            'why' => 'Island ink: the fallback when the admin set no text colour, over the admin\'s own fill.',
        ],
        [
            'selector' => '.local-dimensions-hero-collapse:hover',
            'property' => 'background',
            'value' => 'rgb(255 255 255 / 26%)',
            'why' => 'Glass over the admin\'s own fill or photograph; relative to the island, not to the page.',
        ],
        [
            'selector' => '.local-dimensions-collapsible-toggle',
            'property' => 'background',
            'value' => 'rgb(255 255 255 / 16%)',
            'why' => 'Glass over the admin\'s own fill or photograph; relative to the island, not to the page.',
        ],
        [
            'selector' => '.local-dimensions-collapsible-toggle',
            'property' => 'border',
            'value' => 'rgb(255 255 255 / 30%)',
            'why' => 'Glass over the admin\'s own fill or photograph; relative to the island, not to the page.',
        ],
        [
            'selector' => '.local-dimensions-collapsible-toggle:hover',
            'property' => 'background',
            'value' => 'rgb(255 255 255 / 26%)',
            'why' => 'Glass over the admin\'s own fill or photograph; relative to the island, not to the page.',
        ],
        [
            'selector' => '.local-dimensions-hero-has-image::before',
            'property' => 'background',
            'value' => 'rgb(0 0 0 / 80%)',
            'why' => 'Overlay black over an arbitrary photograph: legibility is relative to the image.',
        ],
        [
            'selector' => '.local-dimensions-hero-has-image::before',
            'property' => 'background',
            'value' => 'rgb(0 0 0 / 90%)',
            'why' => 'Overlay black over an arbitrary photograph: legibility is relative to the image.',
        ],
        [
            'selector' => 'customscss',
            'property' => 'background-color',
            'value' => '#1e1e2e',
            'why' => 'Catppuccin Mocha on the custom-SCSS editor: theme-invariant on purpose, like a diff view.',
        ],
        [
            'selector' => 'customscss',
            'property' => 'color',
            'value' => '#cdd6f4',
            'why' => 'Catppuccin Mocha on the custom-SCSS editor: theme-invariant on purpose, like a diff view.',
        ],
        [
            'selector' => 'customscss',
            'property' => 'border',
            'value' => '#45475a',
            'why' => 'Catppuccin Mocha on the custom-SCSS editor: theme-invariant on purpose, like a diff view.',
        ],
        [
            'selector' => '#id_customfield_customscss:focus',
            'property' => 'border-color',
            'value' => '#89b4fa',
            'why' => 'Catppuccin Mocha\'s own focus blue, chosen against that editor\'s fixed dark ground.',
        ],
        [
            'selector' => '.local-dimensions-filter-tabs',
            'property' => '--local-dimensions-tabs-mask-color-left',
            'value' => 'black',
            'why' => 'A mask-image alpha stop, not a colour: it decides where the platter fades, never a shade.',
        ],
        [
            'selector' => '.local-dimensions-filter-tabs',
            'property' => '--local-dimensions-tabs-mask-color-right',
            'value' => 'black',
            'why' => 'A mask-image alpha stop, not a colour: it decides where the platter fades, never a shade.',
        ],
        [
            'selector' => '.local-dimensions-central-plans-detail-header',
            'property' => 'background',
            'value' => '#0f6cbf',
            'why' => 'Gradient stop fallback for --ld-plans-hdr-0, which helper::darken_hex derives from the admin.',
        ],
        [
            'selector' => '.local-dimensions-central-plans-detail-header',
            'property' => 'background',
            'value' => '#0d5a9f',
            'why' => 'Gradient stop fallback for --ld-plans-hdr-48, which helper::darken_hex derives from the admin.',
        ],
        [
            'selector' => '.local-dimensions-central-plans-detail-header',
            'property' => 'background',
            'value' => '#0a4680',
            'why' => 'Gradient stop fallback for --ld-plans-hdr-100, which helper::darken_hex derives from the admin.',
        ],
        [
            'selector' => '.local-dimensions-central-plans-gear-onhead:hover',
            'property' => 'background',
            'value' => 'rgba(255, 255, 255, 0.12)',
            'why' => 'Glass on the plans header island, composited over the admin\'s own gradient.',
        ],
        [
            'selector' => '.local-dimensions-central-plans-optpanel',
            'property' => 'border',
            'value' => 'rgba(255, 255, 255, 0.18)',
            'why' => 'Glass on the plans header island, composited over the admin\'s own gradient.',
        ],
        [
            'selector' => '.local-dimensions-central-plans-optpanel',
            'property' => 'background',
            'value' => 'rgba(255, 255, 255, 0.12)',
            'why' => 'Glass on the plans header island, composited over the admin\'s own gradient.',
        ],
        [
            'selector' => '.local-dimensions-central-plans-chip-glass',
            'property' => 'border',
            'value' => 'rgba(255, 255, 255, 0.2)',
            'why' => 'Glass on the plans header island, composited over the admin\'s own gradient.',
        ],
        [
            'selector' => '.local-dimensions-central-plans-chip-glass',
            'property' => 'background',
            'value' => 'rgba(255, 255, 255, 0.13)',
            'why' => 'Glass on the plans header island, composited over the admin\'s own gradient.',
        ],
        [
            'selector' => '.local-dimensions-plans-switch-dark',
            'property' => 'background',
            'value' => 'rgba(255, 255, 255, 0.3)',
            'why' => 'The switch track on the plans header island, composited over the admin\'s own gradient.',
        ],
        [
            'selector' => '.local-dimensions-plans-switch-dark',
            'property' => 'background',
            'value' => '#0b427e',
            'why' => 'The checked switch on the plans header island, a darkening of the same admin gradient.',
        ],
        [
            'selector' => '.local-dimensions-related-modal-close',
            'property' => 'border',
            'value' => 'rgba(255, 255, 255, 0.28)',
            'why' => 'Glass on the referenced-competency modal\'s gradient header island.',
        ],
        [
            'selector' => '.local-dimensions-related-modal-close',
            'property' => 'background',
            'value' => 'rgba(255, 255, 255, 0.16)',
            'why' => 'Glass on the referenced-competency modal\'s gradient header island.',
        ],
        [
            'selector' => '.local-dimensions-related-modal-close:hover',
            'property' => 'background',
            'value' => 'rgba(255, 255, 255, 0.3)',
            'why' => 'Glass on the referenced-competency modal\'s gradient header island.',
        ],
    ];

    /** @var array Properties whose value is a colour, and which therefore may not carry a literal. */
    private const COLOUR_PROPERTIES = [
        'color', 'background', 'background-color', 'background-image', 'box-shadow', 'fill', 'stroke',
        'text-decoration-color', 'caret-color', 'text-shadow', 'column-rule-color', 'accent-color',
    ];

    /**
     * Absolute path to the plugin root.
     *
     * @return string Plugin directory without a trailing separator.
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Every stylesheet the plugin ships.
     *
     * The styles_*.css theme overrides are included because CI's grunt leg lints styles.css only,
     * which makes them exactly the place a banned value can re-enter unseen. The glob is empty
     * today; that is why it is a glob and not a filename.
     *
     * @param string|null $root Plugin directory, defaulting to this plugin's own.
     * @return array List of absolute stylesheet paths.
     */
    private function stylesheets(?string $root = null): array {
        $root = $root ?? $this->plugin_root();

        return array_merge([$root . '/styles.css'], glob($root . '/styles_*.css') ?: []);
    }

    /**
     * Every source file that can put a colour, a class or an attribute in front of a user.
     *
     * amd/build is excluded because it is generated from amd/src, and docs because .gitattributes
     * keeps it out of the release zip.
     *
     * @param string|null $root Plugin directory, defaulting to this plugin's own.
     * @return array List of absolute file paths.
     */
    private function source_files(?string $root = null): array {
        $root = $root ?? $this->plugin_root();
        $files = glob($root . '/*.php') ?: [];
        foreach (['templates', 'amd/src', 'classes', 'tests/behat'] as $relative) {
            $dir = $root . '/' . $relative;
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if (!in_array($file->getExtension(), ['php', 'js', 'mustache', 'feature'], true)) {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * A stylesheet with every comment blanked, line numbers preserved.
     *
     * @param string $path Absolute path to a stylesheet.
     * @return string The stylesheet text with comments replaced by their own newlines.
     */
    private function uncommented(string $path): string {
        return preg_replace_callback('~/\*.*?\*/~s', static function (array $m): string {
            return str_repeat("\n", substr_count($m[0], "\n"));
        }, file_get_contents($path));
    }

    /**
     * Split a stylesheet into flat rules, with comments removed.
     *
     * At-rule wrappers are unwrapped so the rules inside them are checked exactly like the rules
     * outside: a focus rule that only appears under a media query is still a focus rule. Each entry
     * carries the file, the 1-based line the selector starts on, the selector text and the
     * declaration body.
     *
     * @param string $path Absolute path to a stylesheet.
     * @return array List of arrays with keys file, line, selector and body.
     */
    private function rules(string $path): array {
        $css = $this->uncommented($path);
        $rules = [];
        $stack = [];
        $selectorstart = 0;
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];
            if ($char === '{') {
                $selector = trim(substr($css, $selectorstart, $i - $selectorstart));
                $line = substr_count(substr($css, 0, $selectorstart), "\n") + 1;
                $stack[] = [$selector, $i, $line];
                $selectorstart = $i + 1;
            } else if ($char === '}') {
                if ($stack) {
                    [$selector, $open, $line] = array_pop($stack);
                    $body = substr($css, $open + 1, $i - $open - 1);
                    /* An at-rule wrapper contains rules, not declarations; its children are
                       already collected on their own. */
                    if (!str_contains($body, '{')) {
                        $rules[] = [
                            'file' => basename($path),
                            'line' => $line,
                            'selector' => trim(preg_replace('/\s+/', ' ', $selector)),
                            'body' => $body,
                        ];
                    }
                }
                $selectorstart = $i + 1;
            }
        }

        return $rules;
    }

    /**
     * The declarations of one rule body, as property => value pairs.
     *
     * Custom properties are included: a literal smuggled into a plugin-owned custom property is
     * exactly as frozen as one written on a colour property, and rather harder to spot.
     *
     * @param string $body The text between a rule's braces.
     * @return array Lower-case property name => trimmed value.
     */
    private function declarations(string $body): array {
        $found = [];
        foreach (explode(';', $body) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            if ($property === '') {
                continue;
            }
            $found[$property] = trim(preg_replace('/\s+/', ' ', $value));
        }

        return $found;
    }

    /**
     * The body text of every at-rule whose prelude contains the given string.
     *
     * @param string $path Absolute path to a stylesheet.
     * @param string $prelude Substring the at-rule's prelude must contain.
     * @return array List of at-rule body strings.
     */
    private function at_rule_bodies(string $path, string $prelude): array {
        $css = $this->uncommented($path);
        $bodies = [];
        $offset = 0;
        while (($start = strpos($css, $prelude, $offset)) !== false) {
            $open = strpos($css, '{', $start);
            if ($open === false) {
                break;
            }
            $depth = 0;
            for ($i = $open; $i < strlen($css); $i++) {
                if ($css[$i] === '{') {
                    $depth++;
                } else if ($css[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $bodies[] = substr($css, $open + 1, $i - $open - 1);
                        $offset = $i;
                        break;
                    }
                }
            }
            if ($depth !== 0) {
                break;
            }
        }

        return $bodies;
    }

    /**
     * The selectors of the three rules that own the colour contract.
     *
     * @return array List of selector strings.
     */
    private function contract_block_selectors(): array {
        return [
            ':root',
            ':root[' . colour_mode::HOST_ATTRIBUTE . '="' . colour_mode::DARK . '"]',
            ':root[' . colour_mode::MEDIA_OPTIN_ATTRIBUTE . ']:not(['
                . colour_mode::HOST_ATTRIBUTE . '="' . colour_mode::LIGHT . '"])',
        ];
    }

    /**
     * The token block's declarations, read out of the stylesheet.
     *
     * This plugin carries a SECOND bare :root rule, declaring motion durations and a loading
     * height, which the token block was deliberately placed after. So the block is identified by
     * what it declares - the surface token - rather than by being the first :root in the file.
     *
     * @param string|null $root Plugin directory, defaulting to this plugin's own.
     * @param string|null $prefix Token namespace to look for, defaulting to this plugin's own.
     * @return array Full token name => declaration text.
     */
    private function token_block(?string $root = null, ?string $prefix = null): array {
        $prefix = $prefix ?? self::PREFIX;
        foreach ($this->rules(($root ?? $this->plugin_root()) . '/styles.css') as $rule) {
            if ($rule['selector'] !== ':root') {
                continue;
            }
            $declarations = $this->declarations($rule['body']);
            if (isset($declarations[$prefix . 'surface'])) {
                return $declarations;
            }
        }

        return [];
    }

    /**
     * The dark activation block's declarations, read out of the stylesheet.
     *
     * @return array Full token name => declaration text.
     */
    private function activation_block(): array {
        $wanted = ':root[' . colour_mode::HOST_ATTRIBUTE . '="' . colour_mode::DARK . '"]';
        foreach ($this->rules($this->plugin_root() . '/styles.css') as $rule) {
            if ($rule['selector'] !== $wanted) {
                continue;
            }
            return $this->declarations($rule['body']);
        }

        return [];
    }

    /* --------------------------------------------------------------------------------------- */
    /* Colour arithmetic. WCAG 2.x relative luminance, alpha compositing included.               */
    /* --------------------------------------------------------------------------------------- */

    /**
     * Parse a CSS colour into red, green, blue and alpha channels.
     *
     * Handles #rgb, #rrggbb, #rrggbbaa, rgb()/rgba() in both the comma and the space syntax, and
     * the two colour keywords this stylesheet family uses.
     *
     * @param string $colour A CSS colour value.
     * @return array|null [r, g, b, a] with r/g/b in 0-255 and a in 0-1, or null when unparseable.
     */
    private function parse_colour(string $colour): ?array {
        $colour = strtolower(trim($colour));
        if ($colour === 'white') {
            return [255, 255, 255, 1.0];
        }
        if ($colour === 'black') {
            return [0, 0, 0, 1.0];
        }
        if (preg_match('/^#([0-9a-f]{3,8})$/', $colour, $m)) {
            $hex = $m[1];
            if (strlen($hex) === 3 || strlen($hex) === 4) {
                $hex = preg_replace('/(.)/', '$1$1', $hex);
            }
            if (strlen($hex) !== 6 && strlen($hex) !== 8) {
                return null;
            }
            $alpha = strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0;

            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha];
        }
        if (preg_match('/^rgba?\(([^)]*)\)$/', $colour, $m)) {
            $parts = preg_split('~[,/\s]+~', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY);
            if (count($parts) < 3) {
                return null;
            }
            $channels = [];
            foreach (array_slice($parts, 0, 3) as $part) {
                $channels[] = str_ends_with($part, '%') ? (float) $part * 2.55 : (float) $part;
            }
            $alpha = 1.0;
            if (isset($parts[3])) {
                $alpha = str_ends_with($parts[3], '%') ? (float) $parts[3] / 100 : (float) $parts[3];
            }

            return [$channels[0], $channels[1], $channels[2], $alpha];
        }

        return null;
    }

    /**
     * Composite a translucent colour over an opaque backdrop.
     *
     * @param array $foreground Four channels as returned by parse_colour().
     * @param array $backdrop Four channels whose alpha is assumed to be 1.
     * @return array The opaque result, with alpha 1.
     */
    private function composite(array $foreground, array $backdrop): array {
        $alpha = $foreground[3];

        return [
            $foreground[0] * $alpha + $backdrop[0] * (1 - $alpha),
            $foreground[1] * $alpha + $backdrop[1] * (1 - $alpha),
            $foreground[2] * $alpha + $backdrop[2] * (1 - $alpha),
            1.0,
        ];
    }

    /**
     * WCAG 2.x relative luminance of an opaque colour.
     *
     * @param array $colour Four channels whose alpha is assumed to be 1.
     * @return float Relative luminance in 0..1.
     */
    private function luminance(array $colour): float {
        $channels = [];
        foreach (array_slice($colour, 0, 3) as $value) {
            $value = $value / 255;
            $channels[] = $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * WCAG 2.x contrast ratio between a foreground and an opaque background.
     *
     * @param string $foreground Foreground CSS colour, possibly translucent.
     * @param string $background Background CSS colour, which must be opaque.
     * @return float|null The ratio, or null when either colour could not be parsed.
     */
    private function contrast(string $foreground, string $background): ?float {
        $fore = $this->parse_colour($foreground);
        $back = $this->parse_colour($background);
        if ($fore === null || $back === null) {
            return null;
        }
        if ($fore[3] < 1) {
            $fore = $this->composite($fore, $back);
        }
        $lighter = max($this->luminance($fore), $this->luminance($back));
        $darker = min($this->luminance($fore), $this->luminance($back));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Resolve one token to a concrete colour in one of the three resolutions.
     *
     * The declaration is read out of the stylesheet and followed exactly one hop: a chain
     * var(--bs-NEW, var(--BS4-OLD, #literal)) yields core's measured value on 5.x and the
     * terminating literal on 4.5, which is precisely what the browser does on each branch.
     *
     * @param string $suffix Token suffix, e.g. ink-muted.
     * @param string $mode One of light, dark or bs4.
     * @return string|null The resolved CSS colour, or null when the chain names a core value the
     *                     checked-in map does not carry.
     */
    private function resolve(string $suffix, string $mode): ?string {
        $name = self::PREFIX . $suffix;
        if ($mode === 'dark') {
            $dark = $this->activation_block();
            if (isset($dark[$name])) {
                return $dark[$name];
            }
        }
        $declaration = $this->token_block()[$name] ?? null;
        if ($declaration === null) {
            return null;
        }
        if ($mode === 'bs4') {
            /* Moodle 4.5 declares no --bs-* and no --gray/--white/--primary either, so every
               var() in the chain misses and the browser lands on the terminating literal. */
            if (preg_match_all('/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?)\([^)]*\)/i', $declaration, $matches)) {
                return end($matches[0]);
            }

            return null;
        }
        if (!preg_match('/^var\(\s*(--[a-z0-9-]+)/i', $declaration, $m)) {
            return $declaration;
        }
        $core = $m[1];
        if ($mode === 'dark') {
            return self::CORE_DARK[$core] ?? self::CORE_LIGHT[$core] ?? null;
        }

        return self::CORE_LIGHT[$core] ?? null;
    }

    /* --------------------------------------------------------------------------------------- */
    /* T1-T3c - the literal ban, the declaration contract and the family lock.                   */
    /* --------------------------------------------------------------------------------------- */

    /**
     * Every colour literal in the stylesheet, outside the blocks that own the contract.
     *
     * @return array List of arrays with keys where, selector, property and value.
     */
    private function literal_findings(): array {
        $contractselectors = $this->contract_block_selectors();
        $gate = 'body.' . bootstrap::BODY_CLASS_BS4;
        $findings = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                if (in_array($rule['selector'], $contractselectors, true)) {
                    continue;
                }
                if (str_contains($rule['selector'], $gate)) {
                    continue;
                }
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    $iscolour = in_array($property, self::COLOUR_PROPERTIES, true)
                        || str_starts_with($property, 'border')
                        || str_starts_with($property, 'outline')
                        || str_starts_with($property, 'background')
                        || str_starts_with($property, '--');
                    if (!$iscolour) {
                        continue;
                    }
                    $pattern = '/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?)\([^)]*\)|(?<![-\w])(?:white|black|red|green|'
                        . 'blue|silver|gray|grey|orange|yellow|purple|navy|teal|maroon|olive|lime|aqua|fuchsia)'
                        . '(?![-\w])/i';
                    if (!preg_match_all($pattern, $value, $matches)) {
                        continue;
                    }
                    foreach (array_unique($matches[0]) as $literal) {
                        $findings[] = [
                            'where' => $rule['file'] . ':' . $rule['line'],
                            'selector' => $rule['selector'],
                            'property' => $property,
                            'value' => $literal,
                        ];
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * No colour literal may live outside the token block, the mode blocks or a named exemption.
     *
     * Checked in both directions. An unexempted literal fails, which is the obvious half; and an
     * exemption that matches nothing fails too, which is the half that keeps the allow-list from
     * rotting into a blanket as rules are deleted around it.
     *
     * Mutations that must redden it: put color: #6c757d back on any component rule; and delete an
     * exempted rule while leaving its allow-list entry behind.
     *
     * @return void
     */
    public function test_no_colour_literal_outside_the_token_block(): void {
        $findings = $this->literal_findings();
        $offenders = [];
        $matched = [];
        foreach ($findings as $finding) {
            $exempt = false;
            foreach (self::LITERAL_EXEMPTIONS as $index => $exemption) {
                if (
                    str_contains($finding['selector'], $exemption['selector'])
                    && $finding['property'] === $exemption['property']
                    && strcasecmp($finding['value'], $exemption['value']) === 0
                ) {
                    $matched[$index] = true;
                    $exempt = true;
                }
            }
            if (!$exempt) {
                $offenders[] = $finding['where'] . ' ' . $finding['property'] . ': ' . $finding['value']
                    . ' (' . $finding['selector'] . ')';
            }
        }
        foreach (self::LITERAL_EXEMPTIONS as $index => $exemption) {
            if (!isset($matched[$index])) {
                $offenders[] = 'exemption ' . $index . ' (' . $exemption['selector'] . ' '
                    . $exemption['property'] . ': ' . $exemption['value'] . ') matches nothing any more';
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'Colour lives in the token block and nowhere else, and every exemption must still match a '
                . 'live literal: ' . implode('; ', $offenders)
        );
    }

    /**
     * The token block declares exactly the contract, with exactly the declared text.
     *
     * Equality on the declaration string, not a shape regex: a pattern can prove a chain exists but
     * not that it terminates in the right literal, and the terminating literal is the whole of the
     * plugin's Moodle 4.5 behaviour.
     *
     * Mutations that must redden it: rewrite one chain as a bare literal; delete a token; add a
     * token to the CSS without adding it to LIGHT.
     *
     * @return void
     */
    public function test_token_block_declares_exactly_the_contract(): void {
        $declared = $this->token_block();
        $expected = [];
        foreach (self::LIGHT as $suffix => $value) {
            $expected[self::PREFIX . $suffix] = $value;
        }
        ksort($declared);
        ksort($expected);
        $offenders = [];
        foreach (array_diff(array_keys($declared), array_keys($expected)) as $extra) {
            $offenders[] = $extra . ' is declared but is not in the contract';
        }
        foreach (array_diff(array_keys($expected), array_keys($declared)) as $missing) {
            $offenders[] = $missing . ' is in the contract but is not declared';
        }
        foreach ($expected as $name => $value) {
            if (isset($declared[$name]) && $declared[$name] !== $value) {
                $offenders[] = $name . ' declares "' . $declared[$name] . '" but the contract is "' . $value . '"';
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'The :root token block must declare exactly the ' . count(self::LIGHT) . ' contract tokens, '
                . 'each with its exact chain: ' . implode('; ', $offenders)
        );
    }

    /**
     * The declared suffixes are the family's, byte for byte.
     *
     * The parity floor. It needs no sibling installed and no CI checkout, so a rename in one plugin
     * reddens that plugin's own build rather than waiting for a cross-repo comparison that might be
     * skipping.
     *
     * @return void
     */
    public function test_token_suffixes_match_the_family_contract(): void {
        $declared = [];
        foreach (array_keys($this->token_block()) as $name) {
            if (str_starts_with($name, self::PREFIX)) {
                $declared[] = substr($name, strlen(self::PREFIX));
            }
        }
        $expected = self::SUFFIXES;
        sort($declared);
        sort($expected);
        $this->assertSame(
            $expected,
            $declared,
            'The token suffix set is shared with ' . self::SIBLING . ' and is what makes the two plugins '
                . 'one system; only the frankenstyle prefix may differ.'
        );
    }

    /**
     * The two plugins' token blocks must be the same block under two prefixes.
     *
     * The comparison is over the DECLARATIONS, not the raw block text. The prose around them
     * differs on purpose - each file explains itself to its own reader - and comparing prose would
     * make this test fail for reasons that are not the contract. Every declaration, its exact value
     * and the complete name set are compared, so the thing the design actually claims is pinned.
     *
     * The residue check is what closes the objection that a normalising comparison is where a real
     * difference hides: after each plugin's own prefix is rewritten to the sentinel, NEITHER
     * original prefix may survive in EITHER string, so an unprefixed or wrong-prefixed name cannot
     * pass by looking the same on both sides.
     *
     * @return void
     */
    public function test_token_block_is_identical_to_the_sibling(): void {
        $siblingroot = \core_component::get_component_directory(self::SIBLING);
        if ($siblingroot === null || !is_readable($siblingroot . '/styles.css')) {
            $this->markTestSkipped(
                'The colour-token family is local_dimensions + ' . self::SIBLING . ', and ' . self::SIBLING
                    . ' is not installed on this site, so the cross-repo half of the contract cannot run. '
                    . 'test_token_suffixes_match_the_family_contract still holds the parity floor here, and '
                    . 'test_ci_checks_out_the_family_sibling is what stops this skip becoming permanent.'
            );
        }
        $mine = $this->token_block();
        $theirs = $this->token_block($siblingroot, self::SIBLING_PREFIX);
        $normalise = static function (array $block, string $prefix): string {
            $lines = [];
            foreach ($block as $name => $value) {
                $lines[] = str_replace($prefix, self::SENTINEL, $name) . ': ' . $value;
            }
            sort($lines);

            return implode("\n", $lines);
        };
        $minetext = $normalise($mine, self::PREFIX);
        $theirstext = $normalise($theirs, self::SIBLING_PREFIX);
        $residue = [];
        foreach (['local_dimensions' => $minetext, self::SIBLING => $theirstext] as $plugin => $text) {
            foreach ([self::PREFIX, self::SIBLING_PREFIX] as $prefix) {
                if (str_contains($text, $prefix)) {
                    $residue[] = $plugin . "'s block still carries " . $prefix . ' after substitution';
                }
            }
        }
        $this->assertSame(
            [],
            $residue,
            'A token name that survives the prefix substitution is a name that is unprefixed or carries '
                . 'the wrong plugin\'s prefix: ' . implode('; ', $residue)
        );
        $this->assertSame(
            $theirstext,
            $minetext,
            'local_dimensions and ' . self::SIBLING . ' must declare the same token block under their own '
                . 'prefixes; they differ.'
        );
    }

    /**
     * CI must check the family sibling out, or the cross-repo comparison silently stops running.
     *
     * This is the anti-vacuity control for the test above. A skip nobody notices is how a cross-repo
     * lock quietly stops running, and the only place that can be observed is the workflow file.
     *
     * @return void
     */
    public function test_ci_checks_out_the_family_sibling(): void {
        $workflow = $this->plugin_root() . '/.github/workflows/ci.yml';
        $this->assertFileExists($workflow, 'The CI workflow is where the sibling checkout is declared.');
        $offenders = [];
        $jobs = preg_split('/\n  (?=[a-z0-9-]+:\n)/', file_get_contents($workflow));
        $found = 0;
        foreach ($jobs as $job) {
            if (!str_contains($job, 'moodle-plugin-ci.yml@main')) {
                continue;
            }
            $found++;
            preg_match('/^  ([a-z0-9-]+):/m', $job, $m);
            $name = $m[1] ?? 'unnamed job';
            if (!preg_match('/plugin-dependencies:.*\n(\s+.*\n)*?\s*\S*moodle-' . self::SIBLING . '\b/', $job)) {
                $offenders[] = $name;
            }
        }
        $this->assertGreaterThan(
            0,
            $found,
            'No reusable-workflow job was found in ci.yml, so this test would pass over an empty list.'
        );
        $this->assertSame(
            [],
            $offenders,
            'These CI jobs do not check out moodle-' . self::SIBLING . ' under plugin-dependencies, so the '
                . 'cross-repo token comparison would skip on them: ' . implode(', ', $offenders)
        );
    }

    /* --------------------------------------------------------------------------------------- */
    /* T4-T6 - the activation contract.                                                          */
    /* --------------------------------------------------------------------------------------- */

    /**
     * The mode layer may assign the three plugin-owned decorative tokens and nothing else.
     *
     * This is the test that makes decision #1 structural rather than aspirational. The other 31
     * tokens follow core's own --bs-* values, so a wrongly firing activation block can only deepen
     * a shadow, darken a veil and brighten a star - it cannot give the plugin a surface or an ink
     * that core did not supply, which is the only way a plugin ends up dark on a light page.
     *
     * Mutations that must redden it: assign a surface token in the dark block; add an ordinary CSS
     * rule inside it.
     *
     * @return void
     */
    public function test_dark_activation_block_assigns_only_plugin_owned_tokens(): void {
        $allowed = [];
        foreach (self::DARK_OWNED as $suffix) {
            $allowed[] = self::PREFIX . $suffix;
        }
        $offenders = [];
        $checked = 0;
        foreach ($this->stylesheets() as $path) {
            /* The media block's own selectors, matched EXACTLY. Asking whether a rule's selector
               appears anywhere in the block's text would make ":root" - the token block itself -
               look like a member of it, because the gated selector begins with those five
               characters. */
            $mediaselectors = [];
            foreach ($this->at_rule_bodies($path, '@media (prefers-color-scheme') as $body) {
                foreach (explode('}', $body) as $chunk) {
                    if (!str_contains($chunk, '{')) {
                        continue;
                    }
                    $mediaselectors[] = trim(preg_replace('/\s+/', ' ', explode('{', $chunk)[0]));
                }
            }
            foreach ($this->rules($path) as $rule) {
                $inmedia = in_array($rule['selector'], $mediaselectors, true);
                if (!str_contains($rule['selector'], colour_mode::HOST_ATTRIBUTE) && !$inmedia) {
                    continue;
                }
                $checked++;
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    if (in_array($property, $allowed, true)) {
                        continue;
                    }
                    $offenders[] = $rule['file'] . ':' . $rule['line'] . ' declares ' . $property
                        . ': ' . $value;
                }
            }
        }
        $this->assertSame(
            2,
            $checked,
            'Exactly two rules carry the mode layer - the activation block and the inert OS-preference '
                . 'block - and this test found ' . $checked . '.'
        );
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'Only ' . implode(', ', $allowed) . ' may be assigned by a mode rule; everything else follows '
                . 'core\'s own --bs-* values and must have no dark rule at all: ' . implode('; ', $offenders)
        );
    }

    /**
     * Every colour-mode selector is anchored at the html element, and no dead mechanism survives.
     *
     * A bare attribute selector matches through any ancestor at any depth, and CSS descendant
     * combinators have no nearest-ancestor-wins rule - theme_boost_union_fundaseg really does set
     * the attribute on the navbar, which theme_boost_union then re-pins to light on five nested
     * templates. :root restricts the match to the html element and forecloses that at zero cost.
     *
     * Mutations that must redden it: change one selector to a bare attribute selector; add a
     * .theme-dark rule of the kind the sibling carried 77 of.
     *
     * @return void
     */
    public function test_activation_selectors_are_root_anchored(): void {
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                if (!str_contains($rule['selector'], colour_mode::HOST_ATTRIBUTE)) {
                    continue;
                }
                foreach (explode(',', $rule['selector']) as $part) {
                    if (!str_starts_with(trim($part), ':root[')) {
                        $offenders[] = $rule['file'] . ':' . $rule['line'] . ' ' . trim($part);
                    }
                }
            }
            $css = $this->uncommented($path);
            foreach (['.theme-dark', 'body.dark', '.darkmode', '[data-theme'] as $dead) {
                if (str_contains($css, $dead)) {
                    $offenders[] = basename($path) . ' still contains ' . $dead
                        . ', which nothing on any supported branch has ever emitted';
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'The host signal is read only from the html element, and no other dark mechanism may live '
                . 'beside it: ' . implode('; ', $offenders)
        );
    }

    /**
     * The OS-preference fallback is written, is gated, and nothing can reach the gate.
     *
     * Three independent assertions, so no two are one assertion wearing two names: the block must
     * EXIST (not merely be absent), every selector in it must carry the gate, and the gate
     * attribute must appear in no runtime file in this plugin or in the installed sibling.
     *
     * Mutations that must redden it, one per assertion: delete the whole media block; drop the gate
     * from the selector; write the attribute into any template.
     *
     * @return void
     */
    public function test_media_fallback_is_written_and_unreachable(): void {
        $bodies = $this->at_rule_bodies($this->plugin_root() . '/styles.css', '@media (prefers-color-scheme');
        $this->assertCount(
            1,
            $bodies,
            'styles.css must carry exactly one @media (prefers-color-scheme) block: the OS-preference '
                . 'fallback, written and ready so switching it on is one edit. Found ' . count($bodies) . '.'
        );
        $ungated = [];
        foreach ($bodies as $body) {
            foreach (explode('}', $body) as $chunk) {
                if (!str_contains($chunk, '{')) {
                    continue;
                }
                $selector = trim(preg_replace('/\s+/', ' ', explode('{', $chunk)[0]));
                if ($selector === '') {
                    continue;
                }
                if (!str_contains($selector, colour_mode::MEDIA_OPTIN_ATTRIBUTE)) {
                    $ungated[] = $selector;
                }
            }
        }
        $this->assertSame(
            [],
            $ungated,
            'Every selector in the OS-preference block must carry [' . colour_mode::MEDIA_OPTIN_ATTRIBUTE
                . ']; these do not, so the block can fire: ' . implode('; ', $ungated)
        );
        $writers = [];
        foreach ($this->family_roots() as $component => $root) {
            foreach ($this->source_files($root) as $path) {
                if (basename($path) === 'colour_mode.php' || str_contains($path, '/tests/')) {
                    /* The constant declaration is the contract's own dictionary and a test is its
                       observer; neither can put an attribute on a page. */
                    continue;
                }
                if (str_contains(file_get_contents($path), colour_mode::MEDIA_OPTIN_ATTRIBUTE)) {
                    $writers[] = $component . '/' . basename($path);
                }
            }
        }
        sort($writers);
        $this->assertSame(
            [],
            $writers,
            'Nothing may write [' . colour_mode::MEDIA_OPTIN_ATTRIBUTE . ']: the OS preference is an input '
                . 'to the host\'s own colour mode, never an independent trigger, and a plugin that fires on '
                . 'it directly is dark inside a light page. Found in: ' . implode(', ', $writers)
        );
    }

    /**
     * The plugin roots of every installed member of the colour-token family.
     *
     * @return array Component name => absolute plugin directory.
     */
    private function family_roots(): array {
        $roots = ['local_dimensions' => $this->plugin_root()];
        $sibling = \core_component::get_component_directory(self::SIBLING);
        if ($sibling !== null && is_dir($sibling)) {
            $roots[self::SIBLING] = $sibling;
        }

        return $roots;
    }

    /* --------------------------------------------------------------------------------------- */
    /* T7-T8 - the contrast obligations.                                                         */
    /* --------------------------------------------------------------------------------------- */

    /**
     * The effective background of every rule that paints one, keyed by selector.
     *
     * Only a flat token background is recorded. A gradient or an image is not a ground a contrast
     * ratio can be computed against, and pretending otherwise would produce numbers that mean
     * nothing.
     *
     * @return array Selector part => token suffix.
     */
    private function background_map(): array {
        $map = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    if ($property !== 'background' && $property !== 'background-color') {
                        continue;
                    }
                    if (!str_starts_with($value, 'var(')) {
                        continue;
                    }
                    if (!preg_match('/' . preg_quote(self::PREFIX, '/') . '([a-z0-9-]+)/', $value, $m)) {
                        continue;
                    }
                    foreach (explode(',', $rule['selector']) as $part) {
                        $map[trim($part)] = $m[1];
                    }
                }
            }
        }

        return $map;
    }

    /**
     * The effective background token for a selector, resolved through its ancestors.
     *
     * The longest recorded selector that this one descends from wins, which is the resolution a
     * reader performs by eye and the reason the split-across-two-rules form of the defect is
     * catchable at all.
     *
     * @param string $selector One selector part, whitespace already collapsed.
     * @param array $map Selector part => token suffix, from background_map().
     * @return string|null The token suffix, or null when no ancestor paints a flat token.
     */
    private function effective_background(string $selector, array $map): ?string {
        $best = null;
        $bestlength = -1;
        foreach ($map as $candidate => $suffix) {
            $matches = $candidate === $selector
                || preg_match('/^' . preg_quote($candidate, '/') . '[\s.:\[>]/', $selector);
            if ($matches && strlen($candidate) > $bestlength) {
                $best = $suffix;
                $bestlength = strlen($candidate);
            }
        }

        return $best;
    }

    /**
     * Three coloured inks may not be normal-size text on the inset surface.
     *
     * accent and brand-ink measure 4.50:1 there in dark and danger-ink 4.20:1 - core's own values
     * against core's own surface, so the same arithmetic applies to every core component on the
     * page. Large text is exempt because all three clear 3:1, but a rule with no resolvable
     * font-size counts as normal, never as large.
     *
     * The rule is satisfiable rather than a constraint on the design: the filter tab's rest label
     * is ink-muted and its active label sits on the indicator pill, which paints surface, where
     * accent measures 6.33:1 dark and 5.36:1 light.
     *
     * Mutations that must redden it: write the denied pairing in one rule; write the SAME pairing
     * split across an ancestor rule and a descendant rule; raise UNRESOLVED_BUDGET.
     *
     * @return void
     */
    public function test_no_low_contrast_ink_on_the_inset_surface(): void {
        $map = $this->background_map();
        $offenders = [];
        $unresolved = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                $declarations = $this->declarations($rule['body']);
                if (!isset($declarations['color'])) {
                    continue;
                }
                if (!preg_match('/' . preg_quote(self::PREFIX, '/') . '([a-z0-9-]+)/', $declarations['color'], $m)) {
                    continue;
                }
                if (!in_array($m[1], self::DENIED_ON_INSET, true)) {
                    continue;
                }
                $islarge = false;
                if (isset($declarations['font-size']) && preg_match('/^([0-9.]+)rem/', $declarations['font-size'], $s)) {
                    $pixels = (float) $s[1] * 16;
                    $bold = isset($declarations['font-weight']) && (int) $declarations['font-weight'] >= 700;
                    $islarge = $pixels >= 24 || ($bold && $pixels >= 18.66);
                }
                foreach (explode(',', $rule['selector']) as $part) {
                    $part = trim($part);
                    $background = $this->effective_background($part, $map);
                    if ($background === null) {
                        $unresolved[] = $rule['file'] . ':' . $rule['line'] . ' ' . $part;
                        continue;
                    }
                    if ($background === 'surface-inset' && !$islarge) {
                        $offenders[] = $rule['file'] . ':' . $rule['line'] . ' paints ' . $m[1]
                            . ' on surface-inset (' . $part . ')';
                    }
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'accent, brand-ink and danger-ink measure 4.50, 4.50 and 4.20 against surface-inset on the dark '
                . 'page, so they may not be normal-size text there: ' . implode('; ', $offenders)
        );
        sort($unresolved);
        $this->assertSame(
            self::UNRESOLVED_BUDGET,
            count($unresolved),
            'Coloured-ink rules whose effective background the scanner cannot resolve are counted rather '
                . 'than silently skipped, and the count is a ratchet that may only fall. Expected '
                . self::UNRESOLVED_BUDGET . ', found ' . count($unresolved) . ': ' . implode('; ', $unresolved)
        );
    }

    /**
     * Every declared pair clears its floor, in all three resolutions.
     *
     * The values are read out of the stylesheet and followed one hop against core's measured --bs-*
     * map. A test that compared one PHP constant against another would prove only that two literals
     * match; this one fails when the CSS changes, which is the point.
     *
     * Mutations that must redden it, all of them applied to the CSS: point favourite at #fd7e14 in
     * light (2.57 on surface); put ink-muted's 4.5 fallback back to var(--gray, #6a737b) (4.07 on
     * surface-inset); chain focus-ring to --bs-focus-ring-color (core does not flip it).
     *
     * @return void
     */
    public function test_declared_values_clear_their_floor(): void {
        $offenders = [];
        foreach (['light', 'dark', 'bs4'] as $mode) {
            foreach (self::PAIRS as [$foreground, $background, $floor]) {
                $fore = $this->resolve($foreground, $mode);
                $back = $this->resolve($background, $mode);
                if ($fore === null || $back === null) {
                    $offenders[] = $mode . ': ' . $foreground . ' on ' . $background
                        . ' could not be resolved out of the stylesheet';
                    continue;
                }
                $ratio = $this->contrast($fore, $back);
                if ($ratio === null) {
                    $offenders[] = $mode . ': ' . $foreground . ' (' . $fore . ') on ' . $background
                        . ' (' . $back . ') could not be parsed as colours';
                    continue;
                }
                if ($ratio + 0.005 < $floor) {
                    $offenders[] = sprintf(
                        '%s: %s (%s) on %s (%s) is %.2f:1, floor %.1f',
                        $mode,
                        $foreground,
                        $fore,
                        $background,
                        $back,
                        $ratio,
                        $floor
                    );
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'Every token pairing must clear its WCAG floor on the light page, on the dark page and on the '
                . 'Moodle 4.5 fallback literals: ' . implode('; ', $offenders)
        );
    }

    /* --------------------------------------------------------------------------------------- */
    /* T9-T10 - the focus indicator.                                                             */
    /* --------------------------------------------------------------------------------------- */

    /**
     * Whether a set of declarations draws a real, visible outline.
     *
     * @param array $declarations Property => value, as returned by declarations().
     * @return bool True when an outline is drawn that forced-colors mode would paint.
     */
    private function draws_an_outline(array $declarations): bool {
        foreach (['outline', 'outline-style', 'outline-width'] as $property) {
            if (!isset($declarations[$property])) {
                continue;
            }
            $value = strtolower($declarations[$property]);
            if ($value === 'none' || $value === '0' || $value === 'hidden') {
                continue;
            }
            if ($property === 'outline' && preg_match('/\b(none|hidden)\b/', $value)) {
                continue;
            }
            if ($property === 'outline' && preg_match('/(^|\s)0(\s|$)/', $value)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * A focus indicator must survive forced-colors mode, so it must be a real outline.
     *
     * Two offences, both of which have shipped in this family: a focus rule that switches the
     * outline off without drawing another one, and a focus rule whose only visible signal is a
     * box-shadow. Windows High Contrast Mode does not render box-shadow at all, and it does not
     * restore an author's outline: none. Either alone leaves a keyboard user with no indicator
     * whatsoever on the branch nobody tests by hand.
     *
     * Mutation that must redden it: revert a filter-tab focus rule to outline: none plus an inset
     * box-shadow.
     *
     * @return void
     */
    public function test_focus_indicators_survive_forced_colors(): void {
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                if (!str_contains($rule['selector'], ':focus')) {
                    continue;
                }
                $declarations = $this->declarations($rule['body']);
                $drawsoutline = $this->draws_an_outline($declarations);
                $where = $rule['file'] . ':' . $rule['line'];
                $suppresses = false;
                foreach (['outline', 'outline-style', 'outline-width'] as $property) {
                    if (!isset($declarations[$property])) {
                        continue;
                    }
                    $value = strtolower($declarations[$property]);
                    if (
                        $value === 'none'
                        || $value === '0'
                        || $value === 'hidden'
                        || preg_match('/\b(none|hidden)\b/', $value)
                    ) {
                        $suppresses = true;
                    }
                }
                if ($suppresses && !$drawsoutline) {
                    $offenders[] = $where . ' switches the outline off and draws no other one';
                    continue;
                }
                if ($drawsoutline || !isset($declarations['box-shadow'])) {
                    continue;
                }
                if (strtolower($declarations['box-shadow']) === 'none') {
                    continue;
                }
                /*
                 * A shadow beside a colour, a border, an underline or a movement is a decoration
                 * on top of a signal forced-colors does paint; a shadow on its own is the whole
                 * signal. transform and opacity are in the list because forced-colors substitutes
                 * a palette and does not stop an element moving or appearing - a card that lifts
                 * when the link inside it takes focus still says so with the ring switched off.
                 */
                $othersignals = ['color', 'background', 'background-color', 'border', 'border-color',
                    'border-bottom-color', 'border-left-color', 'border-right-color', 'border-top-color',
                    'border-bottom', 'border-left', 'border-right', 'border-top', 'text-decoration',
                    'text-decoration-color', 'fill', 'stroke', 'transform', 'opacity'];
                if (!array_intersect($othersignals, array_keys($declarations))) {
                    $offenders[] = $where . ' signals focus with a box-shadow only';
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'box-shadow is not painted in forced-colors mode, so a focus indicator has to be a real '
                . 'outline: ' . implode('; ', $offenders)
        );
    }

    /**
     * A focus ring may never be drawn in the brand colour, nor in a literal.
     *
     * The ring is anchored to the emphasis extreme, which flips and is theme-independent. Core's own
     * --bs-focus-ring-color does NOT flip - measured, it is the same rgba in both modes, 1.02:1
     * against the dark page - and the site owner's link colour measures 2.33:1 on the dark card of
     * the fleet's live theme. A 3:1 obligation cannot be delegated to a colour somebody else picks.
     * currentcolor is accepted on a branded island, whose ground is the same colour in both modes
     * and where the emphasis extreme would be measuring against the page instead of against the
     * thing the ring encloses.
     *
     * Mutation that must redden it: point one focus ring at the accent token, or at a literal.
     *
     * @return void
     */
    public function test_focus_ring_is_never_brand_coloured(): void {
        $banned = [
            self::PREFIX . 'accent',
            self::PREFIX . 'brand-fill',
            '--bs-primary',
            '--bs-focus-ring-color',
            '--dimension-custombgcolor',
            '--primary',
        ];
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                $isfocus = str_contains($rule['selector'], ':focus');
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    $isoutline = $property === 'outline' || str_starts_with($property, 'outline-');
                    if (!$isoutline && !($isfocus && $property === 'box-shadow')) {
                        continue;
                    }
                    $where = $rule['file'] . ':' . $rule['line'] . ' ' . $property;
                    foreach ($banned as $name) {
                        /* Match the whole custom-property name, never a longer one starting with it. */
                        if (preg_match('/' . preg_quote($name, '/') . '(?![-\w])/', $value)) {
                            $offenders[] = $where . ' reads ' . $name;
                        }
                    }
                    if (preg_match('/#[0-9a-f]{3,8}\b|\b(rgba?|hsla?)\s*\(/i', $value)) {
                        $offenders[] = $where . ' uses a colour literal';
                    }
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'A focus ring must resolve to ' . self::PREFIX . 'focus-ring (or currentcolor on a branded '
                . 'island), never to the brand or a literal: ' . implode('; ', $offenders)
        );
    }

    /* --------------------------------------------------------------------------------------- */
    /* T11-T13 - the admin-configured colours and their islands.                                 */
    /* --------------------------------------------------------------------------------------- */

    /**
     * The mode layer never declares an admin-configured colour, in either mode.
     *
     * The admin's colours are instance data, not design tokens: the stylesheet reads them and must
     * never own them, or a site's chosen brand colour stops being its colour the moment the page
     * goes dark. The final assertion is the anti-vacuity control - a ban over names nothing
     * mentions would guard nothing at all.
     *
     * Mutations that must redden it: declare --dimension-customtextcolor in the dark block; declare
     * any of these names anywhere in the stylesheet.
     *
     * @return void
     */
    public function test_admin_colours_are_never_declared_by_the_mode_layer(): void {
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                $ismode = str_contains($rule['selector'], colour_mode::HOST_ATTRIBUTE)
                    || str_contains($rule['selector'], colour_mode::MEDIA_OPTIN_ATTRIBUTE);
                foreach (array_keys($this->declarations($rule['body'])) as $property) {
                    if (!in_array($property, self::ADMIN_COLOUR_NAMES, true)) {
                        continue;
                    }
                    $offenders[] = $rule['file'] . ':' . $rule['line'] . ' declares ' . $property
                        . ($ismode ? ' inside a mode rule' : '');
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'These are admin instance data carried in on the element; the stylesheet reads them and never '
                . 'declares them: ' . implode('; ', $offenders)
        );
        $referenced = [];
        foreach ($this->stylesheets() as $path) {
            $css = $this->uncommented($path);
            foreach (['--dimension-custombgcolor', '--dimension-customtextcolor'] as $name) {
                if (str_contains($css, 'var(' . $name)) {
                    $referenced[] = $name;
                }
            }
        }
        $this->assertSame(
            ['--dimension-custombgcolor', '--dimension-customtextcolor'],
            array_values(array_unique($referenced)),
            'The stylesheet must still READ the admin transport properties, or this ban is guarding names '
                . 'nothing uses.'
        );
    }

    /**
     * The admin colour transport survives from the template to the stylesheet.
     *
     * The hero emits the admin's colours as custom properties on the element it paints, and
     * styles.css reads them from there. Nothing else in the pipeline can see that chain: phpcs does
     * not read Mustache, the mustache lint validates markup rather than cross-file cascade, and
     * stylelint never opens a template. This plugin's hero text colour was inert for exactly that
     * reason - read five times in the stylesheet, written nowhere.
     *
     * The second half pins the {{^hasbgimage}} guard on the background. Both flags are a real,
     * designed-for simultaneous state, and the wrapper already handles it with an overlay; without
     * the guard an opaque inline background-color paints over the admin's photograph, and the
     * mitigation rule in styles.css cannot undo it, because a class selector never outranks an
     * inline style and !important is banned fleet-wide.
     *
     * Mutations that must redden it: remove the custom property from the hero's style attribute;
     * remove the {{^hasbgimage}} guard.
     *
     * @return void
     */
    public function test_admin_colour_transport_is_intact(): void {
        /* The wrapper's own bgcolor section carries the photo overlay rather than the fill, which is
           why the background section accepts either transport and why both are then required to
           appear somewhere: one line alone could satisfy the per-line rule and still lose a colour. */
        $expected = [
            'hasbgcolor' => ['--dimension-custombgcolor', '--hero-overlay-color'],
            'hastextcolor' => ['--dimension-customtextcolor'],
        ];
        $offenders = [];
        $sites = 0;
        $emitted = [];
        foreach (glob($this->plugin_root() . '/templates/*.mustache') ?: [] as $path) {
            foreach (file($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($expected as $section => $properties) {
                    if (!str_contains($line, '{{#' . $section . '}}')) {
                        continue;
                    }
                    $sites++;
                    $carries = false;
                    foreach ($properties as $property) {
                        if (preg_match('/\{\{#' . $section . '\}\}\s*' . preg_quote($property, '/') . '\s*:/', $line)) {
                            $carries = true;
                            $emitted[$property] = true;
                        }
                    }
                    if (!$carries) {
                        $offenders[] = basename($path) . ':' . ($number + 1) . ' opens {{#' . $section
                            . '}} without emitting ' . implode(' or ', $properties);
                    }
                }
            }
        }
        foreach (['--dimension-custombgcolor', '--dimension-customtextcolor', '--hero-overlay-color'] as $property) {
            if (!isset($emitted[$property])) {
                $offenders[] = 'no template emits ' . $property . ', so the stylesheet reads a property '
                    . 'nothing ever sets';
            }
        }
        $this->assertSame(
            3,
            $sites,
            'The hero carries the admin colour on two elements - the wrapper\'s overlay and the fill - so '
                . 'three transport sites are expected; found ' . $sites . '. A missing one means the hero '
                . 'stopped carrying the admin\'s colour.'
        );
        $hero = file_get_contents($this->plugin_root() . '/templates/hero_header.mustache');
        if (!preg_match('/\{\{\^hasbgimage\}\}\s*background-color:/', $hero)) {
            $offenders[] = 'hero_header.mustache no longer guards its inline background-color with '
                . '{{^hasbgimage}}, so an opaque colour paints over the hero photograph';
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'An admin colour section that emits no custom property is a colour the stylesheet can never '
                . 'read, and an unguarded inline background is one no stylesheet can undo: '
                . implode('; ', $offenders)
        );
    }

    /**
     * Inside a branded island, nothing reads a mode token.
     *
     * An island is a surface painted with a colour the admin chose. Inside it "adapt" means relative
     * to that colour, not relative to the page, so a mode token there is measuring against the wrong
     * ground - and the island does not go dark when the page does, correctly, because the admin's
     * colour did not change.
     *
     * Mutation that must redden it: paint an island with an ink or surface token.
     *
     * @return void
     */
    public function test_branded_islands_use_no_mode_token(): void {
        $modetokens = [];
        $roles = ['surface', 'surface-alt', 'surface-inset', 'ink', 'ink-muted', 'ink-faint', 'ink-strong', 'line'];
        foreach ($roles as $role) {
            $modetokens[] = self::PREFIX . $role;
        }
        $offenders = [];
        $reached = 0;
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                $onisland = false;
                foreach (self::ISLAND_ROOTS as $island) {
                    if (str_contains($rule['selector'], $island)) {
                        $onisland = true;
                    }
                }
                if (!$onisland) {
                    continue;
                }
                $reached++;
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    foreach ($modetokens as $token) {
                        if (preg_match('/' . preg_quote($token, '/') . '(?![-\w])/', $value)) {
                            $offenders[] = $rule['file'] . ':' . $rule['line'] . ' ' . $property
                                . ' reads ' . $token;
                        }
                    }
                }
            }
        }
        $this->assertGreaterThan(
            0,
            $reached,
            'No rule matched a documented island root, so this ban is scanning nothing; check ISLAND_ROOTS '
                . 'against the selectors that actually paint an admin colour.'
        );
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'A branded island is measured against the admin\'s own colour, never against the page, so no '
                . 'mode token may appear inside one: ' . implode('; ', $offenders)
        );
    }

    /* --------------------------------------------------------------------------------------- */
    /* T14-T15, T17 - the host signal, the faint ink and the token reads.                        */
    /* --------------------------------------------------------------------------------------- */

    /**
     * The plugin never writes the host's colour-mode signal.
     *
     * Whether the page is dark is not server-knowable - core's own answer needs a stored preference
     * plus a client-side matchMedia resolution - and a wrong guess is precisely the defect this
     * whole design exists to prevent. The attribute is the HOST's to write; the plugin only reads
     * it, from CSS. Only Behat may name it, because a scenario is exercising the host's side of the
     * contract rather than the plugin's.
     *
     * Mutations that must redden it: setAttribute the attribute in an AMD module; move the Behat
     * step's script body into one.
     *
     * @return void
     */
    public function test_plugin_never_writes_the_host_signal(): void {
        $offenders = [];
        foreach ($this->source_files() as $path) {
            if (basename($path) === 'colour_mode.php') {
                /* The constant declaration is the contract's own dictionary, not a writer. */
                continue;
            }
            if (str_contains($path, '/tests/behat/')) {
                continue;
            }
            $relative = str_replace($this->plugin_root() . '/', '', $path);
            foreach (file($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                if (str_contains($line, colour_mode::HOST_ATTRIBUTE)) {
                    $offenders[] = $relative . ':' . ($number + 1);
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            colour_mode::HOST_ATTRIBUTE . ' belongs to the host page. The plugin reads it from CSS and must '
                . 'never write it, because whether the page is dark is not knowable server-side: '
                . implode(', ', $offenders)
        );
    }

    /**
     * ink-faint is the colour of an inactive control, and of nothing else.
     *
     * It measures 2.70:1 on surface-inset and 3.04:1 on surface-alt in LIGHT mode. WCAG 1.4.3's
     * incidental exception covers text in an inactive component; it covers nothing else, and it
     * covers no non-text use at all - a border, an icon fill or a background drawn in it has no
     * exception to stand on.
     *
     * Mutations that must redden it: use it as a border colour; use it as the colour of an ordinary
     * caption.
     *
     * @return void
     */
    public function test_ink_faint_is_only_inactive_text(): void {
        $token = self::PREFIX . 'ink-faint';
        $inactive = [':disabled', '[aria-disabled', '::placeholder', ':read-only', '.disabled'];
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    if (!preg_match('/' . preg_quote($token, '/') . '(?![-\w])/', $value)) {
                        continue;
                    }
                    $where = $rule['file'] . ':' . $rule['line'];
                    if ($property !== 'color') {
                        $offenders[] = $where . ' uses it on ' . $property . ', which has no incidental exception';
                        continue;
                    }
                    $isinactive = false;
                    foreach ($inactive as $marker) {
                        if (str_contains($rule['selector'], $marker)) {
                            $isinactive = true;
                        }
                    }
                    if (!$isinactive) {
                        $offenders[] = $where . ' paints active text (' . $rule['selector'] . ')';
                    }
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            $token . ' is 2.70:1 on the inset surface in light mode and is legitimate only as the text of '
                . 'an inactive control or a placeholder: ' . implode('; ', $offenders)
        );
    }

    /**
     * Every token read names a token the contract declares, or a named runtime property.
     *
     * An unresolved var() does not fall back to anything: the whole declaration is invalid at
     * computed-value time, so a mistyped token name is not a wrong colour, it is no colour. That
     * failure is silent in every gate the pipeline has, and it is what makes a typo the real hazard
     * rather than a missing per-site literal fallback.
     *
     * Two assertions. The first is the typo net. The second keeps the escape hatch honest: a
     * property that is read but declared nowhere in the stylesheet has to be one of the three the
     * design says is set on the element at runtime, so the list cannot quietly grow a fourth
     * pseudo-token that no test measures.
     *
     * Mutation that must redden it: misspell a token name at any consumption site.
     *
     * @return void
     */
    public function test_every_token_read_is_declared(): void {
        $declared = array_keys($this->token_block());
        $local = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                foreach (array_keys($this->declarations($rule['body'])) as $property) {
                    if (str_starts_with($property, self::PREFIX)) {
                        $local[] = $property;
                    }
                }
            }
        }
        $known = array_merge($declared, $local, self::INLINE_DECLARED);
        $offenders = [];
        $undeclared = [];
        $paths = $this->stylesheets();
        foreach (glob($this->plugin_root() . '/amd/src/{,*/}*.js', GLOB_BRACE) ?: [] as $path) {
            $paths[] = $path;
        }
        foreach ($paths as $path) {
            $text = str_ends_with($path, '.css') ? $this->uncommented($path) : file_get_contents($path);
            if (!preg_match_all('/var\(\s*(' . preg_quote(self::PREFIX, '/') . '[a-z0-9-]+)/i', $text, $matches)) {
                continue;
            }
            foreach (array_unique($matches[1]) as $name) {
                if (!in_array($name, $known, true)) {
                    $offenders[] = basename($path) . ' reads ' . $name;
                }
                if (!in_array($name, $declared, true) && !in_array($name, $local, true)) {
                    $undeclared[$name] = true;
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'An undeclared token is not a wrong colour, it is no colour: the declaration is invalid at '
                . 'computed-value time and the property goes unset: ' . implode('; ', $offenders)
        );
        $undeclared = array_keys($undeclared);
        sort($undeclared);
        $expected = self::INLINE_DECLARED;
        sort($expected);
        $this->assertSame(
            $expected,
            $undeclared,
            'Exactly these properties are read by the stylesheet and set on the element instead of in it, '
                . 'and the list may not grow without a reason recorded beside it.'
        );
    }

    /* --------------------------------------------------------------------------------------- */
    /* The two markup rules no stylesheet scan can reach.                                        */
    /* --------------------------------------------------------------------------------------- */

    /**
     * The favourite state must be carried by the glyph, not only by the colour.
     *
     * Every file that touches the favourite-star hook either renders the star or updates it after a
     * click, so every one of them has to know about both glyphs. Colouring a single fa-star is a
     * WCAG 1.4.1 failure and was the one this plugin had.
     *
     * @return void
     */
    public function test_favourite_state_is_not_carried_by_colour_alone(): void {
        $root = $this->plugin_root();
        $candidates = array_merge(
            glob($root . '/templates/*.mustache') ?: [],
            glob($root . '/amd/src/*.js') ?: []
        );
        $touching = [];
        $offenders = [];
        foreach ($candidates as $path) {
            $contents = file_get_contents($path);
            if (!str_contains($contents, 'data-fav-star')) {
                continue;
            }
            $touching[] = basename($path);
            if (!preg_match('/fa-star-o\b/', $contents)) {
                $offenders[] = basename($path) . ' names fa-star but never fa-star-o';
            }
        }
        $this->assertNotEmpty(
            $touching,
            'No file references the favourite-star hook any more, so this test now proves nothing; point it '
                . 'at the hook the star actually uses.'
        );
        $this->assertSame(
            [],
            $offenders,
            'The favourite star must swap fa-star for fa-star-o, in the template that renders it and in the '
                . 'module that toggles it, so the state is not carried by colour alone: '
                . implode('; ', $offenders)
        );
    }

    /**
     * An icon-only control must carry an accessible name.
     *
     * A title attribute is not enough on its own: it is exposed inconsistently across browser and
     * screen-reader pairings and never appears on touch. Elements whose content is only an
     * aria-hidden glyph therefore need aria-label or aria-labelledby.
     *
     * @return void
     */
    public function test_icon_only_controls_are_named(): void {
        $offenders = [];
        foreach (glob($this->plugin_root() . '/templates/{,*/}*.mustache', GLOB_BRACE) ?: [] as $path) {
            /* Blank the docblock comment but keep its newlines, so reported lines stay true. */
            $markup = preg_replace_callback('/\{\{!.*?\}\}/s', static function (array $m): string {
                return str_repeat("\n", substr_count($m[0], "\n"));
            }, file_get_contents($path));
            if (!preg_match_all('~<(button|a)\b[^>]*>(.*?)</\1>~s', $markup, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as $index => $match) {
                [$element, $offset] = $match;
                $inner = preg_replace('/<[^>]+>/', '', $matches[2][$index][0]);
                /* A Mustache tag stands for text the server supplies, so it counts as content. */
                $inner = trim(preg_replace('/\{\{[^}]*\}\}/', 'x', $inner));
                if ($inner !== '' || str_contains($element, 'aria-label')) {
                    continue;
                }
                $line = substr_count(substr($markup, 0, $offset), "\n") + 1;
                $offenders[] = basename($path) . ':' . $line;
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'These controls render only an aria-hidden icon and have no aria-label, so they reach a screen '
                . 'reader with no name: ' . implode(', ', $offenders)
        );
    }

    /**
     * Whether a line is prose rather than markup.
     *
     * @param string $line One raw source line.
     * @return bool True when the line opens with a PHP, JS or Mustache comment marker.
     */
    private function is_comment_line(string $line): bool {
        $trimmed = ltrim($line);

        return $trimmed === ''
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '/*')
            || str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '{{!');
    }
}
