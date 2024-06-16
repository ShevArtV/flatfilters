<?php

interface IndexingInterface{
    public function indexConfig();
    public function indexResource(array $resourceData): void;

    public function getResourceData(object $resource): array;
}
