---
paths:
  - 'app/**'
---

# App

## Reassign CarbonImmutable date math
Dates are CarbonImmutable app-wide (Date::use in AppServiceProvider). Mutating calls like subDay/addDay/startOfDay return new instances — always reassign ($d = $d->subDay()), never rely on in-place mutation, or loops never terminate.
