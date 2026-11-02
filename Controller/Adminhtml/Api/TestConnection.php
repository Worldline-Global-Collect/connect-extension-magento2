<?php

declare(strict_types=1);

namespace Worldline\Connect\Controller\Adminhtml\Api;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Psr\Log\LoggerInterface;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Sdk\V1\ResponseException;

use function array_pop;
use function get_class;
use function sprintf;

// phpcs:ignore PSR12.Files.FileHeader.SpacingAfterBlock

class TestConnection extends Action
{
    /** @var Config */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $epaymentsConfig;

    /** @var StoreManagerInterface */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $storeManager;

    /** @var JsonFactory */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $jsonFactory;

    /** @var ClientInterface */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    protected $worldlineClient;

    /** @var LoggerInterface */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $logger;

    /**
     * @param Context $context
     * @param Config $epaymentsConfig
     * @param StoreManagerInterface $storeManager
     * @param JsonFactory $jsonFactory
     * @param ClientInterface $worldlineClient
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Config $epaymentsConfig,
        StoreManagerInterface $storeManager,
        JsonFactory $jsonFactory,
        ClientInterface $worldlineClient,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->epaymentsConfig = $epaymentsConfig;
        $this->storeManager = $storeManager;
        $this->jsonFactory = $jsonFactory;
        $this->worldlineClient = $worldlineClient;
        $this->logger = $logger;
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
    public function execute()
    {
        $environment = (string) $this->getRequest()->getParam('environment', '');
        $website = (string) $this->getRequest()->getParam('website', '');
        $scopeId = null;

        try {
            $scopeId = $this->resolveScopeId($website);
            $client = $this->worldlineClient->buildFromConfiguration($scopeId, [
                'api_key' => $this->epaymentsConfig->getApiKey($scopeId, $environment),
                'api_secret' => $this->epaymentsConfig->getApiSecret($scopeId, $environment),
                'merchant_id' => $this->epaymentsConfig->getMerchantId($scopeId, $environment),
                'api_endpoint' => $this->epaymentsConfig->getApiEndpoint($scopeId, $environment),
            ]);
            $this->worldlineClient->worldlineTestAccount($scopeId, $client, $environment);
        } catch (Exception $exception) {
            $this->logFailure($exception, $environment, $website, $scopeId);
            if ($exception instanceof ResponseException) {
                $errors = $exception->getErrors();
                return $this->getFailureResult(array_pop($errors)?->message);
            }
            return $this->getFailureResult();
        }

        return $this->getSuccessResult();
    }

    /**
     * The admin store manager resolves getStore() to the default store view of the default website,
     * so the scope is derived from the `website` request parameter: none reads the default config
     * (store 0), a website reads the config of its default store view.
     *
     * @throws LocalizedException
     */
    private function resolveScopeId(string $website): int
    {
        if ($website === '') {
            return Store::DEFAULT_STORE_ID;
        }

        $websiteModel = $this->storeManager->getWebsite($website);
        $defaultStore = $websiteModel instanceof Website ? $websiteModel->getDefaultStore() : null;
        if ($defaultStore === null) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__('Website "%1" has no default store view.', $website));
        }

        return (int) $defaultStore->getId();
    }

    /**
     * Credentials and webhook secrets stay out of the context; the endpoint URL is the only
     * configuration value logged.
     */
    private function logFailure(Exception $exception, string $environment, string $website, ?int $scopeId): void
    {
        $this->logger->error('Worldline Connect API connection test failed.', [
            'environment' => $environment,
            'scope' => $website === '' ? 'default' : 'website ' . $website,
            'store_id' => $scopeId,
            'api_endpoint' => $this->resolveLoggableEndpoint($environment, $scopeId),
            'exception' => get_class($exception) . ': ' . $exception->getMessage(),
        ]);
    }

    private function resolveLoggableEndpoint(string $environment, ?int $scopeId): ?string
    {
        if ($scopeId === null) {
            return null;
        }

        try {
            $endpoint = $this->epaymentsConfig->getApiEndpoint($scopeId, $environment);
        } catch (Exception) {
            return null;
        }

        return $endpoint === null ? null : (string) $endpoint;
    }

    private function getSuccessResult(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setStatusHeader(200);
        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        $result->setData(__(
            'Connection to the Worldline Connect platform could successfully be established.'
        ));
        return $result;
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    private function getFailureResult($message = null): Json
    {
        $result = $this->jsonFactory->create();
        $result->setStatusHeader(422);
        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        $responseMessage = __('Could not establish connection to Worldline Connect platform.');
        if ($message !== null) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            $responseMessage = sprintf('%s %s %s', $responseMessage, __('Error message:'), $message);
        }
        $result->setData($responseMessage);
        return $result;
    }
}
