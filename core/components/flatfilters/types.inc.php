<?php

return [
    'resources' => [
        'indexing' => [
            'path' => 'components/flatfilters/handlers/indexing/indexingresources.class.php',
            'className' => 'IndexingResources'
        ],
        'filtering' => [
            'path' => 'components/flatfilters/handlers/filtering/filteringresources.class.php',
            'className' => 'FilteringResources'
        ]
    ],
    'products' => [
        'indexing' => [
            'path' => 'components/flatfilters/handlers/indexing/indexingproducts.class.php',
            'className' => 'IndexingProducts'
        ],
        'filtering' => [
            'path' => 'components/flatfilters/handlers/filtering/filteringproducts.class.php',
            'className' => 'FilteringProducts'
        ]
    ],
    'customers' => [
        'indexing' => [
            'path' => 'components/flatfilters/handlers/indexing/indexingcustomers.class.php',
            'className' => 'IndexingCustomers'
        ],
        'filtering' => [
            'path' => 'components/flatfilters/handlers/filtering/filteringcustomers.class.php',
            'className' => 'FilteringCustomers'
        ]
    ]
];