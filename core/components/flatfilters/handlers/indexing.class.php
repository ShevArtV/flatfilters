<?php

class Indexing
{
    public function __construct($modx, $config)
    {
        $this->modx = $modx;
        $this->config = $config;
        $this->modx->addPackage('flatfilters', MODX_BASE_PATH . 'core/components/flatfilters/model/');
        $this->tablePrefix = $this->modx->getOption('table_prefix', '', 'modx_');
        $this->allowedTpls = $this->modx->getOption('ff_allowed_tpls', '', '');
    }

    public function indexConfig()
    {
        $classKey = $this->config['class_key'] ?: 'msProduct';
        $offset = $this->config['offset'];

        $q = $this->modx->newQuery($classKey);
        $q->where(['class_key' => $classKey, 'deleted' => false]);
        if($this->allowedTpls){
            $q->andCondition("`template` IN ({$this->allowedTpls})");
        }
        if (!empty($this->config['parents'])) {
            $this->config['parents'] = explode(',', $this->config['parents']);
            $parents = $this->config['parents'];
            foreach ($this->config['parents'] as $id) {
                if ($parent = $this->modx->getObject('modResource', $id)) {
                    $parents = array_merge($parents, $this->modx->getChildIds($id, 10, ['context' => $parent->get('context_key')]));
                }
            }
            $parents = implode(', ', $parents);
            $q->andCondition("`parent` IN ({$parents}) OR `id` IN (SELECT `product_id` FROM `{$this->tablePrefix}ms2_product_categories` WHERE `category_id` IN ({$parents}))");
        }
        $q->prepare();
        $total = $this->modx->getCount($classKey, $q);
        //$this->modx->log(1, $q->toSQL());
        if ($offset >= $total) {
            $this->modx->log(1, 'Индексирование завершено');
            return ['total' => $total, 'offset' => $offset];
            /*unlink($this->offsetPath);
            $i = 0;*/
        }
        $q->limit($this->config['step'], $offset);
        $q->prepare();
        $resources = $this->modx->getIterator($classKey, $q);
        //$this->modx->log(1, print_r($q->toSQL(), 1));
        foreach ($resources as $resource) {
            $resourceData = $resource->toArray();
            $resourceData = array_merge($resourceData, $this->getResourceTVs($resourceData['id']), $this->getUserFields($resourceData['createdby']));
            $this->indexResource($resourceData);
            $offset++;
        }

        return ['total' => $total, 'offset' => $offset];
    }

    public function indexResource($resourceData)
    {
        $filters = !is_array($this->config['filters']) ? json_decode($this->config['filters'], 1) : $this->config['filters'];
        $className = "ffIndex{$this->config['id']}";
        $keys = ['rid'];
        $resourceData['rid'] = $resourceData['id'];
        unset($resourceData['id']);

        foreach ($filters as $key => $data) {
            if (!isset($resourceData[$key]) && strpos($key, '_') !== false) {
                $keyParts = explode('_', $key);
                $keys[] = $keyParts[0];
            } else {
                $keys[] = $key;
            }
        }
        $indexes = [];

        foreach ($resourceData as $key => $value) {
            if (!in_array($key, $keys)) continue;
            if (is_array($value)) {
                $arrays[$key] = $value;
            } else {
                if ($key !== 'rid' && $filters[$key]['field_type'] === 'timestamp') {
                    $value = $value?:'1970';
                    $value = is_string($value) ? strtotime($value) : $value;
                }
                $indexes[$key] = $value;
            }
        }

        if (!empty($arrays)) {
            $combinations = $this->getCombinations($arrays);
            foreach ($combinations as $combination) {
                $combination = array_merge($indexes, $combination);
                foreach ($combination as $key => $val) {
                    if ($key !== 'rid' && $filters[$key]['field_type'] === 'timestamp') {
                        $val = $val?:'1970';
                        $combination[$key] = is_string($val) ? strtotime($val) : $val;
                    }
                    if (!is_array($val)) continue;
                    foreach ($val as $k => $v) {
                        $combination["{$key}_{$k}"] = $v;
                    }
                    unset($combination[$key]);
                }
                if (!$this->modx->getCount($className, $combination)) {
                    $index = $this->modx->newObject($className);
                    $index->fromArray($combination);
                    $index->save();
                }
                //$this->modx->log(1, print_r($combination, 1));
            }
        } else {
            if (!$this->modx->getCount($className, $indexes)) {
                $index = $this->modx->newObject($className);
                $index->fromArray($indexes);
                $index->save();
            }
        }

        $data = ['resource_id' => $resourceData['rid'], 'config_id' => $this->config['id']];
        if (!$this->modx->getCount('ffConfigResource', $data)) {
            $configResource = $this->modx->newObject('ffConfigResource');
            $configResource->fromArray($data);
            $configResource->save();
        }

        //$this->modx->log(1, print_r($resourceData['rid'], 1));
    }

    private function getCombinations($arrays): array
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

    private function getResourceTVs(int $rid): array
    {
        $resourceTvs = [];

        $sqlDefault = "SELECT {$this->tablePrefix}site_tmplvars.name, {$this->tablePrefix}site_tmplvar_contentvalues.value FROM {$this->tablePrefix}site_tmplvars 
                LEFT JOIN {$this->tablePrefix}site_tmplvar_contentvalues 
                ON {$this->tablePrefix}site_tmplvars.id = {$this->tablePrefix}site_tmplvar_contentvalues.tmplvarid
                WHERE {$this->tablePrefix}site_tmplvar_contentvalues.contentid = {$rid}";

        if ($statement = $this->modx->query($sqlDefault)) {
            $tvs = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tvs as $tv) {
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

    private function decodeJsonValue(array $value): array
    {
        foreach ($value as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach ($item as $k => $v) {
                if (is_string($v) && strpos($v, '[{') !== false) {
                    $item[$k] = json_decode($v, 1); // преобразуем поля типа migx в массив
                    $this->decodeJsonValue($value[$k]);
                }
            }
            $value[$key] = $item;
        }

        return $value;
    }

    private function getUserFields($user_id)
    {
        $output = [];
        if ($user = $this->modx->getObject('modUser', $user_id)) {
            $profile = $user->getOne('Profile');
            $output = array_merge($output, $user->toArray(), $profile->toArray());
            unset($output['id']);
        }
        return $output;
    }

    public static function removeResourceIndex($modx, $id)
    {
        // удаляем индексы ресурса из всех конфигураций, к которам он принадлежит
        $crTableName = $modx->getTableName('ffConfigResource');
        $q = $modx->newQuery('ffConfiguration');
        $q->where("`id` IN (SELECT `config_id` FROM {$crTableName} WHERE `resource_id` = {$id})");
        $q->prepare();
        $configs = $modx->getIterator('ffConfiguration', $q);
        foreach ($configs as $config) {
            $classKey = "ffIndex{$config->get('id')}";
            $tableName = $modx->getTableName($classKey);
            $sql = "DELETE FROM {$tableName} WHERE `rid` = {$id}";
            $modx->exec($sql);
        }

        // удаляем записи о принадлежности ресурса к определенным конфигурациям
        $sql = "DELETE FROM {$crTableName} WHERE `resource_id` = {$id}";
        $modx->exec($sql);
    }

    public static function getParentIds($modx, $parent_id, $parents = [])
    {
        $parents[] = $parent_id;
        if ($parent_id) {
            $parent = $modx->getObject('modResource', $parent_id);
            $parents = Indexing::getParentIds($modx, $parent->get('parent'), $parents);
        }
        return $parents;
    }
}