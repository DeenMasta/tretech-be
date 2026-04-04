<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Authorization\AuthorizesRequests;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, ApiResponseTrait;
}
