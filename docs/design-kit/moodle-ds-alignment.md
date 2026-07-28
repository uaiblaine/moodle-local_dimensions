# Moodle Design System alignment (Layer 3)

Capture of the Moodle Design System (MDS) good practices and tokens and how to replicate them in
our kit, **pointing out where they diverge** from our earlier interpretation (Anthropic/CDS aesthetic).
Yardstick: **Bootstrap/Boost (Mustache) today → MDS React components when Moodle 5.3 LTS ships**.

## Sources

- `github.com/moodlehq/design-system` — tokens in `tokens/css/*.css` (Style Dictionary, ZeroHeight origin).
- Component Library `componentlibrary.moodle.com` — the Bootstrap/Boost reference for Mustache.
- `design.moodle.com` (Penpot) — **not extractable as data** (JS-only); covered by the two above.

## The MDS architecture (structural good practice #1)

A **two-layer** model, which we should mirror so the React port is a *rename*, not a redesign:

1. **Primitives** — `--mds-color-{hue}-{50..900}`, `--mds-scale-{0..1800}`, `--mds-typography-*`. Raw values.
2. **Semantics** — what the components consume. Never consume a primitive directly.

MDS semantic axes:

- `--mds-bg-surface-{default,subtle,strong}` · `--mds-text-{default,muted,subtle,emphasis,inverse}`
- `--mds-bg-interactive-{primary,secondary,danger}-{default,hover,active,disabled,default-light}` — **solid fills with states**.
- `--mds-bg-feedback-{primary,info,success,warning,danger,secondary}-{default,light,subtle}` — **status tints**.
- `--mds-border-{default,subtle,feedback-*,interactive-*}` · `--mds-focus-{default,danger}`
- `--mds-border-radius-{xs..xxl,pill}` · `--mds-spacing-{xxs..xxl}` · `--mds-stroke-weight-{sm..xxl}`
- Typography: `--mds-font-size-{headings-1..6,paragraph-default/lead/small}` · `--mds-font-weight-*` · `--mds-line-height-*`
- Composite shadows: `--mds-{color,blur,offset}-{sm,md,lg}` · icons `--mds-icons-{xxs..xxxl}`
- Colour by activity type: `assessment`=pink, `collaboration`=indigo, `communication`=orange, `file/resource`=cyan, `interactive`=red.

## Concrete values (light)

| Axis | Semantic token | Value (primitive) |
| --- | --- | --- |
| Base surface | `bg-surface-default` | `#ffffff` |
| Subtle surface | `bg-surface-subtle` | gray-100 `#f8f9fa` |
| Strong surface | `bg-surface-strong` | gray-200 `#e9ecef` |
| Default border | `border-default` | gray-300 `#dee2e6` |
| Default text | `text-default` | gray-900 `#1d2125` |
| Muted text | `text-muted` | gray-600 `#6a737b` |
| **Primary** | `bg-interactive-primary-default` | blue-500 `#0f6cbf` (hover blue-600 `#0c5699`, active blue-700 `#094173`) |
| Info | `bg-feedback-info-subtle` / `text-feedback-info` | Boost `$info` = `$cyan` cyan-600 `#008196`; tint cyan-100 `#cce6ea`, text cyan-800 `#00343c` |
| Success | `bg-feedback-success-default` | green-500 `#357a32` |
| Warning | `bg-feedback-warning-default` | yellow-500 `#f0ad4e` |
| Danger | `bg-interactive-danger-default` | red-500 `#ca3120` |
| Focus | `focus-default` | = primary blue |

Scale (`--mds-scale-*`): 100=4px, 200=6px, 300=8px, 400=12px, 500=14px, 600=16px, 700=20px,
800=24px, 1000=32px, 1200=48px, 1800=50rem (pill).

Radius: xs=4px, sm=6px, **md=8px**, lg=12px, xl=16px, xxl=32px, pill=50rem.
Stroke: sm=**1px**, md=2px, lg=3px.
Typography: **Noto Sans** / Menlo; h1=2.5rem … h6=1rem; paragraph 1rem (lead 1.25, small 0.875);
weights light 300 / regular 400 / medium 500 / semibold 600 / bold 700; heading margin=8px, paragraph=16px.
Shadows: colour sm/md/lg = black 8%/15%/17%; md ≈ `0 8px 16px rgba(0,0,0,.15)`.

