<?php

namespace Innovia\Paynet\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Innovia\Paynet\Traits\PaynetAPICalls;

class Controller extends BaseController
{
    use DispatchesJobs, ValidatesRequests, PaynetAPICalls;
}
