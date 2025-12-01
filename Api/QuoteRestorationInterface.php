<?php

namespace Worldline\Connect\Api;

/**
 * Restore quote
 */
interface QuoteRestorationInterface
{
    public function preserveQuoteId(int $quoteId): void;

    public function shiftQuoteId(): void;

    public function restoreQuote(): void;
}
