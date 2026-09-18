<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class BlogController extends Controller
{
    public function pillar()
    {
        return Inertia::render('Blog/AssessmentToNextLesson');
    }
}
