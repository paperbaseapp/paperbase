<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $user = new User();

        $user->account = $this->ask('Account');
        $user->display_name = $this->ask('Display name');
        do {
            $user->email = $this->ask('Email address (optional)');
            $validator = Validator::make(['email' => $user->email], ['email' => 'email|nullable']);
            if ($validator->fails()) {
                $this->line('Entered email address is not valid.');
            }
        } while ($validator->fails());
        $user->password = Hash::make($this->secret('Password'));


        $user->save();

        $this->info('User created.');

        return 0;
    }
}
