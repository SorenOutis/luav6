// Tower Defense engine — PixiJS renderer with procedural pixel-art sprites.
// Simulation is pure logic (same as Canvas version); rendering uses Pixi Containers + Sprites.

import {
    Application,
    Container,
    Graphics,
    Sprite,
    Texture,
    RenderTexture,
    TextureStyle,
    type Renderer,
} from 'pixi.js';
import type { EnemyDef, HudState, LevelPayload, TowerDef, WaveDef } from './types';

// Prefer crisp pixel-art scaling globally for generated textures.
TextureStyle.defaultOptions.scaleMode = 'nearest';

interface Enemy {
    id: number;
    def: EnemyDef;
    maxHp: number;
    hp: number;
    speed: number;
    waypointIdx: number;
    x: number;
    y: number;
    alive: boolean;
    reachedEnd: boolean;
    // render
    sprite?: Sprite;
    hpBarBg?: Graphics;
    hpBar?: Graphics;
}

interface PlacedTower {
    id: number;
    def: TowerDef;
    tileX: number;
    tileY: number;
    x: number;
    y: number;
    tier: number;
    cooldown: number;
    totalCost: number;
    damage: number;
    range: number; // px
    fireRate: number;
    splashRadius: number; // px
    angle: number;
    lastTargetId: number | null;
    // render
    base?: Sprite;
    barrel?: Sprite;
    tierMarks?: Graphics;
}

interface Projectile {
    x: number;
    y: number;
    vx: number;
    vy: number;
    speed: number;
    damage: number;
    splashRadius: number;
    targetId: number | null;
    tx: number;
    ty: number;
    alive: boolean;
    color: string;
    sprite?: Sprite;
}

type ListenerMap = {
    hud: (state: HudState) => void;
    end: (result: { status: 'win' | 'lose'; score: number; waves: number; lives: number; goldSpent: number; duration: number }) => void;
    waveComplete: (payload: { waveIdx: number }) => void;
    waveStarting: (payload: { waveIdx: number; totalWaves: number }) => void;
};

export interface GameSnapshot {
    version: 1;
    levelId: number;
    waveIdx: number; // next wave to begin (0-based)
    gold: number;
    lives: number;
    score: number;
    goldSpent: number;
    elapsedMs: number;
    towers: Array<{ slug: string; tileX: number; tileY: number; tier: number; totalCost: number }>;
}

// ---------- Pixel-art texture generation ----------
// Each texture is authored on a tiny logical grid then rendered to a RenderTexture at a fixed pixel size.
// Nearest-neighbor scaling will keep the pixelated feel when drawn at any tile size.

const PIXEL = 4; // logical pixel size in real pixels for generated textures

function buildEnemyTexture(renderer: Renderer, color: number, accent = 0x111111): Texture {
    // 8x8 pixel-art blob with eyes, like a little creep
    const g = new Graphics();
    const size = 8;
    // shadow
    g.rect(1 * PIXEL, 6 * PIXEL, 6 * PIXEL, PIXEL).fill(0x000000);
    // body (roundish)
    const body: [number, number, number, number][] = [
        [2, 1, 4, 1], [1, 2, 6, 3], [1, 5, 6, 1], [2, 6, 4, 1],
    ];
    body.forEach(([x, y, w, h]) => g.rect(x * PIXEL, y * PIXEL, w * PIXEL, h * PIXEL).fill(color));
    // eyes
    g.rect(2 * PIXEL, 3 * PIXEL, PIXEL, PIXEL).fill(accent);
    g.rect(5 * PIXEL, 3 * PIXEL, PIXEL, PIXEL).fill(accent);
    // highlight
    g.rect(2 * PIXEL, 2 * PIXEL, PIXEL, PIXEL).fill(0xffffff);

    const rt = RenderTexture.create({ width: size * PIXEL, height: size * PIXEL, antialias: false });
    renderer.render({ container: g, target: rt });
    g.destroy();
    return rt;
}

function buildTowerBaseTexture(renderer: Renderer, color: number): Texture {
    // 10x10 pixel base: square plate with corners, inner darker ring
    const g = new Graphics();
    const S = 10;
    // base plate
    g.rect(PIXEL, PIXEL, (S - 2) * PIXEL, (S - 2) * PIXEL).fill(0x0f172a);
    g.rect(2 * PIXEL, 2 * PIXEL, (S - 4) * PIXEL, (S - 4) * PIXEL).fill(color);
    // corner bolts
    [[1, 1], [S - 2, 1], [1, S - 2], [S - 2, S - 2]].forEach(([x, y]) =>
        g.rect(x * PIXEL, y * PIXEL, PIXEL, PIXEL).fill(0x1e293b),
    );
    // inner dot
    g.rect((S / 2 - 1) * PIXEL, (S / 2 - 1) * PIXEL, 2 * PIXEL, 2 * PIXEL).fill(0x0f172a);

    const rt = RenderTexture.create({ width: S * PIXEL, height: S * PIXEL, antialias: false });
    renderer.render({ container: g, target: rt });
    g.destroy();
    return rt;
}

