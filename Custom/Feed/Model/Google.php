<?php

/**
 * Class Custom_Feed_Model_Google
 */
class Custom_Feed_Model_Google extends Gorilla_Feed_Model_Google
{

    /**
     * Rewrite google feed removing from the feed products without images and out of stock
     */
   public function generate()
   {
        $products = $this->getProductsFormatted($this->_used_attributes, true);

        $categories = $this->getCategoriesData();

        $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

        $defaultCountryCode = Mage::getStoreConfig('general/country/default');

        foreach ($products as $product) {
            $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')
                ->getParentIdsByChild($product->getId());
            $parentProduct = null;
            if ($parentIds) {
                $parentProduct = Mage::getModel('catalog/product')->load($parentIds[0]);
                if ($parentProduct->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED) {
                    continue;
                }
            }

            $imageUrl = (string)Mage::helper('catalog/image')->init($product, 'image');

            if (!strpos($imageUrl, "placeholder/default/image") === false || $imageUrl == '') {
                $imageUrl = (string)Mage::helper('catalog/image')
                    ->init($parentIds ? $parentProduct : $product, 'image');
            }

            //Removing from the feed products without images and out of stock
            if (!strpos($imageUrl, "placeholder/default/image") === false
                || $imageUrl == ''
                || !$product->isInStock()
            ) {
                continue;
            }

            $rows[] = $this->addRowData(
                $product,
                $imageUrl,
                $parentIds,
                $parentProduct,
                $defaultCountryCode,
                $currencyCode,
                $categories
            );
        }

        if (!empty($rows))
            $this->save($rows, $this->_filename);
   }

    /**
     * Add headers value to feed for each product
     * @param $product
     * @param $imageUrl
     * @param $parentIds
     * @param $parentProduct
     * @param $defaultCountryCode
     * @param $currencyCode
     * @param $categories
     * @return array
     */
    protected function addRowData(
        $product,
        $imageUrl,
        $parentIds,
        $parentProduct,
        $defaultCountryCode,
        $currencyCode,
        $categories
    ) {
        $productData = array();
        foreach ($this->_headers as $header) {
            $productData[$header] = $this->addRowDataByHeader(
                $product,
                $imageUrl,
                $parentIds,
                $parentProduct,
                $defaultCountryCode,
                $currencyCode,
                $categories,
                $header
            );
        }

        return $productData;
    }

    /**
     * Add headers value to feed for each header
     * @param $product
     * @param $imageUrl
     * @param $parentIds
     * @param $parentProduct
     * @param $defaultCountryCode
     * @param $currencyCode
     * @param $categories
     * @param $header
     * @return string
     */
    protected function addRowDataByHeader(
        $product,
        $imageUrl,
        $parentIds,
        $parentProduct,
        $defaultCountryCode,
        $currencyCode,
        $categories,
        $header
    ) {
            switch ($header) {
                case 'g:id':
                case 'g:gtin':
                    return $product->getSku();
                case 'g:image_link':
                    return $imageUrl;
                case 'title':
                    return $product->getData('name');
                case 'g:price':
                    $value = array(number_format($product->getFinalPrice(), 2, ".", ""), $currencyCode);
                    return implode(' ', $value);
                case 'link':
                    return $this->getLink($parentProduct, $parentIds, $defaultCountryCode, $currencyCode, $product);
                case 'g:google_product_category':
                    return implode(' &gt; ', $this->getLowerCategoryPath($product, $categories));
                case 'g:availability':
                    return $product->isInStock() ? 'in stock' : 'out of stock';
                case 'g:mpn':
                    return $product->getData('mpn');
                case 'g:color':
                    return $product->getData('color');
                case 'g:size':
                    return $product->getData('size') == NULL ?
                        "OS" : $product->getData('size');
                case 'g:condition':
                    $value = $product->getData('condition');
                    return empty($value) ? 'new': $value;
                case 'g:age_group':
                    return $product->getData('age');
                case 'g:gender':
                    return $product->getData('gender');
                default:
                    return $product->getData($header);
            }

    }

    /**
     * Find the lowest category and return it's path
     * @param $product
     * @param $categories
     * @return array
     */
    protected function getLowerCategoryPath($product, $categories)
    {
        $value = array();
        $categoryIds = $product->getCategoryIds();
        if (!empty($categoryIds) && !empty($categories)) {
            $categoryPath = null;
            foreach ($categories as $single) {
                $ids = array_keys($single);
                $matchIds = array_intersect($categoryIds, $ids);
                if (!empty($matchIds) && empty($categoryPath)) {
                    $category = $single[reset($matchIds)];
                    $categoryPath = explode('/', $category['path']);
                }

                if (!empty($categoryPath)) {
                    $currentMatchIds = array_intersect($categoryPath, $ids);
                    if (!empty($currentMatchIds)) {
                        $value[] = htmlentities($single[reset($currentMatchIds)]['name']);
                    }
                }
            }

            return array_reverse($value);
        }
    }

    /**
     * Get Link for the parent product IF exist or get the product link IF NOT.
     * @param $parentProduct
     * @param $parentIds
     * @param $defaultCountryCode
     * @param $currencyCode
     * @param $product
     * @return string
     */
    protected function getLink($parentProduct, $parentIds, $defaultCountryCode, $currencyCode, $product)
    {
        $parentLink = $parentProduct ? $parentProduct->getProductUrl() .
            '?country=' . $defaultCountryCode .
            '&amp;currency=' . $currencyCode : null;
        $productLink = $product->getProductUrl() .
            '?country=' . $defaultCountryCode .
            '&amp;currency=' . $currencyCode;
        return $parentIds ? $parentLink : $productLink;

    }
}
