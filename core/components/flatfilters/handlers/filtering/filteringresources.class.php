<?php

require_once 'filteringinterface.class.php';

class FilteringResources implements FilteringInterface
{

    protected ModX $modx;
    protected $pdoTools;
    protected array $configData;
    protected array $properties;
    public array $filters;
    protected array $defaultFilters;
    public array $values = [];
    protected array $tokens = [];
    protected string $tablePrefix;
    protected string $corePath;
    protected string $tableName;
    protected string $resourcesProp = 'resources';
    protected int $total = 0;
    protected int $limit;
    protected int $page;
    protected int $offset;

    public function __construct($modx, $configData)
    {
        $this->modx = $modx;
        $this->pdoTools = $modx->getParser()->pdoTools;
        $this->configData = $configData;

        $this->modx->addPackage('flatfilters', MODX_BASE_PATH . 'core/components/flatfilters/model/');

        $this->initialize();
    }

    protected function initialize(): void
    {
        $this->tablePrefix = $this->modx->getOption('table_prefix');
        $this->corePath = $this->modx->getOption('core_path');
        $this->tableName = $this->modx->getTableName('ffIndex' . $this->configData['id']);
        $this->configData['scriptProperties']['parents'] = $this->configData['parents'] ?: 0;
        $this->properties = $this->configData['scriptProperties'];
        $this->filters = json_decode($this->configData['filters'], true) ?: [];
        $this->defaultFilters = json_decode($this->configData['default_filters'], true) ?: [];
        $this->limit = (int)$this->modx->getOption('limit', $this->properties, 10);

        $this->prepareFilters();
    }

    protected function prepareFilters(): void
    {
        if (!empty($_REQUEST['sortby'])) {
            $sortby = explode('|', $_REQUEST['sortby']);
            if(is_string($this->properties['sortby'])){
                $this->properties['sortby'] = json_decode($this->properties['sortby'], true) ?: [];
            }
            $this->properties['sortby'][$sortby[0]] = $sortby[1];
        }

        $filtersKeys = array_keys($this->filters);
        $this->page = (int)$this->modx->getOption('page', $_REQUEST, 1);
        $this->offset = ($this->page - 1) >= 1 ? (($this->page - 1) * $this->limit) : 0;
        if ($filtersKeys) {
            foreach ($filtersKeys as $key) {
                $value = $this->modx->getOption($key, $_REQUEST, false);
                if ($value) {
                    $this->values[$key] = ($this->filters[$key]['filter_type'] === 'multiple' && !is_array($value)) ? explode(',', $value) : $value;
                }
            }
        }
    }

    public function run(): array
    {
        $time_start = microtime(true);
        $output = [];

        $this->modx->invokeEvent('ffOnBeforeFilter', [
            'configData' => $this->configData,
            'FlatFilters' => $this
        ]);

        $hash = md5(json_encode($this->values));
        $upd = $this->properties['upd'];
        unset($this->properties['upd']);
        $_SESSION['flatfilters'][$this->configData['id']]['properties'] = array_merge(
            $_SESSION['flatfilters'][$this->configData['id']]['properties'] ?: [],
            $this->properties
        );

        $rids = $_SESSION['flatfilters'][$this->configData['id']]['rids'];
        if (!$rids || $_SESSION['flatfilters'][$this->configData['id']]['hash'] !== $hash || $upd) {
            $rids = $this->filter();

            $this->modx->invokeEvent('ffOnAfterFilter', [
                'configData' => $this->configData,
                'rids' => $rids
            ]);
            $rids = $this->modx->event->returnedValues['rids'] ?? $rids;

            $_SESSION['flatfilters'][$this->configData['id']]['hash'] = $hash;
            $_SESSION['flatfilters'][$this->configData['id']]['rids'] = $rids;
            $output['getDisabled'] = 1;
            $this->page = 1;
        }

        if ($rids) {
            $rids = $this->getOutputIds($rids);
            if ($this->properties['element']) {
                $output['resources'] = $this->runRender($rids);
            } else {
                $output['resources'] = $rids;
            }
        } else {
            $output['resources'] = $this->pdoTools->parseChunk($this->properties["empty"], []);
        }

        $time_end = microtime(true);
        $output['totalTime'] = sprintf('TOTAL TIME %f sec.', $time_end - $time_start);
        $output['totalPages'] = ceil($_SESSION['flatfilters'][$this->configData['id']]['totalResources'] / $this->limit);
        $output['currentPage'] = $this->page;
        $output['totalResources'] = $_SESSION['flatfilters'][$this->configData['id']]['totalResources'];

        return $output;
    }

