var Patrol = Patrol || {
	cpRule:				$("#cpRule"),
	restrictedAreas:	$("#restrictedAreas"),
	cpRuleButton:		$("#addCpRule"),
	requestingIp:		$("#requestingIp"),
	authorizedIps:		$("#authorizedIps"),
	requestingIpButton:	$("#addRequestingIp"),

	hasCpRule: function() {
		return (Patrol.restrictedAreas.val().indexOf(Patrol.cpRule.val()) !== -1);
	},
	hasRequestingIp: function() {
		return (Patrol.authorizedIps.val().indexOf(Patrol.requestingIp.val()) !== -1);
	},
	addRequestingIp: function(e) {
		if (Patrol.hasRequestingIp()) { return; }
		Patrol.authorizedIps.val(Patrol.requestingIp.val() + "\n" + Patrol.authorizedIps.val());
		Patrol.authorizedIps.focus();
		Patrol.requestingIpButton.addClass("disabled");
	},
	addCpRule: function(e) {
		if (Patrol.hasCpRule()) { return; }
		Patrol.restrictedAreas.val(Patrol.cpRule.val() + "\n" + Patrol.restrictedAreas.val());
		Patrol.restrictedAreas.focus();
		Patrol.cpRuleButton.addClass("disabled");
	},
	enableFormElement: function(element) {
		element.removeClass("disabled");
	},
	disableFormElement: function(element) {
		element.addClass("disabled");
	},
	addPatrolRedirect: function() {
		var patrolRedirect = $("#patrolReturn").val();

		if (typeof patrolRedirect == "string" && patrolRedirect.length) {
			$("[name=redirect]").val(patrolRedirect);
		}
	}
};

(function() {
	if (typeof $ == "object" || typeof $ == "function") {
		Patrol.addPatrolRedirect();
		Patrol.requestingIpButton.on("click", function(e) {
			Patrol.addRequestingIp(e);
			e.preventDefault();
		});
		Patrol.cpRuleButton.on("click", function(e) {
			Patrol.addCpRule(e);
			e.preventDefault();
		});
		Patrol.restrictedAreas.on("focus keyup", function(e) {
			if (Patrol.hasCpRule()) {
				Patrol.disableFormElement(Patrol.cpRuleButton);
			} else {
				Patrol.enableFormElement(Patrol.cpRuleButton);
			}
		});
		Patrol.authorizedIps.on("focus keyup", function(e) {
			if (Patrol.hasRequestingIp()) {
				Patrol.disableFormElement(Patrol.requestingIpButton);
			} else {
				Patrol.enableFormElement(Patrol.requestingIpButton);
			}
		});
		$("#importSettingsButton").on("click", function(e) {
			var importSection = $("#importSection");

			if (importSection.hasClass("hidden")) {
				importSection.removeClass("hidden");
				$(this).addClass("disabled");
			}
			e.preventDefault();
		});
		$("#cancelImportButton").on("click", function(e) {
			var importSection = $("#importSection");

			if (!importSection.hasClass("hidden")) {
				importSection.addClass("hidden");
				$("#importSettingsButton").removeClass("disabled");
			}
			e.preventDefault();
		});
		$("#runImportSettings").on("click", function(e) {
			$("[name=action]").val("patrol/importSettings");
			$("form").first().attr("enctype", "multipart/form-data").submit();
			e.preventDefault();
		});
		Mousetrap.bind(['command+s', 'ctrl+s'], function(e) {
			$("form").first().submit();
			return false;
		});
	}
})();
