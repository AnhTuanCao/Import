<?php

namespace Magenest\Import\Model\Export;
use Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor;
use Magento\Catalog\Model\ResourceModel\Product\Option\Collection;
use Magento\Catalog\Model\Product\Option;
use Magento\Store\Model\Store;
class Product extends \Magento\CatalogImportExport\Model\Export\Product
{
    /**
     * @inheritDoc
     */
    protected function _getExportMainAttrCodes()
    {
        $exportMainAttrCodesCore =  parent::_getExportMainAttrCodes();

        return array_merge($exportMainAttrCodesCore, ['categories_id']);
    }
    protected function collectMultirawData()
    {
        return parent::collectMultirawData();
    }

    protected function getCustomOptionsData($productIds)
    {
        $customOptionsData = [];
        $defaultOptionsData = [];

        foreach (array_keys($this->_storeIdToCode) as $storeId) {
            $options = $this->_optionColFactory->create();
            /**
             * @var Collection $options
             */
            $options->reset()
                ->addOrder('sort_order', 'ASC')
                ->addTitleToResult($storeId)
                ->addPriceToResult($storeId)
                ->addProductToFilter($productIds)
                ->addValuesToResult($storeId);

            foreach ($options as $option) {
                $optionData = $option->toArray();
                $row = [];
                $productId = $option['product_id'];
                $row['name'] = $option['title'];
                $row['type'] = $option['type'];
                $row['categories_Ids'] = $this->getCategoriesId();
                $row['required'] = $this->getOptionValue('is_require', $defaultOptionsData, $optionData);
                $row['price'] = $this->getOptionValue('price', $defaultOptionsData, $optionData);
                $row['sku'] = $this->getOptionValue('sku', $defaultOptionsData, $optionData);
                if (array_key_exists('max_characters', $optionData)
                    || array_key_exists('max_characters', $defaultOptionsData)
                ) {
                    $row['max_characters'] = $this->getOptionValue('max_characters', $defaultOptionsData, $optionData);
                }
                foreach (['file_extension', 'image_size_x', 'image_size_y'] as $fileOptionKey) {
                    if (isset($option[$fileOptionKey]) || isset($defaultOptionsData[$fileOptionKey])) {
                        $row[$fileOptionKey] = $this->getOptionValue($fileOptionKey, $defaultOptionsData, $optionData);
                    }
                }
                $percentType = $this->getOptionValue('price_type', $defaultOptionsData, $optionData);
                $row['price_type'] = ($percentType === 'percent') ? 'percent' : 'fixed';

                if (Store::DEFAULT_STORE_ID === $storeId) {
                    $optionId = $option['option_id'];
                    $defaultOptionsData[$optionId] = $option->toArray();
                }

                $values = $option->getValues();

                if ($values) {
                    foreach ($values as $value) {
                        $row['option_title'] = $value['title'];
                        $row['option_title'] = $value['title'];
                        $row['price'] = $value['price'];
                        $row['price_type'] = ($value['price_type'] === 'percent') ? 'percent' : 'fixed';
                        $row['sku'] = $value['sku'];
                        $customOptionsData[$productId][$storeId][] = $this->optionRowToCellString($row);
                    }
                } else {
                    $customOptionsData[$productId][$storeId][] = $this->optionRowToCellString($row);
                }
                $option = null;
            }
            $options = null;
        }

        return $customOptionsData;
    }

    private function getOptionValue($optionName, $defaultOptionsData, $optionData)
    {
        $optionId = $optionData['option_id'];

        if (array_key_exists($optionName, $optionData) && $optionData[$optionName] !== null) {
            return $optionData[$optionName];
        }

        if (array_key_exists($optionId, $defaultOptionsData)
            && array_key_exists($optionName, $defaultOptionsData[$optionId])
        ) {
            return $defaultOptionsData[$optionId][$optionName];
        }

        return null;
    }

    protected function getCategoriesId()
    {
        $collection = $this->_categoryColFactory->create()->addNameToResult();
        /* @var $collection \Magento\Catalog\Model\ResourceModel\Category\Collection */
        foreach ($collection as $category) {
            $structure = preg_split('#/+#', $category->getPath());
            $pathSize = count($structure);
            if ($pathSize > 1) {
                $path = [];
                for ($i = 1; $i < $pathSize; $i++) {
                    $childCategory = $collection->getItemById($structure[$i]);
                    $path[] = $this->quoteCategoryDelimiter($childCategory);
                }
                $categories_id = $this->_rootCategories[$category->getId()] = array_shift($path);
                if ($pathSize > 2) {
                    $categories_id = $this->_categories[$category->getId()] = implode(CategoryProcessor::DELIMITER_CATEGORY, $path);
                }
            }
        }
        return $categories_id;
    }
    private function quoteCategoryDelimiter($string)
    {
        return str_replace(
            CategoryProcessor::DELIMITER_CATEGORY,
            '\\' . CategoryProcessor::DELIMITER_CATEGORY,
            $string
        );
    }
}