    protected function filter(): string
    {
        $rids = [];
        $sql = $this->getFilterSql();
        /* основная фильтрация */
        if ($statement = $this->execute($sql, $this->tokens)) {
            $_SESSION['flatfilters'][$this->configData['id']]['totalResources'] = $statement->rowCount();
            $rids = $statement->fetchAll(PDO::FETCH_COLUMN);
        }

        return implode(', ', $rids);
    }

    protected function getFilterSql()
    {
        $sql = "SELECT SQL_CALC_FOUND_ROWS `rid` FROM {$this->tableName} ";

        $conditions = [];
        foreach ($this->filters as $key => $data) {
            $value = $this->values[$key] ?: $this->defaultFilters[$key]['value'];
            if (!isset($value)) {
                continue;
            }
            $conditions[] = $this->getCondition($key, $value, $data['filter_type']);
        }

        $this->modx->invokeEvent('ffOnBeforeSetFilterConditions', [
            'conditions' => $conditions,
            'configData' => $this->configData,
            'FlatFilters' => $this
        ]);
        $conditions = is_array($this->modx->event->returnedValues['conditions']) ? $this->modx->event->returnedValues['conditions'] : $conditions;

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY `rid`";

        return $sql;
    }

    protected function getCondition($key, $value, $type)
    {
        $sign = $this->getCompareSign($key, $type);

        $keyStr = "`{$key}`";
        if (in_array($type, ['number', 'numrange'])) {
            $keyStr = "CAST(`{$key}` AS DECIMAL)";
        }

        switch ($sign) {
            case 'BETWEEN':
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                $keyStart = $key . '_start';
                $keyEnd = $key . '_end';
                $this->tokens[$keyStart] = $value[0];
                $this->tokens[$keyEnd] = $value[1];
                if (strpos($type, 'date') !== false) {
                    $this->tokens[$keyStart] = strtotime($value[0]);
                    $this->tokens[$keyEnd] = strtotime($value[1]);
                }
                $condition = " {$keyStr} >= :{$keyStart} AND {$keyStr} <= :{$keyEnd} ";
                break;

            case 'IN':
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                foreach ($value as $k => $v) {
                    $k = $key . '_' . $k;
                    $this->tokens[$k] = $v;
                    $tokens[] = ":{$k}";
                }
                $tokens = implode(', ', $tokens);
                $condition = " {$keyStr} IN ({$tokens}) ";
                break;

            default:
                $this->tokens[$key] = $value;
                $condition = " {$keyStr} {$sign} :{$key} ";
                break;
        }

        return $condition;
    }

    protected function getCompareSign($key, $type)
    {
        $sign = '=';
        if ($this->defaultFilters[$key]) {
            $sign = $this->defaultFilters[$key]['sign'];
        } else {
            if (strpos($type, 'range') !== false) {
                $sign = 'BETWEEN';
            } elseif ($type === 'multiple') {
                $sign = 'IN';
            }
        }
        return $sign;
    }

    protected function execute($sql, $tokens = [])
    {
        $statement = $this->modx->prepare($sql);
        $time_start = microtime(true);
        if ($statement->execute($tokens)) {
            $time_end = microtime(true);
            $this->modx->queryTime += $time_end - $time_start;
            $this->modx->executedQueries++;
            return $statement;
        }
    }

