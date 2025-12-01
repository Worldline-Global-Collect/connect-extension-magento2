<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Session;

use Magento\Checkout\Model\Session;
use Magento\Sales\Api\Data\OrderInterface;
use Worldline\Connect\Api\Data\SessionInterface;
use Worldline\Connect\Api\SessionManagerInterface;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\RequestBuilder\CreateSession\RequestBuilder;
use Worldline\Connect\Model\Worldline\Token\TokenServiceInterface;

class SessionManager implements SessionManagerInterface
{
    /** @var ClientInterface */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    protected $worldlineClient;

    /** @var RequestBuilder */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    protected $createSessionRequestBuilder;

    /** @var TokenServiceInterface */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    protected $tokenService;

    /** @var SessionBuilder */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    protected $sessionBuilder;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @param ClientInterface $worldlineClient
     * @param RequestBuilder $createSessionRequestBuilder
     * @param TokenServiceInterface $tokenService
     * @param SessionBuilder $sessionBuilder
     * @param Session $checkoutSession
     */
    public function __construct(
        ClientInterface $worldlineClient,
        RequestBuilder $createSessionRequestBuilder,
        TokenServiceInterface $tokenService,
        SessionBuilder $sessionBuilder,
        Session $checkoutSession
    ) {
        $this->worldlineClient = $worldlineClient;
        $this->createSessionRequestBuilder = $createSessionRequestBuilder;
        $this->tokenService = $tokenService;
        $this->sessionBuilder = $sessionBuilder;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param int $customerId
     * @return SessionInterface
     */
    public function createCustomerSession($customerId): SessionInterface
    {
        return $this->createSession($customerId);
    }

    /**
     * @return SessionInterface
     */
    public function createAnonymousSession(): SessionInterface
    {
        return $this->createSession();
    }

    /**
     * @param int|null $customerId
     * @return SessionInterface
     */
    protected function createSession($customerId = null): SessionInterface
    {
        $tokens = $customerId !== null ? $this->tokenService->find($customerId) : [];

        $createSessionRequest = $this->createSessionRequestBuilder->build($tokens);
        $createSessionResponse = $this->worldlineClient->worldlineCreateSession($createSessionRequest);

        if ($customerId !== null) {
            $this->tokenService->deleteAll($customerId, $createSessionResponse->invalidTokens);
        }

        return $this->sessionBuilder->build($createSessionResponse);
    }

    /**
     * @inheritDoc
     */
    public function setOrderData(OrderInterface $order): void
    {
        $this->checkoutSession->setLastOrderId((int) $order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
    }
}
