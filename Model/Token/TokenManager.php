<?php
declare(strict_types=1);

namespace Worldline\Connect\Model\Token;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Worldline\Connect\Api\TokenManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;

class TokenManager implements TokenManagerInterface
{
    /**
     * Sepa product id
     */
    private const SEPA_DIRECT_DEBIT_PRODUCT_ID = 771;
    /**
     * @var PaymentTokenManagementInterface
     */
    private $paymentTokenManagement;

    /**
     * @var Json
     */
    private $json;

    public function __construct(
        PaymentTokenManagementInterface $paymentTokenManagement,
        Json $json
    ) {
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->json = $json;
    }

    public function getToken(CartInterface $quote): ?PaymentTokenInterface
    {
        $payment = $quote->getPayment();
        if (!$publicHash = $payment->getAdditionalInformation(PaymentTokenInterface::PUBLIC_HASH)) {
            return null;
        }

        return $this->paymentTokenManagement->getByPublicHash($publicHash, (int) $quote->getCustomerId());
    }

    public function isSepaToken(PaymentTokenInterface $token): bool
    {
        $details = $this->json->unserialize($token->getTokenDetails());
        $paymentProductId = $details['payment_product_id'] ?? null;
        if (!$details) {
            return false;
        }

        return $paymentProductId === self::SEPA_DIRECT_DEBIT_PRODUCT_ID;
    }
}