**Provenance (audited 2026-07-16 against `theme/boost/scss/preset/default.scss`):** the **9 grays**
(`#f8f9fa`→`#1d2125`) and the brand bases **blue `#0f6cbf`**, **red `#ca3120`**, **yellow `#f0ad4e`**,
**green `#357a32`** match the preset **exactly**; the rem→px conversions check out (0.5rem=8px,
0.0625rem=1px, 2.5rem=40px); the feedback tints are real Moodle colours (`#fcefdc` appears literally
in `theme/boost/scss/moodle/modules.scss`). The only divergence found was the **cyan** — the kit had
`#006778`, which does not exist in Moodle; corrected to the real `$cyan` `#008196`. The spacing and
radius scales are T-shirt scales of our own (labelled as such), not Boost values.

## Mapping: MDS → Boost/Bootstrap (today) → MDS React (Moodle 5.3 LTS)

| MDS semantic | Boost/Bootstrap 5 (Mustache today) | MDS React (Moodle 5.3 LTS) |
| --- | --- | --- |
| `bg-interactive-primary-*` | `$primary` / `.btn-primary` | `--mds-bg-interactive-primary-*` |
| `bg-interactive-secondary-*` | `$secondary` / `.btn-secondary` | same |
| `bg-interactive-danger-*` | `$danger` / `.btn-outline-danger` | same |
| `bg-feedback-{info,success,warning,danger}` | `.alert-{info,success,warning,danger}`, `.badge` | `--mds-bg-feedback-*` |
| `bg-surface-{default,subtle,strong}` | `$body-bg` / `$gray-100` / `$gray-200` | `--mds-bg-surface-*` |
| `text-{default,muted}` | `$body-color` / `.text-muted` | `--mds-text-*` |
| `border-default` | `$border-color` (gray-300) | `--mds-border-default` |
| radius `md` | `$border-radius` (.375rem in Boost ≈ 6px) | `--mds-border-radius-md` |
| focus | `$focus-ring-*` / `:focus-visible` | `--mds-focus-default` |

> The MDS grays **are** the Bootstrap grays — so in Boost we can lean on the native `$gray-*`/`$primary`;
> do not invent new CSS vars in the theme. The Mustache components use Bootstrap classes (`.btn`,
> `.alert`, `.badge`, `.card`, `.nav-tabs`, `.form-*`) — see the Component Library.

## Divergences from our earlier interpretation

| Aspect | Our interpretation (CDS/Anthropic) | Moodle DS | Recommendation |
| --- | --- | --- | --- |
| Surfaces | **warm** neutrals (`#f7f6f3`, `#f0eee9`) | **cool** Bootstrap grays | adopt the Bootstrap grays |
| Primary/accent | `#185fa5` (a single accent) | blue-500 `#0f6cbf` + states | adopt the Moodle blue + hover/active |
| Info | **merged** into the accent (blue) | separate **cyan** | separate info (cyan) from primary |
| Success | `#0f6e56` (teal) | green `#357a32` | adopt the Moodle green |
| Warning / Danger | `#854f0b` / `#a32d2d` | yellow `#f0ad4e` / red `#ca3120` | adopt Moodle's |
| Border | **0.5px** hairline | **1px** (`stroke-sm`) | use 1px (0.5px was the Anthropic aesthetic) |
| Radius | **a single 8px** | xs..xxl scale + pill | adopt the scale (md=8px already matches) |
| States | tints only (`bg-accent`) | solid **default/hover/active/disabled** | add interactive fills + states |
| feedback vs interactive | **conflated** | **separate** | adopt the separation |
| Elevation | none (flat) | composite sm/md/lg shadows | add elevation tokens |
| Focus | **absent** | `focus-default`/`focus-danger` | add a focus ring (WCAG 2.2 AA) |
| Font | Anthropic Sans | **Noto Sans** | use Noto Sans / the Boost stack |
| Naming | flat (`--surface-2`) | semantic `--mds-*` (primitive→semantic) | mirror the taxonomy → React = rename |

## Good practices captured (beyond the visual)

1. **Two-layer tokens**; components consume semantics only. React migration = swap the implementation.
2. **interactive (solid + states) × feedback (tint)** — do not use one for the other.
3. **State coverage**: default/hover/active/disabled **+ focus** for everything interactive.
4. **T-shirt sizing** (xxs..xxxl) over a numeric scale for spacing/radius/icons.
5. **WCAG 2.2 AA** — the Component Library's three pillars: *links*, *colour contrast*, *keyboard access*.
   Text over a tint uses stop **800** of the same family; text over a solid fill uses white.
