<?php

require_once 'indexingresources.class.php';

class IndexingProducts extends IndexingResources
{
    protected string $classKey = 'msProduct';

    protected function getQuery(): xPDOQuery_mysql
    {
        $q = parent::getQuery();
        $q->andCondition(['class_key' => $this->classKey]);

        return $q;
    }

    protected function addParentConditions(array $parents, xPDOQuery_mysql $query): xPDOQuery_mysql
    {
        $query = parent::addParentConditions($parents, $query);
        $parents = implode(', ', $parents);
        $query->orCondition("`id` IN (SELECT `product_id` FROM `{$this->tablePrefix}ms2_product_categories` WHERE `category_id` IN ({$parents}))");
        return $query;
    }
}