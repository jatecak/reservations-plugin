jQuery(function($) {

var $form = $(".res-subscribe form"),
	data = window.subscribe_data.data;

data.paidSubscriptions = data.paidSubscriptions.map(function(sub) {
	return {
		dateFrom: moment(sub.date_from),
		dateTo: moment(sub.date_to)
	};
});

if(data.activeTerm)
	data.activeTerm = [
		moment(data.activeTerm[0]),
		moment(data.activeTerm[1])
	];

if(data.activeYear)
	data.activeYear = [
		moment(data.activeYear[0]),
		moment(data.activeYear[1])
	];

if(data.maxMonthlyDate)
	data.maxMonthlyDate = moment(data.maxMonthlyDate);

function getActiveSubscriptionCount(dateFrom, dateTo) {
	var activeSubscriptions = data.paidSubscriptions.filter(function(sub) {
		return dateFrom.isSameOrBefore(sub.dateTo) && dateTo.isSameOrAfter(sub.dateFrom);
	});

	var datesToCheck = [dateFrom, dateTo];

	activeSubscriptions.forEach(function(sub) {
		if(sub.dateFrom.isAfter(dateFrom))
			datesToCheck.push(sub.dateFrom);

		if(sub.dateTo.isBefore(dateTo))
			datesToCheck.push(sub.dateTo);
	});

	var maxCount = 0;
	datesToCheck.forEach(function(date) {
		var count = activeSubscriptions.reduce(function(count, sub) {
			return count + (sub.dateFrom.isSameOrBefore(date) && sub.dateTo.isSameOrAfter(date));
		}, 0);

		maxCount = Math.max(maxCount, count);
	});

	return maxCount;
}

function getValues() {
	var $radio = $("input[name=subscription_type]:checked", $form);

	return {
		ageGroup: parseInt($("select[name=age_group]", $form).val(), 10),
		type: $radio.length ? $radio.attr("value") : null,
		dateStart: moment($("input[name=start_date]", $form).val()),
		numMonths: parseInt($("input[name=num_months]", $form).val(), 10)
	};
}

function formatNumber(n) {
	function rev(s) {
		return String(s).split("").reverse().join("");
	}

	return rev(rev(n).replace(/([0-9]{3})/g, "$1 ").trim());
}

var $levels = $("select[name=preferred_level] optgroup").detach();

$("select[name=preferred_level]").html($levels.first().children().clone());

function updateForm() {
	var values = getValues(),
		today = moment().startOf("day");

	$(".res-price-total", $form).toggleClass("res-hidden", values.type !== "monthly");

	var dateEnd;

	var totalAmount = data.totalAmount[values.type],
		initialAmount = data.initialAmount[values.type];

	if(values.type === "biannual" && data.activeTerm) {
		values.dateStart = data.activeTerm[0];
		dateEnd = data.activeTerm[1];

		$("input[name=start_date]", $form).prop("disabled", true).val(values.dateStart.format("YYYY-MM-DD"));
	} else if(values.type === "annual" && data.activeYear) {
		values.dateStart = data.activeYear[0];
		dateEnd = data.activeYear[1];

		$("input[name=start_date]", $form).prop("disabled", true).val(values.dateStart.format("YYYY-MM-DD"));
	} else {
		var $startDate = $("input[name=start_date]", $form);

		if($startDate.prop("disabled"))
			$startDate.val(today.format("YYYY-MM-DD"));

		$startDate.prop("disabled", false);

		if(!values.dateStart.isValid()) {
			values.dateStart = today;

			$("input[name=start_date]", $form).val(values.dateStart.format("YYYY-MM-DD"));
		}

		if(values.type === "monthly") {
			var numMonths = values.numMonths;

			if(isNaN(numMonths) || numMonths < 1)
				numMonths = 1;

			dateEnd = values.dateStart.clone().add(numMonths, "months");

			totalAmount *= numMonths;
			initialAmount *= numMonths;

			$(".res-price-total span", $form).first().text(formatNumber(totalAmount));
			$(".res-price-total .res-price-initial span", $form).first().text(formatNumber(initialAmount));

			$(".res-price-total .res-price-initial").toggleClass("res-hidden", totalAmount === initialAmount);
		} else {
			var duration = data.defaultDuration[values.type === "annual" ? "year" : "term"];
			dateEnd = values.dateStart.clone().add(duration.months, "months").add(duration.days, "days");
		}
	}

	$("input[name=end_date]", $form).val(dateEnd.format("YYYY-MM-DD"));

	var overCapacity = data.capacity - getActiveSubscriptionCount(moment.max(today, values.dateStart), dateEnd) <= 0,
		startInvalid = !((values.type === "biannual" && data.activeTerm) || (values.type === "annual" && data.activeYear)) && values.dateStart.isBefore(today),
		endInvalid = data.maxMonthlyDate && values.type === "monthly" && dateEnd.isAfter(data.maxMonthlyDate);

	$(".res-over-capacity", $form).toggleClass("res-hidden", !overCapacity || startInvalid || endInvalid);
	$(".res-start-date-invalid", $form).toggleClass("res-hidden", !startInvalid);
	$(".res-end-date-invalid", $form).toggleClass("res-hidden", !endInvalid);
	$(".res-submit", $form).prop("disabled", overCapacity || startInvalid || endInvalid);

	$(".res-submit .res-no-deposit span, .res-submit .res-deposit span:eq(1)", $form).text(formatNumber(totalAmount));
	$(".res-submit .res-deposit span", $form).first().text(formatNumber(initialAmount));

	$(".res-submit .res-deposit", $form).toggleClass("res-hidden", totalAmount === initialAmount);
	$(".res-submit .res-no-deposit", $form).toggleClass("res-hidden", totalAmount !== initialAmount);

	if(isNaN(totalAmount) || totalAmount === 0) {
		$(".res-submit", $form).hide();
		$(".res-submit-replacement").hide();
	} else {
		$(".res-submit", $form).show();

		if(data.replacementsEnabled && !startInvalid && !endInvalid && overCapacity) {
			$(".res-submit", $form).addClass("res-submit-replacement-enabled");
			$(".res-submit-replacement", $form).show();
		} else {
			$(".res-submit", $form).removeClass("res-submit-replacement-enabled");
			$(".res-submit-replacement", $form).hide();
		}
	}

	var selectedValue = $("select[name=preferred_level] option:selected").attr("value");
	$("select[name=preferred_level]")
		.html($levels.filter("[data-age-group=" + values.ageGroup + "]").children().clone())
		.find("option[value=" + selectedValue + "]").prop("selected", true);
}

$("select[name=age_group],input[name=subscription_type],input[name=num_months],input[name=start_date]", $form).on("change input", updateForm);
updateForm();

$("input[name=num_months]", $form).focus(function() {
	$("input[name=subscription_type][value=monthly]", $form).prop("checked", true);
	updateForm();
});

$("input[name=start_date]", $form).datepicker({
	weekStart: 1,
	maxViewMode: 0,
    todayBtn: "linked",
    format: "yyyy-mm-dd",
    language: "cs",
    startDate: moment().format("YYYY-MM-DD"),
    beforeShowDay: function (date) {
    	date = moment(date);

    	if(date.isBefore(moment().startOf("day")))
    		return false;

    	var capacity = data.capacity - getActiveSubscriptionCount(date, date),
    		values = getValues();

    	var enabled = true;

    	if(capacity <= 0)
    		enabled = false;

    	if(values.type === "monthly") {
			var numMonths = values.numMonths;

			if(isNaN(numMonths) || numMonths < 1)
				numMonths = 1;

			var dateEnd = date.clone().add(numMonths, "months");

			if(data.maxMonthlyDate && dateEnd.isAfter(data.maxMonthlyDate))
				enabled = false;
		}

    	if(!enabled) {
    		return {
    			enabled: false,
    			classes: "res-datepicker-over-capacity"
    		};
    	} else {
    		return {
    			classes: "res-datepicker-available"
    		};
    	}
    }
});

var submitting = false;
$form.submit(function(e) {
	if(submitting) {
		e.preventDefault();
		return;
	}

	submitting = true;
	$(".res-submit", $form).addClass("res-disabled");
});

$form.find("button[data-type=submit]").click(function() {
	$(this).attr("type", "submit");
});

});
