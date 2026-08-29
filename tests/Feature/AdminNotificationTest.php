<?php

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AdminNotificationService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Notifications\DatabaseNotification;

test('super admin receives notifications with workspace info', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => true,
        'is_super_admin' => true,
    ]);

    $workspace = Workspace::factory()->create(['name' => 'Math Workspace']);

    AdminNotificationService::notifyAdmins(
        title: 'Test Event',
        body: 'Something happened in math workspace.',
        workspace: $workspace,
    );

    $notification = DatabaseNotification::query()
        ->where('notifiable_id', $superAdmin->id)
        ->get()
        ->first(fn ($n) => str_contains($n->data['title'] ?? '', 'Test Event'));

    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toContain('[Math Workspace]')
        ->and($notification->data['body'])->toContain('Workspace: Math Workspace');
});

test('workspace admin receives notifications only for their workspace', function () {
    $workspaceA = Workspace::factory()->create(['name' => 'Workspace A']);
    $workspaceB = Workspace::factory()->create(['name' => 'Workspace B']);

    $adminA = User::factory()->create([
        'is_admin' => true,
        'is_super_admin' => false,
        'current_workspace_id' => $workspaceA->id,
    ]);
    $adminA->workspaces()->attach($workspaceA->id, ['role' => Workspace::ROLE_ADMIN]);

    $adminB = User::factory()->create([
        'is_admin' => true,
        'is_super_admin' => false,
        'current_workspace_id' => $workspaceB->id,
    ]);
    $adminB->workspaces()->attach($workspaceB->id, ['role' => Workspace::ROLE_ADMIN]);

    AdminNotificationService::notifyAdmins(
        title: 'Workspace A Event',
        body: 'Event in Workspace A',
        workspace: $workspaceA,
    );

    $notificationA = DatabaseNotification::query()
        ->where('notifiable_id', $adminA->id)
        ->get()
        ->first(fn ($n) => str_contains($n->data['title'] ?? '', 'Workspace A Event'));

    $notificationB = DatabaseNotification::query()
        ->where('notifiable_id', $adminB->id)
        ->get()
        ->first(fn ($n) => str_contains($n->data['title'] ?? '', 'Workspace A Event'));

    expect($notificationA)->not->toBeNull()
        ->and($notificationB)->toBeNull();
});

test('registered event triggers notifications', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => true,
        'is_super_admin' => true,
    ]);

    $newUser = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    event(new Registered($newUser));

    $notification = DatabaseNotification::query()
        ->where('notifiable_id', $superAdmin->id)
        ->get()
        ->first(fn ($n) => str_contains($n->data['title'] ?? '', 'New User Registered'));

    expect($notification)->not->toBeNull()
        ->and($notification->data['body'])->toContain('John Doe');
});

test('login event triggers notifications', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => true,
        'is_super_admin' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
    ]);

    event(new Login('web', $user, false));

    $notification = DatabaseNotification::query()
        ->where('notifiable_id', $superAdmin->id)
        ->get()
        ->first(fn ($n) => str_contains($n->data['title'] ?? '', 'User Logged In'));

    expect($notification)->not->toBeNull()
        ->and($notification->data['body'])->toContain('Jane Smith');
});

test('exam submission triggers notifications', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => true,
        'is_super_admin' => true,
    ]);

    $workspace = Workspace::factory()->create(['name' => 'Physics Class']);
    $student = User::factory()->create(['name' => 'Physics Student']);
    $exam = Exam::factory()->create(['title' => 'Physics Final', 'workspace_id' => $workspace->id]);
    $examPart = ExamPart::factory()->create(['exam_id' => $exam->id]);

    ExamSubmission::query()->create([
        'user_id' => $student->id,
        'exam_id' => $exam->id,
        'exam_part_id' => $examPart->id,
        'status' => 'submitted',
    ]);

    $notification = DatabaseNotification::query()
        ->where('notifiable_id', $superAdmin->id)
        ->get()
        ->first(fn ($n) => str_contains($n->data['title'] ?? '', 'Exam Submitted'));

    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toContain('[Physics Class]');
});
