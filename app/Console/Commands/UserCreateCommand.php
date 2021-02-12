<?php

namespace App\Console\Commands;

use App\Jobs\OCRPDFJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
        $user->password = Hash::make($this->secret('Password'));

        $user->save();

        $this->info('User created.');

        return 0;
    }
}
