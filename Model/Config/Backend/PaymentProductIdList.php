<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Config\Backend;

use Magento\Framework\Exception\LocalizedException;

use function __;
use function preg_match;

/**
 * Accepts only numeric Worldline payment product IDs.
 *
 * Deliberately a format check and not an allow list: the Hosted Checkout filter legitimately
 * accepts any Worldline payment product ID, including products this module does not expose as a
 * separate Magento payment method (for example 840 / PayPal). Restricting the field to the
 * <product_id> values in etc/config.xml would reject valid configurations.
 */
class PaymentProductIdList extends AbstractIdentifierList
{
    private const IDENTIFIER_PATTERN = '/^[1-9][0-9]*$/';

    /**
     * @throws LocalizedException
     */
    protected function validateIdentifier(string $identifier): void
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $identifier) === 1) {
            return;
        }

        throw new LocalizedException(
            __(
                'Invalid payment product ID "%1" in "%2". A payment product ID must be a positive integer.',
                $identifier,
                (string) $this->getData('path'),
            )
        );
    }
}
