<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class GraphifyService
{
    public const OUT_DIR = 'graphify-out';

    public static function basePath(string $file = ''): string
    {
        return base_path(self::OUT_DIR.($file !== '' ? DIRECTORY_SEPARATOR.$file : ''));
    }

    public static function hasGraph(): bool
    {
        return File::exists(self::basePath('graph.html'))
            && File::exists(self::basePath('graph.json'));
    }

    public static function lastModified(): ?string
    {
        if (! File::exists(self::basePath('graph.html'))) {
            return null;
        }

        return date('Y-m-d H:i:s', File::lastModified(self::basePath('graph.html')));
    }

    /**
     * @return array{
     *     nodes: int,
     *     edges: int,
     *     communities: int,
     *     subsystems: int,
     *     extracted_pct: string,
     *     inferred_edges: int,
     *     commit: ?string,
     *     last_updated: ?string,
     *     top_nodes: array<int, array{name: string, edges: int}>
     * }
     */
    public static function getStats(): array
    {
        $stats = [
            'nodes' => 15985,
            'edges' => 40568,
            'communities' => 489,
            'subsystems' => 489,
            'extracted_pct' => '94%',
            'inferred_edges' => 2455,
            'commit' => null,
            'last_updated' => self::lastModified(),
            'top_nodes' => [
                ['name' => 'User', 'edges' => 472],
                ['name' => 'vue', 'edges' => 235],
                ['name' => 'Exam', 'edges' => 183],
                ['name' => 'Section', 'edges' => 164],
                ['name' => 'PendingAiAction', 'edges' => 127],
                ['name' => 'lucide-vue-next', 'edges' => 102],
                ['name' => '@inertiajs/vue3', 'edges' => 101],
                ['name' => 'Season', 'edges' => 98],
                ['name' => 'Workspace', 'edges' => 97],
                ['name' => 'Assignment', 'edges' => 90],
                ['name' => 'Setting', 'edges' => 83],
                ['name' => 'ExamSubmission', 'edges' => 79],
                ['name' => 'ExamPart', 'edges' => 78],
            ],
        ];

        $reportPath = self::basePath('GRAPH_REPORT.md');
        if (File::exists($reportPath)) {
            $content = File::get($reportPath);

            // Extract summary line: 15985 nodes · 40568 edges · 489 communities
            if (preg_match('/(\d+)\s+nodes\s+·\s+(\d+)\s+edges\s+·\s+(\d+)\s+communities/', $content, $m)) {
                $stats['nodes'] = (int) $m[1];
                $stats['edges'] = (int) $m[2];
                $stats['communities'] = (int) $m[3];
                $stats['subsystems'] = (int) $m[3];
            }

            // Extract commit: Built from commit: `cddda8b0`
            if (preg_match('/commit:\s*`([^`]+)`/', $content, $m)) {
                $stats['commit'] = $m[1];
            }

            // Extract Extraction %: Extraction: 94% EXTRACTED
            if (preg_match('/Extraction:\s*([0-9]+%)/', $content, $m)) {
                $stats['extracted_pct'] = $m[1];
            }

            // Inferred edges: INFERRED: 2455 edges
            if (preg_match('/INFERRED:\s*(\d+)\s+edges/', $content, $m)) {
                $stats['inferred_edges'] = (int) $m[1];
            }
        }

        return $stats;
    }

    /**
     * Platform Architecture Domain Definitions.
     *
     * @return array<string, array{
     *     slug: string,
     *     name: string,
     *     icon: string,
     *     badge: string,
     *     color: string,
     *     description: string
     * }>
     */
    public static function getDomainDefinitions(): array
    {
        return [
            'ai_studio' => [
                'slug' => 'ai_studio',
                'name' => 'AI Studio & Echo Agent',
                'icon' => 'heroicon-o-sparkles',
                'badge' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400 border-indigo-200 dark:border-indigo-500/20',
                'color' => 'indigo',
                'description' => 'Echo AI agent runtime, tool executors, action approvals, and prompt guardrails.',
            ],
            'academic_assessments' => [
                'slug' => 'academic_assessments',
                'name' => 'Academic & Assessment Engine',
                'icon' => 'heroicon-o-academic-cap',
                'badge' => 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400 border-blue-200 dark:border-blue-500/20',
                'color' => 'blue',
                'description' => 'Exams, question sets, proctoring blocks, submissions, auto-scoring, and rubric tasks.',
            ],
            'courseware' => [
                'slug' => 'courseware',
                'name' => 'Courseware & Learning Content',
                'icon' => 'heroicon-o-book-open',
                'badge' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400 border-cyan-200 dark:border-cyan-500/20',
                'color' => 'cyan',
                'description' => 'Courses, modules, lessons, downloadable learning materials, and lesson quizzes.',
            ],
            'gamification' => [
                'slug' => 'gamification',
                'name' => 'Gamification & Season Economy',
                'icon' => 'heroicon-o-trophy',
                'badge' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 border-amber-200 dark:border-amber-500/20',
                'color' => 'amber',
                'description' => 'Season XP progression curve, daily claim streaks, badges, shop rewards, and leaderboards.',
            ],
            'student_portal' => [
                'slug' => 'student_portal',
                'name' => 'Student Portal & Workspaces',
                'icon' => 'heroicon-o-user-group',
                'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20',
                'color' => 'emerald',
                'description' => 'Multi-tenant workspace contexts, student dashboard, calendar, activity hub, and assignments.',
            ],
            'admin_filament' => [
                'slug' => 'admin_filament',
                'name' => 'Filament Admin Studio',
                'icon' => 'heroicon-o-squares-2x2',
                'badge' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border-rose-200 dark:border-rose-500/20',
                'color' => 'rose',
                'description' => 'Super-admin & workspace back-office resources, forms, tables, metric widgets, and relation managers.',
            ],
            'security_auth' => [
                'slug' => 'security_auth',
                'name' => 'Identity, Security & Auth',
                'icon' => 'heroicon-o-shield-check',
                'badge' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400 border-red-200 dark:border-red-500/20',
                'color' => 'red',
                'description' => 'Fortify authentication, user identity, roles, impersonation, maintenance mode, and security headers.',
            ],
            'ui_design_system' => [
                'slug' => 'ui_design_system',
                'name' => 'Frontend UI & Design System',
                'icon' => 'heroicon-o-paint-brush',
                'badge' => 'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400 border-purple-200 dark:border-purple-500/20',
                'color' => 'purple',
                'description' => 'Vue 3 / Reka UI primitives, Tailwind design tokens, layouts, alerts, dialogs, and animations.',
            ],
            'interactive_games' => [
                'slug' => 'interactive_games',
                'name' => 'Interactive Games & Modules',
                'icon' => 'heroicon-o-puzzle-piece',
                'badge' => 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400 border-orange-200 dark:border-orange-500/20',
                'color' => 'orange',
                'description' => 'Tower defense gamified learning engine, wave controllers, balance stats, and Pixi/Canvas graphics.',
            ],
            'core_infrastructure' => [
                'slug' => 'core_infrastructure',
                'name' => 'Data Layer & Infrastructure',
                'icon' => 'heroicon-o-server-stack',
                'badge' => 'bg-slate-50 text-slate-700 dark:bg-slate-500/10 dark:text-slate-400 border-slate-200 dark:border-slate-500/20',
                'color' => 'slate',
                'description' => 'Eloquent models, LibSQL SQLite/Turso client, caching, queues, events, seeders, and utilities.',
            ],
        ];
    }

    /**
     * Organize communities into domain architecture subsystems with semantic names.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function organizeCommunities(): array
    {
        $analysisPath = self::basePath('.graphify_analysis.json');
        $graphPath = self::basePath('graph.json');

        if (! File::exists($analysisPath) || ! File::exists($graphPath)) {
            return [];
        }

        $analysis = json_decode(File::get($analysisPath), true);
        $graph = json_decode(File::get($graphPath), true);

        if (! is_array($analysis) || ! isset($analysis['communities']) || ! is_array($graph) || ! isset($graph['nodes'])) {
            return [];
        }

        $nodeMap = [];
        foreach ($graph['nodes'] as $node) {
            $nodeMap[$node['id']] = $node;
        }

        $cohesion = $analysis['cohesion'] ?? [];
        $domainDefs = self::getDomainDefinitions();

        $labels = [];
        $subsystems = [];

        foreach ($analysis['communities'] as $cid => $members) {
            $cIdInt = (int) $cid;
            $count = count($members);

            $domainScores = array_fill_keys(array_keys($domainDefs), 0);
            $dirCounts = [];
            $symbols = [];
            $pascals = [];
            $filenames = [];

            foreach ($members as $m) {
                $node = $nodeMap[$m] ?? null;
                if (! $node) {
                    continue;
                }

                $sf = str_replace('\\', '/', $node['source_file'] ?? '');
                $lbl = $node['label'] ?? '';

                $cleanLbl = ltrim($lbl, '.');
                $cleanLbl = preg_replace('/\(\)$/', '', $cleanLbl);
                $cleanLbl = preg_replace('/\.(php|vue|ts|js)$/', '', $cleanLbl);

                if (strlen($cleanLbl) > 1 && ! str_starts_with($cleanLbl, '{closure') && ! in_array($cleanLbl, ['vue', 'Stringable', 't', 'update', 'props', 'Il', 'Ac', 'aa', 'd', 'e', 'n'], true)) {
                    $symbols[] = $cleanLbl;
                    if (preg_match('/^[A-Z][a-zA-Z0-9]+$/', $cleanLbl) && ! in_array($cleanLbl, ['Model', 'Schema', 'Action', 'Page', 'Props', 'Stringable', 'Component'], true)) {
                        $pascals[$cleanLbl] = ($pascals[$cleanLbl] ?? 0) + 1;
                    }
                }

                if ($sf !== '') {
                    $base = basename($sf);
                    $cleanBase = preg_replace('/\.(php|vue|ts|js|css)$/', '', $base);
                    if ($cleanBase && ! in_array($cleanBase, ['index', 'app', 'types', 'utils'], true)) {
                        $filenames[$cleanBase] = ($filenames[$cleanBase] ?? 0) + 1;
                    }

                    $dir = dirname($sf);
                    $dirCounts[$dir] = ($dirCounts[$dir] ?? 0) + 1;

                    if (str_contains($sf, 'TowerDefense') || str_contains($sf, 'Games/')) {
                        $domainScores['interactive_games'] += 5;
                    } elseif (str_contains($sf, 'app/Ai/') || str_contains($sf, 'AiAction') || str_contains($sf, 'Chats.vue') || str_contains($sf, 'AiSettings') || str_contains($sf, 'AdminAiChat') || str_contains($sf, 'AiQuestionDraft') || str_contains($sf, 'PlatformKnowledge')) {
                        $domainScores['ai_studio'] += 5;
                    } elseif (str_contains($sf, 'Exam') || str_contains($sf, 'Grade') || str_contains($sf, 'Scores') || str_contains($sf, 'Quiz') || str_contains($sf, 'ActivityTask') || str_contains($sf, 'Rubric')) {
                        $domainScores['academic_assessments'] += 4;
                    } elseif (str_contains($sf, 'Course') || str_contains($sf, 'Lesson') || str_contains($sf, 'LearningMaterial') || str_contains($sf, 'Module')) {
                        $domainScores['courseware'] += 4;
                    } elseif (str_contains($sf, 'Season') || str_contains($sf, 'Streak') || str_contains($sf, 'Badge') || str_contains($sf, 'LevelCurve') || str_contains($sf, 'Reward') || str_contains($sf, 'Shop') || str_contains($sf, 'Leaderboard') || str_contains($sf, 'Merch') || str_contains($sf, 'Soundtrack')) {
                        $domainScores['gamification'] += 4;
                    } elseif (str_contains($sf, 'Fortify') || str_contains($sf, 'Auth') || str_contains($sf, 'Security') || str_contains($sf, 'Impersonation') || str_contains($sf, 'Maintenance') || str_contains($sf, 'Workspace') || str_contains($sf, 'User')) {
                        $domainScores['security_auth'] += 3;
                    } elseif (str_contains($sf, 'components/ui/') || str_contains($sf, 'resources/css/')) {
                        $domainScores['ui_design_system'] += 4;
                    } elseif (str_contains($sf, 'app/Filament/') || str_contains($sf, 'filament/')) {
                        $domainScores['admin_filament'] += 4;
                    } elseif (str_contains($sf, 'resources/js/')) {
                        $domainScores['student_portal'] += 2;
                    } else {
                        $domainScores['core_infrastructure'] += 2;
                    }
                }
            }

            arsort($domainScores);
            $domain = array_key_first($domainScores);
            if ($domainScores[$domain] === 0) {
                $domain = 'core_infrastructure';
            }

            arsort($pascals);
            arsort($filenames);
            arsort($dirCounts);

            $topPascal = array_key_first($pascals);
            $topFile = array_key_first($filenames);

            if ($topPascal && ($pascals[$topPascal] >= 2 || ! $topFile)) {
                $rawTitle = $topPascal;
            } elseif ($topFile) {
                $rawTitle = $topFile;
            } elseif ($topPascal) {
                $rawTitle = $topPascal;
            } else {
                $rawTitle = $symbols[0] ?? "Subsystem {$cid}";
            }

            $title = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $rawTitle);
            $title = ucwords(str_replace(['-', '_'], ' ', $title));

            if ($title === 'Code Editor') {
                $title = 'Filament Code Editor';
            }
            if ($title === 'Rich Editor') {
                $title = 'Filament Rich Text Editor';
            }
            if ($title === 'Chart') {
                $title = 'Filament Chart Visualizer';
            }
            if ($title === 'Show') {
                $title = 'Resource Viewer';
            }

            $labels[(string) $cid] = $title;
            $subsystems[] = [
                'id' => $cIdInt,
                'name' => $title,
                'domain' => $domain,
                'domain_label' => $domainDefs[$domain]['name'],
                'badge' => $domainDefs[$domain]['badge'],
                'node_count' => $count,
                'cohesion' => (float) ($cohesion[(string) $cid] ?? 0.01),
                'hub' => $symbols[0] ?? $title,
                'top_symbols' => array_slice(array_unique($symbols), 0, 4),
                'top_dirs' => array_slice(array_keys($dirCounts), 0, 2),
            ];
        }

        // 1. Cache structured subsystems
        File::put(self::basePath('subsystems.json'), json_encode($subsystems, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. Write labels file for Graphify
        File::put(self::basePath('.graphify_labels.json'), json_encode($labels, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 3. Inject organized subsystem names into graph.json nodes
        $graphModified = false;
        foreach ($graph['nodes'] as &$node) {
            if (isset($node['community'])) {
                $cid = (string) $node['community'];
                if (isset($labels[$cid])) {
                    $node['community_name'] = $labels[$cid];
                    $graphModified = true;
                }
            }
        }
        unset($node);

        if ($graphModified) {
            File::put($graphPath, json_encode($graph, JSON_UNESCAPED_SLASHES));
        }

        // 4. Inject organized subsystem names into graph.html
        $htmlPath = self::basePath('graph.html');
        if (File::exists($htmlPath)) {
            $html = File::get($htmlPath);
            if (preg_match('/const RAW_NODES = (\[.*?\]);/s', $html, $m)) {
                $rawNodes = json_decode($m[1], true);
                if (is_array($rawNodes)) {
                    foreach ($rawNodes as &$node) {
                        $cid = (string) ($node['community'] ?? $node['id'] ?? '');
                        if (isset($labels[$cid])) {
                            $name = $labels[$cid];
                            $node['label'] = $name;
                            $node['title'] = $name;
                            $node['community_name'] = $name;
                        }
                    }
                    unset($node);

                    $replacement = 'const RAW_NODES = '.json_encode($rawNodes, JSON_UNESCAPED_SLASHES).';';
                    $html = str_replace($m[0], $replacement, $html);

                    // Ensure high-performance baseline interaction settings
                    $html = str_replace(
                        "hideEdgesOnDrag: true,\n    navigationButtons: false,",
                        "hideEdgesOnDrag: true,\n    hideEdgesOnZoom: true,\n    hoverConnectedEdges: false,\n    tooltipDelay: 250,\n    navigationButtons: false,",
                        $html
                    );

                    File::put($htmlPath, $html);
                }
            }
        }

        // 5. Inject organized subsystem names into GRAPH_REPORT.md
        $reportPath = self::basePath('GRAPH_REPORT.md');
        if (File::exists($reportPath)) {
            $report = File::get($reportPath);
            foreach ($labels as $cid => $label) {
                $report = preg_replace(
                    "/### Community {$cid} - \"[^\"]*\"/",
                    "### Subsystem {$cid} - \"{$label}\"",
                    $report
                );
                $report = preg_replace(
                    "/^- Community {$cid}\r?$/m",
                    "- Subsystem {$cid}: {$label}",
                    $report
                );
            }
            $report = str_replace('## Community Hubs (Navigation)', '## Architecture Subsystems (Navigation)', $report);
            $report = str_replace('## Communities (', '## Architecture Subsystems (', $report);
            File::put($reportPath, $report);
        }

        return $subsystems;
    }

    /**
     * Get organized subsystems with optional domain and search filtering.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getSubsystems(?string $domain = null, ?string $search = null): array
    {
        $cachePath = self::basePath('subsystems.json');
        if (! File::exists($cachePath)) {
            $subsystems = self::organizeCommunities();
        } else {
            $subsystems = json_decode(File::get($cachePath), true) ?? [];
        }

        if ($domain !== null && $domain !== '' && $domain !== 'all') {
            $subsystems = array_values(array_filter($subsystems, function (array $s) use ($domain): bool {
                return ($s['domain'] ?? '') === $domain;
            }));
        }

        if ($search !== null && trim($search) !== '') {
            $term = mb_strtolower(trim($search));
            $subsystems = array_values(array_filter($subsystems, function (array $s) use ($term): bool {
                if (str_contains(mb_strtolower($s['name'] ?? ''), $term)) {
                    return true;
                }
                if (str_contains(mb_strtolower($s['domain_label'] ?? ''), $term)) {
                    return true;
                }
                if (str_contains(mb_strtolower($s['hub'] ?? ''), $term)) {
                    return true;
                }
                foreach ($s['top_symbols'] ?? [] as $sym) {
                    if (str_contains(mb_strtolower($sym), $term)) {
                        return true;
                    }
                }
                foreach ($s['top_dirs'] ?? [] as $dir) {
                    if (str_contains(mb_strtolower($dir), $term)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        usort($subsystems, function (array $a, array $b): int {
            return ($b['node_count'] ?? 0) <=> ($a['node_count'] ?? 0);
        });

        return $subsystems;
    }

    /**
     * Get high-level domain summary metrics.
     *
     * @return array<string, array{
     *     slug: string,
     *     name: string,
     *     icon: string,
     *     badge: string,
     *     color: string,
     *     description: string,
     *     subsystems_count: int,
     *     nodes_count: int,
     *     percentage: string,
     *     top_subsystems: array<int, string>
     * }>
     */
    public static function getDomainSummary(): array
    {
        $definitions = self::getDomainDefinitions();
        $subsystems = self::getSubsystems();

        $totalNodes = 0;
        $summary = [];

        foreach ($definitions as $slug => $def) {
            $summary[$slug] = array_merge($def, [
                'subsystems_count' => 0,
                'nodes_count' => 0,
                'percentage' => '0%',
                'top_subsystems' => [],
            ]);
        }

        foreach ($subsystems as $s) {
            $d = $s['domain'] ?? 'core_infrastructure';
            if (isset($summary[$d])) {
                $summary[$d]['subsystems_count']++;
                $summary[$d]['nodes_count'] += ($s['node_count'] ?? 0);
                if (count($summary[$d]['top_subsystems']) < 3 && ! in_array($s['name'], $summary[$d]['top_subsystems'], true)) {
                    $summary[$d]['top_subsystems'][] = $s['name'];
                }
            }
            $totalNodes += ($s['node_count'] ?? 0);
        }

        if ($totalNodes > 0) {
            foreach ($summary as $slug => &$item) {
                $pct = round(($item['nodes_count'] / $totalNodes) * 100, 1);
                $item['percentage'] = "{$pct}%";
            }
            unset($item);
        }

        return $summary;
    }

    /**
     * Get detailed information for a single subsystem.
     *
     * @return array<string, mixed>|null
     */
    public static function getSubsystem(int $id): ?array
    {
        $subsystems = self::getSubsystems();
        $match = null;
        foreach ($subsystems as $s) {
            if (($s['id'] ?? -1) === $id) {
                $match = $s;
                break;
            }
        }

        if (! $match) {
            return null;
        }

        $analysisPath = self::basePath('.graphify_analysis.json');
        $graphPath = self::basePath('graph.json');

        if (File::exists($analysisPath) && File::exists($graphPath)) {
            $analysis = json_decode(File::get($analysisPath), true);
            $graph = json_decode(File::get($graphPath), true);

            $nodeMap = [];
            foreach ($graph['nodes'] ?? [] as $n) {
                $nodeMap[$n['id']] = $n;
            }

            $members = $analysis['communities'][(string) $id] ?? [];
            $nodes = [];
            foreach ($members as $m) {
                if (isset($nodeMap[$m])) {
                    $nodes[] = [
                        'id' => $nodeMap[$m]['id'] ?? $m,
                        'label' => $nodeMap[$m]['label'] ?? '',
                        'file' => $nodeMap[$m]['source_file'] ?? '',
                        'type' => $nodeMap[$m]['file_type'] ?? '',
                    ];
                }
            }

            $match['nodes'] = $nodes;
        }

        return $match;
    }

    public static function getHtmlContent(string $type = 'network'): ?string
    {
        $fileName = match ($type) {
            'tree' => 'GRAPH_TREE.html',
            default => 'graph.html',
        };

        $path = self::basePath($fileName);
        if (! File::exists($path)) {
            return null;
        }

        return File::get($path);
    }

    public static function getReportMarkdown(): ?string
    {
        $path = self::basePath('GRAPH_REPORT.md');
        if (! File::exists($path)) {
            return null;
        }

        return File::get($path);
    }

    /**
     * Regenerate the Graphify codebase graph and organize architectural subsystems.
     *
     * @return array{success: bool, output: string}
     */
    public static function generate(): array
    {
        $commands = [
            ['uv', 'tool', 'run', '--from', 'graphifyy', 'graphify', 'extract', '.', '--code-only'],
            ['uv', 'tool', 'run', '--from', 'graphifyy', 'graphify', 'cluster-only', '.'],
            ['uv', 'tool', 'run', '--from', 'graphifyy', 'graphify', 'tree', '--graph', 'graphify-out/graph.json', '--output', 'graphify-out/GRAPH_TREE.html'],
        ];

        $combinedOutput = '';

        foreach ($commands as $cmd) {
            $process = new Process($cmd, base_path(), null, null, 180);
            $process->run();

            $combinedOutput .= $process->getOutput().$process->getErrorOutput()."\n";

            if (! $process->isSuccessful()) {
                return [
                    'success' => false,
                    'output' => $combinedOutput,
                ];
            }
        }

        // Organize communities into subsystems and refresh labels
        self::organizeCommunities();

        return [
            'success' => true,
            'output' => $combinedOutput,
        ];
    }
}
