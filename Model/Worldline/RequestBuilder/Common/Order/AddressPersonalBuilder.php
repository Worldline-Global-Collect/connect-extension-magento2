<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Worldline\Connect\Helper\Format;
use Worldline\Connect\Sdk\V1\Domain\AddressPersonal;
use Worldline\Connect\Sdk\V1\Domain\AddressPersonalFactory;

use function array_key_exists;

class AddressPersonalBuilder
{
    public function __construct(
        private readonly AddressPersonalFactory $addressPersonalFactory,
        private readonly Format $format,
    ) {
    }

    public function build(OrderAddressInterface $orderAddress): AddressPersonal
    {
        $addressPersonal = $this->addressPersonalFactory->create();
        $addressPersonal->city = $this->format->limit($orderAddress->getCity(), 40);
        $addressPersonal->countryCode = $orderAddress->getCountryId();
        $addressPersonal->state = $orderAddress->getRegion();
        $addressPersonal->zip = $orderAddress->getPostcode();

        $street = $orderAddress->getStreet();
        if ($street !== null) {
            $addressPersonal->street = $this->format->limit(array_key_exists(0, $street) ? $street[0] : '', 50);
            $addressPersonal->houseNumber = $this->format->limit(array_key_exists(1, $street) ? $street[1] : '', 15);
            $addressPersonal->additionalInfo = $this->format->limit(array_key_exists(2, $street) ? $street[2] : '', 50);
        }

        return $addressPersonal;
    }
}
