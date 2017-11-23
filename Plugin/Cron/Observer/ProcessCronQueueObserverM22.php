<?php

/* *
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Plugin\Cron\Observer;

class ProcessCronQueueObserver extends \Magento\Cron\Observer\ProcessCronQueueObserver
{

    /**
     * @var \Magento\Framework\Event\Manager
     */
    protected $_eventManager = null;

    /**
     * @var array
     */
    protected $_jobStatus = [];

    /**
     * @var \Wyomind\CronScheduler\Helper\Task
     */
    protected $_taskHelper = null;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * 
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Cron\Model\ScheduleFactory $scheduleFactory
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\Cron\Model\ConfigInterface $config
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\App\Console\Request $request
     * @param \Magento\Framework\ShellInterface $shell
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Magento\Framework\Process\PhpExecutableFinderFactory $phpExecutableFinderFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\Event\Manager $eventManager
     * @param \Wyomind\CronScheduler\Helper\Task $taskHelper
     */
    public function __construct(
    \Magento\Framework\ObjectManagerInterface $objectManager,
            \Magento\Cron\Model\ScheduleFactory $scheduleFactory,
            \Magento\Framework\App\CacheInterface $cache,
            \Magento\Cron\Model\ConfigInterface $config,
            \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
            \Magento\Framework\App\Console\Request $request,
            \Magento\Framework\ShellInterface $shell,
            \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
            \Magento\Framework\Process\PhpExecutableFinderFactory $phpExecutableFinderFactory,
            \Psr\Log\LoggerInterface $logger,
            \Magento\Framework\App\State $state,
            \Magento\Framework\Event\Manager $eventManager,
            \Wyomind\CronScheduler\Helper\Task $taskHelper
    )
    {       
        $construct= "__construct"; // in order to bypass the compiler
        parent::$construct($objectManager, $scheduleFactory, $cache, $config, $scopeConfig, $request, $shell, $dateTime, $phpExecutableFinderFactory, $logger, $state);
        $this->logger = $logger; // because private
        $this->state = $state; // because private
        $this->_eventManager = $eventManager;
        $this->_taskHelper = $taskHelper;
        $jobGroupsRoot = $this->_config->getJobs();
        $groups = array_values($jobGroupsRoot);
        foreach (array_values($groups) as $jobs) {
            foreach ($jobs as $job) {
                if (isset($job['code'])) {
                    $this->_jobStatus[$job['code']] = isset($job['status']) ? $job['status'] : 1;
                } elseif (isset($job['name'])) {
                    $this->_jobStatus[$job['name']] = isset($job['status']) ? $job['status'] : 1;
                }
            }
        }
    }

    /**
     * Override the observer on cron:run
     * @param \Magento\Cron\Observer\ProcessCronQueueObserver $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Event\Observer $observer
     * @event <i>cronscheduler_task_failed(\Magento\Cron\Model\Scheduler $task, array $error)</i> when a task fails
     * @event <i>cronscheduler_task_succes(\Magento\Cron\Model\Scheduler $task)</i> when a task is successful
     * @event <i>cronscheduler_task_run_before(\Magento\Cron\Model\Scheduler $task)</i> before running a task
     * @event <i>cronscheduler_task_run when(\Magento\Cron\Model\Scheduler $task)</i> running a task
     * @event <i>cronscheduler_task_run_after(\Magento\Cron\Model\Scheduler $task)</i> after running a task
     */
    public function aroundExecute(
    \Magento\Cron\Observer\ProcessCronQueueObserver $subject,
            \Closure $proceed,
            \Magento\Framework\Event\Observer $observer)
    {

        // <CRONSCHEDULER>
        // current task ran
        $currentShedule = null;

        // set the shutdown/error_hnalder functions to catch a task that throws a fatal error (like parsing error)
        register_shutdown_function(function() use (&$currentSchedule) {
            $lastError = error_get_last();
            if ($lastError) {
                if ($currentSchedule != null) {
                    $s = $currentSchedule;
                    $s->setMessages($lastError['message']);
                    $s->setStatus(\Magento\Cron\Model\Schedule::STATUS_ERROR);
                    $s->setErrorFile($lastError['file']);
                    $s->setErrorLine($lastError['line']);
                    $this->_taskHelper->setTrace($s);
                    $s->save();
                    $this->_eventManager->dispatch('cronscheduler_task_failed', ['task' => $s]);
                }
            }
        });
        set_error_handler(function($errorLevel,
                $errorMessage,
                $errorFile,
                $errorLine,
                $errorContext) use (&$currentSchedule) {

            if ($errorLevel != "" && $currentSchedule != null) {
                $s = $currentSchedule;
                $s->setMessages($errorMessage);
                $s->setStatus(\Magento\Cron\Model\Schedule::STATUS_ERROR);
                $s->setErrorFile($errorFile);
                $s->setErrorLine($errorLine);
                $this->_taskHelper->setTrace($s);
                $s->save();
                $this->_eventManager->dispatch('cronscheduler_task_failed', ['task' => $s]);
            }
        });
        // </CRONSCHEDULER>


        $pendingJobs = $this->_getPendingSchedules();
        $currentTime = $this->dateTime->gmtTimestamp();
        $jobGroupsRoot = $this->_config->getJobs();


        $phpPath = $this->phpExecutableFinder->find() ? : 'php';

        foreach ($jobGroupsRoot as $groupId => $jobsRoot) {

            $this->_cleanup($groupId);
            $this->_generate($groupId);

            if ($this->_request->getParam('group') !== null && $this->_request->getParam('group') !== '\'' . ($groupId) . '\'' && $this->_request->getParam('group') !== $groupId) {
                continue;
            }
            if (($this->_request->getParam(self::STANDALONE_PROCESS_STARTED) !== '1') && (
                    $this->_scopeConfig->getValue(
                            'system/cron/' . $groupId . '/use_separate_process', \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ) == 1
                    )) {

                $this->_shell->execute(
                        $phpPath . ' %s cron:run --group=' . $groupId . ' --' . \Magento\Framework\Console\Cli::INPUT_KEY_BOOTSTRAP . '='
                        . self::STANDALONE_PROCESS_STARTED . '=1', [
                    BP . '/bin/magento'
                        ]
                );
                continue;
            }


            foreach ($pendingJobs as $schedule) {

                // <CRONSCHEDULER>
                // set the current task running
                $currentSchedule = $schedule;
                $this->_eventManager->dispatch('cronscheduler_task_run_before', ['task' => $schedule]);
                // </CRONSCHEDULER>

                $jobConfig = isset($jobsRoot[$schedule->getJobCode()]) ? $jobsRoot[$schedule->getJobCode()] : null;
                if (!$jobConfig /* <CRONSCHEDULER> */ || isset($jobConfig['status']) && $jobConfig['status'] == 0 /* </CRONSCHEDULER> */) {
                    continue;
                }

                $scheduledTime = strtotime($schedule->getScheduledAt());
                if ($scheduledTime > $currentTime) {
                    continue;
                }

                try {
                    if ($schedule->tryLockJob()) {

                        // <CRONSCHEDULER>
                        $this->_eventManager->dispatch('cronscheduler_task_run', ['task' => $schedule]);
                        // </CRONSCHEDULER>

                        $this->_runJob($scheduledTime, $currentTime, $jobConfig, $schedule, $groupId);
                    }
                    // <CRONSCHEDULER>
                    $this->_taskHelper->setTrace($schedule);
                    $schedule->save();
                    $this->_eventManager->dispatch('cronscheduler_task_success', ['task' => $schedule]);
                    // </CRONSCHEDULER>
                } catch (\Exception $e) {
                    $schedule->setMessages($e->getMessage());


                    // <CRONSCHEDULER>
                    $schedule->setErrorFile($e->getFile());
                    $schedule->setErrorLine($e->getLine());
                    $this->_taskHelper->setTrace($schedule);
                    $schedule->setStatus(\Magento\Cron\Model\Schedule::STATUS_ERROR);
                    $schedule->save();
                    // </CRONSCHEDULER>

                    if ($schedule->getStatus() === \Magento\Cron\Model\Schedule::STATUS_ERROR) {
                        $this->logger->critical($e);
                    }
                    if ($schedule->getStatus() === \Magento\Cron\Model\Schedule::STATUS_MISSED && $this->state->getMode() === \Magento\Cron\Model\Schedule::MODE_DEVELOPER
                    ) {
                        $this->logger->info(
                                sprintf(
                                        "%s Schedule Id: %s Job Code: %s", $schedule->getMessages(), $schedule->getScheduleId(), $schedule->getJobCode()
                                )
                        );
                    }

                    // <CRONSCHEDULER>
                    $this->_eventManager->dispatch('cronscheduler_task_failed', ['task' => $schedule]);
                    // </CRONSCHEDULER>
                }

                // <CRONSCHEDULER>
                $this->_eventManager->dispatch('cronscheduler_task_run_after', ['task' => $schedule]);
                // </CRONSCHEDULER>
            }

            $this->_generate($groupId);
            $this->_cleanup($groupId);
        }
    }

    /**
     * Save a schedule only if the job is enable (pro version => cannot plugin it because of protected modifier)
     * @param string $jobCode
     * @param string $cronExpression
     * @param int $timeInterval
     * @param array $exists
     * @return void
     */
    protected function saveSchedule($jobCode,
            $cronExpression,
            $timeInterval,
            $exists)
    {
        if (isset($this->_jobStatus[$jobCode]) && $this->_jobStatus[$jobCode]) {
            parent::saveSchedule($jobCode, $cronExpression, $timeInterval, $exists);
        }
    }

}
