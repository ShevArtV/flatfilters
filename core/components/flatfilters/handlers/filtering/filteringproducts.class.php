<?php

require_once 'filteringresources.class.php';

class FilteringProducts extends FilteringResources
{
    protected function getOutputSQL(string $rids): string
    {
        $productTableName = $this->modx->getTableName('msProductData');
        $resourceTableName = $this->modx->getTableName('modResource');
        return "SELECT `Resource`.`id` FROM $resourceTableName Resource JOIN $productTableName Data USING (id) WHERE `Resource`.`id` IN ($rids)";
    }

    /**
     * Для products в индексе несколько строк на rid (опции), поэтому JOIN индекса
     * размножил бы выдачу. Сортируем через уже присутствующий JOIN Data
     * (ms_product_data): голые имена фильтров разрешены, только если это колонки Data.
     */
    protected function getAllowedSortKeys(): array
    {
        $dataColumns = $this->getTableColumns($this->tablePrefix . 'ms2_products');
        $keys = [];
        foreach (array_keys($this->filters) as $k) {
            if ($this->isValidColumn($k) && in_array($k, $dataColumns, true)) {
                $keys[] = $k;
            }
        }
        $keys[] = 'id';
        foreach ($this->getTableColumns($this->tablePrefix . 'site_content') as $col) {
            $keys[] = 'Resource.' . $col;
        }
        foreach ($dataColumns as $col) {
            $keys[] = 'Data.' . $col;
        }
        return array_values(array_unique($keys));
    }

    protected function mapSortKey(string $key): string
    {
        if (strpos($key, 'Data.') === 0) {
            return '`Data`.`' . substr($key, 5) . '`';
        }
        if (isset($this->filters[$key])) {
            return "`Data`.`{$key}`";
        }
        return parent::mapSortKey($key);
    }
}
