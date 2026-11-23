<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'integration:admin {email} {--name=Admin} {--password= : Omit to be prompted}';

    protected $description = 'Create or promote an administrator for the monitoring dashboard.';

    public function handle(): int
    {
        $email = filter_var($this->argument('email'), FILTER_VALIDATE_EMAIL);
        if (! $email) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('Password (min 12 chars)');
        if (strlen((string) $password) < 12) {
            $this->error('Password must be at least 12 characters.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->name ?: $this->option('name');
        $user->password = Hash::make($password);
        $user->is_admin = true;
        $user->save();

        $this->info("Admin {$user->email} ready.");

        return self::SUCCESS;
    }
}
