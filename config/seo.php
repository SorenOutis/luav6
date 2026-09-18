<?php

return [

    'site_name' => env('SEO_SITE_NAME', 'LSI'),

    'tagline' => env('SEO_TAGLINE', 'School-ready online assessment, exams, and assignments'),

    'description' => env(
        'SEO_DESCRIPTION',
        'LSI is a school-ready learning platform for exams, assignments, grades, and AI feedback — with a clear path for every learner.',
    ),

    'site_url' => env('APP_URL', 'http://localhost'),

    'og_image' => env('SEO_OG_IMAGE', '/brand/og-cover.png'),

    'locale' => env('SEO_LOCALE', 'en_US'),

    'twitter_site' => env('SEO_TWITTER_SITE', ''),
];
