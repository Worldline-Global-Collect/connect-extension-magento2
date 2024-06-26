<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Client;

use Worldline\Connect\Sdk\Logging\CommunicatorLogger;

class CommunicatorLoggerPool implements CommunicatorLogger
{
    /**
     * @var array<CommunicatorLogger>
     */
    private array $communicatorLoggers = [];

    /**
     * @param array<CommunicatorLogger> $communicatorLoggers
     */
    public function __construct(array $communicatorLoggers)
    {
        $this->setCommunicatorLoggers($communicatorLoggers);
    }

    /**
     * @param array<CommunicatorLogger> $communicatorLoggers
     */
    public function setCommunicatorLoggers(array $communicatorLoggers)
    {
        $this->communicatorLoggers = [];
        $this->addCommunicatorLoggers($communicatorLoggers);
    }

    /**
     * @param array<CommunicatorLogger> $communicatorLoggers
     */
    public function addCommunicatorLoggers(array $communicatorLoggers)
    {
        foreach ($communicatorLoggers as $communicatorLogger) {
            $this->addCommunicatorLogger($communicatorLogger);
        }
    }

    public function addCommunicatorLogger(CommunicatorLogger $communicatorLogger)
    {
        $this->communicatorLoggers[] = $communicatorLogger;
    }

    /**
     * {@inheritdoc}
     */
    public function log($message)
    {
        foreach ($this->communicatorLoggers as $communicatorLogger) {
            $communicatorLogger->log($message);
        }
    }

    /**
     * {@inheritdoc}
     */
    // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    public function logException($message, \Exception $exception)
    {
        foreach ($this->communicatorLoggers as $communicatorLogger) {
            $communicatorLogger->logException($message, $exception);
        }
    }
}
