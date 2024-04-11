<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Controller\Webhooks;

use DateTimeImmutable;
use Exception;
use Laminas\Http\Request;
use Magento\Framework\App\Action\Action as CoreAction;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Webapi\Exception as WebApiException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Worldline\Connect\Model\Worldline\Webhook\Handler;
use Worldline\Connect\Model\Worldline\Webhook\Unmarshaller;
use Worldline\Connect\Sdk\V1\Domain\WebhooksEvent;

use function strlen;

/**
 * Webhook class encapsulating general request validation functionality for webhooks
 */
class Index extends CoreAction
{
    /**
     * @var Unmarshaller
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $unmarshaller;

    /**
     * @var Handler
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $handler;

    /**
     * @var LoggerInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $logger;

    public function __construct(
        Context $context,
        Unmarshaller $unmarshaller,
        Handler $handler,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->unmarshaller = $unmarshaller;
        $this->handler = $handler;
        $this->logger = $logger;
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
    public function execute()
    {
        /** @var Raw $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $response->setHeader('Content-type', 'text/plain');

        /** @var Request $request */
        $request = $this->getRequest();
        $verificationString = (string) $request->getHeader('X-GCS-Webhooks-Endpoint-Verification');
        if (strlen($verificationString) > 0) {
            $response->setContents($verificationString);
        } else {
            try {
                $signature = (string) $request->getHeader('X-GCS-Signature');
                $keyId = (string) $request->getHeader('X-GCS-KeyId');

                $this->handler->handle($this->getWebhookEvent($signature, $keyId), new DateTimeImmutable());

                $response->setContents($signature);
            } catch (RuntimeException $exception) {
                $this->logException($exception);
                $response->setHttpResponseCode(WebApiException::HTTP_INTERNAL_ERROR);
            } catch (Exception $exception) {
                $this->logException($exception);
                $response->setContents($exception->getMessage());
            }
        }

        return $response;
    }

    private function getWebhookEvent(string $signature, string $keyId): WebhooksEvent
    {
        /** @var Request $request */
        $request = $this->getRequest();
        return $this->unmarshaller->unmarshal($request->getContent(), [
            'X-GCS-Signature' => $signature,
            'X-GCS-KeyId' => $keyId,
        ]);
    }

    private function logException(Exception $exception)
    {
        $this->logger->warning(
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            sprintf(
                'Exception occurred when attempting to handle webhook: %1$s',
                $exception->getMessage()
            )
        );
        $this->logger->debug(
            'Webhook details',
            [
                'headers' => $this->getHeaders(),
                'body' => $this->getBody(),
            ]
        );
    }

    private function getHeaders(): array
    {
        $request = $this->getRequest();

        if (!$request instanceof Http) {
            return [];
        }

        $headers = [];
        foreach ($request->getHeaders() as $header) {
            $headers[$header->getFieldName()] = $header->getFieldValue();
        }
        return $headers;
    }

    private function getBody(): string
    {
        $request = $this->getRequest();

        if (!$request instanceof Http) {
            return '';
        }

        return $request->getContent();
    }
}
