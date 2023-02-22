<?php

import('lib.pkp.classes.controllers.grid.GridHandler');
import('plugins.generic.vgWort.controllers.grid.PixelTagGridRow');
import('lib.pkp.classes.linkAction.request.AjaxModal');

class PixelTagGridHandler extends GridHandler {

    function __construct() {
        parent::__construct();

        $this->addRoleAssignment(
            [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR],
            [
                'fetchGrid', 'fetchRow', 'pixelTagsTab', 'registerPixelTag', 'statusMessage'
            ]
        );
    }

    function authorize($request, &$args, $roleAssignments) {
        import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    function initialize($request, $args = NULL) {
        parent::initialize($request, $args);

        $router = $request->getRouter();
        $context = $request->getContext();

        AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_PKP_COMMON);

        import('plugins.generic.vgWort.controllers.grid.PixelTagGridCellProvider');
        $pixelTagGridCellProvider = new PixelTagGridCellProvider();

        $this->setTitle('plugins.generic.vgWort.distribution.pixelTags.tab');
        $this->addColumn(
            new GridColumn(
                'pixelTagCodes',
                'plugins.generic.vgWort.distribution.pixelTags.codes',
                NULL,
                'controllers/grid/gridCell.tpl',
                $pixelTagGridCellProvider,
                [
                    'html' => true,
                    'alignment' => COLUMN_ALIGNMENT_LEFT,
                    'width' => 20
                ]
            )
        );
        $this->addColumn(
            new GridColumn(
                'title',
                'submission.title',
                NULL,
                'controllers/grid/gridCell.tpl',
                $pixelTagGridCellProvider,
                [
                    'html' => true,
                    'alignment' => COLUMN_ALIGNMENT_LEFT
                ]
            )
        );
        $this->addColumn(
            new GridColumn(
                'chapter',
                'submission.chapter',
                NULL,
                'controllers/grid/gridCell.tpl',
                $pixelTagGridCellProvider,
                [
                    'html' => true,
                    'alignment' => COLUMN_ALIGNMENT_LEFT
                ]
            )
        );
        $this->addColumn(
            new GridColumn(
                'domain',
                'plugins.generic.vgWort.distribution.pixelTags.domain',
                NULL,
                'controllers/grid/gridCell.tpl',
                $pixelTagGridCellProvider,
                [
                    'alignment' => COLUMN_ALIGNMENT_LEFT,
                    'width' => 10
                ]
            )
        );
        $this->addColumn(
            new GridColumn(
                'status',
                'common.status',
                NULL,
                'controllers/grid/gridCell.tpl',
                $pixelTagGridCellProvider,
                [
                    'alignment' => COLUMN_ALIGNMENT_LEFT,
                    'width' => 10
                ]
            )
        );
        $this->addColumn(
            new GridColumn(
                'message',
                'plugins.generic.vgWort.distribution.pixelTags.message',
                NULL,
                'controllers/grid/gridCell.tpl',
                $pixelTagGridCellProvider,
                [
                    'html' => true,
                    'alignment' => COLUMN_ALIGNMENT_LEFT,
                    'width' => 10
                ]
            )
        );
        $this->addColumn(
            new GridColumn(
                'dates',
                'plugins.generic.vgWort.distribution.pixelTags.dates',
                NULL,
                'controllers/grid/gridCell.tpl',
                $pixelTagGridCellProvider,
                [
                    'html' => true,
                    'alignment' => COLUMN_ALIGNMENT_LEFT,
                    'width' => 10
                ]
            )
        );
    }

    function getRowInstance() {
        return new PixelTagGridRow();
    }

    function initFeatures($request, $args) {
        import('lib.pkp.classes.controllers.grid.feature.PagingFeature');
        return [new PagingFeature()];
    }

    function getFilterColumns() {
        return [
            PT_FIELD_PRIVCODE => __('plugins.generic.vgWort.pixelTag.privateCode'),
            PT_FIELD_PUBCODE => __('plugins.generic.vgWort.pixelTag.publicCode')
        ];
    }

    function renderFilter($request, $filterData = []) {
        $context = $request->getContext();
        $statusNames = [
            PT_STATUS_ANY => __('plugins.generic.vgWort.pixelTag.status.any'),
            PT_STATUS_AVAILABLE => __('plugins.generic.vgWort.pixelTag.status.available'),
            PT_STATUS_UNREGISTERED_ACTIVE => __('plugins.generic.vgWort.pixelTag.status.unregistered.active'),
            PT_STATUS_UNREGISTERED_REMOVED => __('plugins.generic.vgWort.pixelTag.status.unregistered.removed'),
            PT_STATUS_REGISTERED_ACTIVE => __('plugins.generic.vgWort.pixelTag.status.registered.active'),
            PT_STATUS_REGISTERED_REMOVED => __('plugins.generic.vgWort.pixelTag.status.registered.removed'),
            PT_STATUS_FAILED => __('plugins.generic.vgWort.pixelTag.status.failed')
        ];
        $filterColumns = $this->getFilterColumns();
        $allFilterData = array_merge(
            $filterData,
            [
                'columns' => $filterColumns,
                'status' => $statusNames,
                'gridId' => $this->getId(),
            ]
        );
        return parent::renderFilter($request, $allFilterData);
    }

