jQuery(function($) {
	function updateIds() {
		var ids = $ul.children(":not(.no-instructors)").map(function() {
			return $(this).data("id");
		}).toArray().join(",");

		$ids.val(ids);
	}

	function updateContactInstructorSelect() {
		var selected = $contactInstructorSelect.children(":selected").attr("value");

		$contactInstructorSelect.children().slice(1).remove();
		$ul.children(":not(.no-instructors)").each(function() {
			$contactInstructorSelect.append(new Option($(this).data("name"), $(this).data("id")));
		});

		if(selected) {
			$contactInstructorSelect.children("[value=\"" + selected + "\"]").prop("selected", true);
		}
	}

	if($("#res-instructors-add").length) {
		var $select = $("#res-instructors-add"),
			$ul = $(".res-instructors-wrap ul"),
			$ids = $("#res-instructor-ids"),
			$contactInstructorSelect = $("#res-contact-instructor-id");

		$select.change(function(e) {
			var id = $select.children(":selected").attr("value"),
				name = $select.children(":selected").text();

			$ul.append('<li data-id="' + id + '" data-name="' + name + '">' + name + ' <a href="#" class="delete">' + $ul.data("delete-text") + '</li>');
			$ul.find(".no-instructors").remove();

			updateIds();
			updateContactInstructorSelect();

			$select.children(":selected").remove();
			$select.children().first().prop("selected", true);
		});

		$ul.on("click", ".delete", function(e) {
			e.preventDefault();

			var $li = $(this).parent();

			$select.append(new Option($li.data("name"), $li.data("id")));
			$li.remove();

			updateIds();
			updateContactInstructorSelect();

			if(!$ul.children().length) {
				$ul.append('<li class="no-instructors">' + $ul.data("no-instructors-text") + '</li>');
			}
		});
	}
});

