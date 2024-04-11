<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\Token;

use Magento\Sales\Model\Order;
use Worldline\Connect\Sdk\V1\Domain\Payment;

// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface TokenServiceInterface
{
    /**
     * Get token values from db
     *
     * @param int $customerId
     * @return array
     */
    public function find($customerId);

    // phpcs:disable SlevomatCodingStandard.TypeHints.DisallowArrayTypeHintSyntax.DisallowedArrayTypeHintSyntax
    /**
     * @param int $customerId
     * @param string[] $tokens
     * @return void
     */
    // phpcs:enable SlevomatCodingStandard.TypeHints.DisallowArrayTypeHintSyntax.DisallowedArrayTypeHintSyntax
    public function deleteAll($customerId, $tokens = []);

    /**
     * @param Order $order
     * @param Payment $payment
     * @return void
     */
    public function createByOrderAndPayment(Order $order, Payment $payment);
}
