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
    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }


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
            '
            SELECT `%s` FROM `catalog_product_entity` WHERE `sku` IN ("%s")',
            'entity_id',
            implode(",'", $skuList)
        );
        $productIds = $connection->fetchAll($sqlProductId);

        foreach ($productIds as $productId) {
            $idList[] = $productId['entity_id'];
        }

        //get Parents
        $sqlParentId = sprintf(
            'SELECT `%s`,`%s` FROM `%s` WHERE `%s` IN ("%s")',
            'product_id',
            'parent_id',
            'catalog_product_super_link',
            'product_id',
            implode(",'", $idList)
        );
        $parents = $connection->fetchAll($sqlParentId);

        //update stock of cataloginventory_stock_item & cataloginventory_stock_status
        foreach ($parents as $parentId) {
            $allParents[] = $parentId['parent_id'];
        }

        if (!isset($allParents)) {
            return $result;
        }

        //update stock status of parents
        $sqlUpdateStockItem = sprintf(
            'UPDATE `%s` SET `%s` = 1 WHERE `%s` IN ("%s")',
            'cataloginventory_stock_item',
            'is_in_stock',
            'product_id',
            implode(",'", $allParents)
        );
        $connection->query($sqlUpdateStockItem);

        //if no result break
        $sqlUpdateStockStatus = sprintf(
            'UPDATE `%s` SET `%s` = 1 WHERE `%s` IN ("%s")',
            'cataloginventory_stock_status',
            'stock_status',
            'product_id',
            implode(",'", $allParents)
        );
        $connection->query($sqlUpdateStockStatus);

        return $result;
    }
}
