<?php

require_once 'filteringinterface.class.php';
require_once MODX_CORE_PATH . 'components/flatfilters/ffLogger.class.php';

class FilteringResources implements FilteringInterface
{

    protected ModX $modx;
    protected object $pdoTools;
    protected array $configData;
    protected array $properties;
    public array $filters;
    protected array $defaultFilters;
    public array $excludeFilters;
    public array $values = [];
    public array $tokens = [];
    protected string $tablePrefix;
    protected string $corePath;
    protected string $tableName;
    protected string $totalVar;
    protected string $resourcesProp = 'resources';
    protected int $total = 0;
    protected int $limit;
    protected int $offset;
    /** @var ffLogger */
    protected $logger;

    public function __construct($modx, $configData)
    {
        $this->modx = $modx;
        $this->configData = $configData;
        $this->initialize();
    }

    protected function initialize(): void
    {
        $this->logger = new ffLogger($this->modx);
        $this->modx->addPackage('flatfilters', MODX_BASE_PATH . 'core/components/flatfilters/model/');
        $this->pdoTools = $this->modx->getParser()->pdoTools;
        $this->tablePrefix = $this->modx->getOption('table_prefix');
        $this->corePath = $this->modx->getOption('core_path');
        $this->tableName = $this->modx->getTableName('ffIndex' . $this->configData['id']) ?: '';
        $this->configData['scriptProperties']['parents'] = $this->configData['parents'] ?: 0;
        $this->properties = $this->configData['scriptProperties'];
        $this->excludeFilters = $this->properties['excludeFilters'] ? explode(',', $this->properties['excludeFilters']) : [];
        $this->filters = json_decode($this->configData['filters'], true) ?: [];
        $this->defaultFilters = json_decode($this->configData['default_filters'], true) ?: [];
        $this->limit = (int)$this->modx->getOption('limit', $this->properties, 10);
        $this->totalVar = $this->properties['totalVar'] ?: 'total';

        $this->prepareFilters();
    }

    protected function prepareFilters(): void
    {
        if (!empty($_REQUEST['sortby'])) {
            $sortby = explode('|', $_REQUEST['sortby']);
            if (is_string($this->properties['sortby'])) {
                $this->properties['sortby'] = json_decode($this->properties['sortby'], true) ?: [];
            }
            $this->properties['sortby'] = array_merge([$sortby[0] => $sortby[1]], $this->properties['sortby'] ?? []);
        }

        if (!empty($this->excludeFilters)) {
            foreach ($this->excludeFilters as $key) {
                unset($this->filters[$key]);
            }
        }

        $filtersKeys = array_keys($this->filters);
        $pageKeyPrefix = $this->properties['pagination'] ?: '';
        $this->page = (int)$this->modx->getOption($pageKeyPrefix . 'page', $_REQUEST, 1);
        $this->offset = ($this->page - 1) >= 1 ? (($this->page - 1) * $this->limit) : 0;

        /*   $e = new \Exception;
           $this->modx->log(1, print_r($_REQUEST, 1));
           $this->modx->log(1, print_r($e->getTraceAsString(), 1));*/
        if ($filtersKeys) {
            foreach ($filtersKeys as $key) {
                $value = $this->modx->getOption($key, $_REQUEST, false);
                if ($value) {
                    $this->values[$key] = $value;
                    if ($this->filters[$key]['filter_type'] === 'multiple' || $this->filters[$key]['filter_type'] === 'numrange') {
                        $this->values[$key] = !is_array($value) ? explode(',', $value) : $value;
                    } elseif (is_array($value)) {
                        /* Разметка name="key[]" (штатный чанк ffcheckboxgroup) — это
                           множественный выбор, а тип фильтра в конфигурации говорит об
                           одиночном. Раньше массив уходил в условие «= :key» и выдача
                           молча становилась пустой: GET «key=1,2» работал, AJAX той же
                           формы — нет. Приводим к списку и предупреждаем в логе. */
                        $normalized = array_values(array_filter(
                            $value,
                            function ($v) { return $v !== '' && $v !== null; }
                        ));
                        if (!$normalized) {
                            unset($this->values[$key]);
                            continue;
                        }
                        $this->values[$key] = $normalized;
                        $this->logger->write(
                            "Фильтр «{$key}»: из запроса пришёл массив, а тип фильтра не «multiple». "
                            . 'Значения приведены к списку (IN); проверьте тип фильтра в конфигурации.',
                            [
                                'configId' => $this->configData['id'] ?? null,
                                'key' => $key,
                                'filter_type' => $this->filters[$key]['filter_type'] ?? null,
                                'values' => $normalized,
                            ],
                            'warning',
                            'filter'
                        );
                    }
                    if ($this->filters[$key]['filter_type'] === 'numrange') {
                        $start = explode('.', $this->values[$key][0]);
                        if ((int)$start[1] === 0) {
                            $this->values[$key][0] = (int)$start[0];
                        }
                        $end = explode('.', $this->values[$key][1]);
                        if ((int)$end[1] === 0) {
                            $this->values[$key][1] = (int)$end[0];
                        }
                    }
                    //$this->modx->log(1, print_r([$this->values[$key], $this->filters[$key]['filter_type']], 1));
                }
            }
        }
    }

