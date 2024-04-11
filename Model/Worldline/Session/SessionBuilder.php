<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\Session;

use Worldline\Connect\Api\Data\SessionInterface;
use Worldline\Connect\Sdk\V1\Domain\SessionResponse;

class SessionBuilder
{
    /** @var SessionFactory */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    protected $sessionFactory;

    public function __construct(SessionFactory $sessionFactory)
    {
        $this->sessionFactory = $sessionFactory;
    }

    public function build(SessionResponse $sessionResponse): SessionInterface
    {
        $session = $this->sessionFactory->create();
        $session->fromJson($sessionResponse->toJson());

        return $session;
    }
}
