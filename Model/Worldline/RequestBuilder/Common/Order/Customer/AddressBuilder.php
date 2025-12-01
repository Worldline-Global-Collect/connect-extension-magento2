<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\AddressPersonalBuilder;
use Worldline\Connect\Sdk\V1\Domain\AddressPersonal;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Api\Data\OrderAddressInterface as OrderAddress;

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
        /** @var OrderAddress|null $billingAddress */
        $billingAddress = $order->getBillingAddress();

        if ($billingAddress === null) {
            throw new LocalizedException(__('No shipping address available for this order'));
        }

        return $this->addressPersonalBuilder->build($billingAddress);
    }

    /**
     * @throws LocalizedException
     */
    public function createNew(Quote $quote): AddressPersonal
    {
        /** @var QuoteAddress|null $billingAddress */
        $billingAddress = $quote->getBillingAddress();

        if ($billingAddress === null) {
            throw new LocalizedException(__('No shipping address available for this order'));
        }

        return $this->addressPersonalBuilder->buildNew($billingAddress);
    }
}
