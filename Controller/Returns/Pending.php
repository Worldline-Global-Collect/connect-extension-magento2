<?php

namespace Worldline\Connect\Controller\Returns;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Worldline\Connect\Api\QuoteManagerInterface;

class Pending extends Action implements HttpGetActionInterface
{
    /**
     * @var QuoteManagerInterface
     */
    private $quoteManager;

    public function __construct(
        Context $context,
        QuoteManagerInterface $quoteManager
    ) {
        parent::__construct($context);
        $this->quoteManager = $quoteManager;
    }

    public function execute(): ResultInterface
    {
        $this->quoteManager->clearQuote();

        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend(__('Your payment is being processed'));
        return $resultPage;
    }
}