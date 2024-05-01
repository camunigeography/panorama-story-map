const panoramaStoryMap = (function ()
{
	'use strict';
	
	
	// Class properties
	let _map;
	let _settings;
	
	
	return {
		
		// Main entry point
		initialise: function (config)
		{
			// Obtain the config
			_settings = config;
			
			// Create the map
			panoramaStoryMap.createMap ();
			
			// Load the data
			panoramaStoryMap.loadData ();
		},
		
		
		// Map
		createMap: function ()
		{
			// Create the map
			_map = new maplibregl.Map ({
				container: 'map',
				style: `https://api.maptiler.com/maps/hybrid/style.json?key=${_settings.maptilerApiKey}`,
				center: [0, 0], // lng, lat
				hash: true,
				zoom: 1,
				maxZoom: 13
			});
			
			// Enable controls
			_map.addControl (new maplibregl.NavigationControl ());
			
			// Add terrain; see: https://www.maptiler.com/news/2022/05/maplibre-v2-add-3d-terrain-to-your-map/
			_map.on ('load', function () {
				_map.addSource ('terrain', {
					type: 'raster-dem',
					url: `https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key=${_settings.maptilerApiKey}`,
				});
				_map.setTerrain ({
					source: 'terrain',
					exaggeration: 1.5
				});
			});
			
			// Fly in after initial load
			_map.on ('load', function () {
				_map.flyTo ({
					duration: 2000,
					essential: true,
					center: _settings.flyTo.center,
					zoom: _settings.flyTo.zoom,
					pitch: _settings.flyTo.pitch,
				});
			});
		},
		
		
		// Load data
		loadData: function ()
		{
			// Load and show the data
			_map.on ('load', function () {
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
							html    += '<p><a href="' + sceneUrl + '"><img src="' + feature.properties.thumbnail + '"></a></p>';
							return html;
						};
						
						// Add each marker
						for (const feature of geojson.features) {
							const image = createIconDom ('images/marker.png', [60, 60]);	// Icon from https://emojipedia.org/volcano/
							const popup = new maplibregl.Popup ().setHTML (popupHtml (feature));
							new maplibregl.Marker ({element: image})
								.setLngLat (feature.geometry.coordinates)
								.setPopup (popup)
								.addTo (_map);
						};
					});
			});
		}
	};
} ());