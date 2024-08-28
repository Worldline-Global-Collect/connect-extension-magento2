<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\AddressPersonalBuilder;
use Worldline\Connect\Sdk\V1\Domain\AddressPersonal;

use function __;

class AddressBuilder
{
    public function __construct(
        private readonly AddressPersonalBuilder $addressPersonalBuilder,
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function create(OrderInterface $order): AddressPersonal
    {
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress === null) {
            throw new LocalizedException(__('No shipping address available for this order'));
        }

        return $this->addressPersonalBuilder->build($billingAddress);
    }
}