    function getFilterSelectionData($request) {
        $search = (string) $request->getUserVar('search');
        $column = (string) $request->getUserVar('column');
        $statusId = (string) $request->getUserVar('statusId');
        return [
            'search' => $search,
            'column' => $column,
            'statusId' => $statusId
        ];
    }

    function getFilterForm() {
        $vgWortPlugin = PluginRegistry::getPlugin('generic', VGWORT_PLUGIN_NAME);
        $template = method_exists($vgWortPlugin, 'getTemplateResource')
            ? $vgWortPlugin->getTemplateResource('/controllers/grid/pixelTagGridFilter.tpl')
            : $vgWortPlugin->getTemplatePath() . '/controllers/grid/pixelTagGridFilter.tpl';
        return $template;
    }

    protected function getFilterValues($filter) {
        if (isset($filter['search']) && $filter['search']) {
            $search = $filter['search'];
        } else {
            $search = NULL;
        }
        if (isset($filter['column']) && $filter['column']) {
            $column = $filter['column'];
        } else {
            $column = NULL;
        }
        if (isset($filter['statusId']) && $filter['statusId'] != PT_STATUS_ANY) {
            $statusId = $filter['statusId'];
        } else {
            $statusId = NULL;
        }
        return [$search, $column, $statusId];
    }

    function loadData($request, $filter) {
        $sortBy = 'pixel_tag_id';
        $sortDirection = SORT_DIRECTION_DESC;
        $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
        $context = $request->getContext();
        $rangeInfo = $this->getGridRangeInfo($request, $this->getId());
        list($search, $column, $statusId) = $this->getFilterValues($filter);

        $pixelTags = $pixelTagDao->getPixelTagsByContextId(
            $context->getId(),
            $column,
            $search,
            $statusId,
            $rangeInfo,
            $sortBy,
            $sortDirection
        );

        return $pixelTags->toAssociativeArray();
    }

    function pixelTagsTab($args, $request) {
        $vgWortPlugin = PluginRegistry::getPlugin('generic', VGWORT_PLUGIN_NAME);
        $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign(
            'failedExists',
            $pixelTagDao->failedUnregisteredActiveExists($request->getContext()->getId())
        );
        $templateFile = method_exists($vgWortPlugin, 'getTemplateResource')
            ? $vgWortPlugin->getTemplateResource('pixelTagsTab.tpl')
            : $vgWortPlugin->getTemplatePath() . 'pixelTagsTab.tpl';
        return $templateMgr->fetchJson($templateMgr);
    }

    function statusMessage($args, $request) {
        $vgWortPlugin = PluginRegistry::getPlugin('generic', VGWORT_PLUGIN_NAME);
        $pixelTagId = $request->getUserVar('pixelTagId');
        $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
        $pixelTag = $pixelTagDao->getById($pixelTagId);
        //error_log("[PixelTagGridHandler] pixelTagId " . var_export($pixelTagId,true));
        //error_log("[PixelTagGridHandler] pixelTagDao " . var_export($pixelTagDao,true));
        //error_log("[PixelTagGridHandler] pixelTag " . var_export($pixelTag,true));
        $statusMessage = !empty($pixelTag->getMessage())
            ? $pixelTag->getMessage()
            : __('plugins.generic.vgWort.pixelTag.noStatusMessage');
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'statusMessage' => htmlentities($statusMessage)
        ]);
        return $templateMgr->fetchJson($vgWortPlugin->getTemplateResource('statusMessage.tpl'));
    }

    function registerPixelTag($args = [], $request) {
        $pixelTagId = $request->getUserVar('rowId');
        if (!$pixelTagId) $pixelTagId = $request->getUserVar('pixelTagId');

        $context = $request->getContext();
        $contextId = $context->getId();

        $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
        $pixelTag = $pixelTagDao->getById($pixelTagId, $contextId);

        if ($pixelTag && $pixelTag->getStatus() == PT_STATUS_UNREGISTERED_ACTIVE && !$pixelTag->getDateRemoved()) {
            $vgWortPlugin = PluginRegistry::getPlugin('generic', VGWORT_PLUGIN_NAME);
            import('plugins.generic.vgWort.classes.VGWortEditorAction');
            $vgWortEditorAction = new VGWortEditorAction($vgWortPlugin);
            $vgWortEditorAction->registerPixelTag($pixelTag, $request);
        }
        return DAO::getDataChangedEvent($pixelTagId);
    }
}

?>
