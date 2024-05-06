<?php

namespace App\Jobs;

use AlexaCRM\WebAPI\ClientFactory;
use AlexaCRM\WebAPI\OData\OnlineSettings;
use AlexaCRM\Xrm\Entity;
use AlexaCRM\Xrm\Query\FetchExpression;
use AlexaCRM\Xrm\Query\QueryByAttribute;
use App\Events\SendResponseEvent;
use App\Http\Controllers\CredentialsController;
use App\Models\Credential;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $id;
    protected $controller;
    public function __construct(CredentialsController $controller, $id)
    {
        $this->controller = $controller;
        $this->id = $id;
    }

    /**
     * Execute the job.
     */
    public function handle() {
        $this->controller->execute($this->id);

    }

}
