# Tower Defense — Build Notes

An admin-managed Tower Defense game, fully isolated from the existing exam/XP gamification system. It has its own tables (`td_*`), own progression (`TdPlayerProgress`), and own leaderboard (`TdRun`).

## Routes

- `GET  /games/tower-defense` — level select (`Games/TowerDefense/Index.vue`)
- `GET  /games/tower-defense/play/{level:slug}` — game screen (`Games/TowerDefense/Play.vue`)
- `POST /games/tower-defense/runs` — start a run (returns `run_id` + `seed`)
- `POST /games/tower-defense/runs/{run}/finish` — submit final result (server sanity-checks + awards stars)
- `GET  /games/tower-defense/leaderboard/{level:slug}` — top 50 winning runs

## Admin (Filament — "Tower Defense" group)

- **Enemies** — HP, speed, armor, damage, bounty, color, abilities
- **Towers** — cost, DPS stats, projectile type, splash, upgrade tiers
- **Maps** — grid size, path waypoints (`[[x,y],...]`), background color
- **Difficulties** — starting gold/lives, HP/speed/gold/score multipliers
- **Levels** — compose map + difficulty + allowed towers + **nested Waves → Spawn groups** (enemy + count + interval + offset)
- **Runs & Leaderboard** — read-only runs viewer with filters
- **Playtest** button on Edit Level opens the game in a new tab

## Data Model

```
td_maps           ─┐
td_difficulties   ─┼── td_levels ── td_waves ── td_wave_spawns ── td_enemies
td_towers         ─┘
td_runs (user_id, level_id, status, score, waves, duration, seed)
td_player_progress (user_id, level_id, best_score, best_waves, stars, plays, wins)
```

## Engine (Vue + Canvas2D, no new deps)

- `resources/js/pages/Games/TowerDefense/engine/types.ts` — payload/HUD types
- `resources/js/pages/Games/TowerDefense/engine/Game.ts` — self-contained class:
  - fixed-timestep (60 Hz) simulation, speed 1x/2x/3x
  - entities: Enemy, PlacedTower, Projectile
  - waypoint path following; homing projectiles; splash damage
  - input: click-to-place, click placed tower to select, right-click cancel
  - emits `hud` + `end` events to the Vue layer

## Anti-cheat (server-side in `finishRun`)

- Caps `score` at theoretical max (all enemies × score × difficulty score_mult + reward + headroom)
- Minimum duration floor on `win` runs (≥ 2s × waves_completed)
- Rate limit: 30/min on start + finish
- Seed generated server-side; stored for later replay verification

## Stars (on win)

- 1 star: any lives remaining
- 2 stars: ≥ 50% starting lives
- 3 stars: ≥ 90% starting lives

## Re-seed

```
php artisan db:seed --class=TowerDefenseSeeder
```

Re-runs safely (uses `updateOrCreate`; wipes and recreates waves for seeded levels).
