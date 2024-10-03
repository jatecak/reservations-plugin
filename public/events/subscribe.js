jQuery(function($) {

var $form = $(".res-subscribe form"),
	data = window.subscribe_data.data;

function getValues() {
	return {
		ageGroup: parseInt($("select[name=age_group]", $form).val(), 10)
	};
}

function updateForm() {
	var values = getValues();

	// var totalPrice = data.price[values.ageGroup],
	// 	paidSubscriptions = data.paidSubscriptions[values.ageGroup];

	var paidSubscriptions = data.paidSubscriptions;

	var overCapacity = data.capacity - paidSubscriptions <= 0;

	$(".res-over-capacity", $form).toggleClass("res-hidden", !overCapacity);
	$(".res-submit", $form).prop("disabled", overCapacity);

	// $(".res-submit span", $form).text(totalPrice);

	// if(isNaN(totalPrice) || totalPrice === 0) {
	// 	$(".res-submit", $form).hide();
	// 	$(".res-submit-replacement").hide();
	// } else {
		$(".res-submit", $form).show();

		if(data.replacementsEnabled && overCapacity) {
			$(".res-submit", $form).addClass("res-submit-replacement-enabled");
			$(".res-submit-replacement", $form).show();
		} else {
			$(".res-submit", $form).removeClass("res-submit-replacement-enabled");
			$(".res-submit-replacement", $form).hide();
		}
	// }
}

$("select[name=age_group]", $form).on("change input", updateForm);
updateForm();

var submitting = false;
$form.submit(function(e) {
	if(submitting) {
		e.preventDefault();
		return;
	}

	submitting = true;
	$(".res-submit", $form).addClass("res-disabled");
});

$form.find("select[name=referrer],select[name=reason]").change(function() {
	$("input[name=" + $(this).attr("name") + "_other]").css("display", $(this).val() === "other" ? "block" : "none");
}).trigger("change");

$form.find("select[name=carpool]").change(function() {
	if($(this).val() !== "none") {
		$(".carpool-contact, .carpool-seats").show();
	} else {
		$(".carpool-contact, .carpool-seats").hide();
	}
}).trigger("change");

$form.find("select[name=catering]").change(function() {
	if($(this).val() !== "0") {
		$(".catering-meal").show();
	} else {
		$(".catering-meal").hide();
	}
}).trigger("change");

$form.find("button[data-type=submit]").click(function() {
	$(this).attr("type", "submit");
});

});
