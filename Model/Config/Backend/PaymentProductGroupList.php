<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Config\Backend;

use Magento\Framework\Exception\LocalizedException;

use function __;
use function implode;
use function in_array;

/**
 * Accepts only payment product groups the module actually supports on the Hosted Checkout.
 */
class PaymentProductGroupList extends AbstractIdentifierList
{
    /**
     * "cards" is the only payment product group this module uses - it is also the only
     * <product_group> value in etc/config.xml (on worldline_cards).
     */
    public const ALLOWED_GROUPS = ['cards'];

    /**
     * @throws LocalizedException
     */
    protected function validateIdentifier(string $identifier): void
    {
        if (in_array($identifier, self::ALLOWED_GROUPS, true)) {
            return;
        }

        throw new LocalizedException(
            __(
                'Invalid payment product group "%1" in "%2". The only supported value is "%3".',
                $identifier,
                (string) $this->getData('path'),
                implode('", "', self::ALLOWED_GROUPS),
            )
        );
    }
}
