<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Adds a "Google Product Category" attribute to BOTH categories and products.
 *
 * - Category attribute (inherited up the tree by the feed): set it on a top
 *   branch once and every product underneath maps to that Google taxonomy,
 *   unless a deeper category or the product itself overrides it.
 * - Product attribute: per-product override for the exceptions.
 *
 * This is the admin-native way to map Magento categories to the Google
 * product taxonomy — edited right in Catalog → Categories / product edit,
 * no separate mapping screen to maintain.
 */
class AddGoogleCategoryAttributes implements DataPatchInterface
{
    public const ATTR_CODE = 'dlabsit_google_category';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly CategorySetupFactory $categorySetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        // --- Category attribute (inherited up the tree) ---
        /** @var \Magento\Catalog\Setup\CategorySetup $categorySetup */
        $categorySetup = $this->categorySetupFactory->create(['setup' => $this->moduleDataSetup]);
        $categorySetup->addAttribute(
            Category::ENTITY,
            self::ATTR_CODE,
            [
                'type' => 'varchar',
                'label' => 'Google Product Category',
                'input' => 'text',
                'required' => false,
                'sort_order' => 100,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'General Information',
                'note' => 'Google taxonomy ID or full path. Inherited by all products under this '
                    . 'category unless a deeper category or the product overrides it.',
            ]
        );

        // --- Product attribute (per-product override) ---
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->addAttribute(
            Product::ENTITY,
            self::ATTR_CODE,
            [
                'type' => 'varchar',
                'label' => 'Google Product Category',
                'input' => 'text',
                'required' => false,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Search Engine Optimization',
                'is_used_in_grid' => false,
                'note' => 'Overrides the category-level Google Product Category for this product only.',
            ]
        );

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
