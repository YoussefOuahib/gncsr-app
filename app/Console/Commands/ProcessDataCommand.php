<?php

namespace App\Console\Commands;

use App\Http\Controllers\CredentialsController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('hello again');
        $controller = new CredentialsController();
        $controller->execute();
        $this->info('execute command');
    }
}
