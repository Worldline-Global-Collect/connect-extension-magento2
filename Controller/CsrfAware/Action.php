<?php

declare(strict_types=1);

namespace Worldline\Connect\Controller\CsrfAware;

use Magento\Framework\App\Action\Action as CoreAction;

abstract class Action extends CoreAction
{
    /**
     * {@inheritdoc}
     */
    abstract public function execute();
}
