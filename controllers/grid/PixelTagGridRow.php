<?php

import('lib.pkp.classes.controllers.grid.GridRow');
import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

class PixelTagGridRow extends GridRow {

    function initialize($request, $template = null) {
        parent::initialize($request);

        $router = $request->getRouter();
        $pixelTagId = $this->getId();

        if(!empty($pixelTagId) && is_numeric($pixelTagId)) {
            $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
            $pixelTag = $pixelTagDao->getById($pixelTagId);
            $pixelTagStatus = $pixelTag->getStatus();

            switch ($pixelTagStatus) {
                case PT_STATUS_UNREGISTERED_ACTIVE:
                    if ($pixelTag->isPublished()) {
                        $this->addAction(
                            new LinkAction(
                                'register',
                                new RemoteActionConfirmationModal(
                                    $request->getSession(),
                                    __('plugins.generic.vgWort.pixelTags.register.confirm'),
                                    __('plugins.generic.vgWort.pixelTags.register'),
                                    $router->url(
                                        $request,
                                        NULL,
                                        NULL,
                                        'registerPixelTag',
                                        NULL,
                                        array('pixelTagId' => $pixelTagId)
                                    ),
                                    'modal_confirm'
                                ),
                                __('plugins.generic.vgWort.pixelTags.register'),
                                'advance'
                            )
                        );
                    }
                    break;
                default:
                    break;
            }
        }
    }
}

?>
