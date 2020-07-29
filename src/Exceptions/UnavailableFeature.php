<?php

namespace Ngekoding\Browsershot\Exceptions;

use Exception;

class UnavailableFeature extends Exception
{
    public function __construct($feature)
    {
        parent::__construct("Sorry, the `{$feature} feature is not implemented yet");
    }
}
