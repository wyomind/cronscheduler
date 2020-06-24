<?php

namespace Wyomind\CronScheduler\Plugin\Cron\Observer;

/**
 * Magento 2.2.7
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
    /**
     * @var null
     */
    private $jobs = null;


    /**
     * @var \Magento\Framework\Lock\LockManagerInterface
     */
    private $lockManager;

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
        parent::$construct($objectManager, $scheduleFactory, $cache, $config, $scopeConfig, $request, $shell, $dateTime, $phpExecutableFinderFactory, $logger, $state, $statFactory, $lockManager);
        $this->logger = $logger; // because private
        $this->state = $state; // because private
        $this->lockManager = $lockManager; //because private
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
        register_shutdown_function(function () use (&$currentSchedule) {
            $lastError = error_get_last();
            if ($lastError && strpos($lastError['message'], 'mcrypt') === false && strpos($lastError['message'], 'mdecrypt') === false) {
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
        set_error_handler(function ($errorLevel,
                                    $errorMessage,
                                    $errorFile,
                                    $errorLine,
                                    $errorContext) use (&$currentSchedule) {

            if ($errorLevel != "" && $currentSchedule != null && strpos($errorMessage, 'mcrypt') === false && strpos($errorMessage, 'mdecrypt') === false) {
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


        $currentTime = $this->dateTime->gmtTimestamp();
        $jobGroupsRoot = $this->_config->getJobs();
        // sort jobs groups to start from used in separated process
        uksort(
            $jobGroupsRoot,
            function ($a, $b) {
                return $this->getCronGroupConfigurationValue($b, 'use_separate_process')
                    - $this->getCronGroupConfigurationValue($a, 'use_separate_process');
            }
        );

        $phpPath = $this->phpExecutableFinder->find() ?: 'php';

        foreach ($jobGroupsRoot as $groupId => $jobsRoot) {
            if (!$this->isGroupInFilter($groupId)) {
                continue;
            }
            if ($this->_request->getParam(self::STANDALONE_PROCESS_STARTED) !== '1'
                && $this->getCronGroupConfigurationValue($groupId, 'use_separate_process') == 1
            ) {
                $this->_shell->execute(
                    $phpPath . ' %s cron:run --group=' . $groupId . ' --' . \Magento\Framework\Console\Cli::INPUT_KEY_BOOTSTRAP . '='
                    . self::STANDALONE_PROCESS_STARTED . '=1',
                    [
                        BP . '/bin/magento'
                    ]
                );
                continue;
            }

            $this->lockGroup(
                $groupId,
                function ($groupId) use ($currentTime, $jobsRoot) {
                    $this->cleanupJobs($groupId, $currentTime);
                    $this->generateSchedules($groupId);
                    $this->processPendingJobs($groupId, $jobsRoot, $currentTime);
                }
            );
        }

    }

    /**
     * @param string $groupId
     * @param array $jobsRoot
     * @param int $currentTime
     * @throws \Exception
     */
    private function processPendingJobs($groupId, $jobsRoot, $currentTime)
    {
        $jobGroupsRoot = $this->_config->getJobs();
        $procesedJobs = [];
        $pendingJobs = $this->getPendingSchedules($groupId);
        /** @var \Magento\Cron\Model\Schedule $schedule */
        foreach ($pendingJobs as $schedule) {
            // <CRONSCHEDULER>
            // set the current task running
            $currentSchedule = $schedule;
            $this->_eventManager->dispatch('cronscheduler_task_run_before', ['task' => $schedule]);
            // </CRONSCHEDULER>

            if (isset($procesedJobs[$schedule->getJobCode()])) {
                // process only on job per run
                continue;
            }
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
                // <CRONSCHEDULER>
                $schedule->setErrorFile($e->getFile());
                $schedule->setErrorLine($e->getLine());
                $this->_taskHelper->setTrace($schedule);
                $schedule->setStatus(\Magento\Cron\Model\Schedule::STATUS_ERROR);
                $schedule->save();
                // </CRONSCHEDULER>
                $this->processError($schedule, $e);
                // <CRONSCHEDULER>
                $this->_eventManager->dispatch('cronscheduler_task_failed', ['task' => $schedule]);
                // </CRONSCHEDULER>
            }
            // <CRONSCHEDULER>
            $this->_eventManager->dispatch('cronscheduler_task_run_after', ['task' => $schedule]);
            // </CRONSCHEDULER>
            if ($schedule->getStatus() === \Magento\Cron\Model\Schedule::STATUS_SUCCESS) {
                $procesedJobs[$schedule->getJobCode()] = true;
            }
            $schedule->save();
        }
    }

    /**
     * @param \Magento\Cron\Model\Schedule $schedule
     * @param \Exception $exception
     */
    private function processError(\Magento\Cron\Model\Schedule $schedule, \Exception $exception)
    {
        $schedule->setMessages($exception->getMessage());
        if ($schedule->getStatus() === \Magento\Cron\Model\Schedule::STATUS_ERROR) {
            $this->logger->critical($exception);
        }
        if ($schedule->getStatus() === \Magento\Cron\Model\Schedule::STATUS_MISSED
            && $this->state->getMode() === \Magento\Framework\App\State::MODE_DEVELOPER
        ) {
            $this->logger->info($schedule->getMessages());
        }
    }

    /**
     * @param string $groupId
     * @return \Magento\Cron\Model\ResourceModel\Schedule\Collection|\Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    private function getPendingSchedules($groupId)
    {
        $jobs = $this->_config->getJobs();
        $pendingJobs = $this->_scheduleFactory->create()->getCollection();
        $pendingJobs->addFieldToFilter('status', \Magento\Cron\Model\Schedule::STATUS_PENDING);
        $pendingJobs->addFieldToFilter('job_code', ['in' => array_keys($jobs[$groupId])]);
        return $pendingJobs;
    }

    /**
     * @param string $groupId
     * @param int $currentTime
     * @return $this|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function cleanupJobs($groupId, $currentTime)
    {
        // check if history cleanup is needed
        $lastCleanup = (int)$this->_cache->load(self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT . $groupId);
        $historyCleanUp = (int)$this->getCronGroupConfigurationValue($groupId, self::XML_PATH_HISTORY_CLEANUP_EVERY);
        if ($lastCleanup > $this->dateTime->gmtTimestamp() - $historyCleanUp * self::SECONDS_IN_MINUTE) {
            return $this;
        }
        // save time history cleanup was ran with no expiration
        $this->_cache->save(
            $this->dateTime->gmtTimestamp(),
            self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT . $groupId,
            ['crontab'],
            null
        );

        $this->cleanupDisabledJobs($groupId);

        $historySuccess = (int)$this->getCronGroupConfigurationValue($groupId, self::XML_PATH_HISTORY_SUCCESS);
        $historyFailure = (int)$this->getCronGroupConfigurationValue($groupId, self::XML_PATH_HISTORY_FAILURE);
        $historyLifetimes = [
            \Magento\Cron\Model\Schedule::STATUS_SUCCESS => $historySuccess * self::SECONDS_IN_MINUTE,
            \Magento\Cron\Model\Schedule::STATUS_MISSED => $historyFailure * self::SECONDS_IN_MINUTE,
            \Magento\Cron\Model\Schedule::STATUS_ERROR => $historyFailure * self::SECONDS_IN_MINUTE,
            \Magento\Cron\Model\Schedule::STATUS_PENDING => max($historyFailure, $historySuccess) * self::SECONDS_IN_MINUTE,
        ];

        $jobs = $this->_config->getJobs()[$groupId];
        $scheduleResource = $this->_scheduleFactory->create()->getResource();
        $connection = $scheduleResource->getConnection();
        $count = 0;
        foreach ($historyLifetimes as $status => $time) {
            $count += $connection->delete(
                $scheduleResource->getMainTable(),
                [
                    'status = ?' => $status,
                    'job_code in (?)' => array_keys($jobs),
                    'created_at < ?' => $connection->formatDate($currentTime - $time)
                ]
            );
        }

        if ($count) {
            $this->logger->info(sprintf('%d cron jobs were cleaned', $count));
        }
    }

    /**
     * @param string $groupId
     * @return $this|\Magento\Cron\Observer\ProcessCronQueueObserver
     */
    private function generateSchedules($groupId)
    {
        /**
         * check if schedule generation is needed
         */
        $lastRun = (int)$this->_cache->load(self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT . $groupId);
        $rawSchedulePeriod = (int)$this->getCronGroupConfigurationValue(
            $groupId,
            self::XML_PATH_SCHEDULE_GENERATE_EVERY
        );
        $schedulePeriod = $rawSchedulePeriod * self::SECONDS_IN_MINUTE;
        if ($lastRun > $this->dateTime->gmtTimestamp() - $schedulePeriod) {
            return $this;
        }

        /**
         * save time schedules generation was ran with no expiration
         */
        $this->_cache->save(
            $this->dateTime->gmtTimestamp(),
            self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT . $groupId,
            ['crontab'],
            null
        );

        $schedules = $this->getPendingSchedules($groupId);
        $exists = [];
        /** @var Schedule $schedule */
        foreach ($schedules as $schedule) {
            $exists[$schedule->getJobCode() . '/' . $schedule->getScheduledAt()] = 1;
        }

        /**
         * generate global crontab jobs
         */
        $jobs = $this->_config->getJobs();
        $this->invalid = [];
        $this->_generateJobs($jobs[$groupId], $exists, $groupId);
        $this->cleanupScheduleMismatches();

        return $this;
    }

    /**
     * @param array $jobs
     * @param array $exists
     * @param string $groupId
     */
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
        /** @var \Magento\Cron\Model\ResourceModel\Schedule $scheduleResource */
        $scheduleResource = $this->_scheduleFactory->create()->getResource();
        foreach ($this->invalid as $jobCode => $scheduledAtList) {
            $scheduleResource->getConnection()->delete($scheduleResource->getMainTable(), [
                'status = ?' => \Magento\Cron\Model\Schedule::STATUS_PENDING,
                'job_code = ?' => $jobCode,
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
        $jobs = $this->_config->getJobs();
        $jobsToCleanup = [];
        foreach ($jobs[$groupId] as $jobCode => $jobConfig) {
            if (!$this->getCronExpression($jobConfig)) {
                /** @var \Magento\Cron\Model\ResourceModel\Schedule $scheduleResource */
                $jobsToCleanup[] = $jobCode;
            }
        }

        if (count($jobsToCleanup) > 0) {
            $scheduleResource = $this->_scheduleFactory->create()->getResource();
            $count = $scheduleResource->getConnection()->delete(
                $scheduleResource->getMainTable(),
                [
                    'status = ?' => \Magento\Cron\Model\Schedule::STATUS_PENDING,
                    'job_code in (?)' => $jobsToCleanup,
                ]
            );

            $this->logger->info(sprintf('%d cron jobs were cleaned', $count));
        }
    }

    /**
     * @param string $groupId
     * @param string $path
     * @return int|mixed
     */
    private function getCronGroupConfigurationValue($groupId, $path)
    {
        return $this->_scopeConfig->getValue(
            'system/cron/' . $groupId . '/' . $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @param string $groupId
     * @return bool
     */
    private function isGroupInFilter($groupId)
    {
        return !($this->_request->getParam('group') !== null
            && trim($this->_request->getParam('group'), "'") !== $groupId);
    }

    /**
     * @param int $groupId
     * @param callable $callback
     */
    private function lockGroup($groupId, callable $callback)
    {

        if (!$this->lockManager->lock(self::LOCK_PREFIX . $groupId, self::LOCK_TIMEOUT)) {
            $this->logger->warning(
                sprintf(
                    "Could not acquire lock for cron group: %s, skipping run",
                    $groupId
                )
            );
            return;
        }
        try {
            $callback($groupId);
        } finally {
            $this->lockManager->unlock(self::LOCK_PREFIX . $groupId);
        }
    }
}