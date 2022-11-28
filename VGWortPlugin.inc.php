<?php

/**
 * @file VGWortPlugin.inc.php
 *
 * Copyright (c) 2018 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Copyright (c) 2022 Heidelberg University Library
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE
 *
 * @class VGWortPlugin
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.submission.SubmissionFile');
import('lib.pkp.classes.components.forms.FieldOptions');

define('NOTIFICATION_TYPE_VGWORT_ERROR', 0x400000A);

class VGWortPlugin extends GenericPlugin {

    const DATA_FIELDS = [
        'vgWort::texttype',
        'vgWort::pixeltag::status',
        'vgWort::pixeltag::assign',
        'vgWort::pixeltag::remove'
    ];

    /**
     * @copydoc GenericPlugin::register()
     */
    public function register($category, $path, $mainContextId = NULL)
    {
        // Register the plugin even when it is not enabled.
        $success = parent::register($category, $path);
        if ($success && $this->getEnabled()) {
            $this->import('classes.form.VGWortForm');
            $this->import('classes.PixelTag');
            $this->import('classes.PixelTagDAO');
            $pixelTagDao = new PixelTagDAO($this->getName());
            $returner = DAORegistry::registerDAO('PixelTagDAO', $pixelTagDao);

            // Extend Schemas and DAOs for some new properties.
            HookRegistry::register('Schema::get::publication', [$this, 'addToSchema']);
            HookRegistry::register('Schema::get::author', [$this, 'addToSchema']);
            HookRegistry::register('Schema::get::user', [$this, 'addToSchema']);
            HookRegistry::register('chapterdao::getAdditionalFieldNames', [$this, 'addAdditionalFieldNames']);
            HookRegistry::register('chapterdao::getLocaleFieldNames', [$this, 'addAdditionalFieldNames']);

            // Add new tabs to the distribution settings and publication workflow form.
            HookRegistry::register('Template::Settings::distribution', [$this, 'addNewTabs']);
            HookRegistry::register('Template::Workflow::Publication', [$this, 'addNewTabs']);

            //
            HookRegistry::register('TemplateManager::display', [$this, 'handleTemplateDisplay']);
            HookRegistry::register('TemplateManager::fetch', [$this, 'handleTemplateFetch']);

            // Create new table that lists ordered pixel tags.
            HookRegistry::register('LoadComponentHandler', [$this, 'setupGridHandler']);

            // Add new field for VG Wort Card Number to the user's and author's form template.
            HookRegistry::register('Common::UserDetails::AdditionalItems', [$this, 'metadataFieldEdit']);
            HookRegistry::register('User::PublicProfile::AdditionalItems', [$this, 'metadataFieldEdit']);

            // Initialize data.
            HookRegistry::register('authorform::initdata', [$this, 'metadataInitData']);

            // Read user's input.
            HookRegistry::register('authorform::readuservars', [$this, 'metadataReadUserVars']);
            HookRegistry::register('chapterform::readuservars', [$this, 'metadataReadUserVars']);

            // Execute form.
            HookRegistry::register('authorform::execute', [$this, 'metadataExecute']);
            HookRegistry::register('chapterform::execute', [$this, 'handleChapterFormExecute']);
            HookRegistry::register('chapterform::display', [$this, 'handleChapterFormDisplay']);

            // Add validation check for VG Wort Card No. field.
            HookRegistry::register('authorform::Constructor', [$this, 'addCheck']);

            // Add VG Wort pixel to PDF JS Viewer.
            HookRegistry::register('Templates::Common::Footer::PageFooter', [$this, 'insertPixelTagJSViewer']);

            // Assign pixel tag to submission object.
            HookRegistry::register('Publication::edit', [$this, 'pixelExecuteSubmission']);

            $this->pixelTagStatusLabels = [
                0 => __('plugins.generic.vgWort.pixelTag.status.notassigned'),
                PT_STATUS_REGISTERED_ACTIVE => __('plugins.generic.vgWort.pixelTag.status.registered.active'),
                PT_STATUS_UNREGISTERED_ACTIVE => __('plugins.generic.vgWort.pixelTag.status.unregistered.active'),
                PT_STATUS_REGISTERED_REMOVED => __('plugins.generic.vgWort.pixelTag.status.registered.removed'),
                PT_STATUS_UNREGISTERED_REMOVED => __('plugins.generic.vgWort.pixelTag.status.unregistered.removed')
            ];

        }
        return $success;
    }

    /**
     * Provide a name for this plugin.
     *
     * The name will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.generic.vgWort.displayName');
    }

    /**
     * Provide a description for this plugin.
     *
     * The description will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     *
     * @return string
     */
    public function getDescription()
    {
        return __('plugins.generic.vgWort.description');
    }

    /**
     * Enable the settings form in the site-wide plugins list.
     *
     * @return boolean
     */
    function isSitePlugin()
    {
        return true;
    }

    // TODO: SOAP will no longer be used. Other requirements need to be checked.
    function requirementsFulfilled()
    {
        $isSoapExtension = in_array('soap', get_loaded_extensions());
        $isOpenSSL = in_array('openssl', get_loaded_extensions());
        $isCURL = function_exists('curl_init');
        return $isSoapExtension && $isOpenSSL && $isCURL;
    }

    /**
     * Return the canonical template path of this plugin.
     *
     * @param bool $inCore
     */
    function getTemplatePath($inCore = false)
    {
        // TODO: ojsVersion?
        $ojsVersion = Application::getApplication()->getCurrentVersion()->getVersionString();
        return parent::getTemplatePath();
    }

    /**
     * Add a settings action to the plugin's entry in the
     * plugins list.
     *
     * @param Request $request
     * @param array $actionArgs
     *
     * @return array
     */
    public function getActions($request, $actionArgs)
    {
        // Get the existing actions
        $actions = parent::getActions($request, $actionArgs);

        // Only add the settings action when the plugin is enabled
        if (!$this->getEnabled()) {
            return $actions;
        }

        // Create a LinkAction that will make a request to the
        // plugin's `manage` method with the `settings` verb.
        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        $linkAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    NULL,
                    NULL,
                    'manage',
                    NULL,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->getName(),
                        'category' => 'generic'
                    ]
                ),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            NULL
        );

        // Add the LinkAction to the existing actions.
        // Make it the first action to be consistent with
        // other plugins.
        array_unshift($actions, $linkAction);

        return $actions;
    }

    /**
     * Show and save the settings form when the settings action
     * is clicked.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {

            // Return a JSON response containing the settings form
            case 'settings':
                // Load the custom form
                $this->import('classes.form.VGWortSettingsForm');
                $contextId = $request->getContext()->getId();
                $settingsForm = new VGWortSettingsForm($this, $contextId);

                // Fetch the form the first time it loads, before
                // the user has tried to save it
                if (!$request->getUserVar('save')) {
                    $settingsForm->initData();
                    return new JSONMessage(true, $settingsForm->fetch($request));
                }

                // Validate and save the form data
                $settingsForm->readInputData();
                if ($settingsForm->validate()) {
                    $settingsForm->execute();
                    return new JSONMessage(true);
                }
        }
        return parent::manage($args, $request);
    }

    /**
     * Hook callback to add some pixel tag properties to the
     * publication's, author's and user's schema.
     *
     * @param string $hookName
     * @param array $args
     */
    public function addToSchema($hookName, $args) {
        $schema = $args[0];
        switch ($hookName) {
            case 'Schema::get::publication':
            // case 'Schema::get::chapter':
                $schema->properties->{"vgWort::texttype"} = (object) [
                    'type' => 'string',
                ];
                $schema->properties->{"vgWort::pixeltag::assign"} = (object) [
                    'type' => 'boolean',
                    'validation' => ['NULLable']
                ];
                $schema->properties->{"vgWort::pixeltag::remove"} = (object) [
                    'type' => 'boolean',
                    'validation' => ['NULLable']
                ];
                $schema->properties->{"vgWort::pixeltag::status"} = (object) [
                    'type' => 'string',
                ];
                break;
            case 'Schema::get::author':
            case 'Schema::get::user':
                $schema->properties->{"vgWortCardNo"} = (object) [
                    'type' => 'integer'
                ];
                break;
        }
        return false;
    }

    // TODO: Still not possible to use addToSchema() callback
    // for extending chapter properties.
    /**
     * Hook callback to add some pixel tag properties to the
     * chapter's DAO.
     *
     * @param string $hookName
     * @param array $args
     */
    function addAdditionalFieldNames($hookName, $args) {
        switch ($hookName) {
            // case 'userdao::getAdditionalFieldNames':
            // case 'authordao::getAdditionalFieldNames':
            //     $fields =& $params[1];
            //     $fields[] = 'vgWortCardNo';
            case 'chapterdao::getAdditionalFieldNames':
                $fields =& $args[1];
                $fields[] = 'vgWort::texttype';
                $fields[] = 'vgWort::pixeltag::assign';
                $fields[] = 'vgWort::pixeltag::remove';
                $fields[] = 'vgWort::pixeltag::status';
                break;
            case 'chapterdao::getLocaleFieldNames':
                $fields =& $args[1];
                $fields[] = 'chapterNumber';
                break;
        }
        return false;
    }

    /**
     * Hook callback to add new tabs to the distribution
     * settings and publication workflow form.
     *
     * @param string $hookName
     * @param array $args
     */
    function addNewTabs($hookName, $args)
    {
        switch ($hookName) {
            case 'Template::Settings::distribution':
                $smarty =& $args[1];
                $output =& $args[2];
                $templateFile = method_exists($this, 'getTemplateResource')
                    ? $this->getTemplateResource('distributionNavLink.tpl')
                    : $this->getTemplatePath() . 'distributionNavLink.tpl';
                $output .= $smarty->fetch($templateFile);
                break;
            case 'Template::Workflow::Publication':
                $html =& $args[2];
                $html = '<tab id="vgwortformtab" label="VG Wort">
                    <pkp-form v-bind="components.' . FORM_VGWORT . '" @set="set" />
                    </tab>';
                break;
        }
        return false;
    }

    /**
     * Hook callback to initialize data in the user and
     * author form when first loaded.
     *
     * @param string $hookName
     * @param array $args
     */
    function metadataInitData($hookName, $args)
    {
        $form =& $args[0];
        $user = NULL;

        switch ($hookName) {
            case 'userdetailsform::initdata':
                if (isset($form->userId)) {
                    $userDao = DAORegistry::getDAO('UserDAO');
                    $user = $userDao->getById($form->userId);
                }
                break;
            case 'authorform::initdata':
                $user = $form->getAuthor();
                break;
            case 'publicprofileform::initdata':
                $user = $form->getUser();
                break;
        }
        if ($user) {
            $form->setData('vgWortCardNo', $user->getData('vgWortCardNo'));
        }
        return false;
    }

    /**
     * Hook callback for adding a new field for VG Wort Card Number
     * to the user's and author's form template.
     *
     * @param string $hookName
     * @param array $args
     */
    function metadataFieldEdit($hookName, $args)
    {
        $smarty =& $args[1];
        $output =& $args[2];

        if ($hookName == 'Common::UserDetails::AdditionalItems') {
            $smarty->assign('vgWortFieldTitle', 'plugins.generic.vgWort.cardNo');
        }
        $templateFile = method_exists($this, 'getTemplateResource')
            ? $this->getTemplateResource('vgWortCardNo.tpl')
            : $this->getTemplatePath() . 'vgWortCardNo.tpl';
        $output .= $smarty->fetch($templateFile);
        return false;
    }

    /**
     * Hook callback for reading user's input.
     *
     * @param string $hookName
     * @param array $args
     */
    function metadataReadUserVars($hookName, $args)
    {
        $form =& $args[0];
        $vars =& $args[1];
        switch ($hookName) {
            case 'authorform::readuservars':
                $vars[] = 'vgWortCardNo';
                break;
            case 'chapterform::readuservars':
                $vars = array_merge($vars, self::DATA_FIELDS);
                $vars[] = 'vgWortAssignRemoveCheckbox';
                break;
        }
        return false;
    }

    /**
     * Hook callback to save the form.
     *
     * @param string $hookName
     * @param array $args
     */
    function metadataExecute($hookName, $args)
    {
        $form =& $args[0];
        $user = NULL;

        switch ($hookName) {
            case 'userdetailsform::execute':
                $user = $form->user;
                break;
            case 'authorform::execute':
                $user = $form->getAuthor();
                break;
            case 'publicprofileform::execute':
                $user =& $args[2];
                break;
        }
        return false;
    }

    /**
     * Hook callback for extending "Edit Chapter" form.
     */
    public function handleChapterFormDisplay($hookName, $args)
    {
        $request = Application::get()->getRequest();
        try {
            $templateMgr = TemplateManager::getManager($request);
        } catch (Exception $e) {
            return false;
        }

        $vgWortTextTypes = [
            TYPE_TEXT => __('plugins.generic.vgWort.pixelTag.textType.text'),
            TYPE_LYRIC => __('plugins.generic.vgWort.pixelTag.textType.lyric')
        ];
        $chapterForm =& $args[0];
        $chapter = $chapterForm->getChapter();

        if ($chapter) {
            foreach (self::DATA_FIELDS as $field) {
                $chapterForm->setData($field, $chapter->getData($field));
            }
            $pixelTagStatus = $chapter->getData("vgWort::pixeltag::status");
            $chapterForm->setData("vgWortPixeltagStatus", $pixelTagStatus);
            switch ($pixelTagStatus) {
                case PT_STATUS_REGISTERED_ACTIVE:
                    $chapterForm->setData("vgWortAssignRemoveCheckbox", "vgWortAssignPixelTag");
                    break;
                case PT_STATUS_UNREGISTERED_ACTIVE:
                    $chapterForm->setData("vgWortAssignRemoveCheckbox", "vgWortAssignPixelTag");
                    break;
                case PT_STATUS_REGISTERED_REMOVED:
                    $chapterForm->setData("vgWortAssignRemoveCheckbox", "vgWortRemovePixelTag");
                    break;
                case PT_STATUS_UNREGISTERED_REMOVED:
                    $chapterForm->setData("vgWortAssignRemoveCheckbox", "vgWortRemovePixelTag");
                    break;
            }

            $templateMgr->assign([
                'vgWortTextTypes' => $vgWortTextTypes ?: 0,
                'vgWortTextType' => $chapter->getData('vgWort::texttype') ?: 0
            ]);
        }

        try {
            $templateMgr->registerFilter('output', [$this, '_chapterFormFilter']);
        } catch (SmartyException $e) {
            return false;
        }

        return false;
    }

    public function _chapterFormFilter($output, $templateMgr)
    {
        if (preg_match('/<div[\s\S]*id="authors\[\]"/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1];
            $newOutput = substr($output, 0, $offset);
            $newOutput .= $templateMgr->fetch($this->getTemplateResource('vgWortChapter.tpl'));
            $newOutput .= substr($output, $offset);
            $output = $newOutput;
            $templateMgr->unregisterFilter('output', [$this, '_chapterFormFilter']);
        }

        return $output;
    }

    /**
     * Hook callback for executing "Edit Chapter" form.
     *
     * @param string $hookName
     * @param array $args
     */
    public function handleChapterFormExecute($hookName, $args)
    {
        $form =& $args[0];

        $vgWortTextType = $form->getData('vgWort::texttype');
        $pixelTagStatus = $form->getData('vgWort::pixeltag::status');
        $vgWortAssignRemoveCheckbox = $form->getData("vgWortAssignRemoveCheckbox");
        if ($vgWortAssignRemoveCheckbox == NULL) {
            $pixelTagAssigned = false;
            $pixelTagRemoved = false;
        } elseif ($vgWortAssignRemoveCheckbox == "vgWortAssignPixelTag") {
            $pixelTagAssigned = true;
            $pixelTagRemoved = false;
        } elseif ($vgWortAssignRemoveCheckbox == "vgWortRemovePixelTag") {
            $pixelTagRemoved = true;
            $pixelTagAssigned = false;
        }

        try {
            $chapterDao = DAORegistry::getDAO('ChapterDAO');
        } catch (Exception $e) {
            return false;
        }

        $chapter = $form->getChapter();
        $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');

        if ($chapter) {
            $publicationId = $chapter->getData('publicationId');
            $publicationDao = DAORegistry::getDAO('PublicationDAO');
            $publication = $publicationDao->getById($publicationId);

            $submissionId = $publication->getData('submissionId');
            $submissionDao = DAORegistry::getDAO('SubmissionDAO');
            $submission = $submissionDao->getById($submissionId);

            $contextId = $submission->getData('contextId');

            $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
            $pixelTag = $pixelTagDao->getPixelTagByChapterId($chapter->getId(), $submissionId, $contextId);

            $chapter->setData('vgWort::texttype', $vgWortTextType);
            $chapter->setData('vgWort::pixeltag::assign', $pixelTagAssigned);
            $chapter->setData('vgWort::pixeltag::remove', $pixelTagRemoved);

            $this->pixelExecute($submission, $chapter, $pixelTag);
        }

        $form->setChapter($chapter);

        return false;
    }

    /**
     * Hook callback
     *
     * @param string $hookName
     * @param array $args
     */
    function handleTemplateDisplay($hookName, $args) {
        $templateMgr =& $args[0];
        $template =& $args[1];
        $ompVersion = Application::getApplication()->getCurrentVersion()->getVersionString();

        if (strstr($template, "submissionGalley.tpl")) {
            $templateMgr->registerFilter('output', array($this, 'insertPixelTagSubmissionPage'));
            return false;
        }

        switch ($template) {
            case 'frontend/pages/book.tpl':
                $templateMgr->registerFilter('output', array($this, 'insertPixelTagSubmissionPage'));
                break;
            case 'frontend/pages/issue.tpl':
                $templateMgr->registerFilter('output', array($this, 'insertPixelTagIssueTOC'));
                break;
            case 'workflow/workflow.tpl':
                $this->import('classes.form.VGWortForm');
                $context = $templateMgr->getTemplateVars('currentContext');
                $submission = $templateMgr->getTemplateVars('submission');

                $request = Application::get()->getRequest();
                $latestPublicationApiUrl = $request->getDispatcher()->url(
                    $request,
                    ROUTE_API,
                    $context->getPath(),
                    'submissions/' . $submission->getId() . '/publications/' . $submission->getLatestPublication()->getId()
                );

                $form = new VGWortForm($latestPublicationApiUrl, [], $context, $submission);

                // Use "getState()" (instead of "setState()") to avoid
                // accidentally overwriting additional components on page.
                $components = $templateMgr->getState('components');
                $components[FORM_VGWORT] = $form->getConfig();
                $templateMgr->setState([
                    'components' => $components,
                ]);

                $templateMgr->addJavaScript(
                    'vgWort-labels',
                    'window.vgWortPixeltagStatusLabels = ' . json_encode($this->pixelTagStatusLabels) . ';',
                    [
                        'inline' => true,
                        'contexts' => 'backend',
                        'priority' => STYLE_SEQUENCE_CORE
                    ]
                );

                $publication = $submission->getCurrentPublication();
                $publicationFormats = $publication->getData('publicationFormats');
                //error_log("publicationFormats: " . var_export($publicationFormats,true));
                //$supportedPublicationFormats = array_filter($publicationFormats, [$this, 'checkPublicationFormatSupported']);
                $supportedPublicationFormats = array_filter($publicationFormats, function($publicationFormat) use($submission){
                    $submissionFiles = $this->getSubmissionFiles($submission, $publicationFormat)->_current;
                    if (!$submissionFiles) {
                        return false;
                    }
                    $megaByte = 1024*1024;
                    if (round((int) $publicationFormat->getFileSize() / $megaByte > 15)) {
                        return false;
                    }
                    return $this->getSupportedFileTypes($submissionFiles->getData('mimetype'));
                });

                // import('lib.pkp.classes.submission.SubmissionFile'); // File constants
                // foreach ($publicationFormats as $publicationFormat) {
                //     $stageMonographFiles = Services::get('submissionFile')->getMany([
                //         'submissionIds' => [$publication->getData('submissionId')],
                //         'fileStages' => [SUBMISSION_FILE_PROOF],
                //         'assocTypes' => [ASSOC_TYPE_PUBLICATION_FORMAT],
                //         'assocIds' => [$publicationFormat->getId()],
                //     ]);
                //     $supportedPublicationFormats = $this->checkPublicationFormatSupported($submission, $publicationFormat);
                // }

            $templateMgr->addJavaScript(
                'vgWort',
                Application::get()->getRequest()->getBaseUrl()
                    . DIRECTORY_SEPARATOR
                    . $this->getPluginPath()
                    . DIRECTORY_SEPARATOR . 'js'
                    . DIRECTORY_SEPARATOR . 'vgwort.js',
                [
                    'contexts' => 'backend',
                    'priority' => STYLE_SEQUENCE_LAST
                ]);
            break;
        }
        return false;
    }

    /**
     * Hook callback
     *
     * @param string $hookName
     * @param array $args
     */
    function handleTemplateFetch($hookName, $args)
    {
        $templateMgr =& $args[0];
        $template =& $args[1];

        switch ($template) {
            case 'controllers/tab/workflow/production.tpl':
                $submission = $templateMgr->get_template_vars('submission');
                $notificationOptions =& $templateMgr->get_template_vars('productionNotificationRequestOptions');
                $notificationOptions[NOTIFICATION_LEVEL_NORMAL][NOTIFICATION_TYPE_VGWORT_ERROR] = [
                    ASSOC_TYPE_SUBMISSION,
                    $submission->getId()
                ];
                break;
        }
        return false;
    }

    /**
     * Hook callback for validating VG Wort Card No. field (2-7 numbers).
     *
     * @param string $hookName
     * @param array $args
     */
    function addCheck($hookName, $args)
    {
        $form =& $args[0];
        $form->addCheck(new FormValidatorRegExp(
            $form,
            'vgWortCardNo',
            'optional',
            'plugins.generic.vgWort.cardNoValid',
            '/^\d{2,7}$/'
        ));
        return false;
    }

    /**
     * Hook callback for creating a new table that lists all pixel tags.
     *
     * @param string $hookName
     * @param array $args
     */
    function setupGridHandler($hookName, $args)
    {
        $component =& $args[0];
        if ($component == 'plugins.generic.vgWort.controllers.grid.PixelTagGridHandler') {
            define('VGWORT_PLUGIN_NAME', $this->getName());
            return true;
        }
        return false;
    }

    /**
     *
     */
    function insertPixelTagSubmissionPage($output, $templateMgr) {
        $press = $templateMgr->get_template_vars('currentContext');
        $monograph = $templateMgr->get_template_vars('publishedSubmission'); // NICHT "monograph"

        $publicationFormats = $templateMgr->get_template_vars('publicationFormats');
        $availableFiles = $templateMgr->get_template_vars('availableFiles');

        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($monograph->getId());
        $contextId = $submission->getData('contextId');

        if (isset($submission)) {
            $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
            $pixelTag = $pixelTagDao->getPixelTagBySubmissionId($submission->getId(), $contextId);
            if (isset($pixelTag) && !$pixelTag->getDateRemoved()) {
                $application = PKPApplication::getApplication();
                $request = $application->getRequest();
                $httpProtocol = $request->getProtocol() == 'https' ? 'https://' : 'http://';
                $pixelTagSrc = $httpProtocol . $pixelTag->getDomain() . '/na/' . $pixelTag->getPublicCode();
                $pixelTagImg = '<img src=\'' . $pixelTagSrc . '\' width=\'1\' height=\'1\' alt=\'\' />';

                if (!empty($publicationFormats)) {
                    $search = '<div class="entry_details">';
                    $replace = $search . '<script>function vgwPixelCall(galleyId) { document.getElementById("div_vgwpixel_"+galleyId).innerHTML="<img src=\'' . $pixelTagSrc . '\' width=\'1\' height=\'1\' alt=\'\' />"; }</script>';
                    $output = str_replace($search, $replace, $output);
                    foreach ($publicationFormats as $publicationFormat) {
                        $submissionFile = $this->getSubmissionFiles($submission, $publicationFormat)->_current;
                        // error_log("[VGWortPlugin] submissionFile: " . var_export(get_class($submissionFile),true));
                        // change galley download links
                        $publicationFormatUrl = $request->url(
                            null,
                            'catalog',
                            'view',
                            [
                                $submission->getBestId(),
                                $publicationFormat->getId(),
                                $submissionFile->getId()
                            ]
                        );

                        $search = '#<a (.*)href="' . $publicationFormatUrl . '"(.*)>#';
                        // insert pixel tag for galleys download links using JS
                        $replace = '<div style="font-size:0;line-height:0;width:0;" id="div_vgwpixel_' . $publicationFormat->getId() . '"></div><a class="$1" href="' . $publicationFormatUrl . '" onclick="vgwPixelCall(' . $publicationFormat->getId() . ');">';
                        // insert pixel tag for galleys download links using VG Wort redirect
                        $output = preg_replace($search, $replace, $output);
                    }
                }
            }
        }
        return $output;
    }

    /**
     * Hook callback for adding VG Wort pixel to PDF JS Viewer.
     *
     * @param string $hookName
     * @param array $args
     */
    function insertPixelTagJSViewer($hookName, $args)
    {
        $templateMgr =& $args[1];
        $output =& $args[2];

        $press = $templateMgr->get_template_vars('currentContext');
        $monograph = $templateMgr->get_template_vars('publishedSubmission');

        if (isset($press) && !empty($monograph)) {
            if (isset($monograph) && !empty($monograph)) {
                $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
                $pixelTag = $pixelTagDao->getPixelTagBySubmissionId($monograph->getId(), $press->getId());
                if (isset($pixelTag) && !$pixelTag->getDateRemoved()) {
                    $application = PKPApplication::getApplication();
                    $request = $application->getRequest();
                    $httpsProtocol = $request->getProtocol() == 'https';
                    $output = $this->buildPixelTagHTML($pixelTag, $httpsProtocol);
                }
            }
        }

        return false;
    }

    /**
     * Build HTML code for the pixel tag.
     *
     * @param PixelTag $pixelTag
     * @param boolean $https
     */
    function buildPixelTagHTML($pixelTag, $https = false)
    {
        $httpProtocol = $https ? 'https://' : 'http://';
        $pixelTagSrc = $httpProtocol . $pixelTag->getDomain() . '/na/' . $pixelTag->getPublicCode();
        return '<img src=\'' . $pixelTagSrc . '\' width=\'1\' height=\'1\' alt=\'\' />';
    }

    /**
     * @param Submission $submission
     * @param Object $pubObject Publication or Chapter
     * @param PixelTag $pixelTag
     */
    function pixelExecute($submission, $pubObject, $pixelTag)
    {
        $vgWortTextType = $pubObject->getData('vgWort::texttype');
        $chapterId = NULL;
        if (isset($pixelTag)) {
            $updatePixelTag = false;
            // pixel tag has been removed, see if it should be assigned again
            if ($pixelTag->getDateRemoved()) {
                $vgWortAssignPixel = $pubObject->getData('vgWort::pixeltag::assign') ? 1 : 0;
                if ($vgWortAssignPixel) {
                    $pixelTag->setDateRemoved(NULL);
                    if ($pixelTag->getStatus() == PT_STATUS_UNREGISTERED_REMOVED) {
                        $pixelTag->setStatus(PT_STATUS_UNREGISTERED_ACTIVE);
                    } elseif ($pixelTag->getStatus() == PT_STATUS_REGISTERED_REMOVED) {
                        $pixelTag->setStatus(PT_STATUS_REGISTERED_ACTIVE);
                    }
                    $updatePixelTag = true;
                }
            } else {
                $removeVGWortPixel = $pubObject->getData('vgWort::pixeltag::assign') ? 0 : 1;
                if ($removeVGWortPixel) {
                    $pixelTag->setDateRemoved(Core::getCurrentDate());
                    if ($pixelTag->getStatus() == PT_STATUS_UNREGISTERED_ACTIVE) {
                        $pixelTag->setStatus(PT_STATUS_UNREGISTERED_REMOVED);
                    } elseif ($pixelTag->getStatus() == PT_STATUS_REGISTERED_ACTIVE) {
                        $pixelTag->setStatus(PT_STATUS_REGISTERED_REMOVED);
                    }
                    $updatePixelTag = true;
                }
            }
            // if text type changed, update
            // TODO: what if the pixel tag is registered
            if ($vgWortTextType != $pixelTag->getTextType()) {
                $pixelTag->setTextType($vgWortTextType);
                $updatePixelTag = true;
            }
            if ($updatePixelTag) {
                $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
                $pixelTagDao->updateObject($pixelTag);
            }
            $pubObject->setData('vgWort::pixeltag::status', $pixelTag->getStatus());
        } else {
            $vgWortAssignPixel = $pubObject->getData('vgWort::pixeltag::assign') ? 1 : 0;
            if (is_a($pubObject, 'Chapter')) {
                $chapterId = $pubObject->getId();
            }
            if ($vgWortAssignPixel) {
                // assign pixel tag
                if ($this->assignPixelTag($submission, $vgWortTextType, $chapterId)) {
                    $pixelTagStatus = 2; // unregistered, active
                    $pubObject->setData('vgWort::pixeltag::status', $pixelTagStatus);
                }
            }
        }
    }

    /**
     * Hook callback for assigning pixel tags when executing submission's form.
     *
     * @param string hookName
     * @param array args
     */
    function pixelExecuteSubmission($hookName, $args)
    {
        $publication =& $args[0];
        $publicationData = $args[2];

        if (!array_key_exists('vgWort::pixeltag::assign', $publicationData)
            || !array_key_exists('vgWort::texttype', $publicationData) ) {
            // do not execute hook when the vgWort data is not set
            return false;
        }
        $submissionId = $publication->getData('submissionId');
        $submissionFileDao =& DAORegistry::getDAO('SubmissionFileDAO');
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($submissionId);
        $contextId = $submission->getData('contextId');
        $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
        $pixelTag = $pixelTagDao->getPixelTagBySubmissionId($submissionId, $contextId);

        if (is_a($submission, 'Submission')) {
            $this->pixelExecute($submission, $publication, $pixelTag);
        }
        return false;
    }

    /**
     * Assign a pixel tag to a submission.
     *
     * @param Submission $submission
     * @param int $vgWortTextType
     * @return boolean
     */
    function assignPixelTag($submission, $vgWortTextType, $chapterId = NULL)
    {
        $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
        $contextId = $submission->getContextId();
        error_log("[VG Wort] assignPixelTag(): contextId=" . var_export($contextId,true));
        // TODO: it seems possible to order just 1 pixel tag
        $availablePixelTag = $pixelTagDao->getAvailablePixelTag($contextId);

        if(!$availablePixelTag) {
            // order pixel tags
            $this->import('classes.VGWortEditorAction');
            $vgWortEditorAction = new VGWortEditorAction($this);
            $orderResult = $vgWortEditorAction->orderPixel($contextId);
            if (!$orderResult[0]) {
                $application = PKPApplication::getApplication();
                $request = Application::get()->getRequest();
                $user = $request->getUser();
                // Create a form error notification.
                import('classes.notification.NotificationManager');
                $notificationManager = new NotificationManager();
                $notificationManager->createTrivialNotification(
                    $user->getId(), NOTIFICATION_TYPE_FORM_ERROR, ['contents' => $orderResult[1]]
                    // $user->getId(), NOTIFICATION_TYPE_FORM_ERROR, array('contents' => 'Not Found')
                );
                return false;
            } else {
                // insert ordered pixel tags in the db
                $vgWortEditorAction->insertOrderedPixel($contextId, $orderResult[1]);
            }
            $availablePixelTag = $pixelTagDao->getAvailablePixelTag($contextId);
        }
        assert($availablePixelTag);
        // there is an available pixel tag --> assign
        $availablePixelTag->setSubmissionId($submission->getId());
        $availablePixelTag->setDateAssigned(Core::getCurrentDate());
        $availablePixelTag->setStatus(PT_STATUS_UNREGISTERED_ACTIVE);
        $availablePixelTag->setTextType($vgWortTextType);
        if ($chapterId) {
            $availablePixelTag->setChapterId($chapterId);
        }
        $pixelTagDao->updateObject($availablePixelTag);
        return true;
    }

    // /**
    //  * Check whether publication format is supported.
    //  *
    //  * @param PublicationFormat $publicationFormat
    //  * @return bool
    //  */
    // function _checkPublicationFormatSupported($submission, $publicationFormat)
    // {
    //     $submissionFiles = $this->getSubmissionFiles($submission, $publicationFormat)->_current;
    //     if (!$submissionFiles) {
    //         return false;
    //     }
    //     $megaByte = 1024*1024;
    //     if (round((int) $publicationFormat->getFileSize() / $megaByte > 15)) {
    //         return false;
    //     }
    //     return $this->getSupportedFileTypes($submissionFiles->getData('mimetype'));
    //     // $publication = Services::get('publication')->get($publicationFormat->getData('publicationId'));
    //     // import('lib.pkp.classes.submission.SubmissionFile'); // File constants
    //     // $stageMonographFiles = Services::get('submissionFile')->getMany([
    //     //     'submissionIds' => [$publication->getData('submissionId')],
    //     //     'fileStages' => [SUBMISSION_FILE_PROOF],
    //     //     'assocTypes' => [ASSOC_TYPE_PUBLICATION_FORMAT],
    //     //     'assocIds' => [$publicationFormat->getId()],
    //     // ]);
    //     // if (!$stageMonographFiles->_current) {
    //     //     return false;
    //     // }
    //     //
    //     // $megaByte = 1024*1024;
    //     // if (round((int) $publicationFormat->getFileSize() / $megaByte > 15)) {
    //     //     return false;
    //     // }
    //     //
    //     // return $this->fileTypeSupported($stageMonographFiles->_current->getData('mimetype'));
    // }

    /**
     * Return all supported file types.
     *
     * @param array $fileType
     */
    function getSupportedFileTypes($fileType) {
        return ($fileType == 'application/pdf' ||
        $fileType == 'application/epub+zip' ||
        // Added XML support. Check with vgWort if allowed
        $fileType == 'text/xml' ||
        $fileType == 'text/html');
    }

    /**
     * Get all submission files that belong to a given publication format.
     *
     * @param PublicationFormat $publicationFormat
     */
    function getSubmissionFiles($submission, $publicationFormat)
    {
        $publication = $submission->getCurrentPublication();
        //$publication = Services::get('publication')->get($publicationFormat->getData('publicationId')); // TODO: Is this the current version?
        import('lib.pkp.classes.submission.SubmissionFile'); // File constants
        $submissionFiles = Services::get('submissionFile')->getMany([
            'submissionIds' => [$publication->getData('submissionId')],
            'fileStages' => [SUBMISSION_FILE_PROOF],
            'assocTypes' => [ASSOC_TYPE_PUBLICATION_FORMAT],
            'assocIds' => [$publicationFormat->getId()],
        ]);
        // $submissionFiles is an iterator?
        // error_log("[[VGWortPlugin]] getSubmissionFiles: " . var_export($submissionFiles->_current,true));
        // die();
        return $submissionFiles;
    }

    /**
     * Get ID of a publication format from submission file.
     *
     * @param SubmissionFile $submissionFile
     */
    function getPublicationFormatId($submissionFile) {
        return $submissionFile->getData('assocId');
    }

    /**
     * Get locale from submission file.
     *
     * @param SubmissionFile $submissionFile
     */
    function getLocaleFromSubmissionFile($submissionFile) {
        return $submissionFile->getData('locale');
    }

    /**
     * Get publication's format by its ID.
     *
     * @param Array $publicationFormts
     * @param int $publicationFormatId
     */
    //function getPublicationFormatById($publicationFormats, $publicationFormatId) {
    //    foreach ($publicationFormats as $publicationFormat) {
    //        if ($publicationFormat->getData('id') == $publicationFormatId) {
    //            return $publicationFormat;
    //        }
    //    }
    //}

    //function _getPublicationFormatById($publicationFormat, $value) {
    //    return $publicationFormat->getData('id') == $value;
    //}

    /**
     * Get submission file of full document.
     *
     * @param array $submissionFiles
     */
    function getBookManuscriptFile($submissionFiles)
    {
        // Get genre ID that corresponds to the book manuscript.
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genreBook = $genreDao->getByKey('MANUSCRIPT');
        $genreIdBook = $genreBook->getId();

        // Return submission file with genre ID from above.
        foreach ($submissionFiles as $submissionFile) {
            $genreIdSubmissionFile = $submissionFile->getData('genreId');
            if (!$genreIdSubmissionFile) {
                return false;
            } else {
                if ($genreIdSubmissionFile != $genreIdBook) {
                    continue;
                } else {
                    return $submissionFile;
                }
            }
        }
        // TODO: What if there are more than one book manuscript components?
    }

    // /**
    //  * Get all submission files of chapter documents.
    //  *
    //  * @param SubmissionFile
    //  */
    // function getChapterManuscriptFile($submissionFiles)
    // {
    //     // Get genre ID that corresponds to the chapter manuscript.
    //     $genreDao = DAORegistry::getDAO('GenreDAO');
    //     $genreChapter = $genreDao->getByKey('CHAPTER');
    //     $genreIdChapter = $genreChapter->getId();
    //
    //     // // Create json because array_column does not accept SubmissionFile objects.
    //     // $submissionFiles_json = json_decode(json_encode($submissionFiles), true);
    //
    //     $submissionFilesChapter = [];
    //     foreach ($submissionFiles as $submissionFile) {
    //         if ($submissionFile->getData('genreId') = $genreIdChapter) {
    //             $submissionFilesChapter[] = $submissionFile;
    //         }
    //     }
    //
    //     return array_column($submissionFiles_array, NULL, 'genreId')[$genreIdManuscript];
    // }


}
