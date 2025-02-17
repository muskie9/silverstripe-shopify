<?php

namespace Dynamic\Shopify\Page;

use Dynamic\Shopify\Model\ShopifyFile;
use Dynamic\Shopify\Task\ShopifyImportTask;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;

/**
 * Class ShopifyCollection
 * @package Dynamic\Shopify\Page
 */
class ShopifyCollection extends \Page
{
    /**
     * @var string
     */
    private static $table_name = 'ShopifyCollection';

    /**
     * @var string[]
     */
    private static $db = [
        'ShopifyID' => 'Varchar',
        'Published' => 'Boolean',
        'PublishedAt' => 'Datetime',
    ];

    /**
     * @var string[]
     */
    private static $data_map = [
        'id' => 'ShopifyID',
        'handle' => 'URLSegment',
        'title' => 'Title',
        'body_html' => 'Content',
        'updated_at' => 'LastEdited',
        'created_at' => 'Created',
        'published' => 'Published',
        'published_at' => 'PublishedAt',
    ];

    /**
     * @var string[]
     */
    private static $has_one = [
        'File' => ShopifyFile::class
    ];

    /**
     * @var string[]
     */
    private static $many_many = [
        'Products' => ShopifyProduct::class,
    ];

    /**
     * @var \string[][]
     */
    private static $many_many_extraFields = [
        'Products' => [
            'SortValue' => 'Varchar',
            'Position' => 'Int',
            'Featured' => 'Boolean',
            'Imported' => 'Boolean'
        ],
    ];

    /**
     * @var string[]
     */
    private static $owns = [
        'File'
    ];

    /**
     * @var bool[]
     */
    private static $indexes = [
        'ShopifyID' => true,
    ];

    /**
     * @var string[]
     */
    private static $summary_fields = [
        'File.CMSThumbnail' => 'Image',
        'Title',
        'ShopifyID'
    ];

    /**
     * @var string[]
     */
    private static $allowed_children = [
        ShopifyCollection::class,
        ShopifyProduct::class,
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $self =& $this;
        $this->beforeUpdateCMSFields(function (FieldList $fields) use ($self) {
            $fields->dataFieldByName('Title')
                ->setReadonly(true);

            $fields->dataFieldByName('URLSegment')
                ->setReadonly(true);

            $fields->replaceField(
                'Content',
                ReadonlyField::create('Content', 'Description')
            );

            $fields->addFieldsToTab('Root.Details', [
                ReadonlyField::create('ShopifyID'),
                UploadField::create('File')
                    ->setTitle('Image')
                    ->performReadonlyTransformation(),
                ReadonlyField::create('Published'),
                ReadonlyField::create('PublishedAt'),
            ]);

            $fields->addFieldsToTab('Root.Products', [
                GridField::create('Products', 'Products', $this->Products(), GridFieldConfig_RecordViewer::create())
            ]);
        });

        return parent::getCMSFields();
    }

    /**
     * @return mixed
     */
    public function getProductList()
    {
        $products = $this->Products();

        $this->extend('updateProductList', $products);

        $products = $products->filterByCallback(function ($page) {
            return $page->canView(Security::getCurrentUser());
        });

        return $products;
    }

    /**
     * @param $shopifyCollection
     * @return ProductCollection|ShopifyCollection
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function findOrMakeFromShopifyData($shopifyCollection)
    {
        if (!$collection = self::getByShopifyID($shopifyCollection->id)) {
            $collection = self::create();
        }

        $map = self::config()->get('data_map');
        ShopifyImportTask::loop_map($map, $collection, $shopifyCollection);

        if ($collection->isChanged()) {
            $collection->write();
            if ($collection->isPublished()) {
                $collection->publishRecursive();
            }
        }

        return $collection;
    }

    /**
     * @param $shopifyId
     * @return ProductCollection
     */
    public static function getByShopifyID($shopifyId)
    {
        return DataObject::get_one(self::class, ['ShopifyID' => $shopifyId]);
    }

    /**
     * @param $urlSegment
     * @return DataObject|null
     */
    public static function getByURLSegment($urlSegment)
    {
        return DataObject::get_one(self::class, ['URLSegment' => $urlSegment]);
    }
}
