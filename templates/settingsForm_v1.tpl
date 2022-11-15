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
        // Attach the form handler.
        $('#vgWortSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});

    {literal}
        <!--
        // function to clear the dateInYear date filed
        function clearDate() {
            $('[id^="dateInYear"]).val('');
        }
        // -->
    {/literal}
</script>

<form>
    {csrf}
    {include file="controllers/notification/inPlaceNotification.tpl" notificationId="usageStatsSettingsFormNotification"}
    {fbvFormArea 
        id="vgWortUserIdPassword" 
        title="plugins.generic.vgWort.settings.vgWortUserIdPassword" 
        class="border"
        }
        <p>{translate key="plugins.generic.vgWort.settings.vgWortUserIdPassword.description"}</p>
		{fbvFormSection}
			{fbvElement 
                type="text" 
                name="vgWortUserId" 
                id="vgWortUserId" 
                value=$vgWortUserId 
                label="plugins.generic.vgWort.settings.vgWortUserId" 
                required=true 
                size=$fbvStyles.size.MEDIUM
                }
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement 
                type="text" 
                password=true 
                name="vgWortUserPassword" 
                id="vgWortUserPassword" 
                value=$vgWortUserPassword 
                label="plugins.generic.vgWort.settings.vgWortUserPassword" 
                required=true 
                size=$fbvStyles.size.MEDIUM
                }
		{/fbvFormSection}
	{/fbvFormArea}
</form>
