<?php

class  Filtering
{

    public function __construct($modx, $scriptProperties = [])
    {
        $this->modx = $modx;
        $this->pdo = $this->modx->getService('pdoTools');
        $this->modx->addPackage('flatfilters', MODX_BASE_PATH . 'core/components/flatfilters/model/');
        $this->tablePrefix = $this->modx->getOption('table_prefix');
        $this->properties = $scriptProperties;
        $this->corePath = $this->modx->getOption('core_path');

        $this->configId = (int)$this->modx->getOption('configId', $this->properties, 0);
        $this->tableName = $this->modx->getTableName('ffIndex' . $this->configId);
        $this->config = $this->modx->getObject('ffConfiguration', $this->configId);
        $this->filters = $this->config ? json_decode($this->config->get('filters'), 1) : [];
        $this->defaultFilters = $this->config ? json_decode($this->config->get('default_filters'), 1) : [];

        $this->limit = (int)$this->modx->getOption('limit', $this->properties, 10);
        //$this->page = (int)$this->modx->getOption('page', $this->properties, 1);
        //$this->offset = ($this->page - 1) >= 1 ? (($this->page - 1) * $this->limit) : 0;
        //$this->values = [];

        $this->prepareFilters();
        $this->loadLexicons();

        //$this->modx->setPlaceholder('currentPage', $this->page);
    }

    public function initialize()
    {
        $time_start = microtime(true);
        $output = [];
        //$limit = (int)$this->modx->getOption('limit', $this->properties, 10);
        //$page = (int)$this->modx->getOption('page', $this->properties, 1);
        //$offset = ($page - 1) >= 1 ? (($page - 1) * $limit) : 0;
        //$filters = $this->modx->getOption('filters', $this->properties, false);

        $props = array_merge($this->properties, [
            'sortby' => '',
            'offset' => 0,
            'limit' => $this->limit
        ]);
        $hash = md5(json_encode($this->values));
        $_SESSION['flatfilters']['properties'] = array_merge($_SESSION['flatfilters']['properties'] ?: [], $this->properties);

        //$this->modx->log(1, print_r($this->properties, 1));
        //$this->modx->log(1, print_r($page, 1));
        //$this->modx->log(1, print_r($limit, 1));
        //$this->modx->log(1, print_r($offset, 1));
        //$this->modx->log(1, print_r($this->values, 1));
        //$this->modx->log(1, print_r($_SESSION['flatfilters']['hash'], 1));
        //$this->modx->log(1, print_r($hash, 1));
        //$this->modx->log(1, print_r($_SESSION['flatfilters']['rids'], 1));

        $rids = $_SESSION['flatfilters']['rids'];
        $output['totalPages'] = $_SESSION['flatfilters']['totalPages'];
        if (!$rids || $_SESSION['flatfilters']['hash'] !== $hash) {
            $rids = $this->filter();
            $_SESSION['flatfilters']['hash'] = $hash;
            $_SESSION['flatfilters']['rids'] = $rids;

            $time_fq = microtime(true);
            $duration = sprintf('MAIN QUERY: %f sec.', $time_fq - $time_start);
            //$this->modx->log(1, $duration);

            $total = $this->getTotalCountResults();
            $output['totalPages'] = ceil($total / $this->limit);
            $_SESSION['flatfilters']['totalPages'] = $output['totalPages'];
            $_SESSION['flatfilters']['totalResources'] = $total;

            $time_tcq = microtime(true);
            $duration = sprintf('TOTAL COUNT QUERY: %f sec.', $time_tcq - $time_fq);
            ///$this->modx->log(1, $duration);
        }

        if ($rids) {
            //$filterValues = $this->getFiltersValues($this->properties['configName'], $rids);

            $rids = $this->sortResults($rids);

            $time_sq = microtime(true);
            $duration = sprintf('SORT QUERY: %f sec.', $time_sq - ($time_tcq ?: $time_start));
            //$this->modx->log(1, $duration);

            if ($this->properties['element']) {
                unset($props['element'], $props['filters'], $props['offset'], $props['page'], $props['filtersKeys'], $props['configId']);
                //$this->modx->log(1, print_r($rids,1));
                $props['resources'] = $rids;
                $output['resources'] = $this->pdo->runSnippet($this->properties['element'], $props);
            } else {
                $output['resources'] = $rids;
            }
        } else {
            //$filterValues = $this->getFiltersValues($this->properties['configName']);
        }
        //$this->modx->setPlaceholder('filterValues', $filterValues);
        $time_end = microtime(true);
        $duration = sprintf('TOTAL TIME %f sec.', $time_end - $time_start);
        //$this->modx->log(1, $duration);
        //$this->modx->setPlaceholder('totalPages', $totalPages);
        //$this->modx->setPlaceholder('totalTime', $duration);
        $output['totalTime'] = $duration;
        $output['currentPage'] = $this->page;
        $output['totalResources'] = $_SESSION['flatfilters']['totalResources'];
        if (!$output['resources']) {
            $output['resources'] = $this->pdo->parseChunk($this->properties["empty"], []);
        }
        return $output;
    }

