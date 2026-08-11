<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common;

use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Worldline\Connect\Sdk\V1\Domain\FraudFields;
use Worldline\Connect\Sdk\V1\Domain\FraudFieldsFactory;

class FraudFieldsBuilder
{
    /**
     * @var FraudFieldsFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $fraudFieldsFactory;

    /**
     * @var RemoteAddress
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $remoteAddress;

    public function __construct(FraudFieldsFactory $fraudFieldsFactory, RemoteAddress $remoteAddress)
    {
        $this->fraudFieldsFactory = $fraudFieldsFactory;
        $this->remoteAddress = $remoteAddress;
    }

    public function create(OrderInterface $order): FraudFields
    {
        $fraudFields = $this->fraudFieldsFactory->create();
        $fraudFields->customerIpAddress = $this->resolveIpAddress($order->getRemoteIp());

        return $fraudFields;
    }

    public function createNew(Quote $quote): FraudFields
    {
        $fraudFields = $this->fraudFieldsFactory->create();
        $fraudFields->customerIpAddress = $this->resolveIpAddress($quote->getRemoteIp());

        return $fraudFields;
    }

    /**
     * Resolve the customer IP address for the Worldline request.
     *
     * The persisted quote/order `remote_ip` is only populated as a side effect of the Luma
     * checkout session (or at order placement), so in the "order created after redirection" flow
     * the create-payment request can be built from a quote whose `remote_ip` is still empty
     * (notably logged-in customers using grouped cards). Fall back to the live request address so
     * the IP is always sent — the create-payment call runs inside the customer's HTTP request.
     *
     * @param string|null $persistedIp
     * @return string|null
     */
    private function resolveIpAddress(?string $persistedIp): ?string
    {
        return $persistedIp ?: ($this->remoteAddress->getRemoteAddress() ?: null);
    }
}
