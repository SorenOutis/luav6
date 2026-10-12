<?php

return [
    // Start time uses app.timezone; reports cover the previous complete local day.
    'time' => env('AI_DAILY_REPORT_TIME', '00:15'),
    'queue' => 'ai',
    'max_output_tokens' => 2000,
    'max_prompt_characters' => 50000,
    'timeout' => 45,
];
