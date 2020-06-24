<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Plugin\Cron\Observer;

/**
 * Magento 2.2.6
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
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * @var array
     */
    private $invalid = [];
    private $jobs = null;

    /**
     * ProcessCronQueueObserver class constructor.
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
     * @param \Magento\Framework\Profiler\Driver\Standard\StatFactory $statFactory
     * @param \Magento\Framework\Lock\LockManagerInterface $lockManager
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
        \Wyomind\CronScheduler\Helper\Task $taskHelper,
        \Magento\Framework\Profiler\Driver\Standard\StatFactory $statFactory,
        \Magento\Framework\Lock\LockManagerInterface $lockManager
    )
    {
        $construct = "__construct"; // in order to bypass the compiler
        parent::$construct($objectManager, $scheduleFactory, $cache, $config, $scopeConfig, $request, $shell, $dateTime, $phpExecutableFinderFactory, $logger, $state,$statFactory,$lockManager);
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
     * @param \Magento\Cron\Observer\ProcessCronQueueObserver $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundExecute(
        \Magento\Cron\Observer\ProcessCronQueueObserver $subject,
        \Closure $proceed,
        \Magento\Framework\Event\Observer $observer)
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
        $currentTime = $this->dateTime->gmtTimestamp();
        $jobGroupsRoot = $this->_config->getJobs();
        $phpPath = $this->phpExecutableFinder->find() ?: 'php';

        foreach ($jobGroupsRoot as $groupId => $jobsRoot) {
            $this->_cleanup($groupId);
            $this->_generate($groupId);

            if ($this->_request->getParam('group') !== null && $this->_request->getParam('group') !== '\'' . ($groupId) . '\'' && $this->_request->getParam('group') !== $groupId) {
                continue;
            }

            if (($this->_request->getParam(self::STANDALONE_PROCESS_STARTED) !== '1')
                && ($this->_scopeConfig->getValue('system/cron/' . $groupId . '/use_separate_process', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1)
            ) {
                $this->_shell->execute(
                    $phpPath . ' %s cron:run --group=' . $groupId . ' --' . \Magento\Framework\Console\Cli::INPUT_KEY_BOOTSTRAP . '='
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
                    if (isset($jobConfig['status']) && $jobConfig['status'] == 0) {
                        $scheduleResource = $this->_scheduleFactory->create()->getResource();
                        $scheduleResource->getConnection()->delete($scheduleResource->getMainTable(), [
                            'job_code=?' => $schedule->getJobCode(),
                        ]);
                    }
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
                    if ($schedule->getStatus() === \Magento\Cron\Model\Schedule::STATUS_MISSED && $this->state->getMode() === \Magento\Framework\App\State::MODE_DEVELOPER
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
     * @inherit
     */
    protected function _getPendingSchedules()
    {
        if (!$this->_pendingSchedules) {
            $this->_pendingSchedules = $this->_scheduleFactory->create()->getCollection()->addFieldToFilter(
                'status',
                \Magento\Cron\Model\Schedule::STATUS_PENDING
            )->load();
        }
        return $this->_pendingSchedules;
    }

    /**
     * @inherit
     */
    protected function _cleanup($groupId)
    {
        $this->cleanupDisabledJobs($groupId);

        // check if history cleanup is needed
        $lastCleanup = (int)$this->_cache->load(self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT . $groupId);
        $historyCleanUp = (int)$this->_scopeConfig->getValue(
            'system/cron/' . $groupId . '/' . self::XML_PATH_HISTORY_CLEANUP_EVERY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($lastCleanup > $this->dateTime->gmtTimestamp() - $historyCleanUp * self::SECONDS_IN_MINUTE) {
            return $this;
        }

        // check how long the record should stay unprocessed before marked as MISSED
        $scheduleLifetime = (int)$this->_scopeConfig->getValue(
            'system/cron/' . $groupId . '/' . self::XML_PATH_SCHEDULE_LIFETIME,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $scheduleLifetime = $scheduleLifetime * self::SECONDS_IN_MINUTE;

        /**
         * @var \Magento\Cron\Model\ResourceModel\Schedule\Collection $history
         */
        $history = $this->_scheduleFactory->create()->getCollection()->addFieldToFilter(
            'status',
            ['in' => [\Magento\Cron\Model\Schedule::STATUS_SUCCESS, \Magento\Cron\Model\Schedule::STATUS_MISSED, \Magento\Cron\Model\Schedule::STATUS_ERROR]]
        )->load();

        $historySuccess = (int)$this->_scopeConfig->getValue(
            'system/cron/' . $groupId . '/' . self::XML_PATH_HISTORY_SUCCESS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $historyFailure = (int)$this->_scopeConfig->getValue(
            'system/cron/' . $groupId . '/' . self::XML_PATH_HISTORY_FAILURE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $historyLifetimes = [
            \Magento\Cron\Model\Schedule::STATUS_SUCCESS => $historySuccess * self::SECONDS_IN_MINUTE,
            \Magento\Cron\Model\Schedule::STATUS_MISSED => $historyFailure * self::SECONDS_IN_MINUTE,
            \Magento\Cron\Model\Schedule::STATUS_ERROR => $historyFailure * self::SECONDS_IN_MINUTE,
        ];

        $now = $this->dateTime->gmtTimestamp();
        /** @var Schedule $record */
        foreach ($history as $record) {
            $checkTime = $record->getExecutedAt() ? strtotime($record->getExecutedAt()) :
                strtotime($record->getScheduledAt()) + $scheduleLifetime;
            if ($checkTime < $now - $historyLifetimes[$record->getStatus()]) {
                $record->delete();
            }
        }

        // save time history cleanup was ran with no expiration
        $this->_cache->save(
            $this->dateTime->gmtTimestamp(),
            self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT . $groupId,
            ['crontab'],
            null
        );

        return $this;
    }

    /**
     * @inherit
     */
    protected function _generate($groupId)
    {
        /**
         * check if schedule generation is needed
         */
        $lastRun = (int)$this->_cache->load(self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT . $groupId);
        $rawSchedulePeriod = (int)$this->_scopeConfig->getValue(
            'system/cron/' . $groupId . '/' . self::XML_PATH_SCHEDULE_GENERATE_EVERY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $schedulePeriod = $rawSchedulePeriod * self::SECONDS_IN_MINUTE;
        if ($lastRun > $this->dateTime->gmtTimestamp() - $schedulePeriod) {
            return $this;
        }

        $schedules = $this->_getPendingSchedules();
        $exists = [];
        /** @var Schedule $schedule */
        foreach ($schedules as $schedule) {
            $exists[$schedule->getJobCode() . '/' . $schedule->getScheduledAt()] = 1;
        }

        /**
         * generate global crontab jobs
         */
        $jobs = $this->getJobs();
        $this->invalid = [];
        $this->_generateJobs($jobs[$groupId], $exists, $groupId);
        $this->cleanupScheduleMismatches();

        /**
         * save time schedules generation was ran with no expiration
         */
        $this->_cache->save(
            $this->dateTime->gmtTimestamp(),
            self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT . $groupId,
            ['crontab'],
            null
        );

        return $this;
    }

    protected function _generateJobs($jobs, $exists, $groupId)
    {
        foreach ($jobs as $jobCode => $jobConfig) {
            if (isset($jobConfig['status']) && $jobConfig['status'] == 0) {
                continue;
            }
            $cronExpression = $this->getCronExpression($jobConfig);
            if (!$cronExpression) {
                continue;
            }

            $timeInterval = $this->getScheduleTimeInterval($groupId);
            $this->saveSchedule($jobCode, $cronExpression, $timeInterval, $exists);
        }
    }

    /**
     * @inherit
     */
    private function cleanupScheduleMismatches()
    {
        foreach ($this->invalid as $jobCode => $scheduledAtList) {
            /** @var \Magento\Cron\Model\ResourceModel\Schedule $scheduleResource */
            $scheduleResource = $this->_scheduleFactory->create()->getResource();
            $scheduleResource->getConnection()->delete($scheduleResource->getMainTable(), [
                'status=?' => \Magento\Cron\Model\Schedule::STATUS_PENDING,
                'job_code=?' => $jobCode,
                'scheduled_at in (?)' => $scheduledAtList,
            ]);
        }
        return $this;
    }

    /**
     * @inherit
     */
    private function getJobs()
    {
        if ($this->jobs === null) {
            $this->jobs = $this->_config->getJobs();
        }
        return $this->jobs;
    }

    /**
     * @inherit
     */
    private function getCronExpression($jobConfig)
    {
        $cronExpression = null;
        if (isset($jobConfig['config_path'])) {
            $cronExpression = $this->getConfigSchedule($jobConfig) ?: null;
        }

        if (!$cronExpression) {
            if (isset($jobConfig['schedule'])) {
                $cronExpression = $jobConfig['schedule'];
            }
        }
        return $cronExpression;
    }

    /**
     * @inherit
     */
    private function cleanupDisabledJobs($groupId)
    {
        $jobs = $this->getJobs();
        foreach ($jobs[$groupId] as $jobCode => $jobConfig) {
            if (!$this->getCronExpression($jobConfig)) {
                /** @var \Magento\Cron\Model\ResourceModel\Schedule $scheduleResource */
                $scheduleResource = $this->_scheduleFactory->create()->getResource();
                $scheduleResource->getConnection()->delete($scheduleResource->getMainTable(), [
                    'status=?' => \Magento\Cron\Model\Schedule::STATUS_PENDING,
                    'job_code=?' => $jobCode,
                ]);
            }
        }
    }
}