function buildTowerBarrelTexture(renderer: Renderer, color: number): Texture {
    // 12x4 pixel barrel pointing right (origin at middle-left), pivot set after.
    const g = new Graphics();
    const W = 12;
    const H = 4;
    // barrel body
    g.rect(0, PIXEL, W * PIXEL, (H - 2) * PIXEL).fill(color);
    g.rect(0, 0, 4 * PIXEL, H * PIXEL).fill(0x1e293b); // back block
    g.rect((W - 2) * PIXEL, PIXEL, 2 * PIXEL, (H - 2) * PIXEL).fill(0xffffff); // muzzle highlight

    const rt = RenderTexture.create({ width: W * PIXEL, height: H * PIXEL, antialias: false });
    renderer.render({ container: g, target: rt });
    g.destroy();
    return rt;
}

function buildProjectileTexture(renderer: Renderer, color: number): Texture {
    const g = new Graphics();
    g.rect(PIXEL, 0, PIXEL, PIXEL).fill(color);
    g.rect(0, PIXEL, 3 * PIXEL, PIXEL).fill(color);
    g.rect(PIXEL, 2 * PIXEL, PIXEL, PIXEL).fill(color);
    g.rect(PIXEL, PIXEL, PIXEL, PIXEL).fill(0xffffff);
    const rt = RenderTexture.create({ width: 3 * PIXEL, height: 3 * PIXEL, antialias: false });
    renderer.render({ container: g, target: rt });
    g.destroy();
    return rt;
}

function buildTilePathTexture(renderer: Renderer, tileSize: number): Texture {
    // simple dark tile with subtle grid pattern
    const g = new Graphics();
    g.rect(0, 0, tileSize, tileSize).fill(0x0b1220);
    g.rect(0, 0, tileSize, 1).fill(0x1e3a5f);
    g.rect(0, tileSize - 1, tileSize, 1).fill(0x1e3a5f);
    // pixel dots
    for (let i = 4; i < tileSize; i += 8) {
        g.rect(i, tileSize / 2 - 1, 2, 2).fill(0x38bdf8);
    }
    const rt = RenderTexture.create({ width: tileSize, height: tileSize, antialias: false });
    renderer.render({ container: g, target: rt });
    g.destroy();
    return rt;
}

function buildCoreTexture(renderer: Renderer): Texture {
    // 16x16 pixel-art "core" — a layered base with a glowing crystal.
    const g = new Graphics();
    const P = PIXEL;
    // outer platform
    g.rect(1 * P, 12 * P, 14 * P, 3 * P).fill(0x1e293b);
    g.rect(2 * P, 11 * P, 12 * P, P).fill(0x334155);
    g.rect(0, 15 * P, 16 * P, P).fill(0x0f172a);
    // base plating
    g.rect(3 * P, 8 * P, 10 * P, 4 * P).fill(0x475569);
    g.rect(4 * P, 7 * P, 8 * P, P).fill(0x64748b);
    // inner dark slot
    g.rect(5 * P, 9 * P, 6 * P, 2 * P).fill(0x0f172a);
    // core crystal
    g.rect(7 * P, 3 * P, 2 * P, 5 * P).fill(0x22d3ee);
    g.rect(6 * P, 4 * P, 4 * P, 3 * P).fill(0x22d3ee);
    g.rect(6 * P, 5 * P, P, P).fill(0xa5f3fc);
    g.rect(9 * P, 5 * P, P, P).fill(0x0891b2);
    // antenna
    g.rect(7 * P, 1 * P, 2 * P, 2 * P).fill(0xfde68a);
    g.rect(7 * P + P / 2, 0, P, P).fill(0xfff);
    // bolts
    g.rect(3 * P, 13 * P, P, P).fill(0x0f172a);
    g.rect(12 * P, 13 * P, P, P).fill(0x0f172a);
    const size = 16 * P;
    const rt = RenderTexture.create({ width: size, height: size, antialias: false });
    renderer.render({ container: g, target: rt });
    g.destroy();
    return rt;
}

function buildBuildableTileTexture(renderer: Renderer, tileSize: number): Texture {
    const g = new Graphics();
    g.rect(0, 0, tileSize, tileSize).fill(0x0a0a0a);
    // dithered background
    for (let y = 0; y < tileSize; y += 4) {
        for (let x = (y / 4) % 2 === 0 ? 0 : 2; x < tileSize; x += 4) {
            g.rect(x, y, 1, 1).fill(0x1a1a1a);
        }
    }
    // border
    g.rect(0, 0, tileSize, 1).fill(0x1f2937);
    g.rect(0, tileSize - 1, tileSize, 1).fill(0x1f2937);
    g.rect(0, 0, 1, tileSize).fill(0x1f2937);
    g.rect(tileSize - 1, 0, 1, tileSize).fill(0x1f2937);
    const rt = RenderTexture.create({ width: tileSize, height: tileSize, antialias: false });
    renderer.render({ container: g, target: rt });
    g.destroy();
    return rt;
}

const parseColor = (c: string): number => {
    if (c.startsWith('#')) return parseInt(c.slice(1), 16);
    return 0xffffff;
};

// ---------- Game ----------
export class TowerDefenseGame {
    private app!: Application;
    private container: HTMLElement;
    private level: LevelPayload;
    private tileSize: number;
    private waypointsPx: { x: number; y: number }[];
    private pathTiles: Set<string>;

    // Scene layers
    private bgLayer!: Container;
    private pathLayer!: Container;
    private hoverLayer!: Graphics;
    private towerLayer!: Container;
    private enemyLayer!: Container;
    private projectileLayer!: Container;
    private uiLayer!: Graphics; // selected tower range
    private coreLayer!: Container;
    private coreSprite?: Sprite;
    private coreHpBar?: Graphics;
    private coreHpBarBg?: Graphics;
    private coreHitTimer = 0; // seconds remaining for hit flash
    private coreShakeTimer = 0;

