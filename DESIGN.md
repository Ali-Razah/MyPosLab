---
name: MyPosLab
description: Sadu Light, the default daylight theme of a UAE point of sale. Warm-white ground, white work surfaces, charcoal ink, one indigo for the next action, one woven band.
colors:
  sadu-ink: "#23262d"
  sadu-indigo: "#4f46e5"
  sadu-wool: "#c9b48b"
  primary: "#6366f1"
  secondary: "#4f46e5"
  tertiary: "#4338ca"
  selection-tint: "#eef0ff"
  typography: "#1b1e23"
  fontcolor: "#2a2e35"
  fontcolor-hard: "#464b53"
  fontcolor-soft: "#5d626b"
  input-disabled: "#a3a7ae"
  surface: "#f5f4f1"
  surface-hard: "#fbfaf8"
  surface-soft: "#ecebe6"
  box-background: "#ffffff"
  table-th: "#f7f6f3"
  aside-menu-background-hover: "#f3f2ef"
  box-edge: "#e6e3dd"
  input-edge: "#d8d5ce"
  scroll-thumb: "#cfcbc3"
  success-primary: "#dcf5e6"
  success-secondary: "#15803d"
  success-tertiary: "#166534"
  warning-primary: "#fdf0cf"
  warning-secondary: "#b45309"
  warning-tertiary: "#92400e"
  error-primary: "#fde4e3"
  error-secondary: "#d92d2a"
  error-tertiary: "#b42320"
  info-primary: "#e0ebff"
  info-secondary: "#2563eb"
  info-tertiary: "#1d4ed8"
typography:
  display:
    fontFamily: "Jost, sans-serif"
    fontSize: "1.875rem"
    fontWeight: 700
    lineHeight: "2.25rem"
    letterSpacing: "-0.02em"
    fontFeature: "\"tnum\" 1"
  headline:
    fontFamily: "Jost, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: "2rem"
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Jost, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: "1.75rem"
  body:
    fontFamily: "Jost, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: "1.5rem"
    fontFeature: "\"tnum\" 1"
  body-dense:
    fontFamily: "Jost, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: "1.25rem"
    fontFeature: "\"tnum\" 1"
  description:
    fontFamily: "Jost, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 400
    lineHeight: "1.25rem"
  label:
    fontFamily: "Jost, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: "1rem"
    letterSpacing: "0.025em"
rounded:
  band: "1px"
  sm: "4px"
  md: "6px"
  lg: "8px"
  full: "9999px"
spacing:
  grid: "8px"
  band: "6px"
  inset-sm: "8px"
  inset-md: "12px"
  inset-lg: "16px"
components:
  button-primary:
    backgroundColor: "{colors.secondary}"
    textColor: "{colors.box-background}"
    rounded: "{rounded.sm}"
    padding: "8px 12px"
    typography: "{typography.body}"
  button-primary-hover:
    backgroundColor: "{colors.tertiary}"
    textColor: "{colors.box-background}"
  button-default:
    backgroundColor: "{colors.box-background}"
    textColor: "{colors.fontcolor}"
    rounded: "{rounded.sm}"
    padding: "8px 12px"
  button-default-hover:
    backgroundColor: "{colors.aside-menu-background-hover}"
  button-danger:
    backgroundColor: "{colors.error-secondary}"
    textColor: "{colors.box-background}"
    rounded: "{rounded.sm}"
    padding: "8px 12px"
  button-danger-hover:
    backgroundColor: "{colors.error-tertiary}"
  pos-pay-button:
    backgroundColor: "{colors.secondary}"
    textColor: "{colors.box-background}"
    typography: "{typography.headline}"
  input:
    backgroundColor: "{colors.box-background}"
    textColor: "{colors.typography}"
    rounded: "{rounded.md}"
  panel:
    backgroundColor: "{colors.box-background}"
    textColor: "{colors.fontcolor}"
    rounded: "{rounded.lg}"
  nav-item-active:
    backgroundColor: "{colors.selection-tint}"
    textColor: "{colors.tertiary}"
    rounded: "{rounded.md}"
  nav-item-hover:
    backgroundColor: "{colors.aside-menu-background-hover}"
    textColor: "{colors.typography}"
  status-badge:
    backgroundColor: "{colors.surface-hard}"
    textColor: "{colors.fontcolor}"
    rounded: "{rounded.sm}"
    padding: "1px 8px"
    typography: "{typography.label}"
  status-badge-paid:
    backgroundColor: "{colors.success-primary}"
    textColor: "{colors.success-tertiary}"
  status-badge-attention:
    backgroundColor: "{colors.warning-primary}"
    textColor: "{colors.warning-tertiary}"
  status-badge-void:
    backgroundColor: "{colors.error-primary}"
    textColor: "{colors.error-tertiary}"
  product-tile:
    backgroundColor: "{colors.box-background}"
    textColor: "{colors.typography}"
    rounded: "{rounded.md}"
    padding: "6px 8px"
