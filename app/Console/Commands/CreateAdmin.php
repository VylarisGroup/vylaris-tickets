<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:create-admin {email} {name} {--password= : Passwort (sonst wird danach gefragt)}')]
class CreateAdmin extends Command
{
    protected $description = 'Legt einen Plattform-Administrator an oder befördert einen bestehenden Benutzer.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill(['is_platform_admin' => true])->save();
            $this->info("{$email} ist jetzt Plattform-Administrator.");

            return self::SUCCESS;
        }

        $password = $this->option('password') ?: $this->secret('Passwort (mindestens 10 Zeichen)');

        if (strlen((string) $password) < 10) {
            $this->error('Das Passwort muss mindestens 10 Zeichen haben.');

            return self::FAILURE;
        }

        User::create(['name' => $this->argument('name'), 'email' => $email, 'password' => $password])
            ->forceFill(['is_platform_admin' => true])->save();

        $this->info("Administrator {$email} wurde angelegt.");

        return self::SUCCESS;
    }
}
