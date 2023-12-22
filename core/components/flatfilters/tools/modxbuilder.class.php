<?php

/**
 * Class modxBuilder
 * Class for building components, generates model-files and other
 */
class modxBuilder
{
    /**
     * @var modX $modx
     */
    public $modx;
    public $config = [];

    /** @var  xPDOGenerator_my */
    protected $generator;

    public function __construct(&$modx)
    {
        $this->modx = &$modx;
        $corePath = MODX_CORE_PATH . 'components/flatfilters/';
        $this->config = [
            'modx_root' => MODX_BASE_PATH,
            'package_name' => 'flatfilters',
            'package_class_prefix' => 'ff',
            'package_table_prefix' => 'ff_',
            'package_dir' => $corePath,
            'schema_dir' => $corePath . '/model/schema/',
            'model_dir' => $corePath . '/model/',
            'class_dir' => $corePath . '/model/flatfilters/',
            'mysql_class_dir' => $corePath . 'model/flatfilters/mysql/',
            'xml_schema_file' => $corePath . 'model/schema/flatfilters.mysql.schema.xml',
            'regenerate_schema' => true,
            'regenerate_classes' => true,
            'regenerate_maps' => true,
        ];
    }

    protected function getGenerator()
    {
        if (!$this->generator) {
            //Подключаем наш класс генератора
            include_once $this->config['modx_root'] . 'core/xpdo/om/mysql/xpdogenerator.class.php';
            include_once("xpdogenerator.class.php");

            $manager = $this->modx->getManager();

            $this->generator = new xPDOGenerator_my($manager);
        }

        return $this->generator;
    }

    /**
     * @param bool $restrict_prefix - If you specify a table prefix, you probably want this set to 'true'. E.g. if you
     * have custom tables alongside the modx_xxx tables, restricting the prefix ensures
     * that you only generate classes/maps for the tables identified by the $this->config['package_table_prefix'].
     * @param bool $verbose - if true, will print status info.
     * @param bool $debug - if true, will include verbose debugging info, including SQL errors.
     */
    public function writeSchema($restrict_prefix = true, $verbose = true, $debug = true)
    {
        if (!defined('MODX_CORE_PATH')) {
            $this->modx->log(1, 'Reverse Engineering Error! MODX_CORE_PATH not defined! Did you include the correct config file?');
            exit;
        }

        // Validations
        if (empty($this->config['package_name'])) {
            $this->modx->log(1, "Reverse Engineering Error! The package_name cannot be empty!  Please adjust the configuration and try again.");
            exit;
        }

        // Set the package name and root path of that package
        $this->modx->setPackage($this->config['package_name'], $this->config["model_dir"]);
        $this->modx->setDebug($debug);

        //$generator = $manager->getGenerator();  // Станадртное получение mysql генератора
        $generator = $this->getGenerator();
        $generator->setClassPrefix($this->config['package_class_prefix']);

        //Use this to create an XML schema from an existing database
        if ($this->config['regenerate_schema']) {
            if (!file_exists($this->config['xml_schema_file'])) {
                touch($this->config['xml_schema_file']);
            }
            $generator->writeSchema($this->config["xml_schema_file"], $this->config['package_name'], 'xPDOObject', '', $restrict_prefix, $this->config['package_table_prefix']);
        }
    }

    public function parseSchema($verbose = true)
    {
        $this->modx->loadClass('transport.modPackageBuilder', '', false, true);

        if (!is_dir($this->config['model_dir'])) {
            $this->modx->log(1, 'Model directory not found!');
            return false;
        }

        if (!file_exists($this->config['xml_schema_file'])) {
            $this->modx->log(1, "Schema file {$this->config['xml_schema_file']} not found!");
            return false;
        }

        // Use this to generate classes from your schema
        if ($this->config['regenerate_classes']) {
            modxBuilder::deleteClassFiles($this->config["class_dir"], $verbose);
            modxBuilder::deleteClassFiles($this->config["mysql_class_dir"], $verbose);
        }

        // Use this to generate maps from your schema
        if ($this->config['regenerate_maps']) {
            modxBuilder::deleteMapFiles($this->config["mysql_class_dir"]);
        }

        $this->getGenerator()->parseSchema($this->config["xml_schema_file"], $this->config["model_dir"]);
    }

    /**
     * @param string $dir - a directory containing class files you wish to delete.
     * @param bool $verbose
     */
    public function deleteClassFiles($dir, $verbose = false)
    {
        $all_files = scandir($dir);
        foreach ($all_files as $f) {
            if (preg_match('#\.class\.php$#i', $f)) {
                if (!unlink("$dir/$f")) {
                    $this->modx->log(1, sprintf('Failed to delete file: %s/%s', $dir, $f));
                }
            }
        }
    }

    /**
     * @param string $dir - a directory containing map files you wish to delete.
     * @param bool $verbose
     */
    public function deleteMapFiles($dir)
    {
        $all_files = scandir($dir);
        foreach ($all_files as $f) {
            if (preg_match('#\.map\.inc\.php$#i', $f)) {
                if (!unlink("$dir/$f")) {
                    $this->modx->log(1, sprintf('Failed to delete file: %s/%s', $dir, $f));
                }
            }
        }
    }
}