---

# Design System: MyPosLab

## Overview

**Creative North Star: "The Woven Counter"**

Sadu Light is a calm daylight tool for a shop counter. Its structure comes from the discipline of Emirati Al Sadu weaving: bands, a steady rhythm, and one patterned edge. It takes the place of the category's habits, the black sidebar and the candy-coloured buttons. The ground is warm white, never cream-yellow. Work happens on white panels with hairline warm-grey edges. Ink is charcoal. One indigo, taken from the logo, marks the single next action and the current selection, and nothing else is filled with colour.

Density is that of an operating tool: compact tables, 8px gaps in the product grid, and figures set in tabular numerals so money lines up. On the POS the cart, the total and Pay must be readable at a glance. The total is the largest figure on the screen and Pay is the only filled button. The back office reads as quiet white panels on the warm ground, and the active menu item is the only place where the band and indigo meet in the shell.

The only ornament is the Sadu band, a 6px woven sawtooth selvage of indigo and wool triangles over charcoal. It appears under the logo, across the top of the POS total row, along the top edge of the sign-in card, and as a short vertical mark on the active menu item. The owner has ruled out a dark interface: the default experience is light. The `dark` and `phosphor` themes still exist as alternate token sets but are not part of this world.

**Key Characteristics:**
- Warm-white ground (`surface`), white panels (`box-background`), hairline warm-grey edges (`box-edge`).
- Charcoal ink in three steps; no pure black.
- One accent: indigo `secondary` for the next action, selection, focus and caret.
- State colours appear as small stamp badges and text, never as floods.
- Tabular numerals on the whole body; money right-aligned and never wrapped.
- One signature: the 6px Sadu band, used in four fixed places.
- Jost as the single typeface; weight and size carry hierarchy.

## Colors

Warm neutrals with one indigo voice, and muted state colours that stay small.

### Primary
- **Sadu Indigo** (`secondary`): the next action. Used for Pay, Save, Sign In, the payment submit, selected switches and checkboxes, the cart count, focus outlines, input focus rings and the text caret. It is the same hue as the band's indigo triangles (`sadu-indigo`).
- **Deep Indigo** (`tertiary`): the hover and pressed state of indigo fills, and the text colour of the active menu item and active submenu link.
- **Logo Indigo** (`primary`): the lighter base accent. In this world it is used only by the loader spinner and inherited core accents. Filled buttons use `secondary`, not `primary`.
- **Selection Tint** (`selection-tint`): the faint indigo wash behind the active menu item, the selected floating-menu row and hovered options.

### Secondary
- **Sadu Wool** (`sadu-wool`): the warm sand triangles of the band. It is never used outside the band.
- **Sadu Ink** (`sadu-ink`): the band's charcoal ground. It is also the fill of the notification counter, so indigo stays reserved.

