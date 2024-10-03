jQuery(function($) {

var data = window.trainings_data.data;

var showMap = $("#res_map").length;

var map, cluster;

if(showMap) {
 	map = resMap.init(resMap.getCenter(data.cities));
	cluster = resMap.initCluster({
		url: data.marker_url_cluster,
		width: 40,
		height: 47,
		textColor: "#fff",
		textSize: 14,
		anchor: [ 8, 0 ],
		offset: [ 18, 42 ]
	});
}

var markerIcon = {
	anchor: new google.maps.Point(18, 42),
	scaledSize: new google.maps.Size(40, 52),
	url: data.marker_url
};

var $selectCityHeading = $(".res-trainings .res-select-city"),
	$selectGymHeading = $(".res-trainings .res-select-gym"),
	$backBtn = $(".res-trainings .res-back");

var $gymList = $(".res-trainings .res-gym-list");

function displayCitiesMap() {
	$selectGymHeading.fadeOut(function() {
		$selectCityHeading.fadeIn();
	});

	$gymList.find(".res-city-gyms").slideUp();

	if(!showMap)
		return;

	resMap.clearMarkers();

	google.maps.event.addListenerOnce(map, "bounds_changed", function() {
		cluster.resetViewport();
		cluster.redraw();
	});

	resMap.fitPoints(data.cities);

	data.cities.forEach(function(city) {
		var marker = new google.maps.Marker({
			position: city,
			title: city.name,
			icon: markerIcon
		});

		marker.addListener('click', function() {
			displayGymsMap(city);
		});

		resMap.addMarker(marker);
	});

	cluster.resetViewport();
	cluster.redraw();
}

function displayGymsMap(city) {
	$selectCityHeading.fadeOut(function() {
		$selectGymHeading.fadeIn();
	});

	$gymList.find(".res-city").each(function() {
		var $t = $(this);

		if($t.data("city-id") === city.term_id)
			$t.find(".res-city-gyms").slideDown();
		else
			$t.find(".res-city-gyms").slideUp();
	});

	if(!showMap)
		return;

	var gyms = data.gyms.filter(function(gym) {
		return gym.city_id && gym.city_id === city.term_id;
	});

	if(!gyms.length)
		return;

	resMap.clearMarkers();

	google.maps.event.addListenerOnce(map, "bounds_changed", function() {
		cluster.resetViewport();
		cluster.redraw();
	});

	resMap.fitPoints(gyms);

	gyms.forEach(function(gym) {
		var marker = new google.maps.Marker({
			position: gym,
			title: gym.name,
			icon: markerIcon
		});

		marker.addListener('click', function() {
			location.href = gym.url;
		});

		resMap.addMarker(marker);
	});

	cluster.resetViewport();
	cluster.redraw();
}

$backBtn.click(function(e) {
	e.preventDefault();

	displayCitiesMap();
});

$gymList.find(".res-city .res-city-title").click(function(e) {
	e.preventDefault();

	var $btn = $(this);

	var city = data.cities.filter(function(city) {
		return city.term_id === $btn.parent().data("city-id");
	});

	if(city.length)
		displayGymsMap(city[0]);
});

displayCitiesMap();

});
