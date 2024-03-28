<?php

require_once 'indexinginterface.class.php';

class IndexingResources implements IndexingInterface
{
    protected ModX $modx;
    public array $config;
    protected string $tablePrefix;
    protected string $classKey = 'modResource';

    public function __construct($modx, $config)
    {
        $this->modx = $modx;
        $this->config = $config;
        $this->initialize();
    }

    protected function initialize(): void
    {
        $this->modx->addPackage('flatfilters', MODX_BASE_PATH . 'core/components/flatfilters/model/');
        $this->tablePrefix = $this->modx->getOption('table_prefix', '', 'modx_');
    }

    public function indexConfig(): array
    {
        $offset = $this->config['offset'];
        $q = $this->getQuery();
        $total = $this->modx->getCount($this->classKey, $q);
        if ($offset >= $total) {
            return ['total' => $total, 'offset' => $offset];
        }

        $q->limit($this->config['step'], $offset);
        $resources = $this->modx->getIterator($this->classKey, $q);

        foreach ($resources as $resource) {
            $offset++;
            if(!$resourceData = $this->getResourceData($resource)){
                continue;
            }
            $this->indexResource($resourceData);
        }

        return ['total' => $total, 'offset' => $offset];
    }

    protected function getQuery(): xPDOQuery_mysql
    {
        $allowedTpls = $this->modx->getOption('ff_allowed_tpls', '', '');
        $q = $this->modx->newQuery($this->classKey);
        $q->where(['deleted' => false, 'published' => true]);
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
        foreach ($parents as $id) {
            if ($parent = $this->modx->getObject('modResource', $id)) {
                $parents = array_merge($parents, $this->modx->getChildIds($id, 10, ['context' => $parent->get('context_key')]));
            }
        }
        $query->andCondition(['parent:IN' => $parents]);
        return $query;
    }

    public function getResourceData($resource)
    {
        $resourceData = $resource->toArray();
        return array_merge($this->getUserFields($resourceData['createdby']), $resourceData, $this->getResourceTVs($resourceData['id']));
    }

    protected function getResourceTVs(int $rid): array
    {
        $resourceTvs = [];

        $q = $this->modx->newQuery('modTemplateVar');
        $q->leftJoin('modTemplateVarResource', 'TemplateVarResources');
        $q->select(['modTemplateVar.name as name', 'TemplateVarResources.value as value']);
        $q->where(['TemplateVarResources.contentid' => $rid]);

        $tstart = microtime(true);
        if ($q->prepare() && $q->stmt->execute()) {
            $this->modx->queryTime += microtime(true) - $tstart;
            $this->modx->executedQueries++;
            while ($tv = $q->stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = $tv['value'];
                if (is_string($value) && strpos($value, '[{') !== false) {
                    $arr = $this->decodeJsonValue(json_decode($value, 1)); // преобразуем поля типа migx в массив
                    foreach ($arr as $key => $val) {
                        unset($arr[$key]['MIGX_id']);
                    }
                    $resourceTvs[$tv['name']] = $arr;
                } else {
                    $resourceTvs[$tv['name']] = explode('||', $value);
                }
            }
        }

        // $this->modx->log(1, print_r($resourceTvs,1));
        return $resourceTvs;
    }

    protected function decodeJsonValue(array $value): array
    {
        foreach ($value as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach ($item as $k => $v) {
                if (is_string($v) && strpos($v, '[{') !== false) {
                    $item[$k] = json_decode($v, 1); // преобразуем поля типа migx в массив
                    if(!is_array($item[$k])) continue;
                    $this->decodeJsonValue($item[$k]);
                }
            }
            $value[$key] = $item;
        }

        return $value;
    }

    protected function getUserFields($user_id): array
    {
        $output = [];
        if (!$user_id) return $output;
        $userExludeFields = [
            'cachepwd',
            'remote_key',
            'remote_data',
            'hash_class',
            'session_stale',
            'password',
            'salt'
        ];
        $q = $this->modx->newQuery('modUser');
        $q->leftJoin('modUserProfile', 'Profile');
        $q->select($this->modx->getSelectColumns('modUser', 'modUser', '', $userExludeFields, true));
        $q->select($this->modx->getSelectColumns('modUserProfile', 'Profile', '', ['id', 'sessionid'], true));
        $q->where(['modUser.id' => $user_id]);

        $tstart = microtime(true);
        if ($q->prepare() && $q->stmt->execute()) {
            $this->modx->queryTime += microtime(true) - $tstart;
            $this->modx->executedQueries++;
            $output = $q->stmt->fetch(PDO::FETCH_ASSOC);
            $extended = $output['extended'] ? json_decode($output['extended'], 1) : [];
            $output = array_merge($output, $extended);
            unset($output['id'], $output['extended']);
        }

        return $output;
    }

