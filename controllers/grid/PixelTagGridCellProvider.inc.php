<?php

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class PixelTagGridCellProvider extends GridCellProvider {

    function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
        $pixelTag = $row->getData();
        $columnId = $column->getId();
        assert(is_a($pixelTag, 'PixelTag') && !empty($columnId));

        import('lib.pkp.classes.linkAction.request.RedirectAction');
        switch ($columnId) {
            case 'message':
                if (!empty($pixelTag->getMessage())) {
                    $router = $request->getRouter();
                    return [
                        new LinkAction(
                            'failureMessage',
                            new AjaxModal(
                                $router->url(
                                    $request,
                                    NULL,
                                    NULL,
                                    'statusMessage',
                                    NULL,
                                    ['pixelTag' => $pixelTag->getId()]
                                ),
                                __('plugins.generic.vgWort.pixelTag.status.failed'),
                                'failureMessage'
                            ),
                            __('plugins.generic.vgWort.pixelTag.status.failed')
                        )
                    ];
                }
                break;
            case 'title':
                $this->_titleColumn = $column;
                $submission = $pixelTag->getSubmission();
                if ($submission) {
                    $title = $submission->getLocalizedTitle();
                    if (empty($title)) $title = __('common.untitled');
                    $authorsInTitle = $submission->getShortAuthorString();
                    $title = $authorsInTitle . '; ' . $title;
                    return [
                        new LinkAction(
                            'itemWorkflow',
                            new RedirectAction(
                                Services::get('submission')->getWorkflowUrlByUserRoles($submission)
                            ),
                            $title
                        )
                    ];
                }
                break;
            case 'chapter':
                $this->_titleColumn = $column;
                $submission = $pixelTag->getSubmission();
                $chapter = $pixelTag->getChapter();
                if ($chapter) {
                    $title = $chapter->getLocalizedTitle();
                    if (empty($title)) $title = __('common.untitled');
                    return [
                        new LinkAction(
                            'itemWorkflow',
                            new RedirectAction(
                                Services::get('submission')->getWorkflowUrlByUserRoles($submission)
                            ),
                            $title
                        )
                    ];
                }
                break;
        }
        return parent::getCellActions($request, $row, $column, $position);
    }

    function getTemplateVarsFromRowColumn($row, $column) {
        $pixelTag = $row->getData();
        $columnId = $column->getId();
        assert(is_a($pixelTag, 'PixelTag') && !empty($columnId));
        switch ($columnId) {
            case 'pixelTagCodes':
                return ['label' => $pixelTag->getPrivateCode() . '<br />' . $pixelTag->getPublicCode()];
            case 'domain':
                return ['label' => $pixelTag->getDomain()];
            case 'dates':
                $dateOrdered = $pixelTag->getDateOrdered()
                    ? strftime(Config::getVar('general', 'date_format_short'), strtotime($pixelTag->getDateOrdered()))
                    : '&mdash;';
                $dateAssigned = $pixelTag->getDateAssigned()
                    ? strftime(Config::getVar('general', 'date_format_short'), strtotime($pixelTag->getDateAssigned()))
                    : '&mdash;';
                $dateRegistered = $pixelTag->getDateRegistered()
                    ? strftime(Config::getVar('general', 'date_format_short'), strtotime($pixelTag->getDateRegistered()))
                    : '&mdash;';
                $dateRemoved = $pixelTag->getDateRemoved()
                    ? strftime(Config::getVar('general', 'date_format_short'), strtotime($pixelTag->getDateRemoved()))
                    : '&mdash;';
                return ['label' => $dateOrdered .'<br />' . $dateAssigned .'<br />' . $dateRegistered .'<br />' . $dateRemoved];
            case 'status':
                return ['label' => $pixelTag->getStatusString()];
            case 'message':
                if (!empty($pixelTag->getMessage())) {
                    return ['label' => ''];
                }
                return ['label' => '&mdash;'];
            case 'title':
                return ['label' => ''];
            case 'chapter':
                return ['label' => ''];
            default: assert(false); break;
        }
    }
}

?>
