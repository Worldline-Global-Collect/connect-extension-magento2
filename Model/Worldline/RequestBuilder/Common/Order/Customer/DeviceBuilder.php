<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer\Device\BrowserDataBuilder;
use Worldline\Connect\Sdk\V1\Domain\CustomerDevice;
use Worldline\Connect\Sdk\V1\Domain\CustomerDeviceFactory;

class DeviceBuilder
{
    /**
     * @var CustomerDeviceFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $customerDeviceFactory;

    /**
     * @var BrowserDataBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $browserDataBuilder;

    /**
     * @var Http
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $request;

    /**
     * @var RemoteAddress
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $remoteAddress;

    public function __construct(
        CustomerDeviceFactory $customerDeviceFactory,
        BrowserDataBuilder $browserDataBuilder,
        Http $request,
        RemoteAddress $remoteAddress
    ) {
        $this->customerDeviceFactory = $customerDeviceFactory;
        $this->browserDataBuilder = $browserDataBuilder;
        $this->request = $request;
        $this->remoteAddress = $remoteAddress;
    }

    public function create(OrderInterface $order): CustomerDevice
    {
        $customerDevice = $this->customerDeviceFactory->create();
        $customerDevice->browserData = $this->browserDataBuilder->create();

        try {
            $customerDevice->acceptHeader = $this->getAcceptHeader();
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (LocalizedException $exception) {
            // Do nothing
        }

        $customerDevice->ipAddress = $this->resolveIpAddress($order->getRemoteIp());

        return $customerDevice;
    }

    public function createNew(Quote $quote): CustomerDevice
    {
        $customerDevice = $this->customerDeviceFactory->create();
        $customerDevice->browserData = $this->browserDataBuilder->create();

        try {
            $customerDevice->acceptHeader = $this->getAcceptHeader();
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (LocalizedException $exception) {
            // Do nothing
        }

        $customerDevice->ipAddress = $this->resolveIpAddress($quote->getRemoteIp());

        return $customerDevice;
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

    /**
     * @return string
     * @throws LocalizedException
     */
    private function getAcceptHeader(): string
    {
        $acceptHeader = $this->request->getHeader('Accept');
        if (!$acceptHeader) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__('No Accept Header set'));
        }
        return $acceptHeader;
    }
}