jQuery(function($) {
	var locationIds = []. eventIds = [], columnIds = [];

	function updateIds() {
		locationIds = $(".res-locations").children(":not(.no-locations)").map(function() {
			return $(this).data("id");
		}).toArray();

		$("#tgroup-ids").val(locationIds.join(","));

		eventIds = $(".res-events").children(":not(.no-events)").map(function() {
			return $(this).data("id");
		}).toArray();

		$("#event-ids").val(eventIds.join(","));

		columnIds = $(".res-columns").children(":not(.no-columns)").map(function() {
			return $(this).data("id");
		}).toArray();

		$("#column-ids").val(columnIds.join(","));
	}

	if($("#res-export").length) {
		$("#locations-add-city, #tgroups-add, #locations-add, #events-add, #columns-add").change(function() {
			var $select = $(this);

			var $option = $select.find(":selected");

			var id = $option.attr("value"),
				name = $option.text();

			if($option.closest("optgroup").length) {
				name = $option.closest("optgroup").attr("label") + " > " + name;
			}

			var $ul = $select.prevAll("ul").first();
			$ul.find(".no-locations, .no-columns, .no-events").remove();

			$ul.append('<li data-id="' + id + '" data-name="' + name + '">' + name + ' <a href="#" class="delete">' + $ul.data("delete-text") + '</li>');

			updateIds();

			$select.children().first().prop("selected", true);
		});

		$("#res-export ul").on("click", ".delete", function(e) {
			e.preventDefault();

			var $li = $(this).parent();

			$li.remove();

			updateIds();

			var $ul = $(this).closest("ul");

			if(!$ul.children().length) {
				var cl = $ul.hasClass("res-columns") ? "no-columns" : $ul.hasClass("res-events") ? "no-events" : "no-locations";
				$ul.append('<li class="' + cl + '">' + $ul.data(cl + "-text") + '</li>');
			}
		});

		$(".res-columns").sortable({
			items: "li:not(.no-columns):not(.no-locations):not(.no-events)",
			update: updateIds
		});

		$("#res-export form").submit(function(e) {
			if($("#event-ids").length && $("#event-ids").val() === "") {
				e.preventDefault();
				alert($(this).data("no-events-message"));
				return;
			}

			if($("#location-ids").length && $("#location-ids").val() === "") {
				e.preventDefault();
				alert($(this).data("no-locations-message"));
				return;
			}

			if($("#tgroup-ids").length && $("#tgroup-ids").val() === "") {
				e.preventDefault();
				alert($(this).data("no-locations-message"));
				return;
			}

			if($("#column-ids").length && $("#column-ids").val() === "") {
				e.preventDefault();
				alert($(this).data("no-columns-message"));
				return;
			}
		});

		if(window.localStorage) {
			var defaultLabels = $("#presets option").map(function() {
				return $(this).text();
			}).toArray();

			var presets = [];
			try {
				presets = JSON.parse(window.localStorage.getItem("export-presets"));
			} catch(err) {}

			if(!Array.isArray(presets))
				presets = [];

			for(var i = 0; i < 5; i++) {
				if(presets.length <= i)
					presets.push({
						label: defaultLabels[i]
					});
			}

			var $presets = $("#presets").empty();

			presets.forEach(function(preset, i) {
				if(!preset.label)
					preset.label = defaultLabels[i];

				if(!preset.locations)
					preset.locations = [];

				if(!preset.events)
					preset.events = [];

				if(!preset.columns)
					preset.columns = [];

				$presets.append('<option>' + preset.label + '</option>');
			});

			$("#presets-save").click(function() {
				var selectedIndex = $("#presets option:selected").index();

				var preset = presets[selectedIndex],
					newName = prompt($(this).data("prompt"), preset.label);

				if(!newName)
					return;

				preset.label = newName;
				$("#presets option").eq(selectedIndex).text(newName);

				updateIds();

				preset.locations = locationIds;
				preset.events = eventIds;
				preset.columns = columnIds;

				window.localStorage.setItem("export-presets", JSON.stringify(presets));
			});

			$("#presets-load").click(function() {
				var selectedIndex = $("#presets option:selected").index();

				var preset = presets[selectedIndex];

				$(".res-locations, .res-events, .res-columns").find(".delete").trigger("click");

				preset.locations.forEach(function(loc) {
					console.log(loc);
					var $option = $("#locations-add-city, #tgroups-add, #locations-add").find("option[value=\""+loc+"\"]");
					$option.prop("selected", true);
					$option.closest("select").trigger("change");
				});

				preset.events.forEach(function(loc) {
					var $option = $("#events-add").find("option[value=\""+loc+"\"]");
					$option.prop("selected", true);
					$option.closest("select").trigger("change");
				});

				preset.columns.forEach(function(loc) {
					var $option = $("#columns-add").find("option[value=\""+loc+"\"]");
					$option.prop("selected", true);
					$option.closest("select").trigger("change");
				});
			});
		}

		$("#presets")
	}
});

jQuery(function($) {
	function updateIds() {
		var ids = $ul.children().not(".res-empty").map(function() {
			return $(this).data("id");
		}).toArray().join(",");

		$input.val(ids);
	}

	if($("#instructor-accessible-cities-add").length) {
		var $select = $("#instructor-accessible-cities-add"),
			$ul = $("#instructor-accessible-cities"),
			$input = $("#instructor-accessible-cities-input");

		$select.change(function(e) {
			var id = $select.children(":selected").attr("value"),
				name = $select.children(":selected").text();

			$ul.append('<li data-id="' + id + '" data-name="' + name + '">' + name + ' <a href="#" class="delete">' + $ul.data("delete-text") + '</li>');
			$ul.find(".res-empty").remove();

			updateIds();

			$select.children(":selected").remove();
			$select.children().first().prop("selected", true);
		});

		$ul.on("click", ".delete", function(e) {
			e.preventDefault();

			var $li = $(this).parent();

			$select.append(new Option($li.data("name"), $li.data("id")));
			$li.remove();

			updateIds();

			if(!$ul.children().length) {
				$ul.append('<li class="res-empty">' + $ul.data("empty-text") + '</li>');
			}
		});
	}
});


