<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common;

use Magento\Sales\Model\Order;
use Worldline\Connect\Helper\Data as DataHelper;
use Worldline\Connect\Helper\Format;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\AdditionalInputBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\CustomerBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\ShippingBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\ShoppingCartBuilder;
use Worldline\Connect\Sdk\V1\Domain\AmountOfMoneyFactory;
use Worldline\Connect\Sdk\V1\Domain\OrderFactory;
use Worldline\Connect\Sdk\V1\Domain\OrderReferencesFactory;

/**
 * Class OrderBuilder
 */
class OrderBuilder
{
    /**
     * @var ConfigInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $ePaymentsConfig;

    /**
     * @var CustomerBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $customerBuilder;

    /**
     * @var ShoppingCartBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $shoppingCartBuilder;

    /**
     * @var OrderFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $orderFactory;

    /**
     * @var AmountOfMoneyFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $amountOfMoneyFactory;

    /**
     * @var OrderReferencesFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $orderReferencesFactory;

    /**
     * @var ShippingBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $shippingBuilder;
    /**
     * @var AdditionalInputBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $additionalInputBuilder;

    /**
     * @var Format
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $format;

    public function __construct(
        ConfigInterface $ePaymentsConfig,
        CustomerBuilder $customerBuilder,
        ShoppingCartBuilder $shoppingCartBuilder,
        OrderFactory $orderFactory,
        AmountOfMoneyFactory $amountOfMoneyFactory,
        OrderReferencesFactory $orderReferencesFactory,
        AdditionalInputBuilder $additionalInputBuilder,
        ShippingBuilder $shippingBuilder,
        Format $format
    ) {
        $this->ePaymentsConfig = $ePaymentsConfig;
        $this->customerBuilder = $customerBuilder;
        $this->shoppingCartBuilder = $shoppingCartBuilder;
        $this->orderFactory = $orderFactory;
        $this->amountOfMoneyFactory = $amountOfMoneyFactory;
        $this->orderReferencesFactory = $orderReferencesFactory;
        $this->shippingBuilder = $shippingBuilder;
        $this->additionalInputBuilder = $additionalInputBuilder;
        $this->format = $format;
    }

    // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    /**
     * @param Order $order
     * @return \Worldline\Connect\Sdk\V1\Domain\Order
     */
    // phpcs:enable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    public function create(Order $order)
    {
        $worldlineOrder = $this->orderFactory->create();

        $worldlineOrder->amountOfMoney = $this->getAmountOfMoney($order);
        $worldlineOrder->customer = $this->customerBuilder->create($order);

        $worldlineOrder->shoppingCart = $this->shoppingCartBuilder->create($order);
        $worldlineOrder->references = $this->getReferences($order);
        $worldlineOrder->additionalInput = $this->additionalInputBuilder->create($order);
        $worldlineOrder->shipping = $this->shippingBuilder->create($order);

        return $worldlineOrder;
    }

    // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    /**
     * @param Order $order
     * @return \Worldline\Connect\Sdk\V1\Domain\AmountOfMoney
     */
    // phpcs:enable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    private function getAmountOfMoney(Order $order)
    {
        $amountOfMoney = $this->amountOfMoneyFactory->create();
        $amountOfMoney->amount = DataHelper::formatWorldlineAmount($order->getGrandTotal());
        $amountOfMoney->currencyCode = $order->getOrderCurrencyCode();

        return $amountOfMoney;
    }

    // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    /**
     * @param Order $order
     * @return \Worldline\Connect\Sdk\V1\Domain\OrderReferences
     */
    // phpcs:enable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    private function getReferences(Order $order)
    {
        $references = $this->orderReferencesFactory->create();
        $references->merchantReference = $this->format->limit(
            $order->getIncrementId(),
            30
        );
        $references->descriptor = $this->ePaymentsConfig->getDescriptor();

        return $references;
    }
}
