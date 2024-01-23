

<!-- Leaflet map -->
<link rel="stylesheet" href="js/lib/leaflet/dist/leaflet.css" />
<script src="js/lib/leaflet/dist/leaflet.js"></script>

<!-- Application logic -->
<script src="src/panoramaStoryMap.js"></script>
<script>
	document.addEventListener ('DOMContentLoaded', function () {
		const config = {$configJson};
		new panoramaStoryMap (config);
	});
</script>


<p>Browse our map to hear and see volcano voices from around the sites, in a 360° scene.</p>
<p>Within each scene, you can click on an ⓘ button to listen, see, or read more.</p>

<div id="map"></div>
