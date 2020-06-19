<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\ISReservationsApi\Plugin;

use Magento\Framework\App\ResourceConnection;

class UpdateParentStockPlugin
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param  \Magento\Inventory\Model\ResourceModel\SourceItem\SaveMultiple $subject
     * @param  $result
     * @param  array                                                          $sourceItems
     * @return mixed
     */
    public function afterExecute(
        \Magento\Inventory\Model\ResourceModel\SourceItem\SaveMultiple $subject,
        $result,
        array $sourceItems
    ) {
        $connection = $this->resourceConnection->getConnection();

        //get SKU's
        foreach ($sourceItems as $sourceItem) {
            if ($sourceItem->getData('status')) {
                $skuList[] = $sourceItem->getData('sku');
            }
        }

        if (!isset($skuList)) {
            return $result;
        }

        //Get id's from SKU
        $sqlProductId = sprintf(
            'SELECT `%s` FROM `catalog_product_entity` WHERE `sku` IN ("%s")',
            'entity_id',
            implode(",'", $skuList)
        );
        $productIds = $connection->fetchCol($sqlProductId);

        //get Parents
        $sqlParentId = sprintf(
            'SELECT `%s` FROM `%s` WHERE `%s` IN ("%s")',
            'parent_id',
            'catalog_product_super_link',
            'product_id',
            implode(",'", $productIds)
        );
        $parents = $connection->fetchCol($sqlParentId);

        if (!isset($parents)) {
            return $result;
        }

        //update stock status of parents
        $sqlUpdateStockItem = sprintf(
            'UPDATE `%s` SET `%s` = 1 WHERE `%s` IN ("%s")',
            'cataloginventory_stock_item',
            'is_in_stock',
            'product_id',
            implode(",'", $parents)
        );
        $connection->query($sqlUpdateStockItem);

        //if no result break
        $sqlUpdateStockStatus = sprintf(
            'UPDATE `%s` SET `%s` = 1 WHERE `%s` IN ("%s")',
            'cataloginventory_stock_status',
            'stock_status',
            'product_id',
            implode(",'", $parents)
        );
        $connection->query($sqlUpdateStockStatus);

        return $result;
    }
}
