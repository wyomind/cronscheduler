<?php

/* *
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Console\Command\Task;

/**
 * wyomind:cronscheduler:task:list command line
 * @version 1.0.0
 * @description <pre>
 * $ bin/magento help wyomind:cronscheduler:task:list
 * Usage:
 * wyomind:cronscheduler:task:list
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
     * @var \Wyomind\CronScheduler\Model\ResourceModel\Task\Collection
     */
    protected $_taskCollection = null;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_state = null;

    /**
     * Class constructor
     * @param \Wyomind\CronScheduler\Model\ResourceModel\Task\Collection $taskCollection
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
    \Wyomind\CronScheduler\Model\ResourceModel\Task\Collection $taskCollection,
            \Magento\Framework\App\State $state
    )
    {
        $this->_state = $state;
        $this->_taskCollection = $taskCollection->sortByScheduledAtDesc();
        parent::__construct();
    }

    /**
     * Configure the command line
     */
    protected function configure()
    {
        $this->setName('wyomind:cronscheduler:task:list')
                ->setDescription(__('Cron Scheduler : get list of all tasks'))
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

            $data = [];
            $max = [strlen("#"), strlen(__("Code")), strlen(__("Status")), strlen(__("Created at")), strlen(__("Scheduled at")), strlen(__("Executed at")), strlen(__("Finished at"))];

            foreach ($this->_taskCollection as $task) {
                $itemData = [
                    'id' => $task->getScheduleId(),
                    'code' => $task->getJobCode(),
                    'status' => $task->getStatus(),
                    'created_at' => $task->getCreatedAt(),
                    'scheduled_at' => $task->getScheduledAt(),
                    'executed_at' => $task->getExecutedAt(),
                    'finished_at' => $task->getFinishedAt()
                ];

                $max = [
                    max(strlen($itemData['id']), $max[0]),
                    max(strlen($itemData['code']), $max[1]),
                    max(strlen($itemData['status']), $max[2]),
                    max(strlen($itemData['created_at']), $max[3]),
                    max(strlen($itemData['scheduled_at']), $max[4]),
                    max(strlen($itemData['executed_at']), $max[5]),
                    max(strlen($itemData['finished_at']), $max[6]),
                ];
                $data[] = $itemData;
            }

            $output->writeln("");

            $row = sprintf(" %-" . $max[0] . "s | %-" . $max[1] . "s | %-" . $max[2] . "s | %-" . $max[3] . "s | %-" . $max[4] . "s | %-" . $max[5] . "s | %-" . $max[6] . "s ", __("#"), __("Code"), __("Status"), __("Created at"), __("Scheduled at"), __("Executed at"), __("Finished at"));
            $output->writeln($row);
            $separator = sprintf("-%'-" . $max[0] . "s-+-%'-" . $max[1] . "s-+-%'-" . $max[2] . "s-+-%'-" . $max[3] . "s-+-%'-" . $max[4] . "s-+-%'-" . $max[5] . "s-+-%'-" . $max[6] . "s", "", "", "", "", "", "", "");
            $output->writeln($separator);

            $counter = 0;
            $count = count($data);
            foreach ($data as $item) {
                $counter++;
                $row = sprintf(" %-" . $max[0] . "s | %-" . $max[1] . "s | %-" . $max[2] . "s | %-" . $max[3] . "s | %-" . $max[4] . "s | %-" . $max[5] . "s | %-" . $max[6] . "s ", $item['id'], $item['code'], $item['status'], $item['created_at'], $item['scheduled_at'], $item['executed_at'], $item['finished_at']);
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