    public function prepareFilters()
    {
        //$this->modx->log(1, print_r($_REQUEST['sortby'],1));
        if($_REQUEST['sortby']){
            $sortby = explode('|', $_REQUEST['sortby']);
            $this->properties['sortby'][$sortby[0]] = $sortby[1];
        }
        $filtersKeys = array_keys($this->filters);
        $this->page = (int)$this->modx->getOption('page', $_REQUEST, 1);
        $this->offset = ($this->page - 1) >= 1 ? (($this->page - 1) * $this->limit) : 0;
        if ($filtersKeys) {
            foreach ($filtersKeys as $key) {
                $value = $this->modx->getOption($key, $_REQUEST, false);
                if ($value) {
                    $this->values[$key] = $value;
                }
            }
        }
        //$this->modx->log(1, print_r($this->values,1));
    }

    private function filter()
    {
        $rids = [];
        $sql = $this->getFilterSql();

        //$this->modx->log(1, print_r($sql, 1));
        $time_start = microtime(true);
        /* основная фильтрация */
        if ($statement = $this->modx->query($sql)) {
            $time_end = microtime(true);
            $duration = sprintf('FILTER TIME %f sec.', $time_end - $time_start);
            //$this->modx->log(1, $duration);
            $rids = $statement->fetchAll(PDO::FETCH_COLUMN);
        }
        //$this->modx->log(1, print_r(count($rids), 1));

        return implode(', ', $rids);
    }

