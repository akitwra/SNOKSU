<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapOrganizer extends Command
{
    protected $signature = 'society:bootstrap-organizer';

    protected $description = 'Create the first organizer using secure hosting environment fields; never reset an existing account.';

    public function handle(): int
    {
        return DB::transaction(function () {
            DB::select('select pg_advisory_xact_lock(?)', [470831925]);
            if (User::where('role', 'organizer')->exists()) {
                $this->info('An organizer already exists; credentials were not changed.');

                return self::SUCCESS;
            }
            $data = ['name' => env('INITIAL_ORGANIZER_NAME'), 'email' => env('INITIAL_ORGANIZER_EMAIL'), 'password' => env('INITIAL_ORGANIZER_PASSWORD')];
            $validator = Validator::make($data, ['name' => 'required|string|max:120', 'email' => 'required|email|max:190|unique:users,email', 'password' => ['required', Password::min(14)->letters()->numbers()]]);
            if ($validator->fails()) {
                $this->error('Configure INITIAL_ORGANIZER_NAME, INITIAL_ORGANIZER_EMAIL and a password with at least 14 characters, letters and numbers in secure hosting settings. No account was changed.');

                return self::FAILURE;
            }
            $user = new User;
            $user->forceFill($data + ['role' => 'organizer'])->save();
            $this->info('The initial organizer was created. Remove the INITIAL_ORGANIZER_* fields from hosting settings.');

            return self::SUCCESS;
        });
    }
}