    protected function getOutputIds($rids)
    {
        $sql = $this->getOutputSQL($rids);

        if (isset($this->properties['sortby'])) {
            $sql .= $this->getSortby();
        }

        $sql .= " LIMIT {$this->limit} OFFSET {$this->offset}";
        /* получаем список id для отображения на странице */
        if ($statement = $this->execute($sql)) {
            $rids = $statement->fetchAll(PDO::FETCH_COLUMN);
            $rids = implode(',', $rids);
        }

        return $rids;
    }

    protected function getOutputSQL($rids)
    {
        $resourceTableName = $this->modx->getTableName('modResource');
        return "SELECT `Resource`.`id` FROM $resourceTableName Resource WHERE `Resource`.`id` IN ($rids)";
    }

    protected function getSortby()
    {
        /* готовим условия сортировки результатов фильтрации */
        $sortby = [];
        $sortStr = " ORDER BY id";
        $sort = is_array($this->properties['sortby']) ? $this->properties['sortby'] : json_decode($this->properties['sortby'], 1);
        foreach ($sort as $key => $dir) {
            $sortby[] = "{$key} {$dir}";
        }
        if (!empty($sortby)) {
            $sortStr = " ORDER BY " . implode(',', $sortby);
        }

        return $sortStr;
    }

    protected function runRender($rids)
    {
        $props = array_merge($this->properties, [
            'sortby' => "",
            'offset' => 0,
            'limit' => $this->limit
        ]);
        unset($props['element'], $props['filters'], $props['offset'], $props['page'], $props['filtersKeys'], $props['configId']);
        $props[$this->resourcesProp] = $rids;

        $this->modx->invokeEvent('ffOnBeforeRender', [
            'configData' => $this->configData,
            'props' => $props,
            'FlatFilters' => $this
        ]);
        $props = is_array($this->modx->event->returnedValues['props']) ? $this->modx->event->returnedValues['props'] : $props;

        return $this->pdoTools->runSnippet($this->properties['element'], $props);
    }

    public function getAllFiltersValues()
    {
        $output = [];
        $where = '';
        $defaultFilterKeys = $this->defaultFilters ? array_keys($this->defaultFilters) : [];
        $conditions = [];
        if (!empty($this->defaultFilters)) {
            $this->tokens = [];
            foreach ($this->defaultFilters as $k => $data) {
                if (!isset($data['value'])) {
                    continue;
                }
                $conditions[] = $this->getCondition($k, $data['value'], $data['filter_type']);
            }
        }

        $this->modx->invokeEvent('ffOnBeforeGetFilterValues', [
            'configData' => $this->configData,
            'conditions' => $conditions,
            'FlatFilters' => $this
        ]);
        $conditions = is_array($this->modx->event->returnedValues['conditions']) ? $this->modx->event->returnedValues['conditions'] : $conditions;

        if ($conditions) {
            $where = implode('AND ', $conditions);
        }

        foreach ($this->filters as $key => $value) {
            if (in_array($key, $defaultFilterKeys)) {
                continue;
            }
            if (strpos($value['filter_type'], 'range') === false) {
                $output = $this->getNoRangeValues($where, $key, $value, $output);
            } else {
                $output = $this->getRangeValues($where, $key, $value, $output);
            }
        }

        $this->modx->invokeEvent('ffOnAfterGetFilterValues', [
            'configData' => $this->configData,
            'output' => $output,
            'FlatFilters' => $this
        ]);
        $output = is_array($this->modx->event->returnedValues['output']) ? $this->modx->event->returnedValues['output'] : $output;

        $_SESSION['flatfilters'][$this->configData['id']]['properties']['all_ranges'] = $output;
        return $output;
    }

