<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Api;

use Worldline\Connect\Sdk\V1\ResponseException;

/**
 * Classifies Worldline platform errors that carry a business meaning rather than a technical
 * failure, so the checkout can react to them with a shopper-facing outcome instead of a generic
 * "something went wrong".
 */
class ApiErrorClassifier
{
    private const NO_PAYMENT_PRODUCTS_AVAILABLE_ID = 'NO_PAYMENT_PRODUCTS_AVAILABLE';
    private const NO_PAYMENT_PRODUCTS_AVAILABLE_CODE = '1406';

    /**
     * True when the platform reported that no payment product can serve this order (amount,
     * currency, country or the configured payment product filters leave nothing available).
     *
     * Both the error id and the numeric code are matched, so the classification survives a
     * response that carries only one of them.
     */
    public function isNoPaymentProductsAvailable(ResponseException $exception): bool
    {
        foreach ($exception->getErrors() as $error) {
            if ($error->id === self::NO_PAYMENT_PRODUCTS_AVAILABLE_ID
                || (string) $error->code === self::NO_PAYMENT_PRODUCTS_AVAILABLE_CODE
            ) {
                return true;
            }
        }

        return false;
    }
}
