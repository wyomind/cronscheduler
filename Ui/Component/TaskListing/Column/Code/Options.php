<?php

/**
 * Copyright © 2017 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Ui\Component\TaskListing\Column\Code;

/**
 * Define the options available for the column "code" in the tasks listing
 * @version 1.0.0
 */
class Options implements \Magento\Framework\Data\OptionSourceInterface
{

    /**
     * @var array
     */
    protected $options = null;

    /**
     * @var \Wyomind\CronScheduler\Model\ResourceModel\Task\Collection
     */
    public $taskCollection = null;

    public function __construct(
    \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory $taskCollectionFactory)
    {
        $this->taskCollection = $taskCollectionFactory->create();
    }

    /**
     * Get all options available
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        
        if ($this->options === null) {
            $this->options = [];
            $jobCodes = $this->taskCollection->getJobCodes();
            foreach ($jobCodes as $jobCode) {
                $options[] = $jobCode->getJobCode();
            }
            sort($options);
            foreach ($options as $option) {
                $this->options[] = [
                    "label" => $option, "value" => $option
                ];
            }
        }
        return $this->options;
    }

}
