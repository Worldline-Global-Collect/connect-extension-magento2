<?php

namespace Worldline\Connect\Controller\Returns;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Worldline\Connect\Api\QuoteManagerInterface;
use Worldline\Connect\Api\WorldlineStatusCheckerInterface;
use Worldline\Connect\Model\Config;
use Worldline\Connect\WebApi\Checkout\WorldlineStatusChecker;

class PendingOrder extends Action implements HttpPostActionInterface
{
    /** @var WorldlineStatusCheckerInterface */
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
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $incrementId = $this->getRequest()->getParam('incrementId', '');

        $quote = $this->quoteManager->getQuoteByReservedOrderId($incrementId);
        if (!$quote || !$quote->getPayment() ||
            !$hostedCheckoutId = $quote->getPayment()->getAdditionalInformation(Config::HOSTED_CHECKOUT_ID_KEY)) {
            return $result->setData(['status' => false]);
        }

        $route = $this->statusChecker->processQuote($quote, $hostedCheckoutId, true);

        $param['status'] = $route === WorldlineStatusChecker::SUCCESS_URL;

        return $result->setData($param);
    }
}
