<?php

namespace Worldline\Connect\Controller\Returns;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Worldline\Connect\Api\QuoteRestorationInterface;

class Reject extends Action implements HttpGetActionInterface
{
    /**
     * @var QuoteRestorationInterface
     */
    private $quoteRestoration;

    public function __construct(Context $context, QuoteRestorationInterface $quoteRestoration)
    {
        parent::__construct($context);
        $this->quoteRestoration = $quoteRestoration;
    }

    public function execute()
    {
        $this->messageManager->addErrorMessage(__('The payment has rejected, please, try again'));

        $this->quoteRestoration->shiftQuoteId();

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath('checkout/cart');

        return $redirect;
    }
}
