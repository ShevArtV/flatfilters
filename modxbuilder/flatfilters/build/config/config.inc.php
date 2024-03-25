<?php
// /usr/local/php/php-7.4/bin/php -d display_errors -d error_reporting=E_ALL art-sites.ru/htdocs/flatfilters/modxbuilder/flatfilters/build/build.schema.php
// /usr/local/php/php-7.4/bin/php -d display_errors -d error_reporting=E_ALL art-sites.ru/htdocs/flatfilters/modxbuilder/flatfilters/build/build.models.php
// /usr/local/php/php-7.4/bin/php -d display_errors -d error_reporting=E_ALL art-sites.ru/htdocs/flatfilters/modxbuilder/flatfilters/build/build.package.php
//If you need to update only builder component files - set COMPONENT_BUILD to true.
define("COMPONENT_BUILD", true);

$root = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/';
$builderRoot = $root . "modxbuilder/";
$modxRoot = $root;

$buildConfig = array(
    //Name for displaying and category name
    "real_package_name" => "FlatFilters",
    //name for folder
    "package_name" => "flatfilters",
    "package_version" => "1.0.0",
    "package_release" => "rc4",
    "package_table_prefix" => "ff_",
    "package_class_prefix" => "ff",

    "regenerate_schema" => true,
    //switch to false if you don't need to rewrite your class-files
    "regenerate_classes" => true,
    //switch to false if you don't need to rewrite your map.inc-files
    "regenerate_maps" => true,

    "modx_root" => $modxRoot,
    "builder_root" => $builderRoot,
    "tools_root" => $builderRoot . "tools/",
);

$builderComponentRoot = $buildConfig["builder_root"] . $buildConfig['package_name'] . '/';

$sources = array();

if (COMPONENT_BUILD)
{
    $buildConfig = array_merge($buildConfig, array(
        "root" => $root,
        "build" => $builderComponentRoot . "build/",
        "resolvers" => $builderComponentRoot . "build/resolvers/",
        "data" => $builderComponentRoot . "build/data/",

        "source_core" => $modxRoot . "core/components/{$buildConfig['package_name']}/",
        "source_lexicon" => $modxRoot . "core/components/{$buildConfig['package_name']}/lexicon/",
        "source_assets" => $modxRoot . "assets/components/{$buildConfig['package_name']}/",
        "source_docs" => $modxRoot . "core/components/{$buildConfig['package_name']}/docs/",

        "package_dir" => $builderComponentRoot . "core/components/{$buildConfig['package_name']}",
        "model_dir" => $builderComponentRoot . "core/components/{$buildConfig['package_name']}/model",
        "class_dir" => $builderComponentRoot . "core/components/{$buildConfig['package_name']}/model/{$buildConfig['package_name']}",
        "schema_dir" => $builderComponentRoot . "core/components/{$buildConfig['package_name']}/model/schema",
        "mysql_class_dir" => $builderComponentRoot . "core/components/{$buildConfig['package_name']}/model/{$buildConfig['package_name']}/mysql",

        //It's a main file we edit
        "xml_schema_file" => $builderComponentRoot . "core/components/{$buildConfig['package_name']}/model/schema/{$buildConfig['package_name']}.mysql.schema.xml",

        //It's a new file generated automatically. We will transfer new code to file above
        "new_xml_schema_file" => $builderComponentRoot . "core/components/{$buildConfig['package_name']}/model/schema/{$buildConfig['package_name']}.mysql.schema.new.xml"
    ));
}
else
{
    $buildConfig = array_merge($buildConfig, array(
        "root" => $root,
        "build" => $builderComponentRoot . "build/",
        "resolvers" => $builderComponentRoot . "build/resolvers/",
        "data" => $builderComponentRoot . "build/data/",

        "source_core" => $modxRoot . "core/components/{$buildConfig['package_name']}/",
        "source_lexicon" => $modxRoot . "core/components/{$buildConfig['package_name']}/lexicon/",
        "source_assets" => $modxRoot . "assets/components/{$buildConfig['package_name']}/",
        "source_docs" => $modxRoot . "core/components/{$buildConfig['package_name']}/docs/",

        "package_dir" => $root . "core/components/{$buildConfig['package_name']}",
        "model_dir" => $root . "core/components/{$buildConfig['package_name']}/model",
        "class_dir" => $root . "core/components/{$buildConfig['package_name']}/model/{$buildConfig['package_name']}",
        "schema_dir" => $root . "core/components/{$buildConfig['package_name']}/model/schema",
        "mysql_class_dir" => $root . "core/components/{$buildConfig['package_name']}/model/{$buildConfig['package_name']}/mysql",

        //It's a main file we edit
        "xml_schema_file" => $root . "core/components/{$buildConfig['package_name']}/model/schema/{$buildConfig['package_name']}.mysql.schema.xml",

        //It's a new file generated automatically. We will transfer new code to file above
        "new_xml_schema_file" => $root . "core/components/{$buildConfig['package_name']}/model/schema/{$buildConfig['package_name']}.mysql.schema.xml"
    ));
}

//Объявляем базовые константы
define("MODX_CORE_PATH", $modxRoot . "core/");
define("MODX_BASE_PATH", $modxRoot);
define('MODX_BASE_URL', '/');

unset($root,$modxRoot,$builderRoot,$builderComponentRoot);

return $buildConfig;