    // Textures cache
    private enemyTextures = new Map<string, Texture>();
    private towerBaseTextures = new Map<string, Texture>();
    private towerBarrelTextures = new Map<string, Texture>();
    private projectileTextures = new Map<string, Texture>();
    private pathTileTex!: Texture;
    private buildableTileTex!: Texture;

    // State
    private enemies: Enemy[] = [];
    private towers: PlacedTower[] = [];
    private projectiles: Projectile[] = [];
    private nextEnemyId = 1;
    private nextTowerId = 1;

    private gold = 0;
    private lives = 0;
    private score = 0;
    private goldSpent = 0;

    private currentWaveIdx = -1;
    private waveTimer = 0;
    private waveActive = false;
    private awaitingWaveStart = true; // waits for player to click "Start Wave"
    private spawnCursors: { emitted: number; timer: number }[] = [];

    private status: HudState['status'] = 'idle';
    private speed: 1 | 2 | 3 = 1;

    private selectedTowerSlug: string | null = null;
    private selectedPlacedTowerId: number | null = null;
    private hoverTile: { x: number; y: number } | null = null;

    private lastFrame = 0;
    private accumulator = 0;
    private readonly dt = 1 / 60;
    private rafId = 0;
    private elapsedMs = 0;
    private ready = false;

    private listeners: Partial<ListenerMap> = {};

    constructor(container: HTMLElement, level: LevelPayload) {
        this.container = container;
        this.level = level;
        this.tileSize = level.map.tile_size;
        this.waypointsPx = level.map.path_waypoints.map(([x, y]) => ({
            x: x * this.tileSize + this.tileSize / 2,
            y: y * this.tileSize + this.tileSize / 2,
        }));
        this.pathTiles = this.computePathTiles(level.map.path_waypoints);
        this.gold = level.starting_gold;
        this.lives = level.starting_lives;
    }

    async init() {
        const w = this.level.map.grid_width * this.tileSize;
        const h = this.level.map.grid_height * this.tileSize;
        this.app = new Application();
        await this.app.init({
            width: w,
            height: h,
            background: this.level.map.background_color,
            antialias: false,
            resolution: Math.min(window.devicePixelRatio || 1, 2),
            autoDensity: true,
        });
        this.container.appendChild(this.app.canvas);
        this.app.canvas.style.cursor = 'crosshair';
        this.app.canvas.style.imageRendering = 'pixelated';

        // Build textures
        this.pathTileTex = buildTilePathTexture(this.app.renderer, this.tileSize);
        this.buildableTileTex = buildBuildableTileTexture(this.app.renderer, this.tileSize);

        // Layers
        this.bgLayer = new Container();
        this.pathLayer = new Container();
        this.hoverLayer = new Graphics();
        this.towerLayer = new Container();
        this.enemyLayer = new Container();
        this.projectileLayer = new Container();
        this.uiLayer = new Graphics();
        this.coreLayer = new Container();
        this.app.stage.addChild(this.bgLayer, this.pathLayer, this.hoverLayer, this.uiLayer, this.towerLayer, this.coreLayer, this.enemyLayer, this.projectileLayer);

        this.drawBackground();
        this.drawPath();
        this.drawCore();

        this.attachInput();
        this.ready = true;
        this.emitHud();
    }

    // ---------------- Public API ----------------
    on<K extends keyof ListenerMap>(evt: K, cb: ListenerMap[K]) {
        this.listeners[evt] = cb;
    }

    start() {
        if (!this.ready) return;
        this.status = 'playing';
        this.lastFrame = performance.now();
        this.emitHud();
        this.loop();
    }

    destroy() {
        cancelAnimationFrame(this.rafId);
        this.detachInput();
        this.enemyTextures.forEach((t) => t.destroy(true));
        this.towerBaseTextures.forEach((t) => t.destroy(true));
        this.towerBarrelTextures.forEach((t) => t.destroy(true));
        this.projectileTextures.forEach((t) => t.destroy(true));
        this.pathTileTex?.destroy(true);
        this.buildableTileTex?.destroy(true);
        this.app?.destroy(true, { children: true, texture: true });
    }

    pause() {
        if (this.status === 'playing') this.status = 'paused';
        this.emitHud();
    }

    resume() {
        if (this.status === 'paused') {
            this.status = 'playing';
            this.lastFrame = performance.now();
        }
        this.emitHud();
    }

    setSpeed(s: 1 | 2 | 3) {
        this.speed = s;
        this.emitHud();
    }

    selectTower(slug: string | null) {
        this.selectedTowerSlug = slug;
        this.selectedPlacedTowerId = null;
        this.emitHud();
    }

    sellSelected() {
        if (this.selectedPlacedTowerId == null) return;
        const idx = this.towers.findIndex((x) => x.id === this.selectedPlacedTowerId);
        if (idx < 0) return;
        const t = this.towers[idx];
        const refund = Math.floor(t.totalCost * 0.7);
        this.gold += refund;
        t.base?.destroy();
        t.barrel?.destroy();
        t.tierMarks?.destroy();
        this.towers.splice(idx, 1);
        this.selectedPlacedTowerId = null;
        this.emitHud();
    }