6. **Bootstrap/Boost is the substrate** today; the Component Library is the canonical Mustache reference.
7. **Colour by activity type** (assessment/collaboration/communication/file/interactive) — reusable
   in the framework/activity tags of `master-detail` and `MOD.LINKS`.

## Components — Boost guidance captured (Component Library)

> Penpot (design.moodle.com) is JS-only and **was not navigable**; these rules come from the Component
> Library (`componentlibrary.moodle.com`), which documents the same in Bootstrap/Boost terms — the Mustache substrate.

**Buttons** (`moodle/components/buttons`)
- Hierarchy: **a single `.btn-primary` per component/screen**; `.btn-secondary` for cancel/persistent controls;
  `.btn-danger` destructive; `.btn-outline-secondary` for filters/toggles; `.btn-subtle-*` (intermediate); `.btn-icon` icon-only.
- **Dangerous action:** style **Cancel as the primary** to encourage the safe default → reflected in `MOD.DELPLANS`.
- A specific label ("Save changes", "Delete"), never "OK/Yes". Sizes `.btn-sm`/`.btn-lg`. PHP renderer `single_button()`.

**Icons** (`moodle/components/moodle-icons`)
- **FontAwesome 6.7.2**; in Mustache use `{{#pix}} i/edit, core {{/pix}}` (mapped in `icon_system_fontawesome.php`), not a raw `<i class="fa">`.
- Decorative → `aria-hidden="true"`; meaningful → `aria-label`/`visually-hidden` text. `fa-fw` for fixed width.

**Activity icons** (`moodle/components/activityicons`) — colour by **purpose**, useful in the framework/activity tags of `master-detail`/`MOD.LINKS`:
- administration `#da58ef` · assessment `#f90086` · collaboration `#5b40ff` · communication `#eb6200` · interactivecontent `#8d3d1b` · content `#0099ad`.
- `activity_icon` class + a purpose class; vars `$activity-icon-*-bg`; `set_colourize(false)` turns it off; customisable in Boost's SCSS.

**Nav pills** = **our context selector** (`BAR-CTX`). Tokens `--mds-bg-nav-pill-{hover,pressed,selected}` = **gray-200/300/200**:
- the System/Category toggle is a **nav-pill with a grey selected state**, **not** a blue `.btn-primary`. (**Not applied:** `hierarchy-nav` depicts the shipped `btn-primary`.)
- `role="tablist"`, `.nav-link.active`, `aria-selected`; keyboard reachable.

## Platform constraints

Before any aesthetic choice, two things decide what **can be built** here: what the CI stylelint
accepts and what Moodle 4.5's Bootstrap 4 understands. Recorded once in this document instead of
rediscovered on every surface — both have already cost rework.

**The CI stylelint boundary**

CI runs **core's** config (`.stylelintrc` at the Moodle root), not the plugin's `.stylelintrc.json` —
which carries none of the rules below. Hence the impression of an invisible boundary: the local
`npx stylelint` passes and CI fails. It is reproducible, pointing stylelint at core's config
(from the Moodle root):

```sh
npx stylelint --config .stylelintrc public/local/dimensions/styles.css
```

| Rule (core `.stylelintrc`) | Rejects | Way out |
| --- | --- | --- |
| `declaration-no-important` | any `!important` — and `keyframe-declaration-no-important` closes the same door inside `@keyframes` | when a Bootstrap utility in the markup (`.d-flex`, `.d-block`, both `!important`) fights a property we need to toggle, **drop the utility from the template** and own the property in a plugin class |
| `csstree/validator` (`stylelint-csstree-validator` 3.x) | `clamp()`/`min()`/`max()` **wherever a length is expected** — the grammar is old and does not know them. Verified failing in `height`, `min-height`, `max-height`, `width`, `max-width`, `font-size`, `padding`, `margin`, `gap`, `flex-basis` → *"Invalid value"* | `calc()` **passes** (and grid `minmax()` too); in place of `clamp()`, a `height` + `min-height`/`max-height` pair |
| `time-min-milliseconds: 100` | any duration `< 100ms` | it is the **floor of the motion scale** — `--mds-motion-fast` (150ms) is already above it; do not go below 100ms chasing "snappier" |

> All three are **errors**, not warnings. And `csstree/validator` is not only about `height`: it catches
> any length-valued property — framing it as "height-like only" underestimates the reach.

**Bootstrap 4 (Moodle 4.5) × Bootstrap 5 (5.x)**

