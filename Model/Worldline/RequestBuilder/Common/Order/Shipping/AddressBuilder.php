<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Shipping;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\AddressPersonalBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\PersonalNameBuilder;
use Worldline\Connect\Sdk\V1\Domain\AddressPersonal;

use function __;

class AddressBuilder
{
    public function __construct(
        private readonly AddressPersonalBuilder $addressPersonalBuilder,
        private readonly PersonalNameBuilder $nameBuilder,
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function create(Order $order): AddressPersonal
    {
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress === null) {
            throw new LocalizedException(__('No shipping address available for this order'));
        }

        $addressPersonal = $this->addressPersonalBuilder->build($shippingAddress);
        $addressPersonal->name = $this->nameBuilder->create($shippingAddress);

        return $addressPersonal;
    }
}
