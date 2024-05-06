<?php

declare(strict_types=1);

namespace Worldline\Connect\Cron;

use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Item as OrderItemResource;
use Zend_Db_Expr;

use function sprintf;
use function var_dump;

class AutoCaptureInvoice
{
    public function __construct(
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly OrderItemResource $orderItemResource,
    ) {
    }

    public function execute(): void
    {
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->join(
            ['sales_order_item' => $this->orderItemResource->getMainTable()],
            'sales_order_item.order_id = main_table.entity_id',
            null
        );
        $select = $orderCollection->getSelect();
        $select->group('main_table.entity_id');
        $select->columns(['qty_to_invoice' => new Zend_Db_Expr(sprintf(
            'SUM(sales_order_item.%s) - SUM(sales_order_item.%s) - SUM(sales_order_item.%s)',
            OrderItem::QTY_ORDERED,
            OrderItem::QTY_INVOICED,
            OrderItem::QTY_CANCELED,
        )),
        ]);
        $select->having('qty_to_invoice > 0');
        $select->query();


        var_dump($orderCollection->getSelectSql(true));
    }
}
