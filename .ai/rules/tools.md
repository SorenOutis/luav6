---
paths:
  - 'app/Ai/Tools/**'
---

# Tools

## SQLite integer division and JSON whole-float traps in AI tool output
Percent/average SQL in tools must force float math: use `AVG(score * 100.0 / NULLIF(max_score, 0))` — plain `score / max_score` truncates to integer division (0) on SQLite, silently breaking averages.
Tool results are json_encode'd: PHP renders whole floats as ints ("60" not "60.0"), so tests asserting 60.0 (e.g. average_percent) must cast `(float) $data[...]` first.
Lesson.course_module_id is NOT NULL — test fixtures creating lessons need a real module.
