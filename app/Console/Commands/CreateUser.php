<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    protected $signature = 'app:create-user';

    protected $description = 'Create a user who can sign in to the PPR';

    public function handle(): int
    {
        $input = [
            'name' => text('Name', required: true),
            'email' => Str::lower(text('Email', required: true)),
            'password' => password('Password', required: true),
            'password_confirmation' => password('Confirm password', required: true),
            'role' => select('Role', array_column(Role::options(), 'label', 'value'), default: Role::Member->value),
        ];

        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User($validator->safe()->only(['name', 'email', 'password']));
        $user->role = Role::from($input['role']);
        $user->save();

        $this->components->info("User {$user->email} created with the {$user->role->label()} role.");

        return self::SUCCESS;
    }
}
