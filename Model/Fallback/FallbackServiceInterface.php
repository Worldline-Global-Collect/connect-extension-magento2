<?php

namespace Worldline\Connect\Model\Fallback;

interface FallbackServiceInterface
{
    /**
     * Fetch uncertain transactions, check and synchronize them
     *
     * @return void
     */
    public function process(): void;
}