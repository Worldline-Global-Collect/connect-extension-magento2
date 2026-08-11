<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Serialized\ArraySerialized;
use Magento\Framework\Exception\LocalizedException;

use function array_key_exists;
use function is_array;
use function is_scalar;
use function is_string;
use function json_decode;
use function trim;

/**
 * Base backend model for the "In- or exclude payment products" configuration fields.
 *
 * Those fields are rendered by Block\Adminhtml\System\Config\Field\PaymentProductList
 * (an AbstractFieldArray with a single free-text "id" column), so their value is a list of
 * rows shaped ['id' => '<value>']. Without validation any typo is persisted and forwarded to
 * the Worldline Connect API inside hostedCheckoutSpecificInput.paymentProductFilters, where it
 * fails far away from its cause. Subclasses reject invalid rows while the admin is still saving.
 */
abstract class AbstractIdentifierList extends ArraySerialized
{
    /**
     * Validation runs before the parent serializes the rows, so getValue() is still the raw list.
     *
     * @throws LocalizedException
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
    public function beforeSave()
    {
        foreach ($this->parseIdentifiers() as $identifier) {
            $this->validateIdentifier($identifier);
        }

        return parent::beforeSave();
    }

    /**
     * @throws LocalizedException when the identifier is not accepted for this field
     */
    abstract protected function validateIdentifier(string $identifier): void;

    /**
     * Mirrors CreateHostedCheckoutRequestBuilder::getIdentifiers() on purpose: what the admin is
     * allowed to save and what the request builder later reads must never disagree. Keep both in
     * lockstep - empty rows are dropped here exactly as they are ignored there.
     *
     * @return array<int, string>
     */
    private function parseIdentifiers(): array
    {
        $identifiers = [];
        foreach ($this->getRows() as $row) {
            if (!is_array($row) || !array_key_exists('id', $row) || !is_scalar($row['id'])) {
                continue;
            }

            $id = trim((string) $row['id']);
            if ($id === '') {
                continue;
            }

            $identifiers[] = $id;
        }

        return $identifiers;
    }

    /**
     * The admin form hands us an array plus the hidden "__empty" prototype row. Non-form writers
     * (bin/magento config:set, PreparedValueFactory) hand us the already serialized string, which
     * is why the string branch exists - it is the only path that could otherwise bypass validation.
     * A value that is not valid JSON yields no identifiers, which is also how the request builder
     * treats it at runtime.
     *
     * @return array<mixed>
     */
    private function getRows(): array
    {
        $value = $this->getValue();

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return [];
        }

        unset($value['__empty']);

        return $value;
    }
}
