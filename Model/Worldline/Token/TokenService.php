<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\Token;

use DateTime;
use Magento\Sales\Model\Order;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\PaymentTokenFactory;
use Magento\Vault\Model\PaymentTokenManagement;
use Magento\Vault\Model\PaymentTokenRepository;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Worldline\Connect\Model\ConfigProvider;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Worldline\Connect\PaymentMethod\PaymentMethods;
use Worldline\Connect\Sdk\V1\Domain\CardPaymentMethodSpecificOutput;
use Worldline\Connect\Sdk\V1\Domain\Payment;

use function array_key_exists;
use function in_array;
use function json_encode;
use function substr;

class TokenService implements TokenServiceInterface
{
    private const MAP = [
        2 => 'AE',
        146 => 'AU',
        132 => 'DN',
        128 => 'DI',
        163 => 'HC',
        125 => 'JCB',
        117 => 'SM',
        3 => 'MC',
        119 => 'MC',
        1 => 'VI',
        114 => 'VI',
        122 => 'VI',
    ];

    public function __construct(
        private readonly PaymentTokenManagement $paymentTokenManagement,
        private readonly PaymentTokenRepository $paymentTokenRepository,
        private readonly ClientInterface $client,
        private readonly PaymentTokenFactory $paymentTokenFactory,
        private readonly PaymentMethods $paymentMethods,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function find($customerId)
    {
        $tokens = [];

        // phpcs:ignore SlevomatCodingStandard.TypeHints.DisallowArrayTypeHintSyntax.DisallowedArrayTypeHintSyntax
        /** @var PaymentTokenInterface[] $paymentTokens */
        $paymentTokens = $this->paymentTokenManagement->getVisibleAvailableTokens($customerId);
        foreach ($paymentTokens as $paymentToken) {
            if (!$this->paymentMethods->isWorldlinePaymentMethod($paymentToken->getPaymentMethodCode())) {
                continue;
            }
            if ($paymentToken->getIsActive() && $paymentToken->getIsVisible()) {
                $tokens[] = $paymentToken->getGatewayToken();
            }
        }

        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        return array_unique($tokens);
    }

    // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    /**
     * @param int $customerId
     * @param array $tokens
     * @throws \Exception
     */
    // phpcs:enable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    public function deleteAll($customerId, $tokens = [])
    {
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
        if ($customerId && !empty($tokens)) {
            // phpcs:ignore SlevomatCodingStandard.TypeHints.DisallowArrayTypeHintSyntax.DisallowedArrayTypeHintSyntax
            /** @var PaymentTokenInterface[] $paymentTokens */
            $paymentTokens = $this->paymentTokenManagement->getVisibleAvailableTokens($customerId);
            foreach ($paymentTokens as $paymentToken) {
                if (!$this->paymentMethods->isWorldlinePaymentMethod($paymentToken->getPaymentMethodCode())) {
                    continue;
                }

                if (in_array($paymentToken->getGatewayToken(), $tokens)) {
                    $this->paymentTokenRepository->delete($paymentToken);
                }
            }
        }
    }

    // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    public function createByOrderAndPayment(Order $order, Payment $payment)
    {
        /** @var Order\Payment $orderPayment */
        $orderPayment = $order->getPayment();
        if (!$orderPayment->getAdditionalInformation('tokenize')) {
            return;
        }

        if (!$this->paymentMethods->isWorldlinePaymentMethod($orderPayment->getMethod())) {
            return;
        }

        $customerId = $order->getCustomerId();
        if (!$customerId) {
            return;
        }

        if ($payment->status === StatusInterface::REDIRECTED) {
            return;
        }

        $paymentOutput = $payment->paymentOutput;
        if ($paymentOutput === null) {
            return;
        }

        $cardPaymentMethodSpecificOutput = $paymentOutput->cardPaymentMethodSpecificOutput;
        if ($cardPaymentMethodSpecificOutput === null) {
            return;
        }

        $card = $cardPaymentMethodSpecificOutput->card;
        $tokenResponse = $this->client->worldlinePaymentTokenize(
            $payment->id,
            null,
            $card->cardNumber
        );
        $token = $tokenResponse->token;
        if ($token === null) {
            return;
        }

        $paymentToken = $this->paymentTokenManagement->getByGatewayToken($token, ConfigProvider::CODE, $customerId);
        if ($paymentToken !== null) {
            return;
        }

        $orderPayment->setAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE, 1);
        $orderPayment->getExtensionAttributes()->setVaultPaymentToken(
            $this->buildPaymentToken($cardPaymentMethodSpecificOutput, $token)
        );
    }

    public function buildPaymentToken(
        CardPaymentMethodSpecificOutput $cardPaymentMethodSpecificOutput,
        string $token
    ): PaymentTokenInterface {
        $paymentProductId = $cardPaymentMethodSpecificOutput->paymentProductId;
        $card = $cardPaymentMethodSpecificOutput->card;

        $paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
        $paymentToken->setExpiresAt((DateTime::createFromFormat('my', $card->expiryDate))->format('Y-m-1 00:00:00'));
        $paymentToken->setGatewayToken($token);
        $paymentToken->setTokenDetails(json_encode([
            'card' => substr($card->cardNumber, -4),
            'expiry' => (DateTime::createFromFormat('my', $card->expiryDate))->format('m/y'),
            'type' => array_key_exists($paymentProductId, self::MAP) ? self::MAP[$paymentProductId] : null,
            'paymentProductId' => $paymentProductId,
            'transactionId' => $cardPaymentMethodSpecificOutput->schemeTransactionId,
        ]));

        return $paymentToken;
    }
}
