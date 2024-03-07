<?php

require_once 'filteringresources.class.php';

class FilteringProducts extends FilteringResources
{
    protected function getOutputSQL($rids){
        $productTableName = $this->modx->getTableName('msProductData');
        $resourceTableName = $this->modx->getTableName('modResource');
        return "SELECT `Resource`.`id` FROM $resourceTableName Resource JOIN $productTableName Data USING (id) WHERE `Resource`.`id` IN ($rids)";
    }

}
