<?php

namespace Worldline\Connect\Controller\Returns;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Worldline\Connect\Api\QuoteManagerInterface;

class Failure extends Action implements HttpGetActionInterface
{
    /**
     * @var QuoteManagerInterface
     */
    private $quoteManager;

    public function __construct(Context $context, QuoteManagerInterface $quoteManager)
    {
        parent::__construct($context);
        $this->quoteManager = $quoteManager;
    }

    public function execute()
    {
        $this->messageManager->addErrorMessage(__('An unexpected technical error occurred.'
            . ' Please refill your cart and try again.'
        ));

        $this->quoteManager->clearQuote();

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath('checkout/cart');

        return $redirect;
    }
}
