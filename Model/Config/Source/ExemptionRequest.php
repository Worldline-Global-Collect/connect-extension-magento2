<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

use function __;

class ExemptionRequest implements OptionSourceInterface
{
    public const NONE = 'none';
    public const AUTOMATIC = 'automatic';
    public const TRANSACTION_RISK_ANALYSIS = 'transaction-risk-analysis';
    public const LOW_VALUE = 'low-value';

    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::NONE,
                'label' => __('None'),
            ],
            [
                'value' => self::AUTOMATIC,
                'label' => __('Automatic'),
            ],
            [
                'value' => self::TRANSACTION_RISK_ANALYSIS,
                'label' => __('Transaction risk analysis'),
            ],
            [
                'value' => self::LOW_VALUE,
                'label' => __('Low value'),
            ],
        ];
    }
}
