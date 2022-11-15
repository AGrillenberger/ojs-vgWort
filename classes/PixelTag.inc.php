<?php

define('PT_STATUS_ANY', '');
define('PT_STATUS_AVAILABLE', 0x01);
define('PT_STATUS_UNREGISTERED_ACTIVE', 0x02);
define('PT_STATUS_REGISTERED_ACTIVE', 0x03);
define('PT_STATUS_UNREGISTERED_REMOVED', 0x04);
define('PT_STATUS_REGISTERED_REMOVED', 0x05);
define('PT_STATUS_FAILED', 0x06); // only used for filtering, not saved in DB column status

define('TYPE_TEXT', 0x01);
define('TYPE_LYRIC', 0x02);

class PixelTag extends DataObject {

    function getContextId() {
        return $this->getData('contextId');
    }

    function setContextId($contextId) {
        return $this->setData('contextId', $contextId);
    }

    function getSubmissionId() {
        return $this->getData('submissionId');
    }

    function setSubmissionId($submissionId) {
        return $this->setData('submissionId', $submissionId);
    }

    function &getSubmission() {
        // $submissionDao = Application::getDAO('SubmissionDAO');
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($this->getSubmissionId());
        return $submission;
    }

    function getChapterId() {
        return $this->getData('chapterId');
    }

    function setChapterId($chapterId) {
        return $this->setData('chapterId', $chapterId);
    }

    function &getChapter() {
        $chapterDao = DAORegistry::getDAO('ChapterDAO');
        $chapter = $chapterDao->getChapter($this->getChapterId());
        return $chapter;
    }

    function getPrivateCode() {
        return $this->getData('privateCode');
    }

    function setPrivateCode($privateCode) {
        return $this->setData('privateCode', $privateCode);
    }

    function getPublicCode() {
        return $this->getData('publicCode');
    }

    function setPublicCode($publicCode) {
        return $this->setData('publicCode', $publicCode);
    }

    function getDomain() {
        return $this->getData('domain');
    }

    function setDomain($domain) {
        return $this->setData('domain', $domain);
    }

    function getDateOrdered() {
        return $this->getData('dateOrdered');
    }

    function setDateOrdered($dateOrdered) {
        return $this->setData('dateOrdered', $dateOrdered);
    }

    function getDateAssigned() {
        return $this->getData('dateAssigned');
    }

    function setDateAssigned($dateAssigned) {
        return $this->setData('dateAssigned', $dateAssigned);
    }

    function getDateRegistered() {
        return $this->getData('dateRegistered');
    }

    function setDateRegistered($dateRegistered) {
        return $this->setData('dateRegistered', $dateRegistered);
    }

    function getDateRemoved() {
        return $this->getData('dateRemoved');
    }

    function setDateRemoved($dateRemoved) {
        return $this->setData('dateRemoved', $dateRemoved);
    }

    function getStatus() {
        return $this->getData('status');
    }

    function setStatus($status) {
        return $this->setData('status', $status);
    }

    function getStatusString() {
        switch ($this->getData('status')) {
            case PT_STATUS_AVAILABLE:
                return __('plugins.generic.vgWort.pixelTag.status.available');
            case PT_STATUS_UNREGISTERED_ACTIVE:
                if (!$this->isPublished()) {
                    return __('plugins.generic.vgWort.pixelTag.status.unregistered.active.notPublished');
                }
                return __('plugins.generic.vgWort.pixelTag.status.unregistered.active');
            case PT_STATUS_UNREGISTERED_REMOVED:
                return __('plugins.generic.vgWort.pixelTag.status.unregistered.removed');
            case PT_STATUS_REGISTERED_ACTIVE:
                return __('plugins.generic.vgWort.pixelTag.status.registered.active');
            case PT_STATUS_REGISTERED_REMOVED:
                return __('plugins.generic.vgWort.pixelTag.status.registered.removed');
            case PT_STATUS_FAILED:
                return __('plugins.generic.vgWort.pixelTag.status.failed');
            default:
                return __('plugins.generic.vgWort.pixelTag.status');
        }
    }

    function getTextType() {
        return $this->getData('textType');
    }

    function setTextType($textType) {
        return $this->setData('textType', $textType);
    }

    function getTextTypeOptions() {
        static $textTypeOptions = array(
            TYPE_TEXT => 'plugins.generic.vgWort.pixelTag.textType.text',
            TYPE_LYRIC => 'plugins.generic.vgWort.pixelTag.textType.lyric'
        );
        return $textTypeOptions;
    }

    function getMessage() {
        return $this->getData('message');
    }

    function setMessage($message) {
        return $this->setData('message', $message);
    }

    function isPublished() {
        $submission = $this->getSubmission();

        if ($submission->getData('status') == STATUS_PUBLISHED) {
            return true;
        }
        // if ($submission) {
            // error_log("PixelTag: isPublished(): " . var_export($submission,true));
            // $issueDao = DAORegistry::getDAO('IssueDAO');
            // $issue = $issueDao->getBySubmissionId($submission->getId());
            // if (isset($issue) && !empty($issue)) {
            //     if ($issue->getPublished()) return true;
            // }
        // }
    }
}

?>
