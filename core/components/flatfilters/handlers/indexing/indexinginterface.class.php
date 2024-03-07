<?php

interface IndexingInterface{
    public function indexConfig();
    public function indexResource(array $resourceData);
}