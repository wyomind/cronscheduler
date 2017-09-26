<?php

/* *
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Console\Command\Job;

/**
 * wyomind:cronscheduler:job:list command line
 * @version 1.0.0
 * @description <pre>
 * $ bin/magento help wyomind:cronscheduler:job:list
 * Usage:
 * wyomind:cronscheduler:job:list
 *
 * Options:
 * --help (-h)           Display this help message
 * --quiet (-q)          Do not output any message
 * --verbose (-v|vv|vvv) Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
 * --version (-V)        Display this application version
 * --ansi                Force ANSI output
 * --no-ansi             Disable ANSI output
 * --no-interaction (-n) Do not ask any interactive question
 * </pre>
 */
class Listing extends \Symfony\Component\Console\Command\Command
{

    /**
     * @var \Magento\Cron\Model\ConfigInterface
     */
    protected $_cronConfig = null;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_state = null;

    /**
     * Class constructor
     * @param \Magento\Cron\Model\ConfigInterface $cronConfig
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
    \Magento\Cron\Model\ConfigInterface $cronConfig,
            \Magento\Framework\App\State $state
    )
    {
        $this->_state = $state;
        $this->_cronConfig = $cronConfig;
        parent::__construct();
    }

    /**
     * Configure the command line
     */
    protected function configure()
    {
        $this->setName('wyomind:cronscheduler:job:list')
                ->setDescription(__('Cron Scheduler : get list of all jobs'))
                ->setDefinition([]);
        parent::configure();
    }

    /**
     * Execute the command line
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int \Magento\Framework\Console\Cli::RETURN_FAILURE or \Magento\Framework\Console\Cli::RETURN_SUCCESS
     */
    protected function execute(
    \Symfony\Component\Console\Input\InputInterface $input,
            \Symfony\Component\Console\Output\OutputInterface $output
    )
    {


        try {
            $this->_state->setAreaCode('adminhtml');

            $configJobs = $this->_cronConfig->getJobs();


            $data = [];
            $max = [0, 0, 0, 0];

            foreach ($configJobs as $group => $jobs) {
                foreach ($jobs as $code => $job) {
                    $instance = $job['instance'];
                    $method = $job['method'];
                    $schedule = (isset($job['schedule']) ? $job['schedule'] : "");
                    $itemData = [
                        'code' => $code,
                        'instance' => $instance . "::" . $method,
                        'schedule' => $schedule,
                        'group' => $group,
                    ];
                    $max = [
                        max(strlen($itemData['code']), $max[0]),
                        max(strlen($itemData['group']), $max[1]),
                        max(strlen($itemData['instance']), $max[2]),
                        max(strlen($itemData['schedule']), $max[3]),
                    ];
                    $data[] = $itemData;
                }
            }

            sort($data);

            $output->writeln("");

            $row = sprintf(" %-" . $max[0] . "s | %-" . $max[1] . "s | %-" . $max[2] . "s | %-" . $max[3] . "s ", __("Code"), __("Group"), __("Method"), __("Schedule"));
            $output->writeln($row);
            $separator = sprintf("-%'-" . $max[0] . "s-+-%'-" . $max[1] . "s-+-%'-" . $max[2] . "s-+-%'-" . $max[3] . "s", "", "", "", "");
            $output->writeln($separator);

            $counter = 0;
            $count = count($data);
            foreach ($data as $item) {
                $counter++;
                $row = sprintf(" %-" . $max[0] . "s | %-" . $max[1] . "s | %-" . $max[2] . "s | %-" . $max[3] . "s ", $item['code'], $item['group'], $item['instance'], $item['schedule']);
                $output->writeln($row);
                if ($count !== $counter) {
                    $output->writeln($separator);
                }
            }
            $returnValue = \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $output->writeln($e->getMessage());
            $returnValue = \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }


        return $returnValue;
    }

}
