<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Worldline\Connect\Model\Config;

class OrderCreationFlow implements OptionSourceInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
                'label' => __('Order created before redirection to the hosted checkout page'),
                'value' => Config::CONFIG_ORDER_CREATION_FLOW_BEFORE,
            ],
            [
                // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
                'label' => __('Order created after redirection to the hosted checkout page'),
                'value' => Config::CONFIG_ORDER_CREATION_FLOW_AFTER,
            ]
        ];
    }
}
