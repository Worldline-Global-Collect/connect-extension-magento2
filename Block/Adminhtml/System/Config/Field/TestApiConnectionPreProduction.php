<?php

declare(strict_types=1);

namespace Worldline\Connect\Block\Adminhtml\System\Config\Field;

use Worldline\Connect\Model\Config;

use function array_merge;

class TestApiConnectionPreProduction extends TestApiConnection
{
    public function getAjaxUrl(): string
    {
        return $this->getUrl('epayments/Api/TestConnection', array_merge(
            ['environment' => Config::CONFIG_WORLDLINE_API_ENDPOINT_PRE_PROD],
            $this->getScopeUrlParams()
        ));
    }

    public function getId(): string
    {
        return 'test_api_connection_pre_production';
    }
}
