<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

use function in_array;

/**
 * Refund reasons Worldline accepts for a refund request.
 *
 * The labels are intentionally identical to the values: the merchant sees exactly what is sent
 * to Worldline, and the list stays a 1:1 mirror of the platform enum.
 */
class RefundReason implements OptionSourceInterface
{
    public const DEFAULT_REASON = 'OTHER';

    public const REASONS = [
        'RETURN',
        'CORRECTION',
        'PRE_DISPUTE',
        'SUBSCRIPTION',
        'SERVICE_LATE_CANCELLATION',
        self::DEFAULT_REASON,
    ];

    /**
     * @return array<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::REASONS as $reason) {
            $options[] = ['value' => $reason, 'label' => $reason];
        }

        return $options;
    }

    public static function isValid(string $reason): bool
    {
        return in_array($reason, self::REASONS, true);
    }
}
