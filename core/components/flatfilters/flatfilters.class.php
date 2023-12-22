<?php


class FlatFilters
{
    public function __construct($modx)
    {
        $this->modx = $modx;
        $this->core_path = $this->modx->getOption('core_path');
        $this->modx->addPackage('migx', $this->core_path . 'components/migx/model/');
        $this->modx->addPackage('flatfilters', MODX_BASE_PATH . 'core/components/flatfilters/model/');
        $this->pdo = $this->modx->getService('pdoTools');
        $this->tpls = $this->modx->getOption('ff_allowed_tpls', '', false);
        $this->tablePrefix = $this->modx->getOption('table_prefix');

        $this->loadLexicons();
    }

    /**
     * @return void
     */
    public function loadLexicons()
    {
        $this->modx->lexicon->load('flatfilters:default');
    }

    public function getFieldsKeys()
    {
        return array_merge(
            $this->getTableKeys('site_content'),
            $this->getTableKeys('ms2_products'),
            $this->getUserKeys(),
            $this->getOptionsKeys(),
            $this->getTVsKeys()
        );
    }

    private function getTableKeys($tableName)
    {
        $output = [];
        $sql = "SHOW FIELDS FROM {$this->tablePrefix}{$tableName}";
        if ($statement = $this->modx->query($sql)) {
            $items = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                if (in_array($item['Field'], ['id', 'extended'])) continue;
                $output[] = [
                    'key' => $item['Field'],
                    'caption' => $this->modx->lexicon("ff_frontend_{$item['Field']}"),
                ];
            }
        }
        //$this->modx->log(1, print_r($output,1));
        return $output;
    }

    private function getUserKeys()
    {
        $userFields = $this->getTableKeys('users');
        $profileFields = $this->getTableKeys('user_attributes');
        $output =array_merge($userFields, $profileFields);
        //$this->modx->log(1, print_r($output,1));
        return $output;
    }

    private function getOptionsKeys()
    {
        $output = [];
        if (!$this->modx->getService('minishop2')) return $output;
        $sql = "SELECT `key` FROM {$this->table_prefix}ms2_options";
        if ($statement = $this->modx->query($sql)) {
            $items = $statement->fetchAll(PDO::FETCH_COLUMN);
            foreach ($items as $item) {
                $output[] = [
                    'key' => $item,
                    'caption' => $this->modx->lexicon("ff_frontend_{$item}"),
                ];
            }
        }
        //$this->modx->log(1, print_r($output,1));
        return $output;
    }

    private function getTVsKeys()
    {
        $output = [];
        if (!$this->tpls) return $output;
        $q = $this->modx->newQuery('modTemplateVar');
        $q->where("id IN (SELECT tmplvarid FROM modx_site_tmplvar_templates WHERE templateid IN ({$this->tpls}))");
        $q->prepare();
        $tvs = $this->modx->getIterator('modTemplateVar', $q);
        foreach ($tvs as $tv) {
            $tv = $tv->toArray();
            if ($tv['type'] !== 'migx') {
                $output[] = [
                    'key' => $tv['name'],
                    'caption' => $this->modx->lexicon("ff_frontend_{$tv['name']}"),
                ];
            } else {
                if ($tv['input_properties']['configs']) {
                    $config = $this->modx->getObject('migxConfig', ['name' => 'modifications']);
                    $formtabs = json_decode($config->get('formtabs'), 1);
                } else {
                    $formtabs = json_decode($tv['input_properties']['formtabs'],1);
                }
                $migxKeys = $this->getMIGXKeys($formtabs[0]['fields'], $tv['name']);
                $output = array_merge($output,$migxKeys);
            }

        }
        //$this->modx->log(1, print_r($output,1));
        return $output;
    }

    private function getMIGXKeys($fields, $tvName)
    {
        $output = [];
        foreach ($fields as $field){
            $output[] = [
                'key' => "{$tvName}_{$field['field']}",
                'caption' => $this->modx->lexicon("ff_frontend_{$tvName}_{$field['field']}"),
            ];
        }
        //$this->modx->log(1, print_r($output,1));
        return $output;
    }
}

