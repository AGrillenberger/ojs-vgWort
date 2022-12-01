<?php

use \GuzzleHttp\Exception\ClientException;
use \GuzzleHttp\Exception\ServerException;
use \GuzzleHttp\Exception\BadResponseException;

define('PIXEL_SERVICE', 'https://tom.vgwort.de/api/external/metis/rest/pixel/v1.0/order');
define('CHECK_AUTHOR', 'https://tom.vgwort.de/api/external/metis/rest/message/v1.0/checkAuthorRequest');
define('NEW_MESSAGE', 'https://tom.vgwort.de/api/external/metis/rest/message/v1.0/newMessageRequest');

// to just test the plugin, please use the VG Wort test portal:
define('PIXEL_SERVICE_TEST', 'https://tom-test.vgwort.de/api/external/metis/rest/pixel/v1.0/order');
define('CHECK_AUTHOR_TEST', 'https://tom-test.vgwort.de/api/external/metis/rest/message/v1.0/checkAuthorRequest');
define('NEW_MESSAGE_TEST', 'https://tom-test.vgwort.de/api/external/metis/rest/message/v1.0/newMessageRequest');

class VGWortEditorAction {
    var $_plugin;

    function __construct($plugin) {
        $this->_plugin = $plugin;
    }

    /**
     * Order pixel.
     *
     * @param integer $contextId
     */
    function orderPixel($contextId)
    {
        $vgWortPlugin = $this->_plugin;
        $vgWortUserId = $vgWortPlugin->getSetting($contextId, 'vgWortUserId');
        $vgWortUserPassword = $vgWortPlugin->getSetting($contextId, 'vgWortUserPassword');
        $vgWortTestAPI = $vgWortPlugin->getSetting($contextId, 'vgWortTestAPI');
        $vgWortAPI = PIXEL_SERVICE;

        if ($vgWortTestAPI) {
            $vgWortAPI = PIXEL_SERVICE_TEST;
        }

        $httpClient = Application::get()->getHttpClient();
        $data = ['count' => 1];

        try {
            if (!$vgWortPlugin->requirementsFulfilled()) {
                return [false, __('plugins.generic.vgWort.requirementsRequired')];
            }
            $response = $httpClient->request(
                'POST',
                $vgWortAPI,
                [
                    'json' => $data,
                    'auth' => [$vgWortUserId, $vgWortUserPassword]
                ]
            );
            $response = json_decode($response->getBody(), false);
            return [true, $response];
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBodyAsString = $response->getBody()->getContents();
                $statusCode = $response->getStatusCode();
                $reasonPhrase = $response->getReasonPhrase();
                return [false, __('plugins.generic.vgWort.order.errorCode') . $reasonPhrase];
            }
        }
        catch (\GuzzleHttp\Exception\ServerException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBodyAsString = $response->getBody()->getContents();
                $statusCode = $response->getStatusCode();
                $reasonPhrase = $response->getReasonPhrase();
                return [false, $reasonPhrase];
            }
        }
        catch (Exception $e) {
            error_log("[VG Wort] Exception: " . var_export($e->getResponse(),true));
        }
    }

    /**
     * Update database.
     *
     * @param integer $contextId
     * @param $result
     */
    function insertOrderedPixel($contextId, $result)
    {
        $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
        $pixels = $result->pixels;

        foreach ($pixels as $currPixel) {
            $pixelTag = new PixelTag();
            $pixelTag->setContextId($contextId);
            $pixelTag->setDomain($result->domain);
            $pixelTag->setDateOrdered(strtotime($result->orderDateTime));
            $pixelTag->setStatus(PT_STATUS_AVAILABLE);
            $pixelTag->setTextType(TYPE_TEXT);
            $pixelTag->setPrivateCode($currPixel->privateIdentificationId);
            $pixelTag->setPublicCode($currPixel->publicIdentificationId);
            $pixelTagId = $pixelTagDao->insertObject($pixelTag);
        }
    }

    /**
     * Check
     *
     * @param PixelTag $pixelTag
     */
    function check($pixelTag) {
        $submission = $pixelTag->getSubmission();
        $publication = $submission->getCurrentPublication();
        $publicationFormats = $publication->getData('publicationFormats');

        if ($submission->getData('status') != STATUS_PUBLISHED) {
            return [false, __('plugins.generic.vgWort.check.articleNotPublished')];
        } else {
            $supportedPublicationFormats = array_filter($publicationFormats, [$this, '_checkPublicationFormatSupported']);
            if (empty($supportedPublicationFormats)) {
                return [false, __('plugins.generic.vgWort.check.galleyRequired')];
            } else {
                foreach ($submission->getAuthors() as $author) {
                    $cardNo = $author->getData('vgWortCardNo');
                    if (!empty($cardNo)) {
                        $locale = $submission->getLocale();
                        $checkAuthorResult = $this->checkAuthor(
                            $pixelTag->getContextId(),
                            $cardNo, $author->getFamilyName($locale)
                        );
                        if (!$checkAuthorResult[0]) {
                            return array(false, $checkAuthorResult[1]);
                        }
                    }
                }
            }
        }
        return [true, ''];
    }

    /**
     * Register pixel tag.
     *
     * @param PixelTag $pixelTag
     * @param Request $request
     * @param integer $contextId
     */
    function registerPixelTag($pixelTag, $request, $contextId = NULL)
    {
        $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
        $checkResult = $this->check($pixelTag, $request);
        $isError = !$checkResult[0];
        $errorMsg = NULL;
        if ($isError) {
            $errorMsg = $checkResult[1];
        } else {
            $registerResult = $this->newMessage($pixelTag, $request, $contextId);
            $isError = !$registerResult[0];
            $errorMsg = $registerResult[1];
            if (!$isError) {
                $pixelTag->setDateRegistered(Core::getCurrentDate());
                $pixelTag->setMessage(NULL);
                $pixelTag->setStatus(PT_STATUS_REGISTERED_ACTIVE);
                $pixelTagDao->updateObject($pixelTag);
                $this->_removeNotification($pixelTag);
                $notificationType = NOTIFICATION_TYPE_SUCCESS;
                $notificationMsg = __('plugins.generic.vgWort.pixelTags.register.success');
            }
        }
        if ($isError) {
            $pixelTag->setMessage($errorMsg);
            $pixelTagDao->updateObject($pixelTag);
            $this->_createNotification($request, $pixelTag);
            $notificationType = NOTIFICATION_TYPE_FORM_ERROR;
            $notificationMsg = $errorMsg;
        }

        if (!defined('SESSION_DISABLE_INIT')) {
            $user = $request->getUser();
            if ($user) {
                import('classes.notification.NotificationManager');
                $notificationManager = new NotificationManager();
                $notificationManager->createTrivialNotification(
                    $user->getId(), $notificationType, ['contents' => $notificationMsg]
                );
            }
        }
    }

    /**
     * Checks for a matching pair of VG Wort card number and surname.
     *
     * @param integer $contextId
     * @param integer $cardNo
     * @param string $lastName
     */
    function checkAuthor($contextId, $cardNo, $lastName) {
        $vgWortPlugin = $this->_plugin;
        $vgWortUserId = $vgWortPlugin->getSetting($contextId, 'vgWortUserId');
        $vgWortUserPassword = $vgWortPlugin->getSetting($contextId, 'vgWortUserPassword');
        $vgWortTestAPI = $vgWortPlugin->getSetting($contextId, 'vgWortTestAPI');
        $vgWortAPI = CHECK_AUTHOR;

        if ($vgWortTestAPI) {
            $vgWortAPI = CHECK_AUTHOR_TEST;
        }

        $httpClient = Application::get()->getHttpClient();
        $data = [
            'cardNumber' => $cardNo,
            'surName' => $lastName
        ];

        try {
            if (!$vgWortPlugin->requirementsFulfilled()) {
                return [false, __('plugins.generic.vgWort.requirementsRequired')];
            }

            $response = $httpClient->request(
                'GET',
                $vgWortAPI,
                [
                    'json' => $data,
                    'auth' => [$vgWortUserId, $vgWortUserPassword]
                ]
            );

            $response = json_decode(json_encode($response->getBody()), false);

            return [true, $response];
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBodyAsString = $response->getBody()->getContents();
                $statusCode = $response->getStatusCode();
                $reasonPhrase = $response->getReasonPhrase();
                return [false, __('plugins.generic.vgWort.order.errorCode') . $reasonPhrase];
            }
        }
        catch (\GuzzleHttp\Exception\ServerException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBodyAsString = $response->getBody()->getContents();
                $statusCode = $response->getStatusCode();
                $reasonPhrase = $response->getReasonPhrase();
                return [false, $reasonPhrase];
            }
        }
        catch (Exception $e) {
            error_log("[VGWortEditorAction] checkAuthor() Exception: " . var_export($e->getResponse(),true));
        }
    }

    /**
     * Create a new message.
     *
     * @param PixelTag $pixelTag
     * @param Request $request
     * @param integer $contextId
     */
    function newMessage($pixelTag, $request, $contextId = NULL)
    {
        $vgWortPlugin = $this->_plugin;
        if (!isset($contextId)) {
            $contextId = $vgWortPlugin->getCurrentContextId();
        }
        $vgWortUserId = $vgWortPlugin->getSetting($contextId, 'vgWortUserId');
        $vgWortUserPassword = $vgWortPlugin->getSetting($contextId, 'vgWortUserPassword');
        $vgWortTestAPI = $vgWortPlugin->getSetting($contextId, 'vgWortTestAPI');
        $vgWortAPI = NEW_MESSAGE;
        if ($vgWortTestAPI) {
            $vgWortAPI = NEW_MESSAGE_TEST;
        }

        $vgWortPlugin->import('classes.PixelTag'); // TODO: Brauchen wir das?
        $submission = $pixelTag->getSubmission();

        $locale = $submission->getLocale();

        // Get authors and translators
        $contributors = $submission->getAuthors();
        $submissionAuthors = array_filter($contributors, [$this, '_filterChapterAuthors']);
        $submissionTranslators = array_filter($contributors, [$this, '_filterTranslators']);
        assert(!empty($submissionAuthors) || !empty($submissionTranslators));
        $participants = [];
        if (!empty($submissionAuthors)) {
            foreach ($submissionAuthors as $author) {
                $cardNo = $author->getData('vgWortCardNo');
                if (!empty($cardNo)) {
                    $participants[] = [
                        'cardNumber' => $author->getData('vgWortCardNo'),
                        'firstName' => mb_substr($author->getGivenName($locale), 0, 39, 'utf8'),
                        'involvement' => 'AUTHOR',
                        'surName' => $author->getFamilyName($locale)
                    ];
                } else {
                    $participants[] = [
                        'firstName' => mb_substr($author->getGivenName($locale), 0, 39, 'utf8'),
                        'involvement' => 'AUTHOR',
                        'surName' => $author->getFamilyName($locale)
                    ];
                };
            };
        }
        if (!empty($submissionTranslators)) {
            foreach ($submissionTranslators as $translator) {
                $cardNo = $author->getData('vgWortCardNo');
                if (!empty($cardNo)) {
                    $participants[] = [
                        'cardNumber' => $translator->getData('vgWortCardNo'),
                        'firstName' => mb_substr($translator->getGivenName($locale), 0, 39, 'utf8'),
                        'involvement' => 'TRANSLATOR',
                        'surName' => $translator->getFamilyName($locale)
                    ];
                } else {
                    $participants[] = [
                        'firstName' => mb_substr($translator->getGivenName($locale), 0, 39, 'utf8'),
                        'involvement' => 'TRANSLATOR',
                        'surName' => $translator->getFamilyName($locale)
                    ];
                };
            };
        }

        $publication = $submission->getCurrentPublication();
        $publicationFormats = $publication->getData('publicationFormats');

        // Get publication formats that are allowed by VG Wort.
        $supportedPublicationFormats = array_filter($publicationFormats, [$this, '_checkPublicationFormatSupported']);
        foreach ($supportedPublicationFormats as $supportedPublicationFormat) {
            $submissionFiles = $vgWortPlugin->getSubmissionFiles($submission, $supportedPublicationFormat)->_current;
        }
        $webranges = [];

        $dispatcher = Application::get()->getDispatcher();
        foreach ($supportedPublicationFormats as $supportedPublicationFormat) {
            $submissionFiles = $vgWortPlugin->getSubmissionFiles($submission, $supportedPublicationFormat)->_current;
            $url = $dispatcher->url(
                $request,
                ROUTE_PAGE,
                NULL,
                'catalog',
                'view',
                [
                    $submission->getId(),
                    $supportedPublicationFormat->getId(),
                    $submissionFiles->getId()
                ]
            );
            $webrange = ['urls' => [$url]];
            $webranges[] = $webrange;

            $downlaodUrl1 = $dispatcher->url(
                $request,
                ROUTE_PAGE,
                NULL,
                'catalog',
                'view',
                [
                    $submission->getId(),
                    $submissionFiles->getId()
                ]
            );
            $webrange = ['urls' => [$downlaodUrl1]];
            $webranges[] = $webrange;

            $downlaodUrl2 = $dispatcher->url(
                $request,
                ROUTE_PAGE,
                NULL,
                'catalog',
                'view',
                [
                    $submission->getId(),
                    $submissionFiles->getId()
                ]
            );
            $webrange = ['urls' => [$downlaodUrl2]];
            $webranges[] = $webrange;
        }

        $dePublicationFormats = array_filter($supportedPublicationFormats, [$this, '_filterDEPublicationFormats']);
        if (!empty($dePublicationFormats)) {
            reset($dePublicationFormats);
            $publicationFormat = current($dePublicationFormats);
        } else {
            $enPublicationFormats = array_filter($supportedPublicationFormats, [$this, '_filterENPublicationFormats']);
            if (!empty($enPublicationFormats)) {
                reset($enPublicationFormats);
                $publicationFormat = current($enPublicationFormats);
            } else {
                reset($supportedPublicationFormats);
                $publicationFormat = current($supportedPublicationFormat);
            }
        }
        $publicationFormatFile = $vgWortPlugin->getSubmissionFiles($submission, $publicationFormat)->_current;

        $content = Services::get('file')->fs->read($publicationFormatFile->getData('path'));
        // $content = file_get_contents($publicationFormatFile->getData('path')); TODO: What's the difference?
        $publicationFormatFileType = $publicationFormatFile->getData('mimetype');

        if ($publicationFormatFileType == 'text/html' || $publicationFormatFileType == 'text/xml') {
            $text = ['plainText' => strip_tags($content)];
        } elseif ($publicationFormatFileType == 'application/pdf') {
            // base64_encode of pdf causes soapClient/Business Exception -> vgWort Errorcode 8
            // $content = file_get_contents($publicationFormatFile->getData('path'));
            $text = ['pdf' => base64_encode($content)];
        } elseif ($publicationFormatFileType == 'application/epub+zip') {
            // base64_encode of epub causes soapClient/Business Exception -> vgWort Errorcode 20
            $text = ['epub' => $content];
        }
        // TODO: XML?

        $submissionLocale = $submission->getLocale();
        $primaryLocale = AppLocale::getPrimaryLocale();

        $title = $submission->getTitle('de_DE');
        if (!isset($title) || $title == '') {
            $title = $submission->getTitle('en_US');
        }
        if (!isset($title) || $title == '') {
            $title = $submission->getTitle($submissionLocale);
        }
        if (!isset($title) || $title == '') {
            $title = $submission->getTitle($primaryLocale);
        }
        $shorttext = mb_substr($title, 0, 99, 'utf8');

        $isLyric = ($pixelTag->getTextType() == TYPE_LYRIC);

        $message = [
            'shorttext' => $shorttext,
            'text' => $text,
            'lyric' => $isLyric
        ];

        $httpClient = Application::get()->getHttpClient();
        $data = [
            'participants' => $participants,
            'privateidentificationid' => $pixelTag->getPrivateCode(),
            'messagetext' => $message,
            'webranges' => $webranges,
            "distributionRight" => true,
            "publicAccessRight" => true,
            "reproductionRight" => true,
            "rightsGrantedConfirmation" => true
        ];
        try {
            if (!$vgWortPlugin->requirementsFulfilled()) {
                return [false, __('plugins.generic.vgWort.requirementsRequired')];
            }
            $response = $httpClient->request(
                'POST',
                $vgWortAPI,
                [
                    'json' => $data,
                    'auth' => [$vgWortUserId, $vgWortUserPassword],
                    // 'debug' => $debug
                ]
            );
            $response = json_decode($response->getBody(), false);
            return [true, $response];
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBodyAsString = $response->getBody()->getContents();
                $statusCode = $response->getStatusCode();
                $reasonPhrase = $response->getReasonPhrase();
                return [false, __('plugins.generic.vgWort.order.errorCode') . $reasonPhrase];
            }
        }
        catch (\GuzzleHttp\Exception\ServerException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBodyAsString = $response->getBody()->getContents();
                $statusCode = $response->getStatusCode();
                $reasonPhrase = $response->getReasonPhrase();
                return [false, $reasonPhrase];
            }
        }
        catch (Exception $e) {
            // error_log("[VGWortEditorAction] newMessage Exception: " . var_export($e->getResponse(),true));
        }
    }

    /**
     * Check whether publication format is supported.
     *
     * @param PublicationFormat $publicationFormat
     * @return bool
     */
    function _checkPublicationFormatSupported($publicationFormat)
    {
        $submission = $this->getSubmissionByPublicationFormat($publicationFormat);
        $submissionFiles = $this->_plugin->getSubmissionFiles($submission, $publicationFormat)->_current;
        if (!$submissionFiles) {
            return false;
        }
        $megaByte = 1024*1024;
        if (round((int) $publicationFormat->getFileSize() / $megaByte > 15)) {
            return false;
        }
        return $this->_plugin->getSupportedFileTypes($submissionFiles->getData('mimetype'));
    }