    upgradeSelected() {
        if (this.selectedPlacedTowerId == null) return;
        const t = this.towers.find((x) => x.id === this.selectedPlacedTowerId);
        if (!t) return;
        const next = t.def.upgrades?.[t.tier];
        if (!next) return;
        if (this.gold < next.cost) return;
        this.gold -= next.cost;
        t.totalCost += next.cost;
        t.tier++;
        if (next.damage != null) t.damage = next.damage;
        if (next.range != null) t.range = next.range * this.tileSize;
        if (next.fire_rate != null) t.fireRate = next.fire_rate;
        if (next.splash_radius != null) t.splashRadius = next.splash_radius * this.tileSize;
        this.drawTierMarks(t);
        this.emitHud();
    }

    getSelectedTowerInfo() {
        if (this.selectedPlacedTowerId == null) return null;
        const t = this.towers.find((x) => x.id === this.selectedPlacedTowerId);
        if (!t) return null;
        const next = t.def.upgrades?.[t.tier];
        return {
            id: t.id,
            name: t.def.name,
            tier: t.tier,
            damage: t.damage,
            range: t.range / this.tileSize,
            fireRate: t.fireRate,
            sellRefund: Math.floor(t.totalCost * 0.7),
            nextUpgrade: next ? { cost: next.cost, damage: next.damage, range: next.range } : null,
        };
    }

    /** Serialize the current high-level game state between waves. */
    snapshot(): GameSnapshot {
        return {
            version: 1,
            levelId: this.level.id,
            // Save the index of the NEXT wave to start (0-based).
            waveIdx: Math.max(0, this.currentWaveIdx + 1),
            gold: this.gold,
            lives: this.lives,
            score: this.score,
            goldSpent: this.goldSpent,
            elapsedMs: Math.floor(this.elapsedMs),
            towers: this.towers.map((t) => ({
                slug: t.def.slug,
                tileX: t.tileX,
                tileY: t.tileY,
                tier: t.tier,
                totalCost: t.totalCost,
            })),
        };
    }

    /** Restore from a snapshot. MUST be called after init() and before start(). */
    restore(snap: GameSnapshot) {
        if (snap.version !== 1 || snap.levelId !== this.level.id) return;
        this.gold = snap.gold;
        this.lives = snap.lives;
        this.score = snap.score;
        this.goldSpent = snap.goldSpent;
        this.elapsedMs = snap.elapsedMs;
        // Wind wave cursor one behind so beginWave() starts at snap.waveIdx.
        this.currentWaveIdx = snap.waveIdx - 1;
        // Re-create placed towers (tap into same code path, free)
        for (const t of snap.towers) {
            const def = this.level.towers.find((x) => x.slug === t.slug);
            if (!def) continue;
            this.selectedTowerSlug = def.slug;
            const goldBefore = this.gold;
            const spentBefore = this.goldSpent;
            this.gold = def.cost; // ensure placement succeeds
            this.tryPlaceTower(t.tileX, t.tileY);
            this.gold = goldBefore;
            this.goldSpent = spentBefore;
            const placed = this.towers[this.towers.length - 1];
            if (placed) {
                placed.totalCost = t.totalCost;
                // Apply upgrades up to saved tier
                for (let i = 0; i < t.tier; i++) {
                    const up = def.upgrades?.[i];
                    if (!up) break;
                    placed.tier++;
                    if (up.damage != null) placed.damage = up.damage;
                    if (up.range != null) placed.range = up.range * this.tileSize;
                    if (up.fire_rate != null) placed.fireRate = up.fire_rate;
                    if (up.splash_radius != null) placed.splashRadius = up.splash_radius * this.tileSize;
                }
                this.drawTierMarks(placed);
            }
        }
        this.selectedTowerSlug = null;
        this.emitHud();
    }

    getResult() {
        return {
            status: this.status === 'win' ? 'win' : this.status === 'lose' ? 'lose' : 'abandoned',
            score: this.score,
            waves_completed: Math.max(0, this.currentWaveIdx + (this.waveActive ? 0 : 1)),
            gold_spent: this.goldSpent,
            lives_remaining: this.lives,
            duration_ms: Math.floor(this.elapsedMs),
        };
    }

    // ---------------- Loop ----------------
    private loop = () => {
        this.rafId = requestAnimationFrame(this.loop);
        const now = performance.now();
        let delta = (now - this.lastFrame) / 1000;
        this.lastFrame = now;
        if (delta > 0.25) delta = 0.25;

        if (this.status === 'playing') {
            this.elapsedMs += delta * 1000;
            this.accumulator += delta * this.speed;
            while (this.accumulator >= this.dt) {
                this.step(this.dt);
                this.accumulator -= this.dt;
            }
        }
        this.syncRender();
    };

    private step(dt: number) {
        this.updateWaves(dt);
        this.updateEnemies(dt);
        this.updateTowers(dt);
        this.updateProjectiles(dt);
        this.checkEndConditions();
    }

    // ---------------- Wave / Spawn ----------------
    /** Called by the UI to start the next (or first) wave. */
    startNextWave() {
        if (!this.awaitingWaveStart) return;
        if (this.status === 'win' || this.status === 'lose') return;
        this.currentWaveIdx++;
        if (this.currentWaveIdx >= this.level.waves.length) return;
        this.awaitingWaveStart = false;
        this.beginWave();
        this.listeners.waveStarting?.({
            waveIdx: this.currentWaveIdx,
            totalWaves: this.level.waves.length,
        });
    }

