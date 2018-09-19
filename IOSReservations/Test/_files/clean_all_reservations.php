<?php
declare(strict_types=1);

/** @var \Magento\Framework\App\ResourceConnection $resource */
$resource   = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(\Magento\Framework\App\ResourceConnection::class);

$resource->getConnection()->truncateTable($resource->getTableName('inventory_reservation'));
$resource->getConnection()->truncateTable($resource->getTableName('inventory_source_reservation'));
