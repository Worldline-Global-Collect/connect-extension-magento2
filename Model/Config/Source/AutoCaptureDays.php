<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

use function __;

class AutoCaptureDays implements ArrayInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'never', 'label' => __('Never')],
            ['value' => '1', 'label' => __('1 day')],
            ['value' => '2', 'label' => __('2 days')],
            ['value' => '3', 'label' => __('3 days')],
            ['value' => '4', 'label' => __('4 days')],
            ['value' => '5', 'label' => __('5 days')],
            ['value' => '6', 'label' => __('6 days')],
            ['value' => '7', 'label' => __('7 days')],
        ];
    }
}