    private updateWaves(dt: number) {
        if (this.awaitingWaveStart) return;
        if (!this.waveActive) return;
        this.waveTimer += dt * 1000;
        const wave = this.level.waves[this.currentWaveIdx];
        wave.spawns.forEach((spawn, i) => {
            const cur = this.spawnCursors[i];
            if (cur.emitted >= spawn.count) return;
            if (this.waveTimer < spawn.offset_ms) return;
            cur.timer += dt * 1000;
            if (cur.emitted === 0) {
                this.spawnEnemy(spawn.enemy);
                cur.emitted++;
                cur.timer = 0;
                return;
            }
            while (cur.timer >= spawn.interval_ms && cur.emitted < spawn.count) {
                cur.timer -= spawn.interval_ms;
                this.spawnEnemy(spawn.enemy);
                cur.emitted++;
            }
        });

        const allEmitted = wave.spawns.every((s, i) => this.spawnCursors[i].emitted >= s.count);
        const allGone = this.enemies.every((e) => !e.alive || e.reachedEnd);
        if (allEmitted && allGone) {
            this.waveActive = false;
            this.gold += wave.bonus_gold;
            if (this.currentWaveIdx + 1 >= this.level.waves.length) {
                this.status = 'win';
                this.score += this.level.reward_score;
                this.emitEnd();
            } else {
                this.listeners.waveComplete?.({ waveIdx: this.currentWaveIdx });
                // Enter intermission — player can place/upgrade towers and manually start next wave.
                this.awaitingWaveStart = true;
                this.emitHud();
            }
        }
    }

    private beginWave() {
        const wave: WaveDef = this.level.waves[this.currentWaveIdx];
        this.waveActive = true;
        this.waveTimer = -wave.spawn_delay_ms;
        this.spawnCursors = wave.spawns.map(() => ({ emitted: 0, timer: 0 }));
        this.emitHud();
    }

    private getEnemyTexture(def: EnemyDef): Texture {
        let tex = this.enemyTextures.get(def.slug);
        if (!tex) {
            tex = buildEnemyTexture(this.app.renderer, parseColor(def.color));
            this.enemyTextures.set(def.slug, tex);
        }
        return tex;
    }

    private spawnEnemy(def: EnemyDef) {
        const hpMult = this.level.difficulty.enemy_hp_mult;
        const spdMult = this.level.difficulty.enemy_speed_mult;
        const wp = this.waypointsPx[0];
        const tex = this.getEnemyTexture(def);
        const sprite = new Sprite(tex);
        sprite.anchor.set(0.5);
        const scale = (def.radius * 2) / tex.width;
        sprite.scale.set(scale);
        sprite.x = wp.x;
        sprite.y = wp.y;
        this.enemyLayer.addChild(sprite);

        const hpBarBg = new Graphics().rect(-def.radius, -def.radius - 8, def.radius * 2, 4).fill(0x000000);
        const hpBar = new Graphics().rect(-def.radius, -def.radius - 8, def.radius * 2, 4).fill(0x22c55e);
        sprite.addChild(hpBarBg, hpBar);

        const e: Enemy = {
            id: this.nextEnemyId++,
            def,
            maxHp: def.hp * hpMult,
            hp: def.hp * hpMult,
            speed: def.speed * spdMult * this.tileSize,
            waypointIdx: 1,
            x: wp.x,
            y: wp.y,
            alive: true,
            reachedEnd: false,
            sprite,
            hpBarBg,
            hpBar,
        };
        this.enemies.push(e);
    }

    // ---------------- Entities ----------------
    private updateEnemies(dt: number) {
        for (const e of this.enemies) {
            if (!e.alive || e.reachedEnd) continue;
            const target = this.waypointsPx[e.waypointIdx];
            if (!target) {
                e.reachedEnd = true;
                this.lives -= e.def.damage;
                this.coreHitTimer = 0.35;
                this.coreShakeTimer = 0.25;
                continue;
            }
            const dx = target.x - e.x;
            const dy = target.y - e.y;
            const dist = Math.hypot(dx, dy);
            const step = e.speed * dt;
            if (step >= dist) {
                e.x = target.x;
                e.y = target.y;
                e.waypointIdx++;
            } else {
                e.x += (dx / dist) * step;
                e.y += (dy / dist) * step;
            }
        }
        // cleanup dead / leaked
        const next: Enemy[] = [];
        for (const e of this.enemies) {
            if (!e.alive || e.reachedEnd) {
                e.sprite?.destroy({ children: true });
                continue;
            }
            next.push(e);
        }
        this.enemies = next;
    }

    private updateTowers(dt: number) {
        for (const t of this.towers) {
            t.cooldown -= dt;
            let best: Enemy | null = null;
            let bestIdx = -1;
            for (const e of this.enemies) {
                if (!e.alive || e.reachedEnd) continue;
                const d = Math.hypot(e.x - t.x, e.y - t.y);
                if (d > t.range) continue;
                if (e.waypointIdx > bestIdx) {
                    best = e;
                    bestIdx = e.waypointIdx;
                }
            }
            if (best) {
                t.angle = Math.atan2(best.y - t.y, best.x - t.x);
                t.lastTargetId = best.id;
                if (t.cooldown <= 0) {
                    this.fireProjectile(t, best);
                    t.cooldown = 1 / t.fireRate;
                }
            }
        }
    }

