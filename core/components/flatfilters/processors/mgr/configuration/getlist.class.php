<?php

class FlatFiltersGetListProcessor extends modObjectGetListProcessor
{
    /** @var string $defaultSortField The default field to sort by */
    public $defaultSortField = 'id';
    /** @var boolean $checkListPermission If true and object is a modAccessibleObject, will check list permission */
    public $checkListPermission = false;
    public $classKey = 'ffConfiguration';

    /**
     * Prepare the row for iteration
     * @param xPDOObject $object
     * @return array
     */
    public function prepareRow(xPDOObject $object)
    {
        $objectArray = $object->toArray();
        $chunk = '@INLINE ' . file_get_contents(MODX_CORE_PATH . 'components/flatfilters/templates/mgr/chunks/config_table_row.tpl');

        if($chunk && $pdoTools = $this->modx->getService('pdoTools')){
            $objectArray['html'] = $pdoTools->parseChunk($chunk, $objectArray);
        }
        return $objectArray;
    }
}

return 'FlatFiltersGetListProcessor';