The plugin supports 4.5 → 5.2, and **4.5 is Bootstrap 4**. BS5's *classes* are bridged on 4.5
(`form-select` etc.), but the **JS data attributes are not**: BS4's data-API listens on `data-toggle`,
BS5's on `data-bs-toggle`. A component wired by markup (dropdown etc.) needs **both** side by side,
and both alignment classes (`dropdown-menu-right dropdown-menu-end`) — as in
`participants_manager.mustache` and `plans.mustache`. Same for the JS selector: match both.

| Fact (verified on `v4.5.12` × a 5.1 checkout) | Design consequence |
| --- | --- |
| 4.5 **defines no `--bs-*` modal custom property** (`--bs-modal-width`, `--bs-modal-margin`…) — its `_modal.scss` is SCSS variables only | never size a modal by a BS5 var. Use Bootstrap's own classes — `modal-xl` is **identical in 4 and 5** (800px on `lg`, 1140px on `xl`) — or give a fallback: `var(--bs-modal-margin, 1.75rem)` (= 4.5's `$modal-dialog-margin-y-sm-up`) |
| BS5 (`EventHandler.trigger`) fires **both** events, jQuery and native; BS4 fires **jQuery only** | a **jQuery** listener covers both branches; `addEventListener` covers only 5.x |
| `lib/amd/src/first.js` does `window.jQuery = $`, so BS5 **always** takes its jQuery path | binding through jQuery is not a compatibility hack: it is the path core guarantees on both |

The cost of ignoring this has already been paid: two **silent** defects on 4.5, fixed in `f84d30a`.
`context.js` matched the tabs by `data-bs-toggle` only and listened for the native event — on 4.5 the
selector matched nothing and the event never arrived, so the contextbar counter never followed the
tab, `saveNav` never ran and the saved tab's restore never fired. And the participants modal sized
itself with `--bs-modal-width`: undefined on 4.5, it shrank to `$modal-md` (**500px**) with four tabs
and grids inside. Neither breaks visibly on 5.x — they only disappear on the older branch, which is
exactly where nobody looks.

**Contrast of Moodle's accent tokens in dark (WCAG AA)**

A third boundary, of accessibility rather than build: **Moodle's own** `--text-accent` in dark is
`#3f89cc`, and on small text over a dark surface it **fails AA**. Measured: `.load` at 11px over
`#1d2125` gives **4.37:1**, and the same accent over the headers' tint (`#062b4c`) gives
**3.88:1** — below the 4.5:1 AA requires for normal text. It is not a defect of one screen nor a
choice of the kit: it is Moodle's dark token over Moodle's surface. The screens `est-competencies`,
`fwk-structures` and `pln-plans` **depict it faithfully** (as-is) and record the measurement in
the file's own comment — we do not diverge them from the real colour, or the kit would lie about
what Moodle shows. The underlying fix (Moodle's small accent passing AA) is **upstream**, not the
plugin's.

What the kit **prescribes** for **new or reworked** surfaces is another pair: in dark,
`--mds-accent-text` is `blue-200` (`#9fc4e5`), well above AA over `#1d2125` — it is what `tokens.html`,
`states.html` and `paginated-picker.html` already use. **Rule:** accent text on a new surface, in
dark, uses `blue-200`; only the as-is depiction of an element Moodle already paints with the raw
accent keeps `#3f89cc`, always with the measured caveat alongside.

## How this shows up in the kit

- `tokens.html` rewritten to the MDS model (semantic, Moodle values, states, focus, elevation, scales) — done.
- New card `states.html` (interactive states + focus) — done.
- The **8 components** migrated to the MDS tokens (Bootstrap grays, **solid blue primary**, Noto Sans, 1px,
  **info=cyan / success=green** in `cohort-assign`), with the legacy names kept as **deprecated aliases →
  `--mds-*`** for incremental migration — done. The two cards added afterwards (`toast.html`, `tooltip.html`)
  were born on the MDS tokens, so all **12** previews reference `--mds-*`.
- The **14** `screens/` draw the **shipped output** in the Moodle (Boost) palette itself: all of them
  set the same theme literals in `:root` (`#fff` / `#f8f9fa` / `#e9ecef`, `#1d2125` / `#6a737b`,
  accent `#0f6cbf`), and **13** name the palette in the `:root` comment — `pln-plans.html` carries the
  literals with no comment. The dark-accent caveat above travels with them. **Layer 3 complete.**

## Pending

- **The review and the MDS React port.** The port is scheduled for when **Moodle 5.3 LTS** ships; until
  then the substrate is Bootstrap/Boost and the mapping table above is the contract. Nothing in the kit
  blocks on it — the taxonomy was mirrored precisely so the port is a rename, not a redesign.
