<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TopicResearchService
{
    private const USER_AGENT = 'LSI-Learning-Platform/1.0 (contact@lsi.edu; educational AI assistant)';

    private const TIMEOUT_SECONDS = 6;

    /**
     * Research an academic topic online via Wikipedia and educational summaries.
     * Returns structured, clean factual text suitable for exam and question generation.
     */
    public function research(string $topic): ?string
    {
        $topic = trim($topic);
        if ($topic === '') {
            return null;
        }

        try {
            // 1. Try direct summary first
            $summary = $this->fetchSummary($topic);
            if ($summary && mb_strlen($summary) > 50) {
                return $summary;
            }

            // 2. Search for the best matching article
            $bestTitle = $this->searchBestTitle($topic);
            if ($bestTitle && strcasecmp($bestTitle, $topic) !== 0) {
                $summary = $this->fetchSummary($bestTitle);
                if ($summary && mb_strlen($summary) > 50) {
                    return $summary;
                }
            }

            // 3. Fetch extract with intro
            if ($bestTitle) {
                $extract = $this->fetchExtract($bestTitle);
                if ($extract && mb_strlen($extract) > 50) {
                    return $extract;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Topic research network error: '.$e->getMessage(), [
                'topic' => $topic,
            ]);
        }

        return null;
    }

    private function fetchSummary(string $title): ?string
    {
        $url = 'https://en.wikipedia.org/api/rest_v1/page/summary/'.rawurlencode($title);
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(self::TIMEOUT_SECONDS)
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $extract = trim((string) ($data['extract'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($extract === '') {
            return null;
        }

        $out = "Topic: {$title}\n";
        if ($description !== '') {
            $out .= "Overview: {$description}\n\n";
        }
        $out .= $extract;

        return $out;
    }

    private function searchBestTitle(string $query): ?string
    {
        $url = 'https://en.wikipedia.org/w/api.php?action=query&list=search&srsearch='.urlencode($query).'&format=json';
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(self::TIMEOUT_SECONDS)
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $results = $response->json()['query']['search'] ?? [];
        if (empty($results)) {
            return null;
        }

        return (string) ($results[0]['title'] ?? '');
    }

    private function fetchExtract(string $title): ?string
    {
        $url = 'https://en.wikipedia.org/w/api.php?action=query&prop=extracts&exintro=1&explaintext=1&titles='.urlencode($title).'&format=json';
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(self::TIMEOUT_SECONDS)
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $pages = $response->json()['query']['pages'] ?? [];
        $first = reset($pages);

        $extract = trim((string) ($first['extract'] ?? ''));

        return $extract !== '' ? "Topic: {$title}\n\n{$extract}" : null;
    }
}
