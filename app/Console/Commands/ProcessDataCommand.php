<?php

namespace App\Console\Commands;

use App\Http\Controllers\CredentialsController;
use App\Jobs\ProcessData;
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
        Log::info('command executed');
        ProcessData::dispatch();
    }
}
