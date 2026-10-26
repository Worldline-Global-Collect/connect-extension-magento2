<?php

declare(strict_types=1);

namespace Worldline\Connect\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Psr\Log\LoggerInterface;
use Worldline\Connect\Sdk\V1\Domain\APIError;
use Worldline\Connect\Sdk\V1\ResponseException;

use function __;
use function array_reduce;
use function sprintf;

class ApiErrorHandler
{
    public function __construct(
        private readonly ManagerInterface $manager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param ResponseException $e
     * @throws CommandException
     */
    public function handleError(ResponseException $e)
    {
        $errors = $e->getErrors();
        $message = array_reduce(
            $errors,
            static function (
                $message,
                APIError $error
            ) {
                $message .= sprintf(
                    "HTTP: %s Message: %s \n",
                    $error->httpStatusCode,
                    $error->message
                );

                return $message;
            },
            ''
        );
        $this->manager->addErrorMessage($message);
        $this->logger->error($message);

        throw new CommandException(__($e->getMessage()));
    }

    /**
     * Log a locally raised (non-API) command failure and abort the command.
     *
     * The message is NOT pushed to the message manager here: CommandException is a
     * LocalizedException, so Magento's credit memo / order controllers already render
     * $e->getMessage() themselves. Adding it here as well shows it twice.
     *
     * @param LocalizedException $e
     * @throws CommandException
     */
    public function handleLocalizedError(LocalizedException $e)
    {
        $message = $e->getMessage();
        $this->logger->error($message);

        throw new CommandException(__($message));
    }
}