    protected function getNoRangeValues($where, $key, $value, $output)
    {
        if ($where) {
            $sql = "SELECT DISTINCT `{$key}` FROM {$this->tableName} WHERE `{$key}` IS NOT NULL AND `{$key}` != '' AND {$where}";
        } else {
            $sql = "SELECT DISTINCT `{$key}` FROM {$this->tableName} WHERE `{$key}` IS NOT NULL AND `{$key}` != ''";
        }
        if ($statement = $this->execute($sql, $this->tokens)) {
            $output[$key]['values'] = $statement->fetchAll(PDO::FETCH_COLUMN);
            $output[$key]['type'] = $value['filter_type'] ?: 'string';
        }

        return $output;
    }

    protected function getRangeValues($where, $key, $value, $output)
    {
        if ($where) {
            $sql = "SELECT MIN(`{$key}`) as `min`, MAX(`{$key}`) as `max` FROM {$this->tableName} WHERE {$where}";
        } else {
            $sql = "SELECT MIN(`{$key}`) as `min`, MAX(`{$key}`) as `max` FROM {$this->tableName}";
        }

        if ($statement = $this->execute($sql, $this->tokens)) {
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            $output[$key]['min'] = $_SESSION['flatfilters'][$this->configData['id']]['properties']['ranges'][$key]['min'] = $result[0]['min'] ?: 0;
            $output[$key]['max'] = $_SESSION['flatfilters'][$this->configData['id']]['properties']['ranges'][$key]['max'] = $result[0]['max'] ?: 0;
            $output[$key]['type'] = $value['filter_type'] ?: 'string';
        }

        return $output;
    }

    public function getCurrentFiltersValues()
    {
        $output = [];
        $defaultFilterKeys = $this->defaultFilters ? array_keys($this->defaultFilters) : [];
        $result = $this->prepareCurrentFiltersKeys($defaultFilterKeys);

        if (!empty($result['minMaxKeys'])) {
            $output = $this->getMinMaxValues($result['minMaxKeys'], $output);
        }

        if (!empty($result['distinctKeys'])) {
            $output = $this->getDistinctValues($result['distinctKeys'], $output);
        }

        return $output;
    }

    protected function prepareCurrentFiltersKeys($defaultFilterKeys)
    {
        $output = [
            'distinctKeys' => [],
            'minMaxKeys' => [],
        ];

        foreach ($this->filters as $key => $value) {
            if (in_array($key, $defaultFilterKeys)) {
                continue;
            }
            if (strpos($value['filter_type'], 'range') === false) {
                $output['distinctKeys'][] = $key;
            } else {
                $output['minMaxKeys'][] = "MIN(`{$key}`) as {$key}__min, MAX(`{$key}`) as {$key}__max";
            }
        }

        return $output;
    }

    protected function getMinMaxValues($minMaxKeys, $output)
    {
        $where = $_SESSION['flatfilters'][$this->configData['id']]['rids'];

        $sqlMinMax = implode(', ', $minMaxKeys);
        $sql = "SELECT {$sqlMinMax} FROM {$this->tableName} WHERE `rid` IN ({$where})";
        if ($statement = $this->execute($sql)) {
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result[0] as $k => $v) {
                $parts = explode('__', $k);
                $output[$parts[0]][$parts[1]] = $_SESSION['flatfilters'][$this->configData['id']]['properties']['ranges'][$parts[0]][$parts[1]] = $v ?: 0;
                $output[$parts[0]]['type'] = $this->filters[$parts[0]]['filter_type'] ?: 'string';
            }
        }
        return $output;
    }

    protected function getDistinctValues($distinctKeys, $output)
    {
        $where = $_SESSION['flatfilters'][$this->configData['id']]['rids'];
        $values = [];

        foreach ($distinctKeys as $key) {
            $sql = "SELECT DISTINCT `{$key}` FROM {$this->tableName} WHERE `rid` IN ({$where})";

            if ($statement = $this->execute($sql)) {
                $values[$key]['values'] = $statement->fetchAll(PDO::FETCH_COLUMN);
                $values[$key]['type'] = $this->filters[$key]['filter_type'] ?: 'string';
            }
        }

        return array_merge($output, $values);
    }
}