    private fireProjectile(t: PlacedTower, target: Enemy) {
        const dx = target.x - t.x;
        const dy = target.y - t.y;
        const d = Math.hypot(dx, dy) || 1;
        const speed = t.def.projectile_speed * this.tileSize;
        let tex = this.projectileTextures.get(t.def.color);
        if (!tex) {
            tex = buildProjectileTexture(this.app.renderer, parseColor(t.def.color));
            this.projectileTextures.set(t.def.color, tex);
        }
        const sprite = new Sprite(tex);
        sprite.anchor.set(0.5);
        sprite.scale.set(2);
        sprite.x = t.x;
        sprite.y = t.y;
        this.projectileLayer.addChild(sprite);
        this.projectiles.push({
            x: t.x,
            y: t.y,
            vx: (dx / d) * speed,
            vy: (dy / d) * speed,
            speed,
            damage: t.damage,
            splashRadius: t.splashRadius,
            targetId: target.id,
            tx: target.x,
            ty: target.y,
            alive: true,
            color: t.def.color,
            sprite,
        });
    }

    private updateProjectiles(dt: number) {
        for (const p of this.projectiles) {
            if (!p.alive) continue;
            if (p.targetId != null) {
                const t = this.enemies.find((e) => e.id === p.targetId && e.alive && !e.reachedEnd);
                if (t) {
                    const dx = t.x - p.x;
                    const dy = t.y - p.y;
                    const d = Math.hypot(dx, dy) || 1;
                    p.vx = (dx / d) * p.speed;
                    p.vy = (dy / d) * p.speed;
                }
            }
            p.x += p.vx * dt;
            p.y += p.vy * dt;

            for (const e of this.enemies) {
                if (!e.alive || e.reachedEnd) continue;
                if (Math.hypot(e.x - p.x, e.y - p.y) <= e.def.radius) {
                    this.applyDamage(e, p.damage);
                    if (p.splashRadius > 0) {
                        for (const e2 of this.enemies) {
                            if (e2 === e || !e2.alive || e2.reachedEnd) continue;
                            if (Math.hypot(e2.x - e.x, e2.y - e.y) <= p.splashRadius) {
                                this.applyDamage(e2, p.damage * 0.6);
                            }
                        }
                    }
                    p.alive = false;
                    break;
                }
            }
            const w = this.level.map.grid_width * this.tileSize;
            const h = this.level.map.grid_height * this.tileSize;
            if (p.x < -40 || p.y < -40 || p.x > w + 40 || p.y > h + 40) p.alive = false;
        }
        const next: Projectile[] = [];
        for (const p of this.projectiles) {
            if (!p.alive) {
                p.sprite?.destroy();
                continue;
            }
            next.push(p);
        }
        this.projectiles = next;
    }

    private applyDamage(e: Enemy, dmg: number) {
        const effective = Math.max(1, dmg - e.def.armor);
        e.hp -= effective;
        if (e.hp <= 0) {
            e.alive = false;
            this.gold += Math.round(e.def.bounty * this.level.difficulty.gold_mult);
            this.score += Math.round(e.def.score * this.level.difficulty.score_mult);
        }
    }

    private checkEndConditions() {
        if (this.lives <= 0 && this.status === 'playing') {
            this.lives = 0;
            this.status = 'lose';
            this.emitEnd();
        }
    }

    // ---------------- Placement ----------------
    private tryPlaceTower(tileX: number, tileY: number) {
        if (!this.selectedTowerSlug) return;
        if (!this.isBuildable(tileX, tileY)) return;
        const def = this.level.towers.find((t) => t.slug === this.selectedTowerSlug);
        if (!def) return;
        if (this.gold < def.cost) return;
        this.gold -= def.cost;
        this.goldSpent += def.cost;

        let baseTex = this.towerBaseTextures.get(def.slug);
        if (!baseTex) {
            baseTex = buildTowerBaseTexture(this.app.renderer, parseColor(def.color));
            this.towerBaseTextures.set(def.slug, baseTex);
        }
        let barrelTex = this.towerBarrelTextures.get(def.slug);
        if (!barrelTex) {
            barrelTex = buildTowerBarrelTexture(this.app.renderer, parseColor(def.color));
            this.towerBarrelTextures.set(def.slug, barrelTex);
        }

        const x = tileX * this.tileSize + this.tileSize / 2;
        const y = tileY * this.tileSize + this.tileSize / 2;
        const base = new Sprite(baseTex);
        base.anchor.set(0.5);
        base.scale.set((this.tileSize * 0.9) / baseTex.width);
        base.x = x;
        base.y = y;
        this.towerLayer.addChild(base);

        const barrel = new Sprite(barrelTex);
        barrel.anchor.set(0.25, 0.5); // pivot near back of barrel
        barrel.scale.set((this.tileSize * 0.55) / barrelTex.width);
        barrel.x = x;
        barrel.y = y;
        this.towerLayer.addChild(barrel);

        const tierMarks = new Graphics();
        tierMarks.x = x;
        tierMarks.y = y;
        this.towerLayer.addChild(tierMarks);

        const tower: PlacedTower = {
            id: this.nextTowerId++,
            def,
            tileX,
            tileY,
            x,
            y,
            tier: 0,
            cooldown: 0,
            totalCost: def.cost,
            damage: def.damage,
            range: def.range * this.tileSize,
            fireRate: def.fire_rate,
            splashRadius: def.splash_radius * this.tileSize,
            angle: 0,
            lastTargetId: null,
            base,
            barrel,
            tierMarks,
        };
        this.towers.push(tower);
        this.emitHud();
    }

    private drawTierMarks(t: PlacedTower) {
        if (!t.tierMarks) return;
        t.tierMarks.clear();
        for (let i = 0; i < t.tier; i++) {
            t.tierMarks.rect(-9 + i * 6, this.tileSize * 0.3, 4, 4).fill(0xfde68a);
        }
    }

