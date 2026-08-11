<?php

declare(strict_types=1);

namespace Worldline\Connect\Controller\InlinePayment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Worldline\Connect\Api\SessionManagerInterface;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\Order\OrderServiceInterface;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Worldline\Connect\Sdk\V1\Domain\Payment;
use Worldline\Connect\WebApi\Checkout\QuoteManager;
use Worldline\Connect\WebApi\Checkout\QuoteRestoration;

class ProcessReturnNew implements HttpGetActionInterface, CsrfAwareActionInterface
{
    private const SUCCESS_URL = 'checkout/onepage/success';
    private const REJECT_URL = 'epayments/returns/reject';
    private const FAILURE_URL = 'epayments/returns/failure';

    private RequestInterface $request;
    private ResultFactory $resultFactory;
    private QuoteManager $quoteManager;
    private QuoteRestoration $quoteRestoration;
    private ClientInterface $worldlineClient;
    private OrderServiceInterface $orderService;
    private SessionManagerInterface $sessionManager;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        QuoteManager $quoteManager,
        QuoteRestoration $quoteRestoration,
        ClientInterface $worldlineClient,
        OrderServiceInterface $orderService,
        SessionManagerInterface $sessionManager,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->quoteManager = $quoteManager;
        $this->quoteRestoration = $quoteRestoration;
        $this->worldlineClient = $worldlineClient;
        $this->orderService = $orderService;
        $this->sessionManager = $sessionManager;
        $this->logger = $logger;
    }

    public function execute(): ResultInterface
    {
        $quote = null;
        try {
            $paymentId = (string) $this->request->getParam('REF');
            if ($paymentId === '') {
                $this->logger->error('Worldline ProcessReturnNew: missing REF parameter.');
                return $this->redirect(self::FAILURE_URL);
            }

            $quote = $this->quoteManager->getQuoteByWorldlinePaymentId($paymentId);
            if ($quote === null) {
                $this->logger->error('Worldline ProcessReturnNew: no quote for payment ' . $paymentId);
                return $this->redirect(self::FAILURE_URL);
            }

            return $this->processQuote($quote, $paymentId);
        } catch (Throwable $exception) {
            return $this->handleFailure($quote, $exception);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function processQuote(CartInterface $quote, string $paymentId): ResultInterface
    {
        $worldlinePayment = $this->worldlineClient->worldlinePayment(
            $paymentId,
            (int) $quote->getStoreId()
        );

        if (in_array($worldlinePayment->status, StatusInterface::DENIED_STATUSES, true)) {
            $this->cancelQuote($quote);
            $this->quoteRestoration->restoreQuote();
            return $this->redirect(self::REJECT_URL);
        }

        $order = $this->orderService->createOrderAndProcessStatus($quote, $worldlinePayment);
        $this->sessionManager->setOrderData($order);
        return $this->redirect(self::SUCCESS_URL);
    }

    private function handleFailure(?CartInterface $quote, Throwable $exception): ResultInterface
    {
        $this->logger->error(
            'Worldline ProcessReturnNew failed: ' . $exception->getMessage(),
            ['exception' => $exception]
        );

        if ($quote !== null) {
            $this->cancelQuote($quote);
            $this->quoteRestoration->restoreQuote();
        }

        return $this->redirect(self::FAILURE_URL);
    }

    private function cancelQuote(CartInterface $quote): void
    {
        $payment = $quote->getPayment();
        if ($payment === null) {
            return;
        }

        $payment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, StatusInterface::CANCELLED);
        $this->quoteManager->save($quote);
    }

    private function redirect(string $path): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath($path);
        return $redirect;
    }
}
