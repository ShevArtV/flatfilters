<?php

class FlatFilters
{
    public modX $modx;
    public pdoTools $pdoTools;
    public miniShop2 $ms2;
    public string $core_path;
    public string $tpls;
    public string $tablePrefix;
    public array $types;
    public array $presets;
    public array $resourceTypes;
    public array $userTypes;

    public function __construct($modx)
    {
        $this->modx = $modx;
        $this->initialize();
    }

    private function initialize(): void
    {
        $this->core_path = $this->modx->getOption('core_path');
        $this->tpls = $this->modx->getOption('ff_allowed_tpls', '', false);
        $resourceTypes = $this->modx->getOption('ff_resource_types', '', 'resources,products');
        $this->resourceTypes = explode(',', $resourceTypes);
        $userTypes = $this->modx->getOption('ff_user_types', '', 'customers');
        $this->userTypes = explode(',', $userTypes);
        $this->tablePrefix = $this->modx->getOption('table_prefix');
        $presets = $this->modx->getOption('ff_preset_names', '', '{"filtering":"flatfilters", "pagination":"ff_pagination", "disabling":"ff_disabling"}');
        $this->presets = json_decode($presets, 1);
        $this->types = [];

        $this->modx->addPackage('migx', $this->core_path . 'components/migx/model/');
        $this->modx->addPackage('flatfilters', $this->core_path . 'components/flatfilters/model/');

        $this->pdoTools = $this->modx->getParser()->pdoTools;
        $this->ms2 = $this->modx->getService('miniShop2');

        $this->loadLexicons();
        $this->getTypes();
    }

    /**
     * @return void
     */
    public function loadLexicons(): void
    {
        $this->modx->lexicon->load('flatfilters:default');
    }

    private function getTypes(): bool
    {
        $pathToTypes = $this->core_path . $this->modx->getOption('ff_path_to_types', '', 'components/flatfilters/types.inc.php');
        if (file_exists($pathToTypes)) {
            $this->types = include($pathToTypes);
            if (!$this->ms2) {
                unset($this->types['products']);
            }
            return true;
        }
        $this->modx->log(1, "[FlatFilters::getTypes] Файл {$pathToTypes} не найден");
        return false;
    }

    public function getFieldsKeys(string $type = 'resources'): array
    {
        switch ($type) {
            case 'resources':
                $fields = array_merge(
                    $this->getUserKeys(),
                    $this->getTableKeys('site_content'),
                    $this->getTVsKeys(),
                );
                break;
            case 'products':
                $fields = array_merge(
                    $this->getUserKeys(),
                    $this->getTableKeys('site_content'),
                    $this->getTableKeys('ms2_products'),
                    $this->getOptionsKeys(),
                    $this->getTVsKeys(),
                );
                break;
            case 'customers':
                $fields = $this->getUserKeys();
                break;
            default:
                $this->modx->invokeEvent('ffOnGetFieldKeys', [
                    'type' => $type,
                    'FlatFilters' => $this,
                ]);

                $fields = is_array($this->modx->event->returnedValues) ? $this->modx->event->returnedValues : [];
                break;
        }

        return $fields;
    }

