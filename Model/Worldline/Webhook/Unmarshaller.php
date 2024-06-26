<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Webhook;

use RuntimeException;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Sdk\V1\Domain\WebhooksEvent;
use Worldline\Connect\Sdk\V1\Webhooks\WebhooksHelperFactory;
use Worldline\Connect\Sdk\Webhooks\ApiVersionMismatchException;
use Worldline\Connect\Sdk\Webhooks\InMemorySecretKeyStoreFactory;
use Worldline\Connect\Sdk\Webhooks\SignatureValidationException;

class Unmarshaller
{
    /**
     * @var Config
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $config;

    /**
     * @var InMemorySecretKeyStoreFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $keyStoreFactory;

    /**
     * @var WebhooksHelperFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $webhooksHelperFactory;

    public function __construct(
        Config $config,
        InMemorySecretKeyStoreFactory $keyStoreFactory,
        WebhooksHelperFactory $webhooksHelperFactory
    ) {
        $this->config = $config;
        $this->keyStoreFactory = $keyStoreFactory;
        $this->webhooksHelperFactory = $webhooksHelperFactory;
    }

    /**
     * Unmarshal and return value
     *
     * @param string $body
     * @param array $requestHeaders
     * @return WebhooksEvent
     * @throws SignatureValidationException
     * @throws ApiVersionMismatchException
     */
    public function unmarshal($body, array $requestHeaders)
    {
        $secretKeys = $this->resolveSecretKey($requestHeaders);
        $secretKeyStore = $this->keyStoreFactory->create(['secretKeys' => $secretKeys]);
        $helper = $this->webhooksHelperFactory->create(['secretKeyStore' => $secretKeyStore]);

        return $helper->unmarshal($body, $requestHeaders);
    }

    /**
     * Find proper pair key => value
     *
     * @param array $requestHeaders
     * @return array
     */
    private function resolveSecretKey(array $requestHeaders)
    {
        switch ($requestHeaders['X-GCS-KeyId']) {
            case $this->config->getWebhooksKeyId():
                $pattern = [$this->config->getWebhooksKeyId() => $this->config->getWebhooksSecretKey()];
                break;
            default:
                throw new RuntimeException('Secret key not found in Magento settings');
        }

        return $pattern;
    }
}
