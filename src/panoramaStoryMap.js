const panoramaStoryMap = (function ()
{
	'use strict';
	
	
	// Class properties
	let _map;
	let _settings;
	let _mapZoomEnough = false;
	
	
	return {
		
		// Main entry point
		initialise: function (config)
		{
			// Obtain the config
			_settings = config;
			
			// Create the map
			panoramaStoryMap.createMap ();
			
			// Add minZoom state handler
			panoramaStoryMap.zoomState ();
			
			// Add the marker layer
			panoramaStoryMap.addMarkerLayer ();
		},
		
		
		// Map
		createMap: function ()
		{
			// Create the map
			_map = new maplibregl.Map ({
				container: 'map',
				style: `https://api.maptiler.com/maps/hybrid/style.json?key=${_settings.maptilerApiKey}`,
				center: [0, 0], // lng, lat
				//hash: true,
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
		
		
		// Function to handle minZoom requirement
		zoomState: function ()
		{
			// Handler function to set state
			const setZoomState = function () {
				_mapZoomEnough = (_map.getZoom () >= _settings.minZoom);
				document.querySelector('#map').classList.toggle ('zoomedout', !_mapZoomEnough);
			};
			
			// Set state on start and upon map move
			setZoomState ();
			_map.on ('moveend', function () {
				setZoomState ();
			});
			
			// If zoomed out, make a click on the map be an implied zoom in
			_map.on ('click', function (e) {
				if (!_mapZoomEnough) {
					const newZoom = _settings.minZoom;
					_map.flyTo ({zoom: newZoom, center: e.lngLat});
				}
			});
		},
		
		
		// Add marker layer
		addMarkerLayer: function ()
		{
			// Function to load the markers
			fetch ('api/locations')
			.then (function (response) {return response.json ();})
			.then (function (geojson) {
				
				// Create a registry of markers
				let markers = [];
				
				// Define a function to load markers
				const loadMarkers = function ()
				{
					// Remove any existing markers
					markers.forEach (function (marker) {
						marker.remove ();
					});
					
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
						
						// Set pointer, if sufficiently zoomed
						if (_mapZoomEnough) {
							marker.style.cursor = 'pointer';
						}
						
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
						const marker = new maplibregl.Marker ({element: image})
							.setLngLat (feature.geometry.coordinates)
							.addTo (_map);
						
						// Add popup, if sufficiently zoomed
						if (_mapZoomEnough) {
							const popup = new maplibregl.Popup ().setHTML (popupHtml (feature));
							marker.setPopup (popup);
						}
						
						// Add marker to registry, so it can be later destroyed if needed
						markers.push (marker);
					};
				};
						
				// Load and show the data, on load and on moveend
				_map.on ('load', loadMarkers);
				_map.on ('moveend', loadMarkers);
			});
		}
	};
} ());