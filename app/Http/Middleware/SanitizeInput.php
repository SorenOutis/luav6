<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;
use Illuminate\Http\Request;

class SanitizeInput extends TransformsRequest
{
    /**
     * The attributes that should not be sanitized.
     *
     * @var array<int, string>
     */
    protected $except = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
    ];

    /**
     * Transform the given value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function transform($key, $value)
    {
        if (in_array($key, $this->except, true) || ! is_string($value)) {
            return $value;
        }

        return $this->cleanXSS($value);
    }

    /**
     * Sanitize string to prevent XSS.
     *
     * @param  string  $value
     * @return string
     */
    protected function cleanXSS(string $value): string
    {
        // 1. Remove <script> tags and their contents
        $value = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $value);
        
        // 2. Remove on* event handlers (e.g. onclick, onload, etc.)
        // This regex is more comprehensive for event handlers
        $value = preg_replace('/on\w+\s*=\s*(["\'])(.*?)\1/is', '', $value);
        $value = preg_replace('/on\w+\s*=\s*[^\s>]+/is', '', $value);

        // 3. Remove javascript: pseudo-protocol
        // We want to remove the entire attribute value if it starts with javascript:
        if (preg_match('/^\s*javascript:/i', $value)) {
            return '';
        }

        // 4. Use strip_tags for everything else to be safe
        return strip_tags($value);
    }
}
