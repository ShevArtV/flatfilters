<?php

require_once 'filteringresources.class.php';

class FilteringCustomers extends FilteringResources
{
    protected string $resourcesProp = 'users';

    protected function getOutputSQL(string $rids): string
    {
        $userTableName = $this->modx->getTableName('modUser');
        $profileTableName = $this->modx->getTableName('modUserProfile');
        return "SELECT `User`.`id` FROM $userTableName User JOIN $profileTableName Profile ON User.id = Profile.internalKey WHERE `User`.`id` IN ($rids)";
    }

}
