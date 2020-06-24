<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Console\Command\Task;

/**
 * wyomind:cronscheduler:show command line
 * @version 1.0.0
 * @description <pre>
 * $ bin/magento help wyomind:cronscheduler:show
 * Usage:
 * wyomind:cronscheduler:task:show id
 *
 * Arguments:
 * task_id               The id of the task
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
class Show extends \Symfony\Component\Console\Command\Command
{
    /**
     * Command line argument name
     */
    const TASK_ID_ARG = "task_id";

    /**
     * @var \Magento\Cron\Model\ScheduleFactory
     */
    protected $_taskModelFactory = null;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_state = null;

    /**
     * Class constructor
     * @param \Magento\Cron\Model\ScheduleFactory $taskModelFactory
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        \Magento\Cron\Model\ScheduleFactory $taskModelFactory,
        \Magento\Framework\App\State $state
    )
    {
        $this->_state = $state;
        $this->_taskModelFactory = $taskModelFactory;
        parent::__construct();
    }

    /**
     * Configure the command line
     */
    protected function configure()
    {
        $this->setName('wyomind:cronscheduler:task:show')
                ->setDescription(__('Cron Scheduler : get details of a task'))
                ->setDefinition([
                    new \Symfony\Component\Console\Input\InputArgument(
                            self::TASK_ID_ARG, \Symfony\Component\Console\Input\InputArgument::REQUIRED, __('The id of the task')
                    )
        ]);
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
            $taskId = $input->getArgument(self::TASK_ID_ARG);
            $task = $this->_taskModelFactory->create()->load($taskId);

            $table = new \Symfony\Component\Console\Helper\Table($output);
            $table->setHeaders(['Id', 'Code', 'Status', 'Created at', 'Schedule at', 'Executed at', 'Finished at', 'Messages']);

            $itemData = [
                $task->getScheduleId(),
                $task->getJobCode(),
                $task->getStatus(),
                $task->getCreatedAt(),
                $task->getScheduledAt(),
                $task->getExecutedAt(),
                $task->getFinishedAt(),
                $task->getMessages()
            ];
            $table->addRow($itemData);

            $table->render($output);
            
            $returnValue = \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $output->writeln($e->getMessage());
            $returnValue = \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        return $returnValue;
    }
}