<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common;

use Magento\Sales\Api\Data\OrderInterface;
use Worldline\Connect\Sdk\V1\Domain\FraudFields;
use Worldline\Connect\Sdk\V1\Domain\FraudFieldsFactory;

class FraudFieldsBuilder
{
    /**
     * @var FraudFieldsFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $fraudFieldsFactory;

    public function __construct(FraudFieldsFactory $fraudFieldsFactory)
    {
        $this->fraudFieldsFactory = $fraudFieldsFactory;
    }

    public function create(OrderInterface $order): FraudFields
    {
        $fraudFields = $this->fraudFieldsFactory->create();
        $fraudFields->customerIpAddress = $order->getRemoteIp();

        return $fraudFields;
    }
}
