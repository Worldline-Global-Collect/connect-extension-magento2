<?php

declare(strict_types=1);

namespace Worldline\Connect\Cron;

use Exception;
use Psr\Log\LoggerInterface;
use Worldline\Connect\Model\DelayedCapture\DelayedCaptureService;

class DelayedCaptureTask
{
    public function __construct(
        private readonly DelayedCaptureService $delayedCaptureService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        try {
            $this->delayedCaptureService->process();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
