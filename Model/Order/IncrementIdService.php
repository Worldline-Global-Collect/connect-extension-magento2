<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\SalesSequence\Model\Profile;
use Magento\SalesSequence\Model\ResourceModel\Meta as MetaResource;
use Magento\SalesSequence\Model\ResourceModel\Profile as ProfileResource;
use Magento\SalesSequence\Model\Sequence;
use Magento\Store\Api\StoreRepositoryInterface;

class IncrementIdService
{
    /**
     * @var Sequence
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $sequence;

    /**
     * @var StoreRepositoryInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $storeRepository;

    /**
     * @var MetaResource
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $metaResource;

    /**
     * @var ProfileResource
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $profileResource;

    public function __construct(
        Sequence $sequence,
        StoreRepositoryInterface $storeRepository,
        MetaResource $metaResource,
        ProfileResource $profileResource
    ) {
        $this->sequence = $sequence;
        $this->storeRepository = $storeRepository;
        $this->metaResource = $metaResource;
        $this->profileResource = $profileResource;
    }

    /**
     * @return int
     * @throws LocalizedException
     */
    public function calculateMaxOrderIncrementIdLength(): int
    {
        $maxLength = 0;

        foreach ($this->storeRepository->getList() as $store) {
            $incrementId = $this->getDummyIncrementIdByStore((int) $store->getId());
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            $maxLength = max($maxLength, strlen($incrementId));
        }

        return $maxLength;
    }

    /**
     * @param int $storeId
     * @return string
     * @throws LocalizedException
     */
    private function getDummyIncrementIdByStore(int $storeId): string
    {
        $sequenceProfile = $this->getSequenceProfileByStore($storeId);

        $getPattern = function () {
            return $this->pattern; // @phpstan-ignore-line
        };
        $pattern = $getPattern->call($this->sequence);

        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        return sprintf(
            $pattern,
            $sequenceProfile->getData('prefix'),
            '1',
            $sequenceProfile->getData('suffix')
        );
    }

    /**
     * @throws LocalizedException
     */
    private function getSequenceProfileByStore(int $storeId): Profile
    {
        $meta = $this->metaResource->loadByEntityTypeAndStore('order', $storeId);
        return $this->profileResource->loadActiveProfile($meta->getId());
    }
}
