<?php

namespace App\Console\Commands;

use App\Support\GraphifyService;
use Illuminate\Console\Command;

class GenerateGraphifyGraph extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'graphify:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract and generate codebase architecture knowledge graph via Graphify';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Graphify codebase extraction and clustering...');

        $result = GraphifyService::generate();

        $this->line($result['output']);

        if (! $result['success']) {
            $this->error('Failed to generate Graphify architecture graph.');

            return self::FAILURE;
        }

        $stats = GraphifyService::getStats();
        $this->info("Graphify graph successfully generated! Nodes: {$stats['nodes']}, Edges: {$stats['edges']}, Communities: {$stats['communities']}");

        return self::SUCCESS;
    }
}
