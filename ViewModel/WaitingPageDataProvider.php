<?php

namespace Worldline\Connect\ViewModel;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as ModelProduct;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\StoreManagerInterface;
use Worldline\Connect\Api\QuoteManagerInterface;

class WaitingPageDataProvider implements ArgumentInterface
{
    /**
     * @var QuoteManagerInterface
     */
    private $quoteManager;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Image
     */
    private $imageHelper;

    /**
     * @param QuoteManagerInterface $quoteManager
     * @param RequestInterface $request
     * @param PriceCurrencyInterface $priceCurrency
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     * @param Image $imageHelper
     */
    public function __construct(
        QuoteManagerInterface $quoteManager,
        RequestInterface $request,
        PriceCurrencyInterface $priceCurrency,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        Image $imageHelper,
    )
    {
        $this->quoteManager = $quoteManager;
        $this->request = $request;
        $this->priceCurrency = $priceCurrency;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->imageHelper = $imageHelper;
    }

    public function getQuote(): ?CartInterface
    {
        return $this->quoteManager->getQuoteByReservedOrderId($this->getIncrementId());
    }

    public function getIncrementId(): string
    {
        return $this->request->getParam('incrementId', '');
    }

    public function getNotificationMessage(): Phrase
    {
        return __('Please wait, the payment is being processed...');
    }

    public function checkOrderUrl(): string
    {
        return $this->urlBuilder->getUrl('epayments/returns/checkOrder');
    }

    public function successUrl(): string
    {
        return $this->urlBuilder->getUrl('checkout/onepage/success');
    }

    public function failUrl(): string
    {
        return $this->urlBuilder->getUrl('epayments/returns/failed');
    }

    public function pendingPageUrl(): string
    {
        return $this->urlBuilder->getUrl(
            'epayments/returns/pending',
            ['incrementId' => $this->getIncrementId()]
        );
    }

    public function pendingOrderUrl(): string
    {
        return $this->urlBuilder->getUrl('epayments/returns/pendingOrder');
    }

    public function getResizedImageUrl(ModelProduct $product): string
    {
        return $this->imageHelper->init($product, 'product_page_image_small')
            ->setImageFile($product->getSmallImage())
            ->resize(75, 75)
            ->getUrl();
    }

    public function formatPrice(float $price): string
    {
        return $this->priceCurrency->format($price);
    }

    public function getStoreCode(): string
    {
        return $this->storeManager->getStore()->getCode();
    }

    public function getSurchargeAmount(): float
    {
        /*$quote = $this->getQuote();
        if (!$quote) {
            return 0.0;
        }

        $quoteId = (int)$quote->getId();
        $surcharging = $this->surchargingRepository->getByQuoteId($quoteId);
        $paymentMethod = str_replace('_vault', '', (string)$quote->getPayment()->getMethod());
        if ($paymentMethod !== $surcharging->getPaymentMethod()) {
            return 0.0;
        }

        return (float)$surcharging->getAmount();*/

        return 0.0;
    }
}
