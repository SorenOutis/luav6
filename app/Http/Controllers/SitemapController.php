<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SitemapController extends Controller
{
    public function __invoke(Request $request)
    {
        $base = rtrim((string) config('app.url'), '/');

        $pages = [
            '/' => ['1.0', 'weekly', $this->lastmodFor('js/pages/Welcome.vue')],
            '/about' => ['0.6', 'monthly', $this->lastmodFor('js/pages/About.vue')],
            '/how-it-works' => ['0.7', 'monthly', $this->lastmodFor('js/pages/HowItWorks.vue')],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";

        foreach ($pages as $path => [$priority, $frequency, $lastmod]) {
            $loc = $path === '/' ? $base.'/' : $base.$path;
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($loc)."</loc>\n";
            $xml .= '    <lastmod>'.$lastmod."</lastmod>\n";
            $xml .= "    <changefreq>{$frequency}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            if ($path === '/') {
                $xml .= '    <image:image><image:loc>'.e($base.'/brand/og-cover.png').'</image:loc><image:caption>LSI — Make every assessment count</image:caption></image:image>'."\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function lastmodFor(string $relative): string
    {
        $path = resource_path($relative);

        if (is_file($path)) {
            return date('Y-m-d', filemtime($path));
        }

        // Fallback to git log or now if file missing (e.g., in build)
        $gitDate = trim((string) shell_exec('git log -1 --format=%cs -- '.escapeshellarg($path).' 2>&1'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $gitDate)) {
            return $gitDate;
        }

        return now()->format('Y-m-d');
    }
}