    public function indexResource(array $resourceData)
    {
        $resourceData['rid'] = $resourceData['id'];
        unset($resourceData['id']);
        $filters = !is_array($this->config['filters']) ? json_decode($this->config['filters'], 1) : $this->config['filters'];
        $keys = $this->getFiltersKeys($filters, $resourceData);
        $className = "ffIndex{$this->config['id']}";

        [$indexes, $arrays] = $this->getIndexedData($resourceData, $keys, $filters);

        if (!empty($arrays)) {
            $combinations = $this->getCombinations($arrays);
            foreach ($combinations as $combination) {
                $combination = $this->prepareCombination(array_merge($indexes, $combination), $filters);
                $this->addIndexes($className, $combination);
            }
        } else {
            $this->addIndexes($className, $indexes);
        }

        $data = [
            'resource_id' => $resourceData['rid'],
            'config_id' => $this->config['id'],
        ];
        $this->addBinding($data);

        //$this->modx->log(1, print_r($resourceData['rid'], 1));
    }

    protected function getFiltersKeys(array $filters, array $resourceData): array
    {
        $keys = ['rid'];
        foreach ($filters as $key => $data) {
            if (!isset($resourceData[$key]) && strpos($key, '_') !== false) {
                $keyParts = explode('_', $key);
                $keys[] = $keyParts[0];
            } else {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    protected function getIndexedData(array $resourceData, array $keys, array $filters): array
    {
        $arrays = $indexes = [];

        foreach ($resourceData as $key => $value) {
            if (!in_array($key, $keys)) continue;
            if (is_array($value)) {
                $arrays[$key] = $value;
            } else {
                if ($key !== 'rid' && $filters[$key]['field_type'] === 'timestamp') {
                    $value = $value ?: '01-01-1971 00:00:00';
                    $value = !is_numeric($value) ? strtotime($value) : $value;
                }
                if ($key !== 'rid' && in_array($filters[$key]['field_type'], ['int', 'decimal'])) {
                    $value = $value ?? 0;
                }

                $indexes[$key] = $value;
            }
        }
        return [$indexes, $arrays];
    }

    protected function getCombinations($arrays): array
    {
        $result = [];

        foreach ($arrays as $key => $values) {

            if (empty($values)) {
                continue;
            }

            if (empty($result)) {
                foreach ($values as $value) {
                    $result[] = [$key => $value];
                }
            } else {
                $append = [];

                foreach ($result as &$product) {
                    $product[$key] = array_shift($values);

                    $copy = $product;

                    foreach ($values as $item) {
                        $copy[$key] = $item;
                        $append[] = $copy;
                    }

                    array_unshift($values, $product[$key]);
                }

                $result = array_merge($result, $append);
            }
        }

        return $result;
    }

    protected function prepareCombination(array $combination, array $filters): array
    {
        foreach ($combination as $key => $val) {
            if ($key !== 'rid' && $filters[$key]['field_type'] === 'timestamp') {
                $val = $val ?: '01-01-1971 00:00:00';
                $combination[$key] = !is_numeric($val) ? strtotime($val) : $val;
            }
            if (!is_array($val)) continue;
            foreach ($val as $k => $v) {
                $combination["{$key}_{$k}"] = $v;
            }
            unset($combination[$key]);
        }

        return $combination;
    }

    protected function addIndexes(string $className, array $indexes): void
    {
        if (!$this->modx->getCount($className, $indexes)) {
            $index = $this->modx->newObject($className);
            $index->fromArray($indexes);
            $index->save();
        }
    }

    protected function addBinding($data): void
    {
        if (!$this->modx->getCount('ffConfigResource', $data)) {
            $configResource = $this->modx->newObject('ffConfigResource');
            $configResource->fromArray($data);
            $configResource->save();
        }
    }
}