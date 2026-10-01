---
paths:
  - 'routes/**/*.php'
---

# Routes

## Stage new files explicitly, never rely on commit -a
git commit -a never stages new (untracked) files. After adding a route plus new controller/model/migration files, always run git status and git add the new files explicitly — otherwise the route merges without its implementation and app boot fails with Invalid route action (broke main in PR #187, fixed in #188).
