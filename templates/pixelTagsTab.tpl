{**
 * plugins/generic/vgWort/templates/pixelTagsTab.tpl
 *
 * Copyright (c) 2018 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the file LICENSE.
 *
 * Pixel tags tab page
 *
 *}
<!-- VG Wort -->
{fbvFormSection}
	<p class="pkp_help">{translate key="plugins.generic.vgwort.pixelTag.LocalizationNotice"}</p>
{/fbvFormSection}
{if $failedExists}
	<div id="pixelTagsNotification" class="pkp_notification " style="display: block;">
		<div id="pkp_notification_pixel_tags">
			<span class="title">{translate key="notification.notification"}</span>
			<span class="pdescription">{translate key="plugins.generic.vgwort.notification.vgWortErrorExists"}</span>
		</div>
	</div>
{/if}
{**{url|assign:pixelTagsGridUrl router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.vgwort.controllers.grid.PixelTagGridHandler" op="fetchGrid" escape=false}*}
{capture assign="pixelTagsGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.vgwort.controllers.grid.PixelTagGridHandler" op="fetchGrid" escape=false}{/capture}
{load_url_in_div id="pixelTagsGridContainer" url=$pixelTagsGridUrl}
<!-- /VG Wort -->

