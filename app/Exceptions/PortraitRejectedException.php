<?php

namespace App\Exceptions;

use RuntimeException;

class PortraitRejectedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Runway could not turn this portrait into a live avatar — it needs a face that '
            .'fills the frame. Trying again creates a fresh portrait, which usually fixes it.',
        );
    }
}
