<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super
                            {--email=admin@example.com : Email for the super admin}
                            {--name=Super Admin : Name for the super admin}
                            {--password=password : Password for the super admin}';

    protected $description = 'Create or ensure a super admin account exists in the database with full privileges';

    public function handle(): int
    {
        $email = $this->option('email');
        $name = $this->option('name');
        $password = $this->option('password');

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update([
                'is_admin' => true,
                'is_super_admin' => true,
                'name' => $name,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            $this->info("✓ Super admin '{$email}' updated successfully.");
            $this->line("  Name: {$name}");
            $this->line("  Password: {$password}");
            $this->line('  Status: super_admin + admin');
        } else {
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => true,
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]);

            $this->info("✓ Super admin '{$email}' created successfully.");
            $this->line("  Name: {$name}");
            $this->line("  Password: {$password}");
            $this->line("  Email: {$email}");
        }

        $this->newLine();
        $this->line('This super admin can now:');
        $this->line('  • Access the Administration → Admins panel');
        $this->line('  • Create, edit, and demote other admin accounts');
        $this->line('  • View data across all workspaces');

        return self::SUCCESS;
    }
}
