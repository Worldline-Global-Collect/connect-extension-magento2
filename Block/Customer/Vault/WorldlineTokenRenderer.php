<?php

declare(strict_types=1);

namespace Worldline\Connect\Block\Customer\Vault;

use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\CcConfigProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;
use Worldline\Connect\PaymentMethod\PaymentMethods;

class WorldlineTokenRenderer extends AbstractCardRenderer
{
    public function __construct(
        Context $context,
        CcConfigProvider $iconsProvider,
        private readonly PaymentMethods $paymentMethods,
        array $data = []
    ) {
        parent::__construct($context, $iconsProvider, $data);
    }

    public function getNumberLast4Digits(): string
    {
        return (string) $this->getTokenDetails()['card'];
    }

    public function getExpDate(): string
    {
        return (string) $this->getTokenDetails()['expiry'];
    }

    public function getIconUrl(): string
    {
        return (string) $this->getIconForType($this->getTokenDetails()['type'])['url'];
    }

    public function getIconHeight(): int
    {
        return (int) $this->getIconForType($this->getTokenDetails()['type'])['height'];
    }

    public function getIconWidth(): int
    {
        return (int) $this->getIconForType($this->getTokenDetails()['type'])['width'];
    }

    public function canRender(PaymentTokenInterface $token): bool
    {
        return $this->paymentMethods->isWorldlinePaymentMethod($token->getPaymentMethodCode());
    }
}
