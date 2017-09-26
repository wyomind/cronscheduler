<?php

/**
 * Copyright © 2017 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Ui\Component\JobListing\Column\Code;

/**
 * Define the options available for the column "code" in the jobs listing
 * @version 1.0.0
 */
class Options implements \Magento\Framework\Data\OptionSourceInterface
{

    /**
     * @var array 
     */
    protected $options = null;

    /**
     * @var \Magento\Cron\Model\ConfigInterface 
     */
    public $cronConfig = null;

    /**
     * Class constructor
     * @param \Magento\Cron\Model\ConfigInterface $cronConfig
     */
    public function __construct(
    \Magento\Cron\Model\ConfigInterface $cronConfig
    )
    {
        $this->cronConfig = $cronConfig;
    }

    /**
     * Get all options available
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        
        if ($this->options === null) {
            $configJobs = $this->cronConfig->getJobs();
            foreach (array_values($configJobs) as $jobs) {
                foreach (array_keys($jobs) as $code) {
                    $options[] = $code;
                }
            }
        }

        sort($options);
        foreach ($options as $option) {
            $this->options[] = [
                "label" => $option, "value" => $option
            ];
        }
        return $this->options;
    }

}
