<?php

/**
 * @file plugins/generic/vgWort/classes/form/VGWortChapterForm.inc.php
 *
 * Copyright (c) 2021 Heidelberg University Library
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 *
 * @class VGWortChapterForm
 */


namespace APP\plugins\generic\vgwort;

use PKP\classes\form\Form;

class VgwortChapterForm extends Form {

	//
	// Private properties
	//
	/** @var integer */
	var $_contextId;

	/**
	 * Get the context ID.
	 *
	 * @return integer
	 */
	function _getContextId() {
		return $this->_contextId;
	}

	/** @var VGWortPlugin */
	var $_plugin;

	/**
	 * Get the plugin.
	 *
	 * @return VGWortPlugin
	 */
	function _getPlugin() {
		return $this->_plugin;
	}


	//
	// Constructor
	//
	/**
	 * Constructor
	 *
	 * @param VGWortPlugin $plugin
	 * @param integer $contextId
	 */
	function __construct($plugin, $contextId) {
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;
		parent::__construct(method_exists($plugin, 'getTemplateResource')
            ? $plugin->getTemplateResource('vgWortChapter.tpl')
            : $plugin->getTemplatePath() . 'vgWortChapter.tpl'
        );

		// Add form validation checks.
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}


	//
	// Implement template methods from Form
	//
	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		foreach ($this->getFormFields() as $settingName => $settingType) {
			$this->setData($settingName, $this->getSetting($settingName));
		}
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array_keys($this->getFormFields()));
	}

	/**
     * @copydoc Form::execute()
     */
    function execute(...$functionArgs) {
        $plugin = $this->_getPlugin();
        $contextId = $this->_getContextId();
        parent::execute(...$functionArgs);
        foreach($this->getFormFields() as $fieldName => $fieldType) {
            $plugin->updateSetting($contextId, $fieldName, $this->getData($fieldName), $fieldType);
        }
    }

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request = null, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request, $template);
	}


	//
	// Public helper methods
	//
	/**
	 * Get a plugin setting.
	 *
	 * @param $settingName
	 * @return mixed The setting value.
	 */
	function getSetting($settingName) {
		$plugin = $this->_getPlugin();
		$settingValue = $plugin->getSetting($this->_getContextId(), $settingName);
		return $settingValue;
	}

	/**
	 * Get form fields
	 *
	 * @return array (field name => field type)
	 */
	function getFormFields() {
        return ['vgWortAssignRemoveCheckbox' => 'string'];
	}

	/**
	 * Is the form field optional
	 *
	 * @param $settingName string
	 * @return boolean
	 */
	function isOptional($settingName) {
		return in_array($settingName, [
			'archiveAccess',
			'automaticDeposit',
			'automaticDepositCheckBox',
			'ojsInstance'
		]);
	}

	/**
	 * Check whether this journal is OA.
	 *
	 * @return boolean
	 */
	function isOAJournal() {
		$journalDao = DAORegistry::getDAO('JournalDAO'); /* @var $journalDao JournalDAO */
		$journal = $journalDao->getById($this->_getContextId());
		return  $journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN &&
		$journal->getSetting('restrictSiteAccess') != 1 &&
		$journal->getSetting('restrictArticleAccess') != 1;
	}
}

?>
