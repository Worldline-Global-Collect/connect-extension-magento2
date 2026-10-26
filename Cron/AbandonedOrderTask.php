<?php

declare(strict_types=1);

namespace Worldline\Connect\Cron;

use Exception;
use Psr\Log\LoggerInterface;
use Worldline\Connect\Model\Abandoned\OrderReconcileServiceInterface;

class AbandonedOrderTask
{
    public function __construct(
        private readonly OrderReconcileServiceInterface $orderReconcileService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Processes the job
     */
    public function execute(): void
    {
        try {
            $this->orderReconcileService->process();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
