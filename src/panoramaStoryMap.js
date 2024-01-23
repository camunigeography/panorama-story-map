function panoramaStoryMap (config)
{
	// Create the map
	const map = new maplibregl.Map ({
		container: 'map',
		style: `https://api.maptiler.com/maps/hybrid/style.json?key=${config.maptilerApiKey}`,
		center: [-72.3931, -43.1210], // lng, lat
		//hash: true
		zoom: 9,
		maxZoom: 13
	});
	
	// Enable controls
	map.addControl (new maplibregl.NavigationControl ());
	
	// Load and show the data
	map.on ('load', function () {
		fetch ('api/locations')
			.then (function (response) {return response.json ();})
			.then (function (geojson) {
				
				// Construct the DOM representation for an icon
				// See: https://docs.mapbox.com/mapbox-gl-js/example/custom-marker-icons/
				// This all has to be done manually in the DOM, unfortunately, as Mapbox GL JS has no support for native dynamically-defined markers
				const createIconDom = function (iconUrl, iconSize)
				{
					// Create the marker
					var marker = document.createElement ('img');
					marker.setAttribute ('src', iconUrl);
					marker.className = 'marker';
					marker.style.width = iconSize[0] + 'px';
					marker.style.height = iconSize[1] + 'px';
					marker.style.cursor = 'pointer';
					
					// Return the marker
					return marker;
				};
				
				// Popup content
				const popupHtml = function (feature)
				{
					const sceneUrl = 'scenes/' + feature.properties.id + '/';
					let html = '<h3><a href="' + sceneUrl + '">' + feature.properties.title + '</a></h3>';
					html    += '<p>' + feature.properties.description + '</p>';
					html    += '<p><a href="' + sceneUrl + '"><img src="thumbnails/' + feature.properties.id + '.jpg" width="300" height="225" border="0"></a></p>"';
					return html;
				};
				
				// Add each marker
				for (const feature of geojson.features) {
					const image = createIconDom ('images/volcano.png', [60, 60]);	// Icon from https://emojipedia.org/volcano/
					const popup = new maplibregl.Popup ().setHTML (popupHtml (feature));
					new maplibregl.Marker ({element: image})
						.setLngLat (feature.geometry.coordinates)
						.setPopup (popup)
						.addTo (map);
				};
			});
	});
}
