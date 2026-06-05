export type UnlockRequirementKind =
    | 'node'
    | 'xp'
    | 'level'
    | 'badge'
    | 'streak';

export interface UnlockRequirement {
    kind: UnlockRequirementKind;
    nodeSlug?: string | null;
    amount?: number | null;
    level?: number | null;
    badgeId?: number | null;
    minScore?: number | null;
}

export interface NodeRewards {
    xp: number;
    points: number;
    badgeId?: number | null;
}

export interface NodeTarget {
    type: string; // e.g. 'Exam'
    id: number;
}

export interface MapNodeDefinition {
    id: string; // slug
    title: string;
    type: 'lesson' | 'exam' | 'boss';
    x: number;
    y: number;
    passScore?: number;
    target?: NodeTarget | null;
    rewards?: NodeRewards;
    requirements?: UnlockRequirement[];
    /** Legacy: simple list of prereq slugs. Kept for backwards compat. */
    dependsOn?: string[];
}

export interface WorldBiome {
    id: string;
    name: string;
    theme: {
        primary: string;
        secondary?: string;
        accent: string;
        background: string;
        particles?: string[];
    };
    nodes: MapNodeDefinition[];
}

export interface PlayerProgress {
    xp: number;
    level: number;
    points: number;
    streakDays: number;
    completedNodeSlugs: string[];
    badgeIds: number[];
    /** XP earned inside the current level bracket (from backend). */
    xpIntoLevel?: number;
    /** XP required to span the current → next level bracket (from backend). */
    xpForNextLevel?: number;
    /** Backend-suggested next unlocked, uncompleted node slug. */
    nextNodeSlug?: string | null;
}

/** Result of evaluating a single requirement. */
export interface RequirementEvaluation {
    met: boolean;
    progress: number; // 0..1
    label: string;
    detail: string;
}

export function evaluateRequirement(
    req: UnlockRequirement,
    player: PlayerProgress,
    nodeTitleLookup: Record<string, string>,
): RequirementEvaluation {
    switch (req.kind) {
        case 'node': {
            const slug = req.nodeSlug ?? '';
            const done = player.completedNodeSlugs.includes(slug);
            return {
                met: done,
                progress: done ? 1 : 0,
                label: 'Complete prerequisite',
                detail: nodeTitleLookup[slug] ?? slug,
            };
        }
        case 'xp': {
            const need = Math.max(1, req.amount ?? 0);
            return {
                met: player.xp >= need,
                progress: Math.min(1, player.xp / need),
                label: 'Earn XP',
                detail: `${player.xp.toLocaleString()} / ${need.toLocaleString()} XP`,
            };
        }
        case 'level': {
            const need = Math.max(1, req.level ?? 1);
            return {
                met: player.level >= need,
                progress: Math.min(1, player.level / need),
                label: 'Reach level',
                detail: `Level ${player.level} / ${need}`,
            };
        }
        case 'badge': {
            const has = !!req.badgeId && player.badgeIds.includes(req.badgeId);
            return {
                met: has,
                progress: has ? 1 : 0,
                label: 'Earn badge',
                detail: has ? 'Earned' : 'Not earned',
            };
        }
        case 'streak': {
            const need = Math.max(1, req.amount ?? 0);
            return {
                met: player.streakDays >= need,
                progress: Math.min(1, player.streakDays / need),
                label: 'Daily streak',
                detail: `${player.streakDays} / ${need} days`,
            };
        }
    }
    return { met: false, progress: 0, label: 'Unknown', detail: '' };
}

export function nodeStatus(
    node: MapNodeDefinition,
    player: PlayerProgress,
    nodeTitleLookup: Record<string, string>,
): 'locked' | 'available' | 'completed' {
    if (player.completedNodeSlugs.includes(node.id)) return 'completed';

    const requirements =
        node.requirements && node.requirements.length
            ? node.requirements
            : (node.dependsOn ?? []).map<UnlockRequirement>((slug) => ({
                  kind: 'node',
                  nodeSlug: slug,
              }));

    if (!requirements.length) return 'available';

    const allMet = requirements.every(
        (r) => evaluateRequirement(r, player, nodeTitleLookup).met,
    );
    return allMet ? 'available' : 'locked';
}

export const MAP_CONFIG: WorldBiome[] = [
    {
        id: 'origin-springs',
        name: 'The Origin Springs',
        theme: {
            primary: '#10b981', // Emerald
            secondary: '#ecfdf5',
            accent: '#34d399',
            background: 'bg-emerald-50/30',
        },
        nodes: [
            {
                id: 'os-1',
                title: 'The Call to Logic',
                type: 'lesson',
                x: 100,
                y: 100,
            },
            {
                id: 'os-2',
                title: 'Variable Valley',
                type: 'lesson',
                x: 300,
                y: 150,
                dependsOn: ['os-1'],
            },
            {
                id: 'os-3',
                title: 'Conditional Caves',
                type: 'lesson',
                x: 500,
                y: 100,
                dependsOn: ['os-2'],
            },
            {
                id: 'os-boss',
                title: 'Guardian of Syntax',
                type: 'boss',
                x: 750,
                y: 200,
                dependsOn: ['os-3'],
            },
        ],
    },
    {
        id: 'crystal-peak',
        name: 'The Crystal Peak',
        theme: {
            primary: '#3b82f6', // Blue
            secondary: '#eff6ff',
            accent: '#60a5fa',
            background: 'bg-blue-50/30',
        },
        nodes: [
            {
                id: 'cp-1',
                title: 'Array Ascent',
                type: 'lesson',
                x: 100,
                y: 300,
            },
            {
                id: 'cp-2',
                title: 'Object Overlook',
                type: 'lesson',
                x: 350,
                y: 250,
                dependsOn: ['cp-1'],
            },
            {
                id: 'cp-boss',
                title: 'The Loop Master',
                type: 'boss',
                x: 600,
                y: 400,
                dependsOn: ['cp-2'],
            },
        ],
    },
    {
        id: 'sunken-library',
        name: 'The Sunken Library',
        theme: {
            primary: '#0d9488', // Teal
            secondary: '#f0fdfa',
            accent: '#2dd4bf',
            background: 'bg-teal-50/30',
        },
        nodes: [
            {
                id: 'sl-1',
                title: 'Data Depths',
                type: 'lesson',
                x: 200,
                y: 100,
            },
            {
                id: 'sl-boss',
                title: 'The Archivist',
                type: 'boss',
                x: 500,
                y: 300,
                dependsOn: ['sl-1'],
            },
        ],
    },
    {
        id: 'celestial-spire',
        name: 'The Celestial Spire',
        theme: {
            primary: '#8b5cf6', // Violet
            secondary: '#f5f3ff',
            accent: '#a78bfa',
            background: 'bg-violet-50/30',
        },
        nodes: [
            {
                id: 'cs-1',
                title: 'Async Asteroids',
                type: 'lesson',
                x: 150,
                y: 400,
            },
            {
                id: 'cs-boss',
                title: 'Event Horizon',
                type: 'boss',
                x: 450,
                y: 150,
                dependsOn: ['cs-1'],
            },
        ],
    },
    {
        id: 'hall-echoes',
        name: 'The Hall of Echoes',
        theme: {
            primary: '#f43f5e', // Rose
            secondary: '#fff1f2',
            accent: '#fb7185',
            background: 'bg-rose-50/30',
        },
        nodes: [
            {
                id: 'final-exam',
                title: 'The Great Synthesis',
                type: 'boss',
                x: 400,
                y: 300,
            },
        ],
    },
];