//    /**
//     * Filter authors.
//     *
//     * @param $contributor
//     */
//    function _filterAuthors($contributor) {
//        $userGroup = $contributor->getUserGroup();
//        return $userGroup->getData('nameLocaleKey') == 'default.groups.name.author';
//    }

    /**
     * Filter volume editor.
     */
    function _filterVolumeEditors($contributor)
    {
        $userGroup = $contributor->getUserGroup();
        return $userGroup->getData('nameLocaleKey') == 'default.groups.name.volumeEditor';
    }

    /**
     * Filter chapter authors.
     */
    function _filterChapterAuthors($contributor)
    {
        $userGroup = $contributor->getUserGroup();
        return $userGroup->getData('nameLocaleKey') == 'default.groups.name.chapterAuthor';
    }

    /**
     * Filter translators.
     */
    function _filterTranslators($contributor)
    {
        $userGroup = $contributor->getUserGroup();
        return $userGroup->getData('nameLocaleKey') == 'default.groups.name.translator';
    }

    function getSubmissionByPublicationFormat($publicationFormat)
    {
        $publicationId = $publicationFormat->getData('publicationId');
        $publication = Services::get('publication')->get($publicationId);
        return Services::get('submission')->get($publication->getData('submissionId'));
    }

    function _filterDEPublicationFormats($publicationFormat)
    {
        $submission = $this->getSubmissionByPublicationFormat($publicationFormat);
        $submissionFile = $this->_plugin->getSubmissionFiles($submission, $publicationFormat);
        return $submissionFile->_current->getData('locale') == 'de_DE';
    }

    function _filterENPublicationFormats($publicationFormat) {
        $submission = $this->getSubmissionByPublicationFormat($publicationFormat);
        $submissionFiles = $this->_plugin->getSubmissionFiles($submission, $publicationFormat);
        return $submissionFiles->_current->getData('locale') == 'en_US';
    }

    function _filterDEGalleys($galley) {
        return $galley->getLocale() == 'de_DE';
    }

    function _filterENGalleys($galley) {
        return $galley->getLocale() == 'en_US';
    }

    function _removeNotification($pixelTag) {
        $submission = $pixelTag->getSubmission();
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $editorStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), WORKFLOW_STAGE_ID_PRODUCTION);
        $notificationDao = DAORegistry::getDAO('NotificationDAO');
        foreach ($editorStageAssignments as $editorStageAssignment) {
            $notificationDao->deleteByAssoc(
                ASSOC_TYPE_SUBMISSION,
                $submission->getId(),
                $editorStageAssignment->getUserId(),
                NOTIFICATION_TYPE_VGWORT_ERROR,
                $pixelTag->getContextId()
            );
        }
    }

    function _createNotification($request, $pixelTag) {
        $submission = $pixelTag->getSubmission();
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $editorStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), WORKFLOW_STAGE_ID_PRODUCTION);
        $notificationDao = DAORegistry::getDAO('NotificationDAO');
        foreach ($editorStageAssignments as $editorStageAssignment) {
            $notificationFactory = $notificationDao->getByAssoc(
                ASSOC_TYPE_SUBMISSION,
                $submission->getId(),
                $editorStageAssignment->getUserId(),
                NOTIFICATION_TYPE_VGWORT_ERROR,
                $pixelTag->getContextId()
            );
            if (!$notificationFactory->next()) {
                $notificationMgr = new NotificationManager();
                $notificationMgr->createNotification(
                    $request,
                    $editorStageAssignment->getUserId(),
                    NOTIFICATION_TYPE_VGWORT_ERROR,
                    $pixelTag->getContextId(),
                    ASSOC_TYPE_SUBMISSION,
                    $submission->getId(),
                    NOTIFICATION_LEVEL_NORMAL,
                    NULL,
                    true
                );
            }
        }
    }
}

?>