### Neutral
- **Warm White Ground** (`surface`): the page ground behind the dashboard, the POS and the sign-in screen.
- **Paper White** (`box-background`): panels, cards, inputs, buttons, product tiles and the sidebar.
- **Near-White** (`surface-hard`): row hover in tables and the cart, the pinned-products strip, and the neutral status badge.
- **Linen** (`surface-soft`): disabled buttons and quiet label chips.
- **Header Linen** (`table-th`): table and cart header rows.
- **Hover Linen** (`aside-menu-background-hover`): hover state for menu items, quiet buttons and numpad keys.
- **Hairline** (`box-edge`): every panel, tile and divider edge.
- **Field Edge** (`input-edge`): input, select and quiet-button borders, one step darker than the hairline.
- **Headline Ink** (`typography`): headings, figures, the POS total and input text.
- **Body Ink** (`fontcolor`): default text.
- **Muted Ink** (`fontcolor-soft`): descriptions, table header labels, menu icons and submenu links. **Firm Ink** (`fontcolor-hard`) is the step between the two.
- **Disabled Grey** (`input-disabled`): text on disabled controls.

### State
Every state hue has three steps. `-primary` is a soft tint for badge grounds, `-secondary` is a deep tone that carries white text at AA, and `-tertiary` is the text colour on its own tint.
- **Paid Green** (`success-*`): paid badges and paid amounts as text.
- **Attention Amber** (`warning-*`): unpaid and partial badges, layaway, owed amounts as text.
- **Void Red** (`error-*`): the void icon, Reset and Void text, the destructive button, error labels and the close-button hover.
- **Info Blue** (`info-*`): informational buttons (blue text on white) and notices.

### Named Rules
**The One Voice Rule.** Indigo fills only the single next action on a screen (Pay, Save, Sign In, Submit) and marks selection. Everything else is white with a hairline edge.

**The Stamp Rule.** State colours appear as small badges (tint ground, tertiary text) or as coloured text. A paid row is not tinted green, and a void row is not filled red.

**The Cyan Stays Home Rule.** The cyan in the logo gradient appears only in the logo files (`public/svg/myposlab-*.svg`). It is not a UI colour.

## Typography

**Display Font:** Jost (with sans-serif)
**Body Font:** Jost (with sans-serif)

**Character:** A single geometric sans for everything. Jost's round, open forms read as calm at counter distance, and its tabular figures keep money columns aligned. Hierarchy comes from weight (400, 500, 600, 700) and size, not from a second family.

### Hierarchy
- **Display** (700, 1.875rem, 2.25rem line, -0.02em): the POS TOTAL figure, the largest number on the till. The customer display's clock scales the same voice up.
- **Headline** (600, 1.5rem, 2rem line, tight tracking): page titles in the dashboard header, and KPI figures on dashboard cards (700, -0.01em). The Pay label sits close to this size (1.375rem).
- **Title** (600, 1.125rem): panel and widget headings, and product names in the cart.
- **Body** (400, 1rem, 1.5rem line): default text, menu items (500), buttons (600).
- **Body Dense** (400, 0.875rem, 1.25rem line): table cells and product-tile names (500, clamped to two lines).
- **Description** (400, 0.8125rem, 1.25rem line, `fontcolor-soft`): help text under fields. It is the smallest running text, and it is enforced at that size so it stays readable.
- **Label** (600, 0.75rem, uppercase, 0.025em tracking, `fontcolor-soft`): table and cart column headers only. Status badges use the same size and weight without uppercase.

### Named Rules
**The Ledger Figures Rule.** `font-variant-numeric: tabular-nums` is set on `body`. Money and quantity columns are right-aligned (`text-align: end`) and table cells do not wrap.

**The Largest Number Rule.** On the POS, TOTAL is the largest figure on the screen. No other figure or label may outsize it.

## Layout

The app has two frames. The **dashboard shell** is a white sidebar with a hairline right edge (logo, Sadu band, grouped menu) beside a warm-white content area. A light top bar there holds round tool buttons (menu toggle, notifications) and the user pill. Content sits in white panels in a responsive card grid.

The **POS** puts a cart panel on the left (toolbox tabs, line items, summary, total row, a four-cell action bar: Pay, Hold, Discount, Void) and a product grid on the right. A row of quiet tool buttons runs across the top. Product tiles sit on a strict 8px rhythm (8px gap, 8px padding), with the photo above a solid name band in normal flow. On phones the POS becomes Cart and Products tabs and the grid drops to two columns.

