<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Shipping\Address;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Worldline\Connect\Helper\Format;
use Worldline\Connect\Sdk\V1\Domain\PersonalName;
use Worldline\Connect\Sdk\V1\Domain\PersonalNameFactory;

class NameBuilder
{
    public function __construct(
        private readonly PersonalNameFactory $personalNameFactory,
        private readonly Format $format
    ) {
    }

    public function create(OrderAddressInterface $address): PersonalName
    {
        $personalName = $this->personalNameFactory->create();
        $personalName->firstName = $this->format->limit($address->getFirstname(), 15);
        $personalName->surname = $this->format->limit($address->getLastname(), 70);
        $personalName->surnamePrefix = $address->getMiddlename();
        $personalName->title = $address->getPrefix();
        return $personalName;
    }
}
