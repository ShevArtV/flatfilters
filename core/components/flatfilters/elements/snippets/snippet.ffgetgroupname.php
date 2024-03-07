<?php
if(!$group = $modx->getObject('modUserGroup', $input)) return '';
return $group->get('name');