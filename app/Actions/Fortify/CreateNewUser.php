<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $timezone = $input['timezone'] ?? null;

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            // Sent by the browser; anything that isn't a real timezone falls back to the default.
            'timezone' => in_array($timezone, timezone_identifiers_list(), true) ? $timezone : null,
            'password' => $input['password'],
        ]);
    }
}
