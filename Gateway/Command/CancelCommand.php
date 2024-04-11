<?php

declare(strict_types=1);

namespace Worldline\Connect\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Worldline\Connect\Model\Worldline\Action\CancelPayment;
use Worldline\Connect\Sdk\V1\ResponseException;

class CancelCommand implements CommandInterface
{
    public function __construct(
        private readonly CancelPayment $cancelPayment,
        private readonly ApiErrorHandler $apiErrorHandler
    ) {
    }

    /**
     * @throws LocalizedException
     * @throws CommandException
     */
    public function execute(array $commandSubject): mixed
    {
        try {
            $this->cancelPayment->process($commandSubject['payment']->getPayment()->getOrder());
        } catch (ResponseException $e) {
            $this->apiErrorHandler->handleError($e);
        }

        return null;
    }
}