    public function run(): array
    {
        $time_start = microtime(true);
        $output = [];
        $output['html'] = $_SESSION['flatfilters'][$this->configData['id']]['html'];

        $this->modx->invokeEvent('ffOnBeforeFilter', [
            'configData' => $this->configData,
            'FlatFilters' => $this
        ]);

        $hash = md5(json_encode($this->values));
        $upd = $this->properties['upd'];
        //unset($this->properties['upd']);

        $_SESSION['flatfilters'][$this->configData['id']]['properties'] = array_merge(
            $_SESSION['flatfilters'][$this->configData['id']]['properties'] ?: [],
            $this->properties
        );
        $getDisabled = 0;
        $rids = $_SESSION['flatfilters'][$this->configData['id']]['rids'];
        if (!$rids || $_SESSION['flatfilters'][$this->configData['id']]['hash'] !== $hash || $upd) {
            $this->offset = 0;
            $rids = $this->filter();

            $this->modx->invokeEvent('ffOnAfterFilter', [
                'configData' => $this->configData,
                'rids' => $rids
            ]);
            $rids = $this->modx->event->returnedValues['rids'] ?? $rids;
            if (isset($this->modx->event->returnedValues['rids'])) {
                $ids = !empty($this->modx->event->returnedValues['rids']) ? explode(',', $this->modx->event->returnedValues['rids']) : [];
                $_SESSION['flatfilters'][$this->configData['id']][$this->totalVar] = count($ids);
            }
            $_SESSION['flatfilters'][$this->configData['id']]['hash'] = $hash;
            $_SESSION['flatfilters'][$this->configData['id']]['rids'] = $rids;
            $getDisabled = $this->properties['noDisabled'] ? 0 : 1;
        }

        if ($rids) {
            $output['ids'] = $this->getOutputIds($rids);
        }

        $time_end = microtime(true);
        $output['resourcesProp'] = $this->resourcesProp;
        $output['sortby'] = $this->properties['sortby'];
        $_SESSION['flatfilters'][$this->configData['id']]['totalVar'] = $this->totalVar;
        $_SESSION['flatfilters'][$this->configData['id']]['totalTime'] = sprintf('TOTAL TIME %f sec.', $time_end - $time_start);
        $_SESSION['flatfilters'][$this->configData['id']]['getDisabled'] = $getDisabled;
        $_SESSION['flatfilters'][$this->configData['id']]['currentPage'] = ($_SESSION['flatfilters'][$this->configData['id']]['hash'] !== $hash || $upd) ? 1 : $this->page;
        $this->modx->setPlaceholder($this->totalVar, $_SESSION['flatfilters'][$this->configData['id']][$this->totalVar]);

        return $output;
    }

    protected function filter(): string
    {
        $rids = [];
        $sql = $this->getFilterSql();
        /* основная фильтрация */
        if ($statement = $this->execute($sql, $this->tokens)) {
            $_SESSION['flatfilters'][$this->configData['id']][$this->totalVar] = $statement->rowCount();
            $rids = $statement->fetchAll(PDO::FETCH_COLUMN);
        }

        return implode(', ', $rids);
    }

