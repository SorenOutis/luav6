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
     * Regenerate the Graphify codebase graph.
     *
     * @return array{success: bool, output: string}
     */
    public static function generate(): array
    {
        $commands = [
            ['uv', 'tool', 'run', '--from', 'graphifyy', 'graphify', 'extract', '.', '--code-only'],
            ['uv', 'tool', 'run', '--from', 'graphifyy', 'graphify', 'cluster-only', '.', '--no-label'],
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

        return [
            'success' => true,
            'output' => $combinedOutput,
        ];
    }
}
