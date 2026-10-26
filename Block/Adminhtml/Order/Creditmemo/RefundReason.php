<?php

declare(strict_types=1);

namespace Worldline\Connect\Block\Adminhtml\Order\Creditmemo;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\Config\Source\RefundReason as RefundReasonSource;
use Worldline\Connect\Model\Order\Payment\WeroPaymentDetector;

use function is_array;
use function sprintf;

/**
 * Renders the "Refund reason" dropdown into the credit memo create page, in the core
 * "submit_before" container - directly above the Refund Offline / Refund buttons.
 *
 * Only rendered for orders paid with Worldline Wero - including Hosted Checkout orders where
 * Wero was picked on the Worldline page; every other payment method gets no markup at all.
 */
class RefundReason extends Template
{
    public const REGISTRY_KEY_CURRENT_CREDITMEMO = 'current_creditmemo';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly RefundReasonSource $refundReasonSource,
        private readonly WeroPaymentDetector $weroPaymentDetector,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<array{value: string, label: string}>
     */
    public function getOptions(): array
    {
        return $this->refundReasonSource->toOptionArray();
    }

    public function getFieldName(): string
    {
        return sprintf('creditmemo[%s]', Config::REFUND_REASON_KEY);
    }

    public function getFieldId(): string
    {
        return Config::REFUND_REASON_KEY;
    }

    /**
     * Keeps the selection after the "Update Qty's" AJAX call, which re-renders this block with
     * the whole credit memo form posted back.
     */
    public function getSelectedReason(): string
    {
        $posted = $this->getRequest()->getParam('creditmemo');
        $reason = is_array($posted) ? (string) ($posted[Config::REFUND_REASON_KEY] ?? '') : '';

        return RefundReasonSource::isValid($reason) ? $reason : RefundReasonSource::DEFAULT_REASON;
    }

    protected function _toHtml(): string
    {
        return $this->isWeroOrder() ? parent::_toHtml() : '';
    }

    private function isWeroOrder(): bool
    {
        $creditmemo = $this->registry->registry(self::REGISTRY_KEY_CURRENT_CREDITMEMO);
        if (!$creditmemo instanceof Creditmemo) {
            return false;
        }

        $payment = $creditmemo->getOrder()->getPayment();

        return $payment instanceof Payment && $this->weroPaymentDetector->isWeroPayment($payment);
    }
}
