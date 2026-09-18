<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonQuiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Seed demo courses with modules, lessons, and quizzes.
     */
    public function run(): void
    {
        $admin = User::where('is_admin', true)->first();
        $adminId = $admin?->id ?? 1;

        // ─── Course 1: Introduction to Computer Science ───
        $course1 = Course::firstOrCreate(
            ['name' => 'Introduction to Computer Science'],
            [
                'description' => 'A beginner-friendly course covering the fundamentals of computer science, including algorithms, data structures, and computational thinking.',
                'total_lessons' => 6,
                'admin_id' => $adminId,
            ]
        );

        $mod1 = CourseModule::firstOrCreate(
            ['course_id' => $course1->id, 'title' => 'Foundations of Computing'],
            ['description' => 'Learn the basic concepts of computing and how computers work.', 'sort_order' => 1, 'admin_id' => $adminId]
        );

        $this->createLessonWithQuiz($mod1->id, 'What is Computer Science?', 1, $adminId, [
            'question' => 'What is the primary focus of computer science?',
            'options' => [
                ['text' => 'The study of computers and computational systems', 'is_correct' => true],
                ['text' => 'The study of how to repair computers', 'is_correct' => false],
                ['text' => 'The study of computer hardware only', 'is_correct' => false],
                ['text' => 'The study of programming languages only', 'is_correct' => false],
            ],
        ]);

        $this->createLessonWithQuiz($mod1->id, 'Binary & Data Representation', 2, $adminId, [
            'question' => 'What is the base of the binary number system?',
            'options' => [
                ['text' => '2', 'is_correct' => true],
                ['text' => '8', 'is_correct' => false],
                ['text' => '10', 'is_correct' => false],
                ['text' => '16', 'is_correct' => false],
            ],
        ]);

        $mod2 = CourseModule::firstOrCreate(
            ['course_id' => $course1->id, 'title' => 'Algorithms & Problem Solving'],
            ['description' => 'Explore how algorithms help solve problems efficiently.', 'sort_order' => 2, 'admin_id' => $adminId]
        );

        $this->createLessonWithQuiz($mod2->id, 'What are Algorithms?', 1, $adminId, [
            'question' => 'What is an algorithm?',
            'options' => [
                ['text' => 'A step-by-step procedure for solving a problem', 'is_correct' => true],
                ['text' => 'A type of computer hardware', 'is_correct' => false],
                ['text' => 'A programming language', 'is_correct' => false],
                ['text' => 'An operating system', 'is_correct' => false],
            ],
        ]);

        $this->createLessonWithQuiz($mod2->id, 'Sorting & Searching', 2, $adminId, [
            'question' => 'Which sorting algorithm has an average time complexity of O(n log n)?',
            'options' => [
                ['text' => 'Merge Sort', 'is_correct' => true],
                ['text' => 'Bubble Sort', 'is_correct' => false],
                ['text' => 'Selection Sort', 'is_correct' => false],
                ['text' => 'Insertion Sort', 'is_correct' => false],
            ],
        ]);

        // ─── Course 2: Web Development Basics ───
        $course2 = Course::firstOrCreate(
            ['name' => 'Web Development Basics'],
            [
                'description' => 'Learn the fundamentals of web development including HTML, CSS, and JavaScript. Build your first website from scratch.',
                'total_lessons' => 6,
                'admin_id' => $adminId,
            ]
        );

        $mod3 = CourseModule::firstOrCreate(
            ['course_id' => $course2->id, 'title' => 'HTML & CSS Fundamentals'],
            ['description' => 'Master the building blocks of the web.', 'sort_order' => 1, 'admin_id' => $adminId]
        );

        $this->createLessonWithQuiz($mod3->id, 'Introduction to HTML', 1, $adminId, [
            'question' => 'What does HTML stand for?',
            'options' => [
                ['text' => 'HyperText Markup Language', 'is_correct' => true],
                ['text' => 'HyperText Modeling Language', 'is_correct' => false],
                ['text' => 'High Tech Modern Language', 'is_correct' => false],
                ['text' => 'Home Tool Markup Language', 'is_correct' => false],
            ],
        ]);

        $this->createLessonWithQuiz($mod3->id, 'Styling with CSS', 2, $adminId, [
            'question' => 'Which CSS property is used to change the text color?',
            'options' => [
                ['text' => 'color', 'is_correct' => true],
                ['text' => 'text-color', 'is_correct' => false],
                ['text' => 'font-color', 'is_correct' => false],
                ['text' => 'text-style', 'is_correct' => false],
            ],
        ]);

        $mod4 = CourseModule::firstOrCreate(
            ['course_id' => $course2->id, 'title' => 'JavaScript Essentials'],
            ['description' => 'Bring your web pages to life with JavaScript.', 'sort_order' => 2, 'admin_id' => $adminId]
        );

        $this->createLessonWithQuiz($mod4->id, 'JavaScript Variables & Data Types', 1, $adminId, [
            'question' => 'Which keyword is used to declare a constant variable in JavaScript?',
            'options' => [
                ['text' => 'const', 'is_correct' => true],
                ['text' => 'let', 'is_correct' => false],
                ['text' => 'var', 'is_correct' => false],
                ['text' => 'static', 'is_correct' => false],
            ],
        ]);

        // ─── Course 3: Mathematics Fundamentals ───
        $course3 = Course::firstOrCreate(
            ['name' => 'Mathematics Fundamentals'],
            [
                'description' => 'Build a strong foundation in mathematics covering algebra, geometry, and basic calculus concepts.',
                'total_lessons' => 4,
                'admin_id' => $adminId,
            ]
        );

        $mod5 = CourseModule::firstOrCreate(
            ['course_id' => $course3->id, 'title' => 'Algebra & Equations'],
            ['description' => 'Master the basics of algebra.', 'sort_order' => 1, 'admin_id' => $adminId]
        );

        $this->createLessonWithQuiz($mod5->id, 'Linear Equations', 1, $adminId, [
            'question' => 'What is the slope-intercept form of a linear equation?',
            'options' => [
                ['text' => 'y = mx + b', 'is_correct' => true],
                ['text' => 'y = ax² + bx + c', 'is_correct' => false],
                ['text' => 'y = 1/x', 'is_correct' => false],
                ['text' => 'y = |x|', 'is_correct' => false],
            ],
        ]);

        $mod6 = CourseModule::firstOrCreate(
            ['course_id' => $course3->id, 'title' => 'Geometry'],
            ['description' => 'Explore shapes, angles, and spatial relationships.', 'sort_order' => 2, 'admin_id' => $adminId]
        );

        $this->createLessonWithQuiz($mod6->id, 'Triangles & The Pythagorean Theorem', 1, $adminId, [
            'question' => 'In a right triangle, what is a² + b² equal to?',
            'options' => [
                ['text' => 'c² (where c is the hypotenuse)', 'is_correct' => true],
                ['text' => '2ab', 'is_correct' => false],
                ['text' => '(a + b)²', 'is_correct' => false],
                ['text' => 'a + b', 'is_correct' => false],
            ],
        ]);

        $this->command->info('Seeded 3 demo courses with modules, lessons, and quizzes.');
    }

    /**
     * Helper to create a lesson with a quiz.
     */
    private function createLessonWithQuiz(int $moduleId, string $title, int $sortOrder, int $adminId, array $quizData): void
    {
        $lesson = Lesson::firstOrCreate(
            ['course_module_id' => $moduleId, 'title' => $title],
            [
                'content' => "<h2>{$title}</h2><p>This is a sample lesson for <strong>{$title}</strong>. In a real deployment, this content would contain rich educational material including text, images, and interactive elements.</p><p>Topics covered in this lesson include foundational concepts, practical examples, and hands-on exercises to reinforce learning.</p><h3>Key Takeaways</h3><ul><li>Understand the core concepts</li><li>Apply knowledge to practical scenarios</li><li>Test your understanding with the quiz below</li></ul>",
                'sort_order' => $sortOrder,
                'admin_id' => $adminId,
            ]
        );

        LessonQuiz::firstOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'questions' => [
                    [
                        'question' => $quizData['question'],
                        'options' => $quizData['options'],
                    ],
                ],
                'pass_score' => 75,
                'allowed_attempts' => 0, // unlimited
            ]
        );
    }
}
