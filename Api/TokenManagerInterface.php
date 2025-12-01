<?php
declare(strict_types=1);

namespace Worldline\Connect\Api;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

interface TokenManagerInterface
{
    public function getToken(CartInterface $quote): ?PaymentTokenInterface;

    public function isSepaToken(PaymentTokenInterface $token): bool;
}
