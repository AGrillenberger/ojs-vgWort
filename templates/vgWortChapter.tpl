{**
 * plugins/generic/vgWort/templates/vgWortCardNo.tpl
 *
 * Copyright (c) 2018 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the file LICENSE.
 *
 * Edit VG Wort card number for an user or author (in the user, profile and author form)
 *
 *}
<!-- VG Wort -->
{fbvFormSection title="plugins.generic.vgWort.pixelTag.textType"}
    {translate key="plugins.generic.vgWort.pixelTag.textType.description"}
    {fbvElement
        type="select"
        id="vgWortTextType"
        name="vgWort::texttype"
        from=$vgWortTextTypes
        selected=$vgWortTextType
        translate=true
        size=$fbvStyles.size.SMALL
    }
{/fbvFormSection}

{fbvFormArea title="plugins.generic.vgWort.pixelTag"}
    <!-- {fbvElement type="text" id="vgWortAssignRemoveCheckbox" value="" label="vgWortAssignRemoveCheckbox"} -->
    {fbvFormSection list="true"}
        {fbvElement
            type="radio"
            id="vgWortAssignPixelTag"
            name="vgWortAssignRemoveCheckbox"
            value="vgWortAssignPixelTag"
            label="plugins.generic.vgWort.pixelTag.assign"
            checked=$vgWortAssignRemoveCheckbox|compare:vgWortAssignPixelTag
        }
        {fbvElement
            type="radio"
            id="vgWortRemovePixelTag"
            name="vgWortAssignRemoveCheckbox"
            value="vgWortRemovePixelTag"
            label="plugins.generic.vgWort.pixelTag.remove"
            checked=$vgWortAssignRemoveCheckbox|compare:vgWortRemovePixelTag
        }
    {/fbvFormSection}
{/fbvFormArea}
<!-- /VG Wort -->
