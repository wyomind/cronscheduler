<?php

/**
 * Copyright © 2017 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Ui\Component\TaskListing\Column;

/**
 * Task listing actions
 * @version 1.0.0
 */
class Actions extends \Magento\Ui\Component\Listing\Columns\Column
{


    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var string
     */
    private $_viewUrl = \Wyomind\CronScheduler\Helper\Url::TASK_VIEW;

    /**
     * Class cosntructor
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
    \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
            \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
            \Magento\Framework\UrlInterface $urlBuilder,
            array $components = [],
            array $data = []
    )
    {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $name = $this->getData('name');
                if (isset($item['schedule_id']) && $item['status'] != \Magento\Cron\Model\Schedule::STATUS_PENDING) {
                    $url = $this->urlBuilder->getUrl($this->_viewUrl);
                    $item[$name]['view_more'] = [
                        'href' => "javascript:void(require(['cs_task'], function (task) { task.view('" . $url . "','" . $item['schedule_id'] . "'); }))",
                        'label' => __('View More'),
                    ];
                }
            }
        }

        return $dataSource;
    }

}
