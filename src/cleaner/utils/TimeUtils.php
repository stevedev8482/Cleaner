<?php

declare(strict_types=1);

namespace cleaner\utils;

class TimeUtils
{

    public static function minToSec(float $minutes): float
    {
        return $minutes * 60;
    }
}