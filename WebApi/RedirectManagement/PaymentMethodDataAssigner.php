<?php
declare(strict_types=1);

namespace Worldline\Connect\WebApi\RedirectManagement;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\PaymentInterface;
use Worldline\Connect\Model\DataAssigner\DataAssignerInterface;

class PaymentMethodDataAssigner implements DataAssignerInterface
{
    /**
     * @param PaymentInterface $payment
     * @param array $additionalInformation
     * @return void
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function assign(
        PaymentInterface $payment,
        array $additionalInformation
    ): void {
        if (isset($additionalInformation['is_active_payment_token_enabler'])) {
            $payment->setAdditionalInformation(
                'is_active_payment_token_enabler',
                $additionalInformation['is_active_payment_token_enabler']
            );
        }
    }
}
