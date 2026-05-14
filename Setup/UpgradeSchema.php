<?php

declare(strict_types=1);

namespace Worldline\Connect\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Psr\Log\LoggerInterface;
use Worldline\Connect\Api\Data\EventInterface;
use Zend_Db_Exception;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Worldline\Connect\Model\Config;

use function version_compare;

// phpcs:ignore PSR12.Files.FileHeader.SpacingAfterBlock

/**
 * Class UpgradeSchema
 *
 * @package Worldline\Connect\Setup
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly WriterInterface $configWriter
    )
    {
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws Zend_Db_Exception
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '1.0.1') < 0) {
            $this->addWxUpdateColumns($setup);
        }

        if (version_compare($context->getVersion(), '1.5.0') < 0) {
            $this->addWebhookEventTable($setup);
        }

        if (version_compare($context->getVersion(), '1.5.1') < 0) {
            $this->updateWebhookEventTable($setup);
        }

        if (version_compare($context->getVersion(), '2.0.0') < 0) {
            $this->updateAclRolesResourceId($setup);
        }

        if (version_compare($context->getVersion(), '2.1.2', '<')) {
            $this->updateWebhookEventCreatedAtAttribute($setup);
        }

        if (version_compare($context->getVersion(), '3.0.3') < 0) {
            $this->dropEventIdAndOrderIncrementId($setup);
        }

        if (version_compare($context->getVersion(), '4.4.0') < 0) {
            $this->updatePaymentActionPath($setup);
        }

        // version is null if it is a fresh installation
        // default configuration should only be set for existing users
        if ($context->getVersion() && version_compare($context->getVersion(), '4.11.0') < 0) {
            $this->addOrderCreationFlowConfiguration($setup);
        }

        if (version_compare($context->getVersion(), '4.12.0') < 0) {
            $this->migrateToGenericSettingsForPaymentMethods($setup);
        }
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    private function addWxUpdateColumns(SchemaSetupInterface $setup)
    {
        $tableName = $setup->getTable('sales_order');
        if ($setup->getConnection()->isTableExists($tableName)) {
            $columns = [
                'order_update_wr_status' => [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Order Update Wr Status',
                ],
                'order_update_wr_first_time' => [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Order Update Wr First Time',
                ],
                'order_update_wr_history' => [
                    'type' => Table::TYPE_TEXT,
                    'nullable' => true,
                    'comment' => 'Order Update Wr History',
                ],
                'order_update_api_last_attempt_time' => [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Order Update Api Last Attempt Time',
                ],
                'order_update_api_history' => [
                    'type' => Table::TYPE_TEXT,
                    'nullable' => true,
                    'comment' => 'Order Update Api History',
                ],
            ];
            $connection = $setup->getConnection();
            foreach ($columns as $name => $definition) {
                $connection->addColumn($tableName, $name, $definition);
            }
        }
    }

    /**
     * @param SchemaSetupInterface $setup
     * @throws Zend_Db_Exception
     */
    // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    private function addWebhookEventTable(SchemaSetupInterface $setup)
    {
        $tableName = $setup->getTable('epayments_webhook_event');
        if ($setup->getConnection()->isTableExists($tableName) === false) {
            $table = $setup->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    EventInterface::ID,
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true,
                    ],
                    'Id'
                )
                ->addColumn(
                    'event_id',
                    Table::TYPE_TEXT,
                    100,
                    [
                        'nullable' => false,
                    ],
                    'Webhook event id'
                )
                ->addColumn(
                    'order_increment_id',
                    Table::TYPE_TEXT,
                    50,
                    [
                        'nullable' => false,
                    ],
                    'merchant reference / order increment id'
                )
                ->addColumn(
                    EventInterface::PAYLOAD,
                    Table::TYPE_TEXT,
                    null,
                    [],
                    'Original event data payload'
                )
                ->addColumn(
                    EventInterface::STATUS,
                    Table::TYPE_INTEGER,
                    1,
                    [
                        'unsigned' => true,
                        'default' => 0,
                    ],
                    'Processing status of the webhook event'
                )
                ->addIndex(
                    $setup->getIdxName($tableName, ['event_id', 'order_increment_id']),
                    ['event_id', 'order_increment_id']
                );
            $setup->getConnection()->createTable($table);
        }
    }

    private function updateWebhookEventTable(SchemaSetupInterface $setup)
    {
        $tableName = $setup->getTable('epayments_webhook_event');
        if ($setup->getConnection()->isTableExists($tableName)) {
            $setup->getConnection()->addColumn(
                $tableName,
                EventInterface::CREATED_TIMESTAMP,
                [
                    'TYPE' => Table::TYPE_TIMESTAMP,
                    'COMMENT' => 'Creation date of event on platform',
                ]
            );
        }
    }

    private function updateAclRolesResourceId(SchemaSetupInterface $setup)
    {
        $tableName = $setup->getTable('authorization_rule');
        if ($setup->getConnection()->isTableExists($tableName)) {
            $setup->getConnection()->update(
                $tableName,
                ['resource_id' => 'Worldline_Connect::epayments_config'],
                'resource_id = "Netresearch_Epayments::epayments_config"'
            );
            $setup->getConnection()->update(
                $tableName,
                ['resource_id' => 'Worldline_Connect::download_logfile'],
                'resource_id = "Netresearch_Epayments::download_logfile"'
            );
        }
    }

    private function updateWebhookEventCreatedAtAttribute(SchemaSetupInterface $setup)
    {
        $tableName = $setup->getTable('epayments_webhook_event');
        if ($setup->getConnection()->isTableExists($tableName)) {
            // We need to perform a native query, since Magento's MySQL adapter does not support
            // setting a length for a timestamp prior before v5.6.4 :
            $version = $setup->getConnection()->fetchOne('SELECT VERSION()');
            if (version_compare($version, '5.6.4', '>=')) {
                $setup->getConnection()->query(
                // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
                    sprintf(
                        'ALTER TABLE %1$s MODIFY COLUMN %2$s TIMESTAMP(4) NULL COMMENT \'%3$s\';',
                        $tableName,
                        EventInterface::CREATED_TIMESTAMP,
                        'Creation date of event on platform'
                    )
                );
            } else {
                // phpcs:ignore Generic.Files.LineLength.TooLong
                $this->logger->warning(
                    'MySQL version does not support fractional seconds. Race conditions in webhooks might occur'
                );
            }
        }
    }

    private function dropEventIdAndOrderIncrementId(SchemaSetupInterface $setup)
    {
        $tableName = $setup->getTable('epayments_webhook_event');
        if ($setup->getConnection()->isTableExists($tableName)) {
            $setup->getConnection()->dropColumn($tableName, 'event_id');
            $setup->getConnection()->dropColumn($tableName, 'order_increment_id');
        }
    }

    private function updatePaymentActionPath(SchemaSetupInterface $setup)
    {
        $tableName = $setup->getTable('core_config_data');
        $sql = "UPDATE $tableName
        SET path = REPLACE(path, 'payment_action', 'capture_config')
        WHERE path LIKE 'payment/worldline%/payment_action'";
        $setup->getConnection()->query($sql);
    }

    private function addOrderCreationFlowConfiguration(SchemaSetupInterface $setup)
    {
        $defaultScope = 'default';

        $connection = $setup->getConnection();
        $select = $connection->select()
            ->from($setup->getTable('core_config_data'), ['value'])
            ->where('path = ?', Config::CONFIG_ORDER_CREATION_FLOW_KEY)
            ->where('scope = ?', $defaultScope)
            ->where('scope_id = ?', 0);

        $currentValue = $connection->fetchOne($select);

        if (empty($currentValue)) {
            $this->configWriter->save(
                Config::CONFIG_ORDER_CREATION_FLOW_KEY,
                Config::CONFIG_ORDER_CREATION_FLOW_BEFORE,
                $defaultScope,
                0
            );
        }
    }

    private function migrateToGenericSettingsForPaymentMethods(SchemaSetupInterface $setup)
    {
        $tableName = $setup->getTable('core_config_data');
        $connection = $setup->getConnection();

        $connection->beginTransaction();

        try {
            $migrationSql = "INSERT INTO {$tableName} (scope, scope_id, path, value)
            SELECT
                source.scope,
                source.scope_id,
                REPLACE(source.path, 'payment/worldline_visa/', CONCAT('payment/', targets.group_name, '/')),
                source.value
            FROM {$tableName} AS source
            CROSS JOIN (
                SELECT 'worldline_payment_group_card' AS group_name
                UNION ALL SELECT 'worldline_payment_group_instant_purchase'
                UNION ALL SELECT 'worldline_payment_group_redirect'
            ) AS targets
            WHERE source.path IN (
                'payment/worldline_visa/allowspecific',
                'payment/worldline_visa/specificcountry',
                'payment/worldline_visa/min_order_total',
                'payment/worldline_visa/max_order_total',
                'payment/worldline_visa/capture_config',
                'payment/worldline_visa/payment_flow'
            )
            AND NOT (
                source.path = 'payment/worldline_visa/payment_flow'
                AND targets.group_name = 'worldline_payment_group_instant_purchase'
            )
            ON DUPLICATE KEY UPDATE value = VALUES(value);";

            $connection->query($migrationSql);

            $cleanupSql = "DELETE FROM {$tableName}
            WHERE (
                path LIKE 'payment/worldline_%/allowspecific'
                OR path LIKE 'payment/worldline_%/specificcountry'
                OR path LIKE 'payment/worldline_%/min_order_total'
                OR path LIKE 'payment/worldline_%/max_order_total'
                OR path LIKE 'payment/worldline_%/capture_config'
                OR path LIKE 'payment/worldline_%/payment_flow'
            )
            AND path NOT LIKE 'payment/worldline_payment_group_%'
            AND path NOT LIKE 'payment/worldline_hpp%'
            AND path NOT LIKE 'payment/worldline_link_plus_%';";

            $connection->query($cleanupSql);

            $connection->commit();

        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
