// Affiche le champ « passager » uniquement si Biplace est cochée.
(function () {
	document.querySelectorAll('.volski-form').forEach(function (form) {
		var box = form.querySelector('[data-volski-cat="biplace"]');
		var field = form.querySelector('[data-volski-show="biplace"]');
		if (!box || !field) return;
		var sync = function () { field.hidden = !box.checked; };
		box.addEventListener('change', sync);
		sync();
	});
})();
