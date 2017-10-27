<?php

/**
 * Copyright © 2017 Magento. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Ui\DataProvider;


/**
 * Task provider for the tasks listing
 * @version 1.0.0
 */
class TaskProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{

    /**
     * @var \Wyomind\CronScheduler\Model\ResourceModel\Task\Collection
     */
    protected $collection;

    /**
     * Class constructor
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

}