    private isBuildable(tx: number, ty: number): boolean {
        if (tx < 0 || ty < 0 || tx >= this.level.map.grid_width || ty >= this.level.map.grid_height) return false;
        if (this.pathTiles.has(`${tx},${ty}`)) return false;
        if (this.towers.some((t) => t.tileX === tx && t.tileY === ty)) return false;
        return true;
    }

    private computePathTiles(wps: [number, number][]): Set<string> {
        const s = new Set<string>();
        for (let i = 0; i < wps.length - 1; i++) {
            const [x1, y1] = wps[i];
            const [x2, y2] = wps[i + 1];
            if (x1 === x2) {
                const [a, b] = y1 < y2 ? [y1, y2] : [y2, y1];
                for (let y = a; y <= b; y++) s.add(`${x1},${y}`);
            } else if (y1 === y2) {
                const [a, b] = x1 < x2 ? [x1, x2] : [x2, x1];
                for (let x = a; x <= b; x++) s.add(`${x},${y1}`);
            }
        }
        return s;
    }

    // ---------------- Rendering ----------------
    private drawBackground() {
        for (let y = 0; y < this.level.map.grid_height; y++) {
            for (let x = 0; x < this.level.map.grid_width; x++) {
                if (this.pathTiles.has(`${x},${y}`)) continue;
                const s = new Sprite(this.buildableTileTex);
                s.x = x * this.tileSize;
                s.y = y * this.tileSize;
                this.bgLayer.addChild(s);
            }
        }
    }

    private drawPath() {
        for (const key of this.pathTiles) {
            const [tx, ty] = key.split(',').map(Number);
            const s = new Sprite(this.pathTileTex);
            s.x = tx * this.tileSize;
            s.y = ty * this.tileSize;
            this.pathLayer.addChild(s);
        }
        // Start marker only — end is replaced by the Core building.
        const first = this.waypointsPx[0];
        const startMarker = new Graphics().rect(-8, -8, 16, 16).fill(0x22c55e);
        startMarker.x = first.x;
        startMarker.y = first.y;
        this.pathLayer.addChild(startMarker);
    }

    private drawCore() {
        const last = this.waypointsPx[this.waypointsPx.length - 1];
        const tex = buildCoreTexture(this.app.renderer);
        const sprite = new Sprite(tex);
        sprite.anchor.set(0.5, 0.5);
        // scale to ~1.5 tiles
        const targetPx = this.tileSize * 1.5;
        sprite.scale.set(targetPx / tex.width);
        sprite.x = last.x;
        sprite.y = last.y;
        this.coreLayer.addChild(sprite);
        this.coreSprite = sprite;

        // HP bar (screen-aligned, above sprite)
        const barW = this.tileSize * 1.6;
        const barH = 6;
        const barY = last.y - targetPx / 2 - 12;
        const bg = new Graphics().rect(last.x - barW / 2, barY, barW, barH).fill(0x000000);
        const fg = new Graphics().rect(last.x - barW / 2, barY, barW, barH).fill(0x22c55e);
        this.coreLayer.addChild(bg, fg);
        this.coreHpBarBg = bg;
        this.coreHpBar = fg;
    }

    private updateCoreAnim(dt: number) {
        if (this.coreHitTimer > 0) this.coreHitTimer = Math.max(0, this.coreHitTimer - dt);
        if (this.coreShakeTimer > 0) this.coreShakeTimer = Math.max(0, this.coreShakeTimer - dt);
        if (!this.coreSprite) return;
        const last = this.waypointsPx[this.waypointsPx.length - 1];
        if (this.coreShakeTimer > 0) {
            const mag = 4 * (this.coreShakeTimer / 0.25);
            this.coreSprite.x = last.x + (Math.random() - 0.5) * mag * 2;
            this.coreSprite.y = last.y + (Math.random() - 0.5) * mag * 2;
        } else {
            this.coreSprite.x = last.x;
            this.coreSprite.y = last.y;
        }
        // damage flash (red tint)
        if (this.coreHitTimer > 0) {
            const t = this.coreHitTimer / 0.35;
            const r = 255;
            const g = Math.floor(255 * (1 - t));
            const b = Math.floor(255 * (1 - t));
            this.coreSprite.tint = (r << 16) | (g << 8) | b;
        } else {
            this.coreSprite.tint = 0xffffff;
        }
        // HP bar redraw
        if (this.coreHpBar && this.coreHpBarBg) {
            const pct = Math.max(0, this.lives / this.level.starting_lives);
            const barW = this.tileSize * 1.6;
            const barH = 6;
            const targetPx = this.tileSize * 1.5;
            const barY = last.y - targetPx / 2 - 12;
            const color = pct > 0.5 ? 0x22c55e : pct > 0.25 ? 0xeab308 : 0xef4444;
            this.coreHpBar.clear().rect(last.x - barW / 2, barY, barW * pct, barH).fill(color);
        }
    }

