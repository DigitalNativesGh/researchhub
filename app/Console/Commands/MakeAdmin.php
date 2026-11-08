<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Grants (or with --revoke, removes) access to the administrator dashboard at /admin. */
class MakeAdmin extends Command
{
    protected $signature = 'researchhub:admin {email : Email address of an existing account} {--revoke : Remove administrator access instead}';

    protected $description = 'Grant or revoke administrator access for an account';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account uses that email address. Register it first, then run this command again.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();
        $this->info($user->is_admin ? "{$user->email} is now an administrator. Sign in and open /admin." : "{$user->email} is no longer an administrator.");

        return self::SUCCESS;
    }
}
