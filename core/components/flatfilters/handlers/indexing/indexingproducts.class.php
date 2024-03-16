<?php

require_once 'indexingresources.class.php';

class IndexingProducts extends IndexingResources
{
    protected string $classKey = 'msProduct';

    protected function getQuery(): xPDOQuery_mysql
    {
        $allowedTpls = $this->modx->getOption('ff_allowed_tpls', '', '');
        $q = $this->modx->newQuery($this->classKey);
        $q->where(['deleted' => false, 'published' => true, 'class_key' => $this->classKey]);
        if ($allowedTpls) {
            $q->andCondition("`template` IN ({$allowedTpls})");
        }
        if (!empty($this->config['parents'])) {
            $q = $this->addParentConditions(explode(',', $this->config['parents']), $q);
        }

        $this->modx->invokeEvent('ffOnGetIndexingQuery', [
            'configData' => $this->config,
            'query' => $q,
        ]);
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