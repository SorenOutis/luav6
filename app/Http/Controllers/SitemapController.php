<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SitemapController extends Controller
{
    public function __invoke(Request $request)
    {
        $base = rtrim((string) config('app.url'), '/');
        $now = now()->format('Y-m-d');

        $pages = [
            '/' => ['1.0', 'weekly', $now],
            '/about' => ['0.6', 'monthly', $now],
            '/how-it-works' => ['0.7', 'monthly', $now],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($pages as $path => [$priority, $frequency, $lastmod]) {
            $loc = $path === '/' ? $base.'/' : $base.$path;
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($loc)."</loc>\n";
            $xml .= '    <lastmod>'.$lastmod."</lastmod>\n";
            $xml .= "    <changefreq>{$frequency}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
