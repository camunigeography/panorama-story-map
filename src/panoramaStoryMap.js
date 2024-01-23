function panoramaStoryMap ()
{
	// Create the map
	const map = L.map('map').setView([-43.1210, -72.3931], 9);
	
	// Add tiles
	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
	}).addTo(map);
	
	// Load and show the data
	fetch ('api/locations')
		.then (function (response) {return response.json ();})
		.then (function (data) {
			L.geoJson (data, {
				
				// Set icon; from: https://emojipedia.org/volcano/
				pointToLayer: function (feature, latlng) {
					return L.marker (latlng, {
						icon: L.icon ({iconUrl: 'images/volcano.png', iconSize: [32,32], popupAnchor: [0,-16]})
					});
				},
				
				// Set popup
				onEachFeature: function (feature, layer) {
					const sceneUrl = 'scenes/' + feature.properties.id + '/';
					let popupHtml = '<h3><a href="' + sceneUrl + '">' + feature.properties.title + '</a></h3>';
					popupHtml    += '<p>' + feature.properties.description + '</p>';
					popupHtml    += '<p><a href="' + sceneUrl + '"><img src="thumbnails/' + feature.properties.id + '.jpg" width="300" height="225" border="0"></a></p>"';
					layer.bindPopup (popupHtml);
				}
			}).addTo(map);
		});
}
