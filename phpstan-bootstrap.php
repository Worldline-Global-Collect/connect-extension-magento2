<?php

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;

// phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
Bootstrap::create(BP, $_SERVER);
