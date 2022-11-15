<?php

/**
 * @file VGWortSettingsForm.inc.php
 *
 * Copyright (c) 2022 Heidelberg University
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VGWortSettingsForm
 */

import('lib.pkp.classes.form.Form');

class VGWortSettingsForm extends Form {

    var $contextId;

    var $plugin;

    function __construct($plugin, $contextId) {
        $this->contextId = $contextId;
        $this->plugin = $plugin;

        // Define the settings template and store a copy of the plugin object.
        parent::__construct(method_exists($plugin, 'getTemplateResource')
            ? $plugin->getTemplateResource('settingsForm.tpl')
            : $plugin->getTemplatePath() . 'settingsForm.tpl');

        $this->addCheck(new FormValidator(
            $this,
            'vgWortUserId',
            'required',
            'plugins.generic.vgWort.settings.vgWortUserIdRequired'
        ));

        $this->addCheck(new FormValidator(
            $this,
            'vgWortUserPassword',
            'required',
            'plugins.generic.vgWort.settings.vgWortUserPasswordRequired'
        ));

        // Always add POST and CSRF validation to secure the form.
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Load settings already saved in the database.
     *
     * Settings are stored by context, so that each journal or press
     * can have different settings.
     */
    function initData() {
        foreach ($this->_getFormFields() as $fieldName => $fieldType) {
            $fieldValue = $this->plugin->getSetting($this->contextId, $fieldName);
            if ($fieldName == 'daysAfterPublication') {
                if (!$fieldValue) {
                    $fieldValue = '';
                }
            }
            $this->setData($fieldName, $fieldValue);
        }
        parent::initData();
    }

    /**
    * Load data that was submitted with the form.
    */
    function readInputData() {
        $this->readUserVars(array_keys($this->_getFormFields()));
    }

    /**
     * Fetch any additional data needed for your form.
     *
     * Data assigned to the form using $this->setData() during the
     * initData() or readInputData() methods will be passed to the
     * template.
     *
     * @return string
     */
    function fetch($request, $template = NULL, $display = false) {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());

        return parent::fetch($request, $template, $display);
    }

    /**
     * Save the settings.
     *
     * @return null|mixed
     */
    function execute(...$functionArgs) {
        foreach ($this->_getFormFields() as $fieldName => $fieldType) {
            if ($fieldName == 'dateInYear') {
            }
            $this->plugin->updateSetting($this->contextId, $fieldName, $this->getData($fieldName), $fieldType);
        }

        // Tell the user that the save was successful.
        import('classes.notification.NotificationManager');
        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification(
            Application::get()->getRequest()->getUser()->getId(),
            NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __('common.changesSaved')]
        );

        return parent::execute($functionArgs);
    }

    /**
     * Get an array with field names together with their data type.
     *
     * @return array
     */
    function _getFormFields() {
        return [
            'vgWortUserId' => 'string',
            'vgWortUserPassword' => 'string',
            'dateInYear' => 'string',
            'daysAfterPublication' => 'int',
            'vgWortTestAPI' => 'bool'
        ];
    }
}

?>
