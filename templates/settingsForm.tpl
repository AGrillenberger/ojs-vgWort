{**
 * plugins/generic/vgWort/templates/settingsForm.tpl
 *
 * Copyright (c) 2018 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Copyright (c) 2022 Heidelberg University Library
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * VG Wort Plugin Settings
 *}

<script>
    $(function() {ldelim}
        $('#vgWortPluginSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});

    {literal}
    function clearDate() {
        $('[id^="dateInYear"]').val('');
    }
    {/literal}
</script>

<form
    class="pkp_form"
    id="vgWortPluginSettings"
    action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
    method="POST"
    >

    <!-- Always add the csrf token to secure the form -->
    {csrf}

    {include
        file="controllers/notification/inPlaceNotification.tpl"
        notificationId="usageStatsSettingsFormNotification"
    }

    {fbvFormArea
        id="vgWortUserIdPassword"
        title="plugins.generic.vgwort.settings.vgWortUserIdPassword"
        class="border"
    }
        <p>{translate key="plugins.generic.vgwort.settings.vgWortUserIdPassword.description"}</p>

        {fbvFormSection}
            {fbvElement
                type="text"
                name="vgWortUserId"
                id="vgWortUserId"
                value=$vgWortUserId
                label="plugins.generic.vgwort.settings.vgWortUserId"
                required=true
                size=$fbvStyles.size.MEDIUM
            }
        <!-- {/fbvFormSection}

        {fbvFormSection} -->
            {fbvElement
                type="text"
                password=true
                name="vgWortUserPassword"
                id="vgWortUserPassword"
                value=$vgWortUserPassword
                label="plugins.generic.vgwort.settings.vgWortUserPassword"
                required=true
                size=$fbvStyles.size.MEDIUM
            }
        {/fbvFormSection}
    {/fbvFormArea}

    {fbvFormArea
        id="vgWortRegistration"
        title="plugins.generic.vgwort.settings.vgWortRegistration"
        class="border"
    }
        <p>{translate key="plugins.generic.vgwort.settings.vgWortRegistration.description"}</p>

        {fbvFormSection}
            {fbvElement
                type="text"
                id="dateInYear"
                name="dateInYear"
                label="plugins.generic.vgwort.settings.vgWortRegistration.dateInYear"
                value=$dateInYear|date_format:$dateFormatShort
                size=$fbvStyles.size.MEDIUM
                class="datepicker"
                inline=true
            }
            <a href="#" onclick="javascript:clearDate()">{translate key="plugins.generic.vgwort.settings.clearDate"}</a>
        <!-- {/fbvFormSection}

        {fbvFormSection} -->
            {fbvElement
                type="text"
                id="daysAfterPublication"
                name="daysAfterPublication"
                label="plugins.generic.vgwort.settings.vgWortRegistration.daysAfterPublication"
                value=$daysAfterPublication
                size=$fbvStyles.size.MEDIUM
            }
        {/fbvFormSection}
    {/fbvFormArea}

    {fbvFormArea
        id="vgWortTestModeArea"
        title="plugins.generic.vgwort.settings.vgWortTestMode"
        class="border"
    }
        {fbvFormSection list="true"}
            {fbvElement
                type="checkbox"
                id="vgWortTestAPI"
                name="vgWortTestAPI"
                label="plugins.generic.vgwort.settings.vgWortTestMode.description"
                checked=$vgWortTestAPI|compare:true
            }
        {/fbvFormSection}
    {/fbvFormArea}

    <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
    {fbvFormButtons submitText="common.save"}
</form>
