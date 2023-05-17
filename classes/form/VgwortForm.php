<?php

namespace APP\plugins\generic\vgwort;

use \PKP\components\forms\FormComponent;

define('FORM_VGWORT', 'vgwortform');

class VgwortForm extends FormComponent {

    public $id = FORM_VGWORT;

    public $method = 'PUT';

    public function __construct($action, $locales, $context, $submission) {
        // // Define the settings template and store a copy of the plugin object.
        // parent::__construct($plugin->getTemplateResource('settings.tpl'));
        // $this->plugin = $plugin;
        // error_log("*** VGWortForm class ***");
        $this->action = $action;
        $this->locales = $locales;
        $this->successMessage = "Success!";

        $this->pixelTagStatusLabels = [
            0 => __('plugins.generic.vgWort.pixelTag.status.notassigned'),
            PT_STATUS_REGISTERED_ACTIVE => __('plugins.generic.vgWort.pixelTag.status.registered.active'),
            PT_STATUS_UNREGISTERED_ACTIVE => __('plugins.generic.vgWort.pixelTag.status.unregistered.active'),
            PT_STATUS_REGISTERED_REMOVED => __('plugins.generic.vgWort.pixelTag.status.registered.removed'),
            PT_STATUS_UNREGISTERED_REMOVED => __('plugins.generic.vgWort.pixelTag.status.unregistered.removed')
        ];

        $pixelTagDao = DAORegistry::getDAO('PixelTagDAO');
        $pixelTag = $pixelTagDao->getPixelTagBySubmissionId($submission->getId());
        // $pixelTag = $pixelTagDao->getPixelTagsByContextId($context->getId());
        $publication = $submission->getLatestPublication(); // TODO: Why not getCurrentPublication()?

        if ($pixelTag == NULL) {
            $pixelTagStatus = 0;
            $pixelTagAssigned = false;
            $pixelTagRemoved = false;

            $publication->setData('vgWort::pixeltag::status', $pixelTagStatus);
            $publication->setData('vgWort::pixeltag::assign', $pixelTagAssigned);
            $publication->setData('vgWort::pixeltag::remove', $pixelTagRemoved);
        } else {
            $pixelTagStatus = $pixelTag->getStatus();
        }

        if ($pixelTagStatus == PT_STATUS_UNREGISTERED_ACTIVE || $pixelTagStatus == PT_STATUS_REGISTERED_ACTIVE) {
            $pixelTagAssigned = true;
            $pixelTagRemoved = false;

            $publication->setData('vgWort::pixeltag::assign', $pixelTagAssigned);
            $publication->setData('vgWort::pixeltag::remove', $pixelTagRemoved);
        } elseif ($pixelTagStatus == PT_STATUS_UNREGISTERED_REMOVED || $pixelTagStatus == PT_STATUS_REGISTERED_REMOVED) {
            $pixelTagAssigned = false;
            $pixelTagRemoved = true;

            $publication->setData('vgWort::pixeltag::assign', $pixelTagAssigned);
            $publication->setData('vgWort::pixeltag::remove', $pixelTagRemoved);
        }

        $this->addField(new \PKP\components\forms\FieldSelect('vgWort::texttype', [
            'label' => __('plugins.generic.vgWort.pixelTag.textType'),
            'description' => __('plugins.generic.vgWort.pixelTag.textType.description'),
            'value' => TYPE_TEXT,
            'options' => [
                [
                    'value' => TYPE_TEXT,
                    'label' => __('plugins.generic.vgWort.pixelTag.textType.text')
                ],
                [
                    'value' => TYPE_LYRIC,
                    'label' => __('plugins.generic.vgWort.pixelTag.textType.lyric')
                ]
            ]
        ]));

        $this->addField(new \PKP\components\forms\FieldOptions('vgWort::pixeltag::assign', [
            'label' => __('plugins.generic.vgWort.pixelTag'),
            'description' => 'Status: ' . $this->pixelTagStatusLabels[$pixelTagStatus],
            'value' => $pixelTagAssigned,
            'type' => 'radio',
            'options' => [
                [
                    'value' => true,
                    'label' => __('plugins.generic.vgWort.pixelTag.assign'),
                    'disabled' => $pixelTagAssigned
                ],
                [
                    'value' => false,
                    'label' => __('plugins.generic.vgWort.pixelTag.remove'),
                    'disabled' => $pixelTagRemoved || $pixelTagStatus == 0
                ]
            ]
        ]));
    }
}

?>
