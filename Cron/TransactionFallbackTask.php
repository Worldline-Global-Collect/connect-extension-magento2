<?php

namespace Worldline\Connect\Cron;

use Psr\Log\LoggerInterface;
use Worldline\Connect\Model\Fallback\FallbackServiceInterface;

class TransactionFallbackTask
{
    /**
     * @var FallbackServiceInterface
     */
    private $fallbackService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     * @param FallbackServiceInterface $fallbackService
     */
    public function __construct(
        LoggerInterface $logger,
        FallbackServiceInterface $fallbackService
    ) {
        $this->logger = $logger;
        $this->fallbackService = $fallbackService;
    }

    /**
     * Processes the job
     */
    public function execute(): void
    {
        try {
            $this->fallbackService->process();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
