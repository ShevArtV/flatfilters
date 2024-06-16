<?php
interface FilteringInterface{
    public function run();
    public function getCurrentFiltersValues();
    public function getAllFiltersValues();
}