Spacing follows Tailwind's 4px scale, with 8px as the governing grid step. Panels use 12 to 16px insets. On phones, KPI cards stack the label above the figure, use 12px vertical padding and size to their content. Bidirectional layout uses logical properties (`inset-inline-start`, `padding-inline-start`, `text-align: end`) so RTL mirrors without extra rules. Floating notices sit 28px above the bottom edge to clear the footer credit line.

## Elevation & Depth

Depth is mostly tonal: white panels on a warm-white ground, separated by hairline edges. Shadows are kept very small, warm and charcoal-tinted (`rgb(35 38 45)`), never grey-blue. Nothing floats high except popups over the dimmed overlay.

### Shadow Vocabulary
- **Hairline lift** (`box-shadow: 0 1px 2px rgb(35 38 45 / .06)`): buttons, the round header tools and the user pill.
- **Panel** (`box-shadow: 0 1px 2px rgb(35 38 45 / .05), 0 4px 12px -6px rgb(35 38 45 / .08)`): boxes, CRUD tables, tab bodies and KPI cards.
- **Work surface** (`box-shadow: 0 1px 2px rgb(35 38 45 / .05), 0 6px 16px -8px rgb(35 38 45 / .10)`): the POS cart table and the product grid container.
- **Focus ring** (`box-shadow: 0 0 0 3px rgb(79 70 229 / .14)` with the border shifted to `secondary`): input groups with focus inside.
- **Tile selection** (`box-shadow: inset 0 0 0 2px var(--color-secondary)`): hovered product tile.

### Named Rules
**The Paper Rule.** Panels are paper on a counter, not cards in the air. Use the three shadows above; never stack a box inside a box. Separate inner regions with a hairline instead.

## Shapes

The corners are gently softened. Panels, the cart table, the grid container and the sign-in card use 8px. Menu items, inputs and product tiles use 6px. Buttons and stamp badges use 4px. Round header tools, the user pill and pagination dots are fully round. The Sadu band itself is a crisp 24×6px repeating SVG tile (charcoal ground, indigo triangle pointing up, wool triangle pointing down) with a 1px corner where it ends in the sidebar. On the active menu item it rotates to a 6×24px vertical mark. Edges are 1px hairlines throughout. There are no thick borders and no offset shadows.

## Components

### Buttons
Quiet by default, with a single indigo voice.
- **Shape:** slightly rounded (4px), 8px × 12px padding, weight 600.
- **Primary (`primary` / `success` types):** indigo `secondary` fill and border with white text. A count label inside inverts to white with indigo text.
- **Hover / Focus:** fills darken to `tertiary`, and quiet buttons move to the hover linen. Transitions run 150ms ease-out on background, border and colour. Focus is a 2px indigo outline with a 2px offset, and it is never removed.
- **Default:** white with a `input-edge` hairline and body ink, plus the hairline-lift shadow.
- **Info:** white with info-blue text. **Warning:** amber tint with deep amber text.
- **Danger (`error` type):** red `error-secondary` fill with white text, darkening on hover. It is used for destructive confirms only. In toolbars (POS Reset, Void) danger is red text or a red icon on a quiet white button, and it tints red only on hover.
- **Disabled:** linen fill, disabled-grey text, no shadow.

### Chips / Status Badges
- **Style:** inline stamp, 1px × 8px padding, 4px radius, 12px weight-600 text.
- **States:** the neutral badge is near-white with a field-edge hairline. Paid is a green tint with deep green text, unpaid or partial is an amber tint with deep amber text, and void is a red tint with deep red text. Tinted badges drop the border.

### Cards / Containers
- **Corner Style:** 8px.
- **Background:** paper white on the warm-white ground.
- **Shadow Strategy:** the Panel shadow (see Elevation & Depth).
- **Border:** 1px `box-edge`.
- **Internal Padding:** 12 to 16px.
- **KPI cards:** core gradient fills are removed. Each card is a white panel with a body-ink label, a 700-weight headline-ink figure and a muted "Today" line.

