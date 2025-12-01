<?php

namespace Worldline\Connect\Controller\Returns;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Worldline\Connect\Api\QuoteManagerInterface;

class Waiting extends Action implements HttpGetActionInterface
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
        $incrementId = $this->getRequest()->getParam('incrementId');

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath('noRoute');
        if (!$incrementId) {
            return $redirect;
        }

        $quote = $this->quoteManager->getQuoteByReservedOrderId($incrementId);
        if (!$quote || !$quote->getId()) {
            return $redirect;
        }

        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend(__('Waiting for payment confirmation'));

        return $resultPage;
    }
}
