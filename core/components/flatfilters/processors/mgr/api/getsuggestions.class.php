<?php

class FlatFiltersGetSuggestionsProcessor extends modObjectGetListProcessor
{
    /** @var string $defaultSortField The default field to sort by */
    public $defaultSortField = 'id';
    /** @var boolean $checkListPermission If true and object is a modAccessibleObject, will check list permission */
    public $checkListPermission = false;
    public $classKey = 'modResource';

    /**
     * Get the data of the query
     * @return array
     */
    public function getData() {
        $data = array();
        $limit = intval($this->getProperty('limit', 10));
        $start = intval($this->getProperty('start', 0));
        $sortDir = $this->getProperty('dir' ,'ASC');
        $value = $this->getProperty('value');
        $fields = $this->getProperty('fields', 'pagetitle');
        $fields = explode(',', $fields);

        /* query for chunks */
        $c = $this->modx->newQuery($this->classKey);
        $c->select($this->modx->getSelectColumns('modResource', 'modResource'));

        if((int)$value){
            $c->where(array('id' => (int)$value));
        }else{
            foreach($fields as $k => $field){
                if(!strpos($field,'id')){
                    if($k === 0){
                        $c->where(array($field.':LIKE' => '%'.$value.'%'));
                    }else{
                        $c->orCondition(array($field.':LIKE' => '%'.$value.'%'));
                    }
                }
            }
        }


        $c->groupby('id');
        $c = $this->prepareQueryBeforeCount($c);
        $data['total'] = $this->modx->getCount($this->classKey,$c);
        $c = $this->prepareQueryAfterCount($c);

        $sortClassKey = $this->getSortClassKey();
        $sortKey = $this->modx->getSelectColumns($sortClassKey,$this->getProperty('sortAlias',$sortClassKey),'',array($this->getProperty('sort')));
        if (empty($sortKey)) $sortKey = $this->getProperty('sort', 'id');
        $c->sortby($sortKey,$sortDir);
        if ($limit > 0) {
            $c->limit($limit,$start);
        }
        $c->prepare();
        $c->stmt->execute();
        $data['results'] = $c->stmt->fetchAll(\PDO::FETCH_ASSOC);
        //$this->modx->log(1, print_r($data,1));
        return $data;
    }

    /**
     * Iterate across the data
     *
     * @param array $data
     * @return array
     */
    public function iterate(array $data) {
        $list = array();
        $list = $this->beforeIteration($list);
        $this->currentIndex = 0;
        /** @var xPDOObject|modAccessibleObject $object */
        foreach ($data['results'] as $object) {
            if ($this->checkListPermission && $object instanceof modAccessibleObject && !$object->checkPolicy('list')) continue;
            $objectArray = $this->prepareArray($object);
            if (!empty($objectArray) && is_array($objectArray)) {
                $list[] = $objectArray;
                $this->currentIndex++;
            }
        }
        $list = $this->afterIteration($list);
        return $list;
    }


    /**
     * Prepare the row for iteration
     * @param array $objectArray
     * @return array
     */
    public function prepareArray(array $objectArray):array
    {
        $objectArray['suggestions'][$objectArray['id']] = $objectArray[$this->getProperty('field')];
        return $objectArray;
    }
}

return 'FlatFiltersGetSuggestionsProcessor';