    protected function getFilterSql(): string
    {
        $sql = "SELECT SQL_CALC_FOUND_ROWS `rid` FROM {$this->tableName} ";

        $conditions = [];
        foreach ($this->filters as $key => $data) {
            $value = ($this->values[$key] ?? null) ?: ($this->defaultFilters[$key]['value'] ?? null);
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

    protected function getCondition($key, $value, $type): string
    {
        $sign = $this->getCompareSign($key, $type, $value);

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
                if (strpos($type, 'date') !== false) {
                    list($startTs, $endTs) = $this->normalizeDateRange($value[0] ?? '', $value[1] ?? '');
                    $this->tokens[$keyStart] = $startTs;
                    $this->tokens[$keyEnd] = $endTs;
                } else {
                    $this->tokens[$keyStart] = $value[0] ?? null;
                    $this->tokens[$keyEnd] = $value[1] ?? null;
                }
                $condition = " {$keyStr} >= :{$keyStart} AND {$keyStr} <= :{$keyEnd} ";
                break;

            case 'IN':
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                if (!$value) {
                    /* «IN ()» — синтаксическая ошибка MySQL, весь запрос упал бы целиком.
                       Пустой список значений не отбирает ничего — так и пишем. */
                    return ' 1 = 0 ';
                }
                $tokens = [];
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

    protected function getCompareSign($key, $type, $value = null): string
    {
        $sign = '=';
        if (!empty($this->defaultFilters[$key])) {
            $sign = $this->defaultFilters[$key]['sign'] ?? '=';
        } else {
            if (strpos($type, 'range') !== false) {
                $sign = 'BETWEEN';
            } elseif ($type === 'multiple') {
                $sign = 'IN';
            }
        }
        /* Набор значений нельзя сравнивать одиночным знаком: PDO не биндит массив,
           и выдача молча пустеет. Знак из конфигурации при этом сохраняем, если он уже
           умеет работать со списком (IN) или диапазоном (BETWEEN). */
        if (is_array($value) && !in_array($sign, ['IN', 'BETWEEN'], true)) {
            $sign = 'IN';
        }
        return $sign;
    }

    protected function execute(string $sql, ?array $tokens = []): ?PDOStatement
    {
        $statement = $this->modx->prepare($sql);
        $time_start = microtime(true);
        if ($statement->execute($tokens)) {
            $time_end = microtime(true);
            $this->modx->queryTime += $time_end - $time_start;
            $this->modx->executedQueries++;
            return $statement;
        }

        return null;
    }

    protected function getOutputIds(string $rids): string
    {
        $orderBy = isset($this->properties['sortby']) ? $this->getSortby() : '';
        $sql = $this->getOutputSQL($rids);

        // Сортировка по колонке индексной таблицы требует её джойна: в output-SQL есть
        // только site_content (`Resource`). Для resources в индексе одна строка на rid.
        if (strpos($orderBy, '`Idx`.') !== false) {
            $sql = str_replace(
                ' WHERE ',
                " LEFT JOIN {$this->tableName} Idx ON Idx.rid = `Resource`.`id` WHERE ",
                $sql
            );
        }

        $sql .= $orderBy;

        $sql .= " LIMIT $this->limit OFFSET $this->offset";
        /* получаем список id для отображения на странице */
        if ($statement = $this->execute($sql)) {
            $rids = $statement->fetchAll(PDO::FETCH_COLUMN);
            $rids = implode(',', $rids);
        }

        return $rids;
    }

    protected function getOutputSQL(string $rids): string
    {
        $resourceTableName = $this->modx->getTableName('modResource');
        return "SELECT `Resource`.`id` FROM $resourceTableName Resource WHERE `Resource`.`id` IN ($rids)";
    }

    protected function getSortby(): string
    {
        /* готовим условия сортировки; ключ пропускаем через whitelist — без него
           значение из $_REQUEST['sortby'] уходило в ORDER BY сырым (SQL-инъекция) */
        $sortby = [];
        $sort = is_array($this->properties['sortby']) ? $this->properties['sortby'] : json_decode($this->properties['sortby'], 1);
        $allowed = $this->getAllowedSortKeys();
        foreach ((array)$sort as $key => $dir) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                continue;
            }
            $sortby[] = $this->mapSortKey($key) . ' ' . $this->normalizeDirection($dir);
        }
        return !empty($sortby) ? ' ORDER BY ' . implode(',', $sortby) : ' ORDER BY `Resource`.`id`';
    }

    /**
     * Границы диапазона дат -> пара timestamp'ов. Начало — 00:00:00 своего дня,
     * конец — 23:59:59 (иначе запись, созданная днём, не попадёт в «по эту дату»).
     * Если передана только одна граница — диапазон трактуется как весь этот день.
     */
    protected function normalizeDateRange($startRaw, $endRaw): array
    {
        $start = trim((string)$startRaw);
        $end = trim((string)$endRaw);
        if ($start === '') {
            $start = $end;
        }
        if ($end === '') {
            $end = $start;
        }
        $startTs = $start !== '' ? strtotime($start) : false;
        $endTs = $end !== '' ? strtotime($end) : false;
        return [
            $startTs !== false ? (int)strtotime(date('Y-m-d', $startTs) . ' 00:00:00') : 0,
            $endTs !== false ? (int)strtotime(date('Y-m-d', $endTs) . ' 23:59:59') : PHP_INT_MAX,
        ];
    }

    protected function normalizeDirection($dir): string
    {
        $up = is_string($dir) ? strtoupper($dir) : '';
        return ($up === 'ASC' || $up === 'DESC') ? $up : 'ASC';
    }

    protected function isValidColumn($name): bool
    {
        return is_string($name) && (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name);
    }

    /**
     * Ключ сортировки → безопасное SQL-выражение. Ключи уже прошли whitelist.
     */
    protected function mapSortKey(string $key): string
    {
        if ($key === 'id') {
            return '`Resource`.`id`';
        }
        if ($key === 'rid') {
            return '`Idx`.`rid`';
        }
        if (strpos($key, 'Resource.') === 0) {
            return '`Resource`.`' . substr($key, 9) . '`';
        }
        if (isset($this->filters[$key])) {
            // колонка индексной таблицы — getOutputIds добавит JOIN Idx
            return "`Idx`.`{$key}`";
        }
        return '`Resource`.`id`';
    }

    /**
     * Разрешённые ключи сортировки: имена фильтров (колонки индекса), id/rid и колонки
     * site_content с префиксом Resource. (например Resource.publishedon — «сначала новые»).
     */
    protected function getAllowedSortKeys(): array
    {
        $keys = [];
        foreach (array_keys($this->filters) as $k) {
            if ($this->isValidColumn($k)) {
                $keys[] = $k;
            }
        }
        $keys[] = 'id';
        $keys[] = 'rid';
        foreach ($this->getTableColumns($this->tablePrefix . 'site_content') as $col) {
            $keys[] = 'Resource.' . $col;
        }
        return array_values(array_unique($keys));
    }

    protected static array $tableColumnsCache = [];

    protected function getTableColumns(string $tableName): array
    {
        if (isset(self::$tableColumnsCache[$tableName])) {
            return self::$tableColumnsCache[$tableName];
        }
        $columns = [];
        if ($statement = $this->modx->query("SHOW FIELDS FROM `{$tableName}`")) {
            foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $col) {
                if ($this->isValidColumn($col)) {
                    $columns[] = $col;
                }
            }
        }
        return self::$tableColumnsCache[$tableName] = $columns;
    }

    public function getAllFiltersValues(string $rids = ''): array
    {
        // Пустая форма (нет пользовательских фильтров): значения facet зависят только
        // от индекса и префильтра, а не от запроса — считаем один раз и кэшируем.
        // На каталоге 100K это снимает 9 полных сканов таблицы с каждого захода.
        $emptyForm = empty($this->values);
        if ($emptyForm && ($cached = $this->getFacetCache()) !== null) {
            $this->restoreRangesToSession($cached);
            $_SESSION['flatfilters'][$this->configData['id']]['properties']['all_ranges'] = $cached;
            return $cached;
        }

        $output = [];
        $defaultFilterKeys = $this->defaultFilters ? array_keys($this->defaultFilters) : [];

        foreach ($this->filters as $key => $value) {
            if (in_array($key, $defaultFilterKeys)) {
                continue;
            }
            // Фасет «excluding self»: доступные значения фильтра считаем по СТРОКАМ
            // индекса, прошедшим все ОСТАЛЬНЫЕ активные фильтры, но не сам этот фильтр.
            // Это (а) не блокирует уже выбранную группу, (б) корректно для MIGX (у rid
            // несколько строк — rid IN показал бы значения из строк, не прошедших другие
            // фильтры), (в) по индексу колонки, а не полный скан от большого rid IN.
            $where = $this->buildFacetWhere((string)$key);
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

        if ($emptyForm) {
            $this->setFacetCache($output);
        }

        return $output;
    }

    /**
     * Ключ кэша facet пустой формы. Один ключ на конфиг; хэш filters+default_filters
     * хранится внутри значения — при изменении настроек конфига кэш считается протухшим.
     */
    protected function getFacetCacheKey(): string
    {
        return 'facet_empty_' . $this->configData['id'];
    }

    protected function getFacetCacheOptions(): array
    {
        return [xPDO::OPT_CACHE_KEY => 'flatfilters'];
    }

    protected function getFacetConfigHash(): string
    {
        return md5(($this->configData['filters'] ?? '') . '|' . ($this->configData['default_filters'] ?? ''));
    }

    protected function getFacetCache(): ?array
    {
        if (!$this->modx->getCacheManager()) {
            return null;
        }
        $cached = $this->modx->cacheManager->get($this->getFacetCacheKey(), $this->getFacetCacheOptions());
        if (is_array($cached) && isset($cached['hash'], $cached['data']) && $cached['hash'] === $this->getFacetConfigHash()) {
            return $cached['data'];
        }
        return null;
    }

    protected function setFacetCache(array $output): void
    {
        if (!$this->modx->getCacheManager()) {
            return;
        }
        $payload = ['hash' => $this->getFacetConfigHash(), 'data' => $output];
        $this->modx->cacheManager->set(
            $this->getFacetCacheKey(),
            $payload,
            0,
            $this->getFacetCacheOptions()
        );
    }

    /**
     * getRangeValues при живом расчёте кладёт min/max диапазонов в сессию (нужны для
     * валидации границ слайдера в следующем запросе). При отдаче из кэша восстанавливаем.
     */
    protected function restoreRangesToSession(array $output): void
    {
        foreach ($output as $key => $item) {
            if (isset($item['min']) || isset($item['max'])) {
                $_SESSION['flatfilters'][$this->configData['id']]['properties']['ranges'][$key]['min'] = $item['min'] ?? 0;
                $_SESSION['flatfilters'][$this->configData['id']]['properties']['ranges'][$key]['max'] = $item['max'] ?? 0;
            }
        }
    }

    /**
     * WHERE-условия всех активных фильтров (пользовательских + дефолтных) КРОМЕ $exceptKey.
     * Накапливает плейсхолдеры в $this->tokens (сбрасывает перед построением).
     */
    protected function buildFacetWhere(string $exceptKey): string
    {
        $this->tokens = [];
        $conditions = [];
        foreach ($this->filters as $k => $data) {
            $k = (string)$k;
            if ($k === $exceptKey) {
                continue;
            }
            $value = isset($this->values[$k]) ? $this->values[$k] : null;
            if ($value === null || $value === '' || $value === []) {
                if (isset($this->defaultFilters[$k]['value'])) {
                    $value = $this->defaultFilters[$k]['value'];
                    $filterType = $this->defaultFilters[$k]['filter_type'] ?? '';
                } else {
                    continue;
                }
            } else {
                $filterType = is_array($data) ? ($data['filter_type'] ?? '') : '';
            }
            $conditions[] = $this->getCondition($k, $value, $filterType);
        }

        $this->modx->invokeEvent('ffOnBeforeGetFilterValues', [
            'configData' => $this->configData,
            'conditions' => $conditions,
            'FlatFilters' => $this
        ]);
        $conditions = is_array($this->modx->event->returnedValues['conditions']) ? $this->modx->event->returnedValues['conditions'] : $conditions;

        return $conditions ? implode(' AND ', $conditions) : '';
    }

    public function renderFilterForm(array $scriptProperties, ?array $output = [])
    {
        if (!empty($output['filtersValues'])) {
            foreach ($output['filtersValues'] as $key => $item) {
                $item['key'] = $key;
                $item['options'] = '';
                $item['props'] = $scriptProperties;
                if (is_array($item['values'])) {
                    $chunk = $scriptProperties["{$key}TplRow"] ?? $scriptProperties["defaultTplRow"];
                    if ($chunk) {
                        foreach ($item['values'] as $idx => $value) {
                            $params = array_merge($scriptProperties, ['key' => $key, 'value' => $value, 'idx' => $idx]);
                            $item['options'] .= $this->pdoTools->parseChunk($chunk, $params);
                        }
                    }
                }
                $chunk = $scriptProperties["{$key}TplOuter"] ?? $scriptProperties["defaultTplOuter"];
                if (!$chunk) {
                    continue;
                }
                $item = array_merge($scriptProperties, $item);
                $output['filters'] .= $this->pdoTools->parseChunk($chunk, $item);
            }
        }

        $output = array_merge($scriptProperties, $output);
        return $scriptProperties['wrapper'] ? $this->pdoTools->parseChunk($scriptProperties['wrapper'], $output) : $output;
    }

    protected function getNoRangeValues($where, $key, $value, $output): array
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

    protected function getRangeValues($where, $key, $value, $output): array
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

    public function getCurrentFiltersValues(): array
    {
        // getAllFiltersValues сам считает каждый фильтр «excluding self» (buildFacetWhere):
        // выбранная группа не блокируется, остальные сужаются по строкам, прошедшим её.
        return $this->getAllFiltersValues();
    }
}
