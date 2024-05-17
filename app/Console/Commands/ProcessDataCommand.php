<?php

namespace App\Console\Commands;

use App\Http\Controllers\CredentialsController;
use App\Jobs\ProcessData;
use App\Models\User;
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
        $users = User::where('is_admin', '<>', 1)->whereHas('credentials')->get();
        Log::info("hello command");
        $controller = app()->make(CredentialsController::class);

        foreach ($users as $user) {
            Log::info('hello user');
            Log::info($user->email);
            dispatch(new ProcessData($controller ,$user->id));
        }
    }
}
