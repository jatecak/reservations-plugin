var resMap = window.resMap = {
	map: null,
	cluster: null,

	markers: [],

	init: function(center) {
		this.map = new google.maps.Map(jQuery("#res_map")[0], {
			zoom: 7,
			center: center,
			scrollwheel: false,
			fullscreenControl: false,
			mapTypeControl: false,
			streetViewControl: true,
			styles: [{"featureType":"landscape","stylers":[{"saturation":-100},{"lightness":65},{"visibility":"on"}]},{"featureType":"poi","stylers":[{"saturation":-100},{"lightness":51},{"visibility":"simplified"}]},{"featureType":"road.highway","stylers":[{"saturation":-100},{"visibility":"simplified"}]},{"featureType":"road.arterial","stylers":[{"saturation":-100},{"lightness":30},{"visibility":"on"}]},{"featureType":"road.local","stylers":[{"saturation":-100},{"lightness":40},{"visibility":"on"}]},{"featureType":"transit","stylers":[{"saturation":-100},{"visibility":"simplified"}]},{"featureType":"administrative.province","stylers":[{"visibility":"off"}]},{"featureType":"water","elementType":"labels","stylers":[{"visibility":"on"},{"lightness":-25},{"saturation":-100}]},{"featureType":"water","elementType":"geometry","stylers":[{"hue":"#ffff00"},{"lightness":-25},{"saturation":-97}]}]
		});

		return this.map;
	},
	initCluster: function(style) {
		this.cluster = new MarkerClusterer(this.map, [], {
			averageCenter: true,
			gridSize: 30,
			styles: [
				style
			]
		});

		return this.cluster;
	},

	setCenter: function(points) {
		this.map.setCenter(this.getCenter(points));
	},
	fitPoints: function(points) {
		this.map.fitBounds(this.getBounds(points));
	},

	clearMarkers: function() {
		if(this.cluster) {
			this.cluster.clearMarkers();
			return;
		}

		var marker;
		while((marker = markers.pop())) {
			marker.setMap(null);
		}
	},
	addMarker: function(marker) {
		if(this.cluster) {
			this.cluster.addMarker(marker);
			return;
		}

		marker.setMap(this.map);
		this.markers.push(marker);
	},

	getBounds(points) {
		var bounds = new google.maps.LatLngBounds();
		points.forEach(function(point) {
			bounds.extend(point);
		});
		return bounds;
	},
	getCenter(points) {
		return this.getBounds(points).getCenter();
	}
};

