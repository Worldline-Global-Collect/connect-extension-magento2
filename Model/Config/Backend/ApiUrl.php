<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

use function __;
use function array_key_exists;
use function is_array;
use function parse_url;
use function rtrim;
use function strtolower;
use function trim;

class ApiUrl extends Value
{
    private const EXAMPLE_URL = 'https://api.preprod.connect.worldline-solutions.com';

    /**
     * The Connect SDK rejects an API endpoint that carries a path, so the value must be a bare
     * https origin.
     *
     * @return Value
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = rtrim(trim((string) $this->getValue()), '/');

        if ($value !== '' && !$this->isHttpsOrigin($value)) {
            throw new LocalizedException(
                __(
                    'The API URL "%1" is invalid. Enter the URL in the format https://<host>, for example %2.',
                    $value,
                    self::EXAMPLE_URL
                )
            );
        }

        $this->setValue($value);

        return parent::beforeSave();
    }

    private function isHttpsOrigin(string $value): bool
    {
        $url = parse_url($value);
        if (!is_array($url)) {
            return false;
        }

        foreach (['path', 'query', 'fragment', 'user', 'pass'] as $component) {
            if (array_key_exists($component, $url)) {
                return false;
            }
        }

        return strtolower($url['scheme'] ?? '') === 'https' && ($url['host'] ?? '') !== '';
    }
}
