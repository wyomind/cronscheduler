<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Setup;

/**
 * Cron Scheduler install schema setup script
 * @version 1.0.0
 */
class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(
        \Magento\Framework\Setup\SchemaSetupInterface $setup,
        \Magento\Framework\Setup\ModuleContextInterface $context
    )
    {
        $installer = $setup;

        $installer->startSetup();

        /*
         * add column `origin` to `cron_schedule`
         */
        $installer->getConnection()->addColumn($installer->getTable('cron_schedule'), 'origin', [
            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            'length' => 1,
            'nullable' => true,
            'comment' => 'Where does the schedule has been triggered? 0:Cron, 1:Backend, 2:CLI, 3:WebAPI'
        ]);

        /*
         * add column `user` to `cron_schedule`
         */
        $installer->getConnection()->addColumn($installer->getTable('cron_schedule'), 'user', [
            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            'length' => 100,
            'nullable' => true,
            'comment' => 'Who triggered the schedule'
        ]);

        /*
         * add column `ip` to `cron_schedule`
         */
        $installer->getConnection()->addColumn($installer->getTable('cron_schedule'), 'ip', [
            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            'length' => 40,
            'nullable' => true,
            'comment' => 'From which IP?'
        ]);
        
        /*
         * add column `error_file` to `cron_schedule`
         */
        $installer->getConnection()->addColumn($installer->getTable('cron_schedule'), 'error_file', [
            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            'length' => 500,
            'nullable' => true,
            'comment' => 'Where (file) the error has been triggered?'
        ]);

        /*
         * add column `error_line` to `cron_schedule`
         */
        $installer->getConnection()->addColumn($installer->getTable('cron_schedule'), 'error_line', [
            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            'length' => 6,
            'nullable' => true,
            'comment' => 'Where (line) the error has been triggered?'
        ]);

        $installer->endSetup();
    }
}