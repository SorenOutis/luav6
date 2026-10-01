---
paths:
  - 'app/Filament/**'
---

# Filament

## Use Filament v4 action namespace
In Filament v4, table/row actions live in Filament\Actions\ (e.g. Filament\Actions\Action). Never use the v3 namespace Filament\Tables\Actions\ — the class does not exist and lazy widgets 500 on scroll while the initial page still renders, hiding the bug until production (outage fixed in PR #186).

## Filament string full columnSpan applies at lg and up only
Widget $columnSpan = 'full' only spans full width at the lg breakpoint and above (smaller screens get span 1). Since every admin dashboard widget is full-width by design, AdminDashboard::getColumns() must stay single-column below xl (default/md = 1); a multi-column md grid squeezes full-width widgets side-by-side. Covered by tests/Feature/AdminDashboardLayoutTest.php.
