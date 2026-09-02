<?php

declare(strict_types=1);

namespace Worldline\Connect\Controller\Returns;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Worldline\Connect\Api\QuoteRestorationInterface;

use function __;

/**
 * Landing page for the Worldline NO_PAYMENT_PRODUCTS_AVAILABLE (1406) outcome: the platform has no
 * payment product for this order total / currency / country, so the shopper is sent back to the
 * cart with an actionable message instead of being stranded on the checkout page.
 *
 * Mirrors Returns\Reject: the quote id is shifted here so the RestoreQuote observer re-activates
 * the cart on checkout/cart.
 */
class NoPaymentMethods extends Action implements HttpGetActionInterface
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
        $this->messageManager->addErrorMessage(__(
            'We\'re sorry, but we cannot process this order. Please try adjusting your cart total or'
             . ' selecting a different payment method. If you continue to experience issues, please'
             . ' contact our support team.'
        ));

        $this->quoteRestoration->shiftQuoteId();

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath('checkout/cart');

        return $redirect;
    }
}