    public function getTableKeys(string $tableName): array
    {
        $excludeFields = [
            'id',
            'sessionid',
            'extended',
            'cachepwd',
            'remote_key',
            'remote_data',
            'hash_class',
            'session_stale',
            'password',
            'salt'
        ];
        $output = [];
        $sql = "SHOW FIELDS FROM `{$this->tablePrefix}{$tableName}`";
        $tstart = microtime(true);
        if ($statement = $this->modx->query($sql)) {
            $this->modx->queryTime += microtime(true) - $tstart;
            $this->modx->executedQueries++;
            $items = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                if (in_array($item['Field'], $excludeFields)) {
                    continue;
                }
                $output[$item['Field']] = [
                    'key' => $item['Field'],
                    'caption' => $this->modx->lexicon("ff_frontend_{$item['Field']}"),
                ];
            }
        }
        //$this->modx->log(1, print_r($output,1));
        return $output;
    }

    public function getUserKeys(): array
    {
        $userFields = $this->getTableKeys('users');
        $profileFields = $this->getTableKeys('user_attributes');
        $extendedFields = $this->getExtendedKeys();
        return array_merge($userFields, $profileFields, $extendedFields);
    }

    public function getExtendedKeys(): array
    {
        $profiles = $this->modx->getIterator('modUserProfile');
        $extendFields = $output = [];
        foreach ($profiles as $profile) {
            $extended = $profile->get('extended') ?? [];
            $extendFields = array_merge($extendFields, array_keys($extended));
        }
        $extendFields = array_unique($extendFields);
        foreach ($extendFields as $key) {
            $output[$key] = [
                'key' => $key,
                'caption' => $this->modx->lexicon("ff_frontend_{$key}"),
            ];
        }
        return $output;
    }

    public function getOptionsKeys(): array
    {
        $output = [];
        if (!$this->ms2) {
            return $output;
        }
        $sql = "SELECT `key` FROM {$this->tablePrefix}ms2_options";
        if ($statement = $this->modx->query($sql)) {
            $items = $statement->fetchAll(PDO::FETCH_COLUMN);
            foreach ($items as $item) {
                $output[$item] = [
                    'key' => $item,
                    'caption' => $this->modx->lexicon("ff_frontend_{$item}"),
                ];
            }
        }
        //$this->modx->log(1, print_r($output,1));
        return $output;
    }

    public function getTVsKeys(): array
    {
        $output = [];
        $q = $this->modx->newQuery('modTemplateVar');
        if ($this->tpls) {
            $q->where("id IN (SELECT tmplvarid FROM modx_site_tmplvar_templates WHERE templateid IN ({$this->tpls}))");
        }
        $q->prepare();
        $tvs = $this->modx->getIterator('modTemplateVar', $q);
        foreach ($tvs as $tv) {
            $tv = $tv->toArray();
            if ($tv['type'] !== 'migx') {
                $output[$tv['name']] = [
                    'key' => $tv['name'],
                    'caption' => $this->modx->lexicon("ff_frontend_{$tv['name']}"),
                ];
            } else {
                if ($tv['input_properties']['configs']) {
                    if ($config = $this->modx->getObject('migxConfig', ['name' => $tv['input_properties']['configs']])) {
                        $formtabs = json_decode($config->get('formtabs'), true);
                    }
                } else {
                    $formtabs = json_decode($tv['input_properties']['formtabs'], true);
                }

                $migxKeys = $formtabs[0]['fields'] ? $this->getMIGXKeys($formtabs[0]['fields'], $tv['name']) : [];
                $output = array_merge($output, $migxKeys);
            }
        }
        //$this->modx->log(1, print_r($output,1));
        return $output;
    }

    private function getMIGXKeys(array $fields, string $tvName): array
    {
        $output = [];
        foreach ($fields as $field) {
            $key = "{$tvName}_{$field['field']}";
            $output[$key] = [
                'key' => $key,
                'caption' => $this->modx->lexicon("ff_frontend_{$tvName}_{$field['field']}"),
            ];
        }
        //$this->modx->log(1, print_r($output,1));
        return $output;
    }

    public function removeResourceIndex(int $id)
    {
        if ($id === 0) {
            return;
        }
        // удаляем индексы ресурса из всех конфигураций, к которым он принадлежит
        $crTableName = $this->modx->getTableName('ffConfigResource');
        $q = $this->modx->newQuery('ffConfiguration');
        $q->where("`id` IN (SELECT `config_id` FROM {$crTableName} WHERE `resource_id` = {$id})");
        $q->prepare();
        $configs = $this->modx->getIterator('ffConfiguration', $q);

        foreach ($configs as $config) {
            $classKey = "ffIndex{$config->get('id')}";
            if ($tableName = $this->modx->getTableName($classKey)) {
                $sql = "DELETE FROM {$tableName} WHERE `rid` = :id";
                $statement = $this->modx->prepare($sql);
                $statement->execute(['id' => $id]);
            }
        }

        // удаляем записи о принадлежности ресурса к определенным конфигурациям
        $sql = "DELETE FROM {$crTableName} WHERE `rid` = :id";
        $statement = $this->modx->prepare($sql);
        $statement->execute(['id' => $id]);
    }

    public function getParents(int $id, int $parentId, string $classKey = 'modResource'): array
    {
        $parents = $this->getParentIds($parentId);
        if ($classKey === 'msProduct') {
            $table_prefix = $this->modx->getOption('table_prefix');
            $sql = "SELECT `category_id` FROM `{$table_prefix}ms2_product_categories` WHERE `product_id` = :id";
            $statement = $this->modx->prepare($sql);
            if ($statement->execute(['id' => $id])) {
                $result = $statement->fetchAll(PDO::FETCH_COLUMN);
                $parents = array_merge($parents, $result);
            }
        }
        return $parents;
    }

    public function getParentIds(int $parentId, array $parents = []): array
    {
        if ($parentId === 0) {
            return [];
        }

        $parents[] = $parentId;
        $q = $this->modx->newQuery('modResource');
        $q->select('parent');
        $q->where(['id' => $parentId]);
        $tstart = microtime(true);
        if ($q->prepare() && $q->stmt->execute()) {
            $this->modx->queryTime += microtime(true) - $tstart;
            $this->modx->executedQueries++;
            $grandParent = $q->stmt->fetch(PDO::FETCH_COLUMN);
            $parents = array_merge($parents, $this->getParentIds($grandParent, $parents));
        }
        return array_unique($parents);
    }

    public function getFormParams(): array
    {
        return [
            'hooks' => '',
            'snippet' => $this->modx->getOption('ff_connector', '', 'ffConnector')
        ];
    }

    public function indexingDocument(modResource $resource): void
    {
        $parents = $this->getParents($resource->get('id'), $resource->get('parent'), $resource->get('class_key'));
        $configs = $this->modx->getIterator('ffConfiguration', ['type:IN' => $this->resourceTypes]);
        foreach ($configs as $config) {
            if (!$Indexing = $this->loadClass($config->toArray(), 'indexing')) {
                continue;
            }

            if(!$resourceData = $Indexing->getResourceData($resource)){
                continue;
            }

            if ($configParents = $config->get('parents')) {
                $configParents = explode(',', $configParents);
                foreach ($configParents as $configParent) {
                    if (in_array($configParent, $parents)) {
                        $Indexing->indexResource($resourceData);
                    }
                }
            } else {
                $Indexing->indexResource($resourceData);
            }
        }
    }

    public function indexingUser(modUser $user): void
    {
        $configs = $this->modx->getIterator('ffConfiguration', ['type:IN' => $this->userTypes]);
        $profile = $user->getOne('Profile');
        $profileData = $profile->toArray();
        $extended = $profileData['extended'];
        unset($profileData['extended']);
        $resourceData = array_merge($user->toArray(), $profileData, $extended);
        foreach ($configs as $config) {
            if (!$Indexing = $this->loadClass($config->toArray(), 'indexing')) {
                continue;
            }
            if ($groups = $config->get('groups')) {
                $names = [];
                $groups = explode(',', $groups);
                $q = $this->modx->newQuery('modUserGroup');
                $q->select('name');
                $q->where(['id:IN' => $groups]);
                if ($q->prepare() && $q->stmt->execute()) {
                    $names = $q->stmt->fetchAll(PDO::FETCH_COLUMN);
                }
                if (empty($names) || $user->isMember($names)) {
                    $Indexing->indexResource($resourceData);
                }
            } else {
                $Indexing->indexResource($resourceData);
            }
        }
    }

    public function loadClass(array $configData, string $method)
    {
        $type = $configData['type'];
        if (!$type) {
            $this->modx->log(1, 'Не указан тип объекта.');
            return false;
        }
        if (!$this->types[$type]) {
            $this->modx->log(1, "Не указан класс-обработчик типа {$type}");
            return false;
        }
        $pathToClass = MODX_CORE_PATH . $this->types[$type][$method]['path'];
        if (!file_exists($pathToClass)) {
            $this->modx->log(1, "Файл класса-обработчика не найден.");
            return false;
        }

        include_once($pathToClass);
        $className = $this->types[$type][$method]['className'];
        return new $className($this->modx, $configData);
    }

    public function regClientScripts(int $tplId): void
    {
        $tpls = $this->modx->getOption('ff_tpls', '', '');
        $tpls = $tpls ? explode(',', $tpls) : [];
        $totalVar = $this->modx->getPlaceholder($totalVar);
        if (in_array($tplId, $tpls)) {
            $jpPath = $this->modx->getOption('ff_js_path', '', 'assets/components/flatfilters/js/web/flatfilters.js');
            $time = time();
            $this->modx->regClientScript("<script type=\"module\" src=\"{$jpPath}\"></script>", 1);
        }
    }

    public function setCookie(): void
    {
        $jsConfigPath = $this->modx->getOption('ff_js_config_path', '', './flatfilters.inc.js');
        $cookies = $_COOKIE['FlatFilters'] ? json_decode($_COOKIE['FlatFilters'], 1) : [];
        $data = [
            'jsConfigPath' => $jsConfigPath,
            'presets' => $this->presets,
        ];
        $data = array_merge($cookies, $data);
        setcookie('FlatFilters', json_encode($data), 0, '/');
    }

    public function setPaginationParams(array $params)
    {
        $result = $this->getFilterResult($params);
        $getDisabled = $_SESSION['flatfilters'][$params['configId']]['getDisabled'];

        if ($getDisabled) {
            $_REQUEST['resultShowMethod'] = 'insert';
        }
        $params[$result['resourcesProp']] = $result['ids'] ?: 9999999999999;
        $params['sortby'] = $result['sortby'] ?: [];
        $params['filtersHash'] = $_SESSION['flatfilters'][$params['configId']]['hash'];
        $params['upd'] = (bool)$_REQUEST['upd'];
        $params['offset'] = 0;
        return $params;
    }

    public function setResponseParams(array $params)
    {
        $params['getDisabled'] = $_SESSION['flatfilters'][$params['configId']]['getDisabled'];
        $params['totalTime'] = $_SESSION['flatfilters'][$params['configId']]['totalTime'];
        return $params;
    }

    public function getFilterResult(array $params)
    {
        $configId = $params['configId'];
        if (!$configId || !($config = $this->modx->getObject('ffConfiguration', $configId))) {
            $this->modx->log(1, $this->modx->lexicon('ff_err_config_id', ['configId' => $configId]));
            return [];
        }

        $params['presetName'] = $this->modx->getOption('ff_preset_name', '', 'flatfilters');
        $params['upd'] = $_REQUEST['upd'];
        $configData = $config->toArray();
        $configData['scriptProperties'] = $params;
        $FF = $this->modx->getService('flatfilters', 'FlatFilters', MODX_CORE_PATH . 'components/flatfilters/');
        if (!$Filtering = $FF->loadClass($configData, 'filtering')) {
            return false;
        }

        return $Filtering->run();
    }
}
