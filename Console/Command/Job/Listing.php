<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
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
    protected $_cronConfigFactory = null;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_state = null;

    /**
     * Class constructor
     * @param \Magento\Cron\Model\ConfigFactory $cronConfigFactory
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        \Magento\Cron\Model\ConfigFactory $cronConfigFactory,
        \Magento\Framework\App\State $state
    )
    {
        $this->_state = $state;
        $this->_cronConfigFactory = $cronConfigFactory;
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

            $configJobs = $this->_cronConfigFactory->create()->getJobs();

            $table = new \Symfony\Component\Console\Helper\Table($output);
            $table->setHeaders(['Code', 'Instance', 'Schedule', 'Group']);

            foreach ($configJobs as $group => $jobs) {
                foreach ($jobs as $code => $job) {
                    $instance = $job['instance'];
                    $method = $job['method'];
                    $schedule = (isset($job['schedule']) ? $job['schedule'] : "");
                    $itemData = [
                        $code,
                        $instance . "::" . $method,
                        $schedule,
                        $group,
                    ];
                    $table->addRow($itemData);
                }
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