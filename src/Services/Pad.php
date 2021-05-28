<?php

namespace JulienMru\PayboxGateway\Services;

class Pad
{
    /**
     * Get integer converted into Paybox format.
     *
     * @param float $number
     * @param bool $fill
     *
     * @return string
     */
    public function get($number, $fill)
    {
        $amount = round($number);
        $amount = str_pad($number, $fill, '0', STR_PAD_LEFT);

        return $amount;
    }
}