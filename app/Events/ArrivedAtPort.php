<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArrivedAtPort
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Trip $trip)
    {
    }
}
