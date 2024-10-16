

<!-- MapLibre GL JS map -->
<link rel="stylesheet" href="js/lib/maplibre-gl/dist/maplibre-gl.css" />
<script src="js/lib/maplibre-gl/dist/maplibre-gl.js"></script>

<!-- Application logic -->
<script src="src/panoramaStoryMap.js"></script>
<script>
	document.addEventListener ('DOMContentLoaded', function () {
		const config = {$configJson};
		panoramaStoryMap.initialise (config);
	});
</script>

{if $userIsAdministrator}
	<p class="actions right"><a href="{$baseUrl}/settings.html#form_introductionHtml"><img src="/images/icons/pencil.png" class="icon" /> Edit introduction text</a></p>
{/if}
{$introductionHtml}

<div id="map"></div>
