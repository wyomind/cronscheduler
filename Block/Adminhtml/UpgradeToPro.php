<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Block\Adminhtml;

/**
 * Message to upgrade to the pro version when using the free version
 * @version 1.0.0
 */
class UpgradeToPro extends \Magento\Backend\Block\Template
{
    /**
     * @var \Magento\Framework\Module\ModuleList|null
     */
    protected $_moduleList = null;
    
    /**
     * Class constructor
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Module\ModuleList $moduleList
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Module\ModuleList $moduleList,
            array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->setTemplate('upgradeToPro.phtml');
        $this->_moduleList = $moduleList;
    }

    /**
     * Using the pro version ?
     * @return type
     */
    public function isPro()
    {
        return $this->_moduleList->has('Wyomind_CronSchedulerPro');
    }
}