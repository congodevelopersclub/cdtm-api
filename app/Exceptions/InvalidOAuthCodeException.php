<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidOAuthCodeException extends Exception
{
    public function __construct(string $message = 'Invalid or expired code')
    {
        parent::__construct($message);
    }
}