jQuery(function($) {
	var file_frame;

	function updateIds($ul) {
		var ids = $ul.children(":not(.no-files)").map(function() {
			return $(this).data("id");
		}).toArray().join(",");

		$ul.nextAll("input[type=hidden]").val(ids);
	}

	$(".event-attachment-sets-wrap, .res-message-templates").on("click", ".res-add-file", function() {
		if (file_frame)
			file_frame.close();

		file_frame = wp.media.frames.file_frame = wp.media({
			title: $(this).data('uploader-title'),
			button: {
				text: $(this).data('uploader-button-text')
			},
			multiple: "toggle"
		});

		var $div = $(this).closest(".res-attachment-set, .res-message-template");

		file_frame.on('open', function() {
			var selection = file_frame.state().get("selection");

			$div.find("input[type=hidden]").val().split(",").map(function(id) {
				if(!id)
					return;

				selection.add(wp.media.attachment(id));
			});
		});

		file_frame.on('select', function() {
			var selection = file_frame.state().get('selection');
			var ids = $div.find("input[type=hidden]").val().split(",").map(function(id) {
				return parseInt(id, 10);
			});

			var $ul = $div.find(".res-files");

			$ul.find(".no-files").remove();

			selection.map(function(attachment, i) {
				attachment = attachment.toJSON();

				if(ids.indexOf(attachment.id) !== -1)
					return;

				$ul.append('<li data-id="' + attachment.id + '">' + attachment.filename + ' <a href="#" class="res-delete">' + $ul.data("delete-text") + '</li>');
			});

			updateIds($ul);
		});

		file_frame.open();
	});

	function updateEmptyText($ul) {
		if(!$ul.children().length) {
			$ul.append('<li class="no-files">' + $ul.data("no-files-text") + '</li>');
		}
	}

	var $template = $(".res-attachment-set.res-template").detach().removeClass("res-template");

	$(".res-add-set").click(function(e) {
		e.preventDefault();

		var $set = $template.clone();

		var lastKey = parseInt($(this).prev(".res-attachment-set").find("[name]").attr("name").match(/\[([0-9]+)\]/)[1], 10),
			key = lastKey + 1;

		$set.find("[name*=_tpl]").each(function() {
			$(this).attr("name", $(this).attr("name").replace(/_tpl/, ""));
		});

		$set.find("[name]").each(function() {
			$(this).attr("name", $(this).attr("name").replace(/\[\]/, "[" + key + "]"));
		});

		var $h4 = $set.find("h4");

		$h4.html($h4.html().replace("#%s", "#" + (key + 1)));

		$set.insertBefore(this);
	});

	var $mtTemplate = $(".res-message-template.res-template").detach().removeClass("res-template");

	$(".res-add-template").click(function(e) {
		e.preventDefault();

		var $t = $mtTemplate.clone();

		var lastKey = parseInt($(this).prev(".res-message-template").find("[name]").attr("name").match(/\[([0-9]+)\]/)[1], 10),
			key = lastKey + 1;

		$t.find("[name*=_tpl]").each(function() {
			$(this).attr("name", $(this).attr("name").replace("_tpl", ""));
		});

		$t.find("[name]").each(function() {
			$(this).attr("name", $(this).attr("name").replace(/\[\]/, "[" + key + "]"));
		});

		var $h3 = $t.find("h3");

		$h3.html($h3.html().replace("#%s", "#" + (key + 1)));

		var $textarea = $t.find("textarea");

		$textarea.attr("id", "template_" + key + "_body");

		$t.insertBefore(this);

		wp.editor.initialize($textarea.attr("id"), wp.editor.getDefaultSettings()); // TODO
	});

	$(".event-attachment-sets-wrap").on("click", ".res-delete", function(e) {
		e.preventDefault();

		if($(this).parent().is("h4")) {
			$(this).closest(".res-attachment-set").remove();
			return;
		}

		var $ul = $(this).closest(".res-attachment-set").find(".res-files");

		$(this).parent().remove();

		updateIds($ul);
		updateEmptyText($ul);
	});

	$(".res-message-templates").on("click", ".res-delete", function(e) {
		e.preventDefault();

		if($(this).parent().is("h3")) {
			$(this).closest(".res-message-template").remove();
			return;
		}

		var $ul = $(this).closest(".res-message-template").find(".res-files");

		$(this).parent().remove();

		updateIds($ul);
		updateEmptyText($ul);
	});
});

