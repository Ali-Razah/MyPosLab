---
version: 1
slug: "resources-css-light-css"
primary_target: "resources/css/light.css"
related_targets: ["resources/views/layout/dashboard.blade.php","resources/views/pages/dashboard/orders/pos.blade.php"]
---

# Surface brief: MyPosLab app shell (dashboard, POS, auth, modules)

Scope: the whole authenticated app (dashboard shell, CRUD lists/forms, settings, popups, POS screen) plus sign-in, rendered through the `light` theme which becomes the default. Mode: Operate.

Audience/job: cashiers at UAE counters (touch and mouse equally) completing sales fast; owners/managers working the back office. English-first, RTL must work.

Constraints: no dark backgrounds (owner's explicit commitment); keep logo; standard web controls; existing Vue/Blade markup and semantic theme classes (tokens in resources/css/light/*) are the delivery mechanism; modules consume the same tokens.

## Direction contract

THESIS: A calm, daylight counter tool where structure comes from Emirati Al Sadu weaving discipline (bands, rhythm, one patterned edge) instead of the category's black sidebar and candy-coloured buttons.

OWN-WORLD: warm-white ground (#F6F5F2-ish, never cream-yellow), white work surfaces with hairline warm-grey borders, charcoal ink, one indigo (from the logo) reserved for the next action and selection, cyan only in the logo; muted state colours (green paid, amber attention, red void) used as small stamp badges and text, never as row floods; one thin woven Sadu band (indigo + charcoal + warm grey geometric triangles/diamonds) as the signature.

STORY: the cashier sees cart, total and Pay instantly; the owner sees numbers that read as trustworthy (tabular figures, aligned right) and always knows where they are in the menu.

FIRST VIEWPORT: light sidebar (white, hairline right border) with logo, grouped menu; the active item carries a short Sadu band on its leading edge and indigo text; top bar white with page title; content on warm-white ground in white panels. POS: cart panel left with a Sadu band across the top of the total bar, a large tabular TOTAL, one indigo Pay button dominant, Hold/Discount/Void as quiet outline buttons; product grid right as clean white tiles on an 8px grid.

FORM: candidate 6 of my grounded list (Al Sadu weaving), raises: Crouwel grid -> strict 8px cell grid; Ikeda -> tabular numerals for all money; ticket wallet -> order states as stamp badges; airport wayfinding -> indigo reserved for the single next action. seed:3879a08a484edb62

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
