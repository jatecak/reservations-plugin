jQuery(function($) {

var data = window.events_data.data;

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

var eventType = null, city = null;

function filterEvents() {
	if(city !== null) {
		$(".res-event-list-title").hide();

		var $title = $(".res-event-list-title-filtered");
		if(!$title.data("oldHtml"))
			$title.data("oldHtml", $title.html());

		$title.html($title.data("oldHtml").replace("%s", city.name)).show();
	} else {
		$(".res-event-list-title-filtered").hide();
		$(".res-event-list-title").show();
	}

	var empty = true;
	$(".res-event-list > li:not(.res-empty)").each(function() {
		var $t = $(this);

		if((city === null || parseInt($t.data("cityId"), 10) === city.term_id) && (eventType === null || $t.data("type") === eventType)) {
			empty = false;
			$(this).fadeIn();
		} else {
			$(this).fadeOut();
		}
	});

	if(empty)
		$(".res-event-list .res-empty").fadeIn();
	else
		$(".res-event-list .res-empty").fadeOut();
}

function displayCitiesMap() {
	if(!showMap)
		return;

	resMap.clearMarkers();

	resMap.fitPoints(data.cities);
	// google.maps.event.addListenerOnce(map, "bounds_changed", function() {
	// 	map.setZoom(Math.min(map.getZoom() - 1, 14));
	// });

	data.cities.forEach(function(_city) {
		var marker = new google.maps.Marker({
			position: _city,
			title: _city.name,
			icon: markerIcon
		});

		marker.addListener('click', function() {
			city = _city;
			filterEvents();
		});

		resMap.addMarker(marker);
	});
}

displayCitiesMap();

$(".res-expand").click(function(e) {
	e.preventDefault();

	var $expanded = $(this).closest("li").find(".res-event-expanded");

	if($expanded.is(":visible")) {
		$expanded.stop().slideUp();
	} else {
		$expanded.stop().slideDown({
			start: function () {
				$(this).css({
					display: "flex"
				});
	 		}
	 	});
	}
});

$(".res-subscribe.btn-disabled").click(function(e) {
	e.preventDefault();
});

$(".res-event-types a").click(function(e) {
	e.preventDefault();

	var $btns = $(this).closest("ul").children(),
		$li = $(this).closest("li");

	$btns.not($li).removeClass("res-active");
	$li.addClass("res-active");

	eventType = $(this).data("type") === "" ? null : $(this).data("type");
	filterEvents();
});

$(".res-event-list-title-filtered").on("click", ".res-back", function(e) {
	e.preventDefault();

	city = null;
	filterEvents();
});

});
