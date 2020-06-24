<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Plugin\Cron\Observer;

/**
 * Magento 2.0.x
 */
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
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Cron\Model\ScheduleFactory $scheduleFactory
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\Cron\Model\ConfigInterface $config
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\App\Console\Request $request
     * @param \Magento\Framework\ShellInterface $shell
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
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
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Framework\Event\Manager $eventManager,
        \Wyomind\CronScheduler\Helper\Task $taskHelper
    )
    {
        $construct= "__construct"; // in order to bypass the compiler
        parent::$construct($objectManager, $scheduleFactory, $cache, $config, $scopeConfig, $request, $shell, $timezone);
        
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
     * @param \Magento\Cron\Observer\ProcessCronQueueObserver $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundExecute(
        \Magento\Cron\Observer\ProcessCronQueueObserver $subject,
        \Closure $proceed,
        \Magento\Framework\Event\Observer $observer
    )
    {
        // <CRONSCHEDULER>
        // current task ran
        $currentSchedule = null;

        // set the shutdown/error_handler functions to catch a task that throws a fatal error (like parsing error)
        register_shutdown_function(function() use (&$currentSchedule) {
            $lastError = error_get_last();
            if ($lastError && strpos($lastError['message'],'mcrypt') === false && strpos($lastError['message'],'mdecrypt') === false) {
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

            if ($errorLevel != "" && $currentSchedule != null && strpos($errorMessage,'mcrypt') === false && strpos($errorMessage,'mdecrypt') === false) {
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
        $currentTime = $this->timezone->scopeTimeStamp();
        $jobGroupsRoot = $this->_config->getJobs();

        $phpExecutable = (new \Symfony\Component\Process\PhpExecutableFinder())->find() ? : 'php';

        foreach ($jobGroupsRoot as $groupId => $jobsRoot) {
            if ($this->_request->getParam('group') !== null && $this->_request->getParam('group') !== '\'' . ($groupId) . '\'' && $this->_request->getParam('group') !== $groupId) {
                continue;
            }

            if (($this->_request->getParam(self::STANDALONE_PROCESS_STARTED) !== '1')
                && ($this->_scopeConfig->getValue('system/cron/' . $groupId . '/use_separate_process', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1)
            ) {
                $this->_shell->execute(
                    $phpExecutable.' %s cron:run --group=' . $groupId . ' --' . \Magento\Framework\Console\Cli::INPUT_KEY_BOOTSTRAP . '='
                    . self::STANDALONE_PROCESS_STARTED . '=1', [BP . '/bin/magento']
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
                    // </CRONSCHEDULER>
                    $schedule->save();
                    // <CRONSCHEDULER>
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
    protected function saveSchedule($jobCode, $cronExpression, $timeInterval, $exists)
    {
        if (isset($this->_jobStatus[$jobCode]) && $this->_jobStatus[$jobCode]) {
            parent::saveSchedule($jobCode, $cronExpression, $timeInterval, $exists);
        }
    }
}