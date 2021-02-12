<?php


namespace App\Console\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CronCommand extends Command
{
    protected $signature = 'cron {--interval=60}';

    protected $description = 'A fake cron job scheduler';

    public function handle()
    {
        $this->comment("Cron has been started");

        while (true) {
            for ($i = $this->option('interval'); $i > 0; $i--) {
                if ($i % 5 === 0) {
                    $this->line('Schedule will run in ' . $i . 's');
                }
                sleep(1);
            }

            Artisan::call('schedule:run');
            $this->output->write(Artisan::output());
        }
    }
}
