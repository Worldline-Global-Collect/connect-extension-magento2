<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Client;

use Exception;
use Psr\Log\LoggerInterface;
use Worldline\Connect\Sdk\CallContext;
use Worldline\Connect\Sdk\Communication\RequestObject;
use Worldline\Connect\Sdk\Communication\ResponseClassMap;
use Worldline\Connect\Sdk\CommunicatorConfiguration;
use Worldline\Connect\Sdk\V1\ResponseExceptionFactory;

// phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
class Communicator extends \Worldline\Connect\Sdk\Communicator
{
    /**
     * @var LoggerInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $logger;

    public function __construct(
        CommunicatorConfiguration $communicatorConfiguration,
        LoggerInterface $logger
    ) {
        parent::__construct($communicatorConfiguration);
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function get(
        ResponseClassMap $responseClassMap,
        $relativeUriPath,
        $clientMetaInfo = '',
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        RequestObject $requestParameters = null,
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        CallContext $callContext = null,
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        ResponseExceptionFactory $responseExceptionFactory = null
    ) {
        try {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            return parent::get(...func_get_args());
        } catch (Exception $exception) {
            $this->logEmergency($exception);
        }

        throw $exception;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(
        ResponseClassMap $responseClassMap,
        $relativeUriPath,
        $clientMetaInfo = '',
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        RequestObject $requestParameters = null,
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        CallContext $callContext = null,
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        ResponseExceptionFactory $responseExceptionFactory = null
    ) {
        try {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            return parent::delete(...func_get_args());
        } catch (Exception $exception) {
            $this->logEmergency($exception);
        }

        throw $exception;
    }

    /**
     * {@inheritdoc}
     */
    public function post(
        ResponseClassMap $responseClassMap,
        $relativeUriPath,
        $clientMetaInfo = '',
        $requestBodyObject = null,
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        RequestObject $requestParameters = null,
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        CallContext $callContext = null,
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        ResponseExceptionFactory $responseExceptionFactory = null
    ) {
        try {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            return parent::post(...func_get_args());
        } catch (Exception $exception) {
            $this->logEmergency($exception);
        }

        throw $exception;
    }

    /**
     * {@inheritdoc}
     */
    public function put(
        ResponseClassMap $responseClassMap,
        $relativeUriPath,
        $clientMetaInfo = '',
        $requestBodyObject = null,
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        RequestObject $requestParameters = null,
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        CallContext $callContext = null,
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        ResponseExceptionFactory $responseExceptionFactory = null
    ) {
        try {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            return parent::put(...func_get_args());
        } catch (Exception $exception) {
            $this->logEmergency($exception);
        }

        throw $exception;
    }

    /**
     * @param Exception $exception
     */
    private function logEmergency(Exception $exception)
    {
        $this->logger->emergency(
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            sprintf(
                'Unable to perform request using communicator configuration: %1$s',
                $exception->getMessage()
            )
        );
    }
}
