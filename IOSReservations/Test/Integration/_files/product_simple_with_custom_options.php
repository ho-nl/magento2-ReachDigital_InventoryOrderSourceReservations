<?php
declare(strict_types=1);
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

Bootstrap::getInstance()->reinitialize();

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var CategoryLinkManagementInterface $categoryLinkManagement */
$categoryLinkManagement = $objectManager->create(CategoryLinkManagementInterface::class);

/** @var $product Product */
$product = $objectManager->create(Product::class);
$product->isObjectNew(true);
$product
    ->setTypeId(Type::TYPE_SIMPLE)
    ->setAttributeSetId(4)
    ->setWebsiteIds([1])
    ->setName('Simple Product')
    ->setSku('simple')
    ->setPrice(10)
    ->setWeight(1)
    ->setShortDescription('Short description')
    ->setTaxClassId(0)
    ->setDescription('Description with <b>html tag</b>')
    ->setMetaTitle('meta title')
    ->setMetaKeyword('meta keyword')
    ->setMetaDescription('meta description')
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setStockData([
        'use_config_manage_stock' => 1,
    ])
    ->setCanSaveCustomOptions(true)
    ->setHasOptions(true);

$oldOptions = [
    [
        'previous_group' => 'select',
        'title' => 'Test Select',
        'type' => 'drop_down',
        'is_require' => 1,
        'sort_order' => 0,
        'values' => [
            [
                'option_type_id' => null,
                'title' => 'Option 1',
                'price' => '-3,000.00',
                'price_type' => 'fixed',
                'sku' => '3-1-select',
            ],
            [
                'option_type_id' => null,
                'title' => 'Option 2',
                'price' => '5,000.00',
                'price_type' => 'fixed',
                'sku' => '3-2-select',
            ],
        ],
    ],
    [
        'previous_group' => 'select',
        'title' => 'Test Radio',
        'type' => 'radio',
        'is_require' => 1,
        'sort_order' => 0,
        'values' => [
            [
                'option_type_id' => null,
                'title' => 'Option 1',
                'price' => '600.234',
                'price_type' => 'fixed',
                'sku' => '4-1-radio',
            ],
            [
                'option_type_id' => null,
                'title' => 'Option 2',
                'price' => '40,000.00',
                'price_type' => 'fixed',
                'sku' => '4-2-radio',
            ],
        ],
    ],
];

$options = [];

/** @var ProductCustomOptionInterfaceFactory $customOptionFactory */
$customOptionFactory = $objectManager->create(ProductCustomOptionInterfaceFactory::class);

foreach ($oldOptions as $option) {
    /** @var ProductCustomOptionInterface $option */
    $option = $customOptionFactory->create(['data' => $option]);
    $option->setProductSku($product->getSku());

    $options[] = $option;
}

$product->setOptions($options);

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->create(ProductRepositoryInterface::class);
$productRepository->save($product, true);

$categoryLinkManagement->assignProductToCategories($product->getSku(), [2]);
