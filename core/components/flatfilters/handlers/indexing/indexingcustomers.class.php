<?php

require_once 'indexingresources.class.php';

class IndexingCustomers extends IndexingResources
{
    protected string $classKey = 'modUser';

    protected function getQuery(): xPDOQuery_mysql
    {
        $q = $this->modx->newQuery($this->classKey);
        $q->where(['active' => true]);
        if (!empty($this->config['groups'])) {
            $q = $this->addGroupConditions(explode(',', $this->config['groups']), $q);
        }

        $this->modx->invokeEvent('ffOnGetIndexingQuery', [
            'configData' => $this->config,
            'query' => $q,
        ]);

        $q->prepare();

        return $q;
    }

    protected function addGroupConditions(array $groups, xPDOQuery_mysql $query): xPDOQuery_mysql
    {
        $query->andCondition(['primary_group:IN' => $groups]);
        $groups = implode(', ', $groups);
        $query->orCondition("`id` IN (SELECT `member` FROM `{$this->tablePrefix}member_groups` WHERE `user_group` IN ({$groups}))");
        return $query;
    }

    public function getResourceData($resource){
        return array_merge($this->getUserFields($resource->get('id')), ['id' => $resource->get('id')]);
    }
}