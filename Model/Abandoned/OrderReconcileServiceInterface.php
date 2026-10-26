<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Abandoned;

// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface OrderReconcileServiceInterface
{
    /**
     * Find Worldline orders left in "pending payment" by an abandoned payment page and resolve
     * their real outcome against Worldline.
     *
     * @return void
     */
    public function process(): void;
}
