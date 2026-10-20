# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- Primary: students of the BSIT program using the LSI learning platform. They browse the shop between classes to see the official department merch, check prices and availability, and decide what to buy.
- Secondary: public visitors (prospective students, alumni, merch shoppers) who land on /shop from the marketing site and need enough story and proof to trust the brand before leaving to buy.

## Product Purpose

The /shop page is the official showcase of KOAMISHIN × BSIT department merchandise. It exists to make the collection and the department's identity tangible, and to route every interested visitor to the official store at koamishin.com to complete a purchase. Success means a visitor understands what the line is, picks a piece they want, and clicks through to the store — not that they check out on this page.

## Positioning

It is the official, department-anchored merch line: KOAMISHIN gear engineered for the BSIT student body. Unlike generic online stores, the line is inseparable from the program's identity — "official BSIT merchandise" is a claim no neighboring store can truthfully copy.

## Operating Context

- The catalog is managed in the platform's Filament admin (Merch resource): name, description, price, currency, stock, variants (name + image each), button URL, active flag, and sort order.
- Prices are real and displayed in PHP (formatter supports PHP, USD, EUR, GBP, JPY).
- Every purchase action leaves the site: items link out to koamishin.com (per-item `button_url`, falling back to the main store).
- The page is public marketing chrome (WelcomeHeader / WelcomeFooter) shared with the rest of the public site, and must work in both light and dark themes.
- Existing page copy references campus pickup and nationwide fulfillment through koamishin.com.

## Capabilities and Constraints

- Catalog data is limited to what the Merch model provides: price, currency, stock count, out-of-stock flag, description, one primary image plus per-variant images, variants by name.
- There is no in-platform checkout, cart, or points redemption on /shop — by design. The page is a lookbook whose conversion is an external click.
- Stock states shown to visitors: in stock, low stock (≤5), out of stock.
- The page must keep working with an empty or partially-filled catalog (empty state exists today).
- Every visitor-facing fact (products, prices, stock, links) must come from the admin-managed catalog; nothing may be hardcoded beyond safe fallbacks.

## Brand Commitments

- KOAMISHIN is the binding brand: name, identity, palette, and techwear/streetwear voice are fixed and must be preserved.
- The line is framed as official BSIT department gear (the "KOAMISHIN × BSIT Department" collaboration is the story).
- Real product photography and real prices exist today and should be treated as the visual truth of the collection.

## Evidence on Hand

- Real product photography: `public/images/merch/techwear-hoodie.jpg`, `techwear-hoodie-black.jpg`, `techwear-hoodie-white.jpg` (Obsidian / Alabaster variants).
- Real prices (e.g. the techwear hoodie at ₱1,650) and stock counts, entry in the Merch catalog.
- The official store: https://koamishin.com/ (all fulfillment and purchase happen there).
- Absent: no testimonials, reviews, order counts, or customer stories exist. Future work must not fabricate social proof, product claims, or benchmarks.

## Product Principles

1. Students first, public welcome — the student/campus angle leads; the page still reads cleanly for a first-time visitor.
2. Lookbook, not checkout — the page's job ends at a confident click to koamishin.com; do not fake commerce affordances it cannot fulfill.
3. The department story is the hook — official BSIT merch, spec-level detail, and craft; never generic e-commerce padding.
4. Catalog is the single source of truth — products, prices, stock, and links come from the admin; the page never invents.
5. Preserve the KOAMISHIN world — the redesign replaces the current layout and styling, not the brand.
