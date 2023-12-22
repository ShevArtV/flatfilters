<?php
/**
 * @var modxBuilder $this
 * @var string $categoryName
 * @var string $namespace
 */

$templates = array();

/** @var modCategory $mainCategory */
$mainCategory = $this->modx->getObject('modCategory',array(
    'category' => $categoryName
));

if(!$mainCategory) return $templates;

/** @var modTemplate[] $realTemplates */
$realTemplates = $mainCategory->getMany('Templates');
if(!$realTemplates) return $templates;

foreach($realTemplates as $realTemplate){
    /** @var modTemplate $template */
    $template = $this->modx->newObject('modTemplate');
    $templateData = $realTemplate->toArray();
    $templateData['id'] = 0;
    //TODO remove comment if you want to make templates static
    //$templateData['static'] = 1;
    $template->fromArray($templateData);
    $templates[] = $template;
}

unset($realTemplates,$templateData);

return $templates;