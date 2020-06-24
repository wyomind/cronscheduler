<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
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
     * @var \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory
     */
    protected $_taskCollectionFactory = null;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_state = null;

    /**
     * Class constructor
     * @param \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory $taskCollectionFactory
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory $taskCollectionFactory,
        \Magento\Framework\App\State $state
    )
    {
        $this->_state = $state;
        $this->_taskCollectionFactory = $taskCollectionFactory;
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
            try {
                $this->_state->setAreaCode('adminhtml');
            } catch (\Exception $e) {

            }
            $collection = $this->_taskCollectionFactory->create();
            $collection->sortByScheduledAtDesc();

            $table = new \Symfony\Component\Console\Helper\Table($output);
            $table->setHeaders(['Id', 'Code', 'Status', 'Created at', 'Schedule at', 'Executed at', 'Finished at']);
            foreach ($collection as $task) {

                $itemData = [
                    $task->getScheduleId(),
                    $task->getJobCode(),
                    $task->getStatus(),
                    $task->getCreatedAt(),
                    $task->getScheduledAt(),
                    $task->getExecutedAt(),
                    $task->getFinishedAt()
                ];
                $table->addRow($itemData);
            }

            $table->render($output);

            $returnValue = \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $output->writeln($e->getMessage());
            $returnValue = \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        return $returnValue;
    }
}