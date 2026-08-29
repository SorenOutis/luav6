<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AdminNotificationService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_receives_notifications_with_workspace_info(): void
    {
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
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('[Math Workspace]', $notification->data['title']);
        $this->assertStringContainsString('Workspace: Math Workspace', $notification->data['body']);
    }

    public function test_workspace_admin_receives_notifications_only_for_their_workspace(): void
    {
        $workspaceA = Workspace::factory()->create(['name' => 'Workspace A']);
        $workspaceB = Workspace::factory()->create(['name' => 'Workspace B']);

        $adminA = User::factory()->create([
            'is_admin' => true,
            'is_super_admin' => false,
            'current_workspace_id' => $workspaceA->id,
        ]);

        $adminB = User::factory()->create([
            'is_admin' => true,
            'is_super_admin' => false,
            'current_workspace_id' => $workspaceB->id,
        ]);

        AdminNotificationService::notifyAdmins(
            title: 'Workspace A Event',
            body: 'Event in Workspace A',
            workspace: $workspaceA,
        );

        $notificationA = DatabaseNotification::query()
            ->where('notifiable_id', $adminA->id)
            ->first();

        $notificationB = DatabaseNotification::query()
            ->where('notifiable_id', $adminB->id)
            ->first();

        $this->assertNotNull($notificationA);
        $this->assertNull($notificationB);
    }

    public function test_registered_event_triggers_notifications(): void
    {
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
            ->where('data->title', 'like', '%New User Registered%')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('John Doe', $notification->data['body']);
    }

    public function test_login_event_triggers_notifications(): void
    {
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
            ->where('data->title', 'like', '%User Logged In%')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Jane Smith', $notification->data['body']);
    }

    public function test_exam_submission_triggers_notifications(): void
    {
        $superAdmin = User::factory()->create([
            'is_admin' => true,
            'is_super_admin' => true,
        ]);

        $workspace = Workspace::factory()->create(['name' => 'Physics Class']);
        $student = User::factory()->create(['name' => 'Physics Student']);
        $exam = Exam::factory()->create(['title' => 'Physics Final', 'workspace_id' => $workspace->id]);

        ExamSubmission::query()->create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'submitted',
        ]);

        $notification = DatabaseNotification::query()
            ->where('notifiable_id', $superAdmin->id)
            ->where('data->title', 'like', '%Exam Submitted%')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('[Physics Class]', $notification->data['title']);
    }
}