jQuery(function($) {
	var $table = $(".event-price");

	$table.find("tbody .res-template").each(function() {
		var $table = $(this).closest("table");

		$table.data("template", $(this).detach().removeClass("res-template"));
	});

	$(".res-add-payment").click(function(e) {
		e.preventDefault();

		var $table = $(this).prevAll(".event-price");

		if($table.filter(".res-active").length)
			$table = $table.filter(".res-active").first();
		else
			$table = $table.first();

		var $tbody = $table.find("tbody");

		var $tr = $table.data("template").clone();

		$tr.find("[name*=_tpl]").each(function() {
			$(this).attr("name", $(this).attr("name").replace("_tpl", ""));
		});

		var lastKey = parseInt($tbody.find("tr:not(.res-template)").last().find("[name]").attr("name").match(/.+\[([0-9]+)\]/)[1], 10),
			key = lastKey + 1;

		$tr.find("[name]").each(function() {
			$(this).attr("name", $(this).attr("name").replace(/\[\]/, "[" + key + "]"));
		});

		$tbody.append($tr);
	});

	$table.on("click", ".res-delete", function(e) {
		e.preventDefault();

		$(this).closest("tr").remove();
	});
});

jQuery(function($) {
	function updateCampTypeSelect() {
		var $campType = $(".camp-type-wrap"),
			eventType = $("select[name=\"event_meta[event_type]\"] option:selected").attr("value");

		if(eventType === "camp")
			$campType.show();
		else
			$campType.hide();
	}

	$("select[name=\"event_meta[event_type]\"]").change(updateCampTypeSelect);
	updateCampTypeSelect();
});

jQuery(function($) {
	var $table = $(".timeslots");

	if(!$table.length)
		return;

	$table.find("tbody .res-template").each(function() {
		var $table = $(this).closest("table");

		$table.data("template", $(this).detach().removeClass("res-template"));
	});

	$(".res-add-timeslot").click(function(e) {
		e.preventDefault();

		var $tbody = $table.find("tbody");

		var $tr = $table.data("template").clone();

		$tr.find("[name*=_tpl]").each(function() {
			$(this).attr("name", $(this).attr("name").replace("_tpl", ""));
		});

		var lastKey = parseInt($tbody.find("tr:not(.res-template)").last().find("[name]").attr("name").match(/.+\[([0-9]+)\]/)[1], 10),
			key = lastKey + 1;

		$tr.find("[name]").each(function() {
			$(this).attr("name", $(this).attr("name").replace(/\[\]/, "[" + key + "]"));
		});

		$tbody.append($tr);
	});

	$table.on("click", ".res-delete", function(e) {
		e.preventDefault();

		$(this).closest("tr").remove();
	});
});

jQuery(function($) {
	function initTabs(tabs) {

		$(tabs).on("click", "a", function(e) {
			e.preventDefault();

			var $li = $(this).parent(),
				$ul = $(this).closest("ul"),
				index = $li.index();

			$ul.find("li").removeClass("res-tab-active");
			$li.addClass("res-tab-active");

			var numTabs = $ul.children().length,
				$tabs = $ul.nextAll().slice(0, numTabs);

			$tabs.removeClass("res-active");
			$tabs.eq(index).addClass("res-active");
		});
	}

	initTabs(".res-tgroup-tabs");
	initTabs(".res-gopay-settings-tabs");
	initTabs(".res-price-tabs");
});

jQuery(function($) {
	$("body").on("click", "input[type=button][data-type],button[type=button][data-type]", function(e) {
		$(this).attr("type", $(this).attr("data-type"));
	});
});
