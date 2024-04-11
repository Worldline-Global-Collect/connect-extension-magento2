<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline;

use Worldline\Connect\Sdk\Client;
use Worldline\Connect\Sdk\Communicator;

class ClientFactory
{
    // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    /**
     * Create class instance with specified parameters
     *
     * @param Communicator $communicator
     * @param string $clientMetaInfo
     * @return \Worldline\Connect\Sdk\Client
     */
    // phpcs:enable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    public function create(Communicator $communicator, $clientMetaInfo = '')
    {
        return new Client($communicator, $clientMetaInfo);
    }
}
