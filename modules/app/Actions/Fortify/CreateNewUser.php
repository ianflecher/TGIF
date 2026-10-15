<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'full_name' => $input['name'],
                'username' => 'customer_'.Str::uuid(),
                'role' => 'customer',
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            $names = explode(' ', $input['name'], 2);
            DB::table('customers')->insert([
                'user_id' => $user->getKey(), 'first_name' => $names[0],
                'last_name' => $names[1] ?? '', 'email' => $input['email'],
                'date_registered' => now()->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $user;
        });
    }
}
