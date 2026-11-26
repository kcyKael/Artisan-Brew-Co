<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'abc:create-admin
                            {--username= : Unique login username (3-50 alphanumeric/underscore/dot/dash)}
                            {--full-name= : Staff full name}
                            {--password= : Initial password (min 8 chars)}';

    protected $description = 'Safely create an active Administrator account for Artisan Brew Co.';

    public function handle(): int
    {
        $username = (string) ($this->option('username') ?: $this->ask('Admin username', 'admin'));
        $fullName = (string) ($this->option('full-name') ?: $this->ask('Admin full name', 'ABC Administrator'));
        $password = (string) ($this->option('password') ?: $this->secret('Admin password (min 8 characters)'));

        $validator = Validator::make([
            'username' => $username,
            'full_name' => $fullName,
            'password' => $password,
        ], [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,username'],
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User();
        $user->username = $username;
        $user->full_name = $fullName;
        $user->password = Hash::make($password);
        $user->role = User::ROLE_ADMIN;
        $user->is_active = true;
        $user->save();

        $this->info("Administrator '{$user->username}' (#{$user->id}) created successfully.");

        return self::SUCCESS;
    }
}
