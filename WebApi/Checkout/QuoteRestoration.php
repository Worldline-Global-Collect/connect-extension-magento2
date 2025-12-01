<?php

namespace Worldline\Connect\WebApi\Checkout;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Worldline\Connect\Api\QuoteRestorationInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

class QuoteRestoration implements QuoteRestorationInterface
{

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    public function preserveQuoteId(int $quoteId): void
    {
        $this->checkoutSession->setWlShiftedQuoteRecoveryId(null);
        $this->checkoutSession->setWlQuoteRecoveryId($quoteId);
    }

    /**
     * Shift functionality is developed on purpose.
     * The requirements are to restore the cart by clicking on the "back" button.
     * Magento does not contain any information that the return has been done directly from the payment page.
     * So it is still possible to emulate cart restoring without clicking on the "back" button.
     *
     * With this functionality we are sure the flow is exactly the same:
     * - make the quote inactive and save the quote id in the session during redirection on the payment page
     * - click back
     * - go to the checkout page - the shift is done here
     * - as the quote is empty, Magento redirects the checkout/cart page
     * - on the checkout/cart page the quote id is taken from getWlShiftedQuoteRecoveryId
     * and the id is used for quote recovery
     *
     * @return void
     */
    public function shiftQuoteId(): void
    {
        $quoteId = $this->checkoutSession->getWlQuoteRecoveryId();
        $this->checkoutSession->setWlQuoteRecoveryId(null);
        $this->checkoutSession->setWlShiftedQuoteRecoveryId($quoteId);
    }

    public function restoreQuote(): void
    {
        if (!$quoteId = $this->checkoutSession->getWlShiftedQuoteRecoveryId()) {
            return;
        }

        $this->checkoutSession->setWlShiftedQuoteRecoveryId(null);

        try {
            $quote = $this->cartRepository->get($quoteId);
            $quote->setIsActive(true);

            $this->cartRepository->save($quote);
            $this->checkoutSession->replaceQuote($quote)->unsLastRealOrderId();
        } catch (NoSuchEntityException $e) {
            $this->logger->critical($e);
        }
    }
}