    /** Called every frame to sync sprite positions to simulation state. */
    private syncRender() {
        this.updateCoreAnim(1 / 60);
        // Enemies
        for (const e of this.enemies) {
            if (!e.sprite) continue;
            e.sprite.x = Math.round(e.x);
            e.sprite.y = Math.round(e.y);
            if (e.hpBar && e.hpBarBg) {
                const pct = Math.max(0, e.hp / e.maxHp);
                e.hpBar.clear().rect(-e.def.radius, -e.def.radius - 8, e.def.radius * 2 * pct, 4).fill(
                    pct > 0.5 ? 0x22c55e : pct > 0.25 ? 0xeab308 : 0xef4444,
                );
            }
        }
        // Towers (rotate barrels)
        for (const t of this.towers) {
            if (t.barrel) t.barrel.rotation = t.angle;
        }
        // Projectiles
        for (const p of this.projectiles) {
            if (!p.sprite) continue;
            p.sprite.x = Math.round(p.x);
            p.sprite.y = Math.round(p.y);
        }

        // Hover preview
        this.hoverLayer.clear();
        if (this.hoverTile && this.selectedTowerSlug) {
            const ok = this.isBuildable(this.hoverTile.x, this.hoverTile.y);
            const def = this.level.towers.find((t) => t.slug === this.selectedTowerSlug);
            this.hoverLayer
                .rect(this.hoverTile.x * this.tileSize, this.hoverTile.y * this.tileSize, this.tileSize, this.tileSize)
                .fill({ color: ok ? 0x22c55e : 0xef4444, alpha: 0.25 });
            if (ok && def) {
                this.hoverLayer
                    .circle(
                        this.hoverTile.x * this.tileSize + this.tileSize / 2,
                        this.hoverTile.y * this.tileSize + this.tileSize / 2,
                        def.range * this.tileSize,
                    )
                    .stroke({ color: 0xffffff, width: 1, alpha: 0.25 });
            }
        }

        // Selected tower range ring
        this.uiLayer.clear();
        if (this.selectedPlacedTowerId != null) {
            const t = this.towers.find((x) => x.id === this.selectedPlacedTowerId);
            if (t) {
                this.uiLayer.circle(t.x, t.y, t.range).stroke({ color: 0xffffff, width: 1, alpha: 0.35 });
                this.uiLayer.rect(t.tileX * this.tileSize, t.tileY * this.tileSize, this.tileSize, this.tileSize)
                    .stroke({ color: 0xffffff, width: 1, alpha: 0.4 });
            }
        }
    }

    // ---------------- HUD / Events ----------------
    private emitHud() {
        const cb = this.listeners.hud;
        if (!cb) return;
        const canBuild = !!(this.selectedTowerSlug && this.hoverTile && this.isBuildable(this.hoverTile.x, this.hoverTile.y));
        cb({
            gold: this.gold,
            lives: this.lives,
            wave: Math.max(0, this.currentWaveIdx + 1),
            totalWaves: this.level.waves.length,
            awaitingWaveStart: this.awaitingWaveStart,
            nextWaveIdx: Math.min(this.level.waves.length, this.currentWaveIdx + 2),
            score: this.score,
            enemiesAlive: this.enemies.filter((e) => e.alive && !e.reachedEnd).length,
            status: this.status,
            selectedTowerSlug: this.selectedTowerSlug,
            hoverTile: this.hoverTile,
            canBuild,
            selectedPlacedTowerId: this.selectedPlacedTowerId,
            speed: this.speed,
        });
    }

    private emitEnd() {
        this.emitHud();
        this.listeners.end?.({
            status: this.status === 'win' ? 'win' : 'lose',
            score: this.score,
            waves: this.currentWaveIdx + (this.status === 'win' ? 1 : 0),
            lives: this.lives,
            goldSpent: this.goldSpent,
            duration: this.elapsedMs,
        });
    }

    // ---------------- Input ----------------
    private onMove = (ev: MouseEvent) => {
        const { tx, ty } = this.eventToTile(ev);
        this.hoverTile = { x: tx, y: ty };
        this.emitHud();
    };
    private onLeave = () => {
        this.hoverTile = null;
        this.emitHud();
    };
    private onClick = (ev: MouseEvent) => {
        const { tx, ty } = this.eventToTile(ev);
        if (this.selectedTowerSlug) {
            this.tryPlaceTower(tx, ty);
            return;
        }
        const hit = this.towers.find((t) => t.tileX === tx && t.tileY === ty);
        this.selectedPlacedTowerId = hit ? hit.id : null;
        this.emitHud();
    };
    private onContext = (ev: MouseEvent) => {
        ev.preventDefault();
        this.selectedTowerSlug = null;
        this.selectedPlacedTowerId = null;
        this.emitHud();
    };

    private eventToTile(ev: MouseEvent) {
        const rect = this.app.canvas.getBoundingClientRect();
        const scaleX = this.app.canvas.width / rect.width / (this.app.renderer.resolution || 1);
        const scaleY = this.app.canvas.height / rect.height / (this.app.renderer.resolution || 1);
        const x = (ev.clientX - rect.left) * scaleX;
        const y = (ev.clientY - rect.top) * scaleY;
        return { tx: Math.floor(x / this.tileSize), ty: Math.floor(y / this.tileSize) };
    }

    private attachInput() {
        const c = this.app.canvas;
        c.addEventListener('mousemove', this.onMove);
        c.addEventListener('mouseleave', this.onLeave);
        c.addEventListener('click', this.onClick);
        c.addEventListener('contextmenu', this.onContext);
    }
    private detachInput() {
        if (!this.app?.canvas) return;
        const c = this.app.canvas;
        c.removeEventListener('mousemove', this.onMove);
        c.removeEventListener('mouseleave', this.onLeave);
        c.removeEventListener('click', this.onClick);
        c.removeEventListener('contextmenu', this.onContext);
    }
}