### Inputs / Fields
- **Style:** white field, 1px `input-edge` border, 6px radius, headline-ink text, a label above and a 13px description below.
- **Focus:** border shifts to indigo with a 3px indigo halo at 14% opacity, over 150ms.
- **Error / Disabled:** the label turns `error-tertiary` and the border `error-primary`. Disabled fields go grey.
- **Switch / Checkbox:** the selected segment or box is filled indigo with white text, and the unselected segment is white.

### Navigation
- **Sidebar:** white, with a hairline right edge. The logo sits above a full-width Sadu band inset 16px on each side.
- **Items:** 6px radius with 8px side margins, weight 500, body ink, muted icons. Hover moves to the hover linen and headline ink.
- **Active item:** selection-tint ground, deep-indigo 600 text, an indigo icon, and a 6×24px vertical Sadu mark on the leading edge (it mirrors in RTL). The active submenu link is deep-indigo 600 text with no ground.
- **Header tools:** round white buttons with a hairline edge and the hairline-lift shadow. The notification counter is a charcoal (`sadu-ink`) disc, not indigo.

### Tables
- **Header:** `table-th` linen ground, 12px uppercase muted labels, hairline rules.
- **Rows:** hover moves to near-white. Money columns are right-aligned, cells do not wrap and the table scrolls sideways instead. Status cells render as stamp badges.

### Sadu Band (signature)
A 6px woven sawtooth selvage drawn as an inline SVG data URI (24×6 repeat: `#23262d` ground, `#4f46e5` triangle up, `#c9b48b` triangle down). It has exactly four placements: under the sidebar logo, across the top edge of the POS total row, across the top of the sign-in card (`::before`), and as the vertical mark on the active menu item. The reusable class is `.mpl-sadu` (block, 6px tall).

### POS Total and Action Bar (signature)
The total row is white with the Sadu band along its top edge. Its label and figure use headline ink, and the figure is set at display size. Below it, four equal cells: **Pay** filled indigo (1.375rem label, white icon), then **Hold**, **Discount** and **Void** as quiet white cells divided by inset hairlines (weight 500, 1rem label, 1.25rem icon). Void keeps body-ink text with only its icon in red. These cells meet the 44px minimum touch target.

### Product Tile
A white tile with a 1px hairline and 6px radius. The photo is cropped to cover and sits above a solid white name band (minimum 3rem tall, hairline top rule, 6px × 8px padding, name clamped to two lines). Hover adds a 2px inset indigo ring. Tiles sit on the 8px grid.

## Do's and Don'ts

### Do:
- **Do** fill exactly one control per screen with indigo `secondary`: the next action. Selection states (active menu, selected switch, checked box, cart count) may use indigo too.
- **Do** keep panels white (`box-background`) with a 1px `box-edge` hairline and the Panel shadow, on the warm-white `surface` ground.
- **Do** show order and payment states as stamp badges: tint ground, tertiary text, 4px radius, 12px weight-600 text.
- **Do** right-align money with `text-align: end`, keep it from wrapping, and rely on the body-wide tabular numerals.
- **Do** make TOTAL the largest figure on the POS (1.875rem, 700, -0.02em).
- **Do** use logical properties (`inset-inline-start`, `padding-inline-start`) so the shell mirrors in Arabic.
- **Do** use only the existing semantic tokens (`bg-box-background`, `text-fontcolor-soft`, `border-input-edge`…) in modules, so every theme keeps working.

### Don't:
- **Don't** build a dark or black sidebar, or any dark-ground default screen. The owner has ruled it out.
- **Don't** fill rows, cards or whole buttons with green, amber or red to signal state. State is a badge or coloured text.
- **Don't** use the logo's cyan, or a gradient fill, anywhere in the UI.
- **Don't** add the Sadu band to new places or invent other ornaments. It has four placements.
- **Don't** give the notification count, info badges or secondary buttons an indigo fill. Indigo is reserved.
- **Don't** add a second typeface; Jost carries every role.
- **Don't** add small uppercase kicker labels above headings. The upstream marketplace widget's kicker is hidden for this reason.
- **Don't** nest a bordered box inside a bordered panel. Use a hairline rule or spacing.
