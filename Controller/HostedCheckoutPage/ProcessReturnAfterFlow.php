<?php

namespace Worldline\Connect\Controller\HostedCheckoutPage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Worldline\Connect\Api\QuoteManagerInterface;
use Worldline\Connect\Api\WorldlineStatusCheckerInterface;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Worldline\Connect\WebApi\Checkout\WorldlineStatusChecker;

class ProcessReturnAfterFlow extends Action
{
    /**
     * @var WorldlineStatusCheckerInterface
     */
    private $statusChecker;

    /**
     * @var QuoteManagerInterface
     */
    private $quoteManager;

    /**
     * @param Context $context
     * @param WorldlineStatusCheckerInterface $statusChecker
     * @param QuoteManagerInterface $quoteManager
     */
    public function __construct(
        Context $context,
        WorldlineStatusCheckerInterface $statusChecker,
        QuoteManagerInterface $quoteManager
    ) {
        parent::__construct($context);
        $this->statusChecker = $statusChecker;
        $this->quoteManager = $quoteManager;
    }

    public function execute()
    {
        $quote = null;
        try {
            $hostedCheckoutId = (string) $this->getRequest()->getParam('hostedCheckoutId');

            $quote = $this->quoteManager->getQuoteByWorldlinePaymentId($hostedCheckoutId);
            if (!$quote) {
                throw new LocalizedException(__('The payment has failed. Please, try again'));
            }

            $orderId = $this->statusChecker->isCancelled($quote, $hostedCheckoutId);
            $route = $this->statusChecker->processQuote($quote, $hostedCheckoutId);

            if ($route === WorldlineStatusChecker::WAITING_URL) {
                return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)
                    ->setPath($route, ['incrementId' => $orderId]);
            }

            // Success or Reject URL
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath($route);
        } catch (LocalizedException $exception) {
            $this->cancelQuote($quote);
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)
                ->setPath(WorldlineStatusChecker::REJECT_URL);
        }
    }

    /**
     * @param CartInterface|null $quote
     *
     * @throws LocalizedException
     */
    private function cancelQuote($quote): void
    {
        if (!$quote || !$quote->getPayment()) {
            return;
        }

        $payment = $quote->getPayment();
        $payment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, StatusInterface::CANCELLED);

        $this->quoteManager->save($quote);
    }
}
