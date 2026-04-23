// Tower Defense engine — shared types

export interface EnemyDef {
    id: number;
    slug: string;
    name: string;
    hp: number;
    speed: number;
    armor: number;
    damage: number;
    bounty: number;
    score: number;
    color: string;
    radius: number;
}

export interface TowerUpgrade {
    cost: number;
    damage?: number;
    range?: number;
    fire_rate?: number;
    splash_radius?: number;
}

export interface TowerDef {
    id: number;
    slug: string;
    name: string;
    cost: number;
    damage: number;
    range: number;
    fire_rate: number;
    projectile_type: 'bullet' | 'laser' | 'splash';
    splash_radius: number;
    projectile_speed: number;
    color: string;
    upgrades: TowerUpgrade[];
}

export interface WaveSpawnDef {
    enemy: EnemyDef;
    count: number;
    interval_ms: number;
    offset_ms: number;
}

export interface WaveDef {
    order: number;
    spawn_delay_ms: number;
    bonus_gold: number;
    spawns: WaveSpawnDef[];
}

export interface MapDef {
    name: string;
    grid_width: number;
    grid_height: number;
    tile_size: number;
    path_waypoints: [number, number][];
    background_color: string;
}

export interface DifficultyDef {
    name: string;
    slug: string;
    enemy_hp_mult: number;
    enemy_speed_mult: number;
    gold_mult: number;
    score_mult: number;
}

export interface LevelPayload {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    reward_score: number;
    starting_gold: number;
    starting_lives: number;
    difficulty: DifficultyDef;
    map: MapDef;
    towers: TowerDef[];
    waves: WaveDef[];
}

export interface HudState {
    gold: number;
    lives: number;
    wave: number;
    totalWaves: number;
    score: number;
    enemiesAlive: number;
    status: 'idle' | 'playing' | 'paused' | 'win' | 'lose';
    selectedTowerSlug: string | null;
    hoverTile: { x: number; y: number } | null;
    canBuild: boolean;
    selectedPlacedTowerId: number | null;
    speed: 1 | 2 | 3;
    awaitingWaveStart: boolean;
    nextWaveIdx: number; // 1-based index of the next wave to start
}