    public function getFilterSql()
    {
        $sql = "SELECT SQL_CALC_FOUND_ROWS `rid` FROM {$this->tableName} ";

        $conditions = [];
        foreach ($this->filters as $key => $data) {
            $value = $this->values[$key] ?: $this->defaultFilters[$key]['value'];
            if (!$value) continue;
            $conditions[] = $this->getCondition($key, $value, $data['filter_type']);
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY `rid`";

        //$this->modx->log(1, print_r($sql, 1));
        return $sql;
    }

    private function getCondition($key, $value, $type)
    {
        $sign = '=';
        if ($this->defaultFilters[$key]) {
            $sign = $this->defaultFilters[$key]['sign'];
        } else {
            if (strpos($type, 'range') !== false) {
                $sign = 'BETWEEN';
            }
            elseif ($type === 'multiple') {
                $sign = 'IN';
            }
        }

        $keyStr = "`{$key}`";
        $valStr = "'{$value}'";
        if (in_array($type, ['daterange', 'date', 'number', 'numrange'])) {
            $keyStr = "CAST(`{$key}` AS DECIMAL)";
            $valStr = "{$value}";
        }

        switch ($sign) {
            case 'BETWEEN':
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                $start = $value[0];
                $end = $value[1];
                if (strpos($type, 'date') !== false) {
                    $start = strtotime($value[0]);
                    $end = strtotime($value[1]);
                }
                $condition = " {$keyStr} >= {$start} AND {$keyStr} <= {$end} ";
                break;

            case 'IN':
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                foreach($value as $k => $v){
                    $value[$k] = "'{$v}'";
                }
                $value = implode(', ',$value);
                $condition = " {$keyStr} IN ({$value}) ";
                break;

            default:
                $condition = " {$keyStr} {$sign} {$valStr} ";
                break;
        }

        return $condition;
    }

    private function getTotalCountResults()
    {
        $total = [0];
        /* подсчёт количества результатов */
        if ($statement = $this->modx->query('SELECT FOUND_ROWS() as total')) {
            $total = $statement->fetchAll(PDO::FETCH_COLUMN);
        }
        return $total[0];
    }

    private function sortResults($rids)
    {
        /* готовим запрос на сортировку результатов фильтрации */
        $productTableName = $this->modx->getTableName('msProductData');
        $resourceTableName = $this->modx->getTableName('modResource');
        $sortby = [];
        $dataSql = "SELECT `Resource`.`id` FROM $resourceTableName Resource JOIN $productTableName Data USING (id) WHERE `Resource`.`id` IN ($rids)";

        if (isset($this->properties['sortby'])) {
            $sort = is_array($this->properties['sortby']) ? $this->properties['sortby'] : json_decode($this->properties['sortby'], 1);
            foreach ($sort as $key => $dir) {
                $sortby[] = "$key $dir";
            }
        }
        if (!empty($sortby)) {
            $dataSql .= " ORDER BY " . implode(',', $sortby);
        }
        $dataSql .= " LIMIT {$this->limit} OFFSET {$this->offset}";
        //$this->modx->log(1, print_r($dataSql,1));
        /* сортируем результаты фильтрации */
        if ($statement = $this->modx->query($dataSql)) {
            $rids = $statement->fetchAll(PDO::FETCH_COLUMN);
            //$this->modx->log(1, print_r($rids, 1));
        }

        return implode(',', $rids);
    }

    /**
     * @return void
     */
    public function loadLexicons()
    {
        $this->modx->lexicon->load('flatfilters:default');
    }

    public function getAllFiltersValues()
    {
        $time_start = microtime(true);
        $output = [];
        $where = '';
        $defaultFilterKeys = $this->defaultFilters ? array_keys($this->defaultFilters) : [];

        if (!empty($this->defaultFilters)) {
            $conditions = [];
            foreach ($this->defaultFilters as $k => $data) {
                if (!$data['value']) continue;
                $conditions[] = $this->getCondition($k, $data['value'], $data['filter_type']);
            }
            if ($conditions) $where = implode('AND ', $conditions);
        }

        foreach ($this->filters as $key => $value) {
            if (in_array($key, $defaultFilterKeys)) continue;

            if (strpos($value['filter_type'], 'range') === false) {
                if ($where) {
                    $sql = "SELECT DISTINCT `{$key}` FROM {$this->tableName} WHERE `{$key}` IS NOT NULL AND `{$key}` != '' AND {$where}";
                } else {
                    $sql = "SELECT DISTINCT `{$key}` FROM {$this->tableName} WHERE `{$key}` IS NOT NULL AND `{$key}` != ''";
                }
                //$this->modx->log(1, print_r($sql,1));
                if ($statement = $this->modx->query($sql)) {
                    $output[$key]['values'] = $statement->fetchAll(PDO::FETCH_COLUMN);
                    $output[$key]['type'] = $value['filter_type'] ?: 'string';
                }
            } else {
                if ($where) {
                    $sql = "SELECT MIN(`{$key}`) as `min`, MAX(`{$key}`) as `max` FROM {$this->tableName} WHERE `{$key}` IS NOT NULL AND `{$key}` != '' AND {$where}";
                } else {
                    $sql = "SELECT MIN(`{$key}`) as `min`, MAX(`{$key}`) as `max` FROM {$this->tableName} WHERE `{$key}` IS NOT NULL AND `{$key}` != ''";
                }
                //$this->modx->log(1, print_r($sql,1));
                if ($statement = $this->modx->query($sql)) {
                    $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                    $output[$key]['min'] = $_SESSION['flatfilters']['properties']['ranges'][$key]['min'] = $result[0]['min'] ?: 0;
                    $output[$key]['max'] = $_SESSION['flatfilters']['properties']['ranges'][$key]['max'] = $result[0]['max'] ?: 0;
                    $output[$key]['type'] = $value['filter_type'] ?: 'string';
                }
            }
        }

        $_SESSION['flatfilters']['properties']['all_ranges'] = $output;

        $time_end = microtime(true);
        $duration = sprintf('GET FILTERS TOTAL: %f sec.', $time_end - $time_start);
        //$this->modx->log(1, print_r($duration,1));
        return $output;
    }

    public function getFiltersValues()
    {
        $time_start = microtime(true);
        $output = [];
        $where = $_SESSION['flatfilters']['rids'];
        $defaultFilterKeys = $this->defaultFilters ? array_keys($this->defaultFilters) : [];
        $sqlDistinctKeys = [];
        $sqlMinMaxKeys = [];
        foreach ($this->filters as $key => $value) {
            if (in_array($key, $defaultFilterKeys)) continue;
            if (strpos($value['filter_type'], 'range') === false) {
                $sqlDistinctKeys[] = $key;
            } else {
                $sqlMinMaxKeys[] = "MIN(`{$key}`) as {$key}__min, MAX(`{$key}`) as {$key}__max";
            }
        }

        if (!empty($sqlMinMaxKeys)) {
            $sqlMinMax = implode(', ', $sqlMinMaxKeys);
            $sql = "SELECT {$sqlMinMax} FROM {$this->tableName} WHERE `rid` IN ({$where})";
            //$this->modx->log(1, print_r($sql,1));
            $time_start4 = microtime(true);
            if ($statement = $this->modx->query($sql)) {
                $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                $time_end4 = microtime(true);
                $duration = sprintf('GET FILTERS MINMAX QUERY: %f sec.', $time_end4 - $time_start4);
                //$this->modx->log(1, print_r($duration, 1));

                $time_start5 = microtime(true);
                foreach ($result[0] as $k => $v) {
                    $parts = explode('__', $k);
                    $output[$parts[0]][$parts[1]] = $_SESSION['flatfilters']['properties']['ranges'][$parts[0]][$parts[1]] = $v ?: 0;
                    $output[$parts[0]]['type'] = $this->filters[$parts[0]]['filter_type'] ?: 'string';
                }
                $time_end5 = microtime(true);
                $duration = sprintf('GET FILTERS MINMAX PREPARE: %f sec.', $time_end5 - $time_start5);
                //$this->modx->log(1, print_r($duration, 1));
            }

        }

        if (!empty($sqlDistinctKeys)) {
            $time_start1 = microtime(true);
            $values = [];
            foreach ($sqlDistinctKeys as $key) {
                $sql = "SELECT DISTINCT `{$key}` FROM {$this->tableName} WHERE `rid` IN ({$where})";
                if ($statement = $this->modx->query($sql)) {
                    $values[$key]['values'] = $statement->fetchAll(PDO::FETCH_COLUMN);
                    $values[$key]['type'] = $this->filters[$key]['filter_type'] ?: 'string';
                }
            }
            $time_end1 = microtime(true);
            $duration = sprintf('GET FILTERS DISTINCT QUERY: %f sec.', $time_end1 - $time_start1);
            //$this->modx->log(1, print_r($duration, 1));
            $output = array_merge($output,$values);
        }

        $time_end = microtime(true);
        $duration = sprintf('GET FILTERS TOTAL: %f sec.', $time_end - $time_start);
        //$this->modx->log(1, print_r($duration, 1));

        //$this->modx->log(1, print_r($output,1));
        return $output;
    }
}