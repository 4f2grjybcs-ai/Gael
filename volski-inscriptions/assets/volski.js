// Champ « passager » pour les catégories qui le demandent (ex. Biplace), et total calculé en direct.
(function () {
	var cur = 'CHF';
	var fmt = function (v) {
		var r = Math.round(v * 100) / 100;
		return (r % 1 ? r.toFixed(2) : String(r)).replace(/\B(?=(\d{3})+(?!\d))/g, "'") + ' ' + cur;
	};
	document.querySelectorAll('.volski-form').forEach(function (form) {
		var field = form.querySelector('[data-volski-passager]');
		var out = form.querySelector('[data-volski-total]');
		if (out && out.getAttribute('data-currency')) cur = out.getAttribute('data-currency');
		var sync = function () {
			if (field) {
				var cat = form.querySelector('[data-volski-cat]:checked');
				field.hidden = !(cat && cat.getAttribute('data-passager') === '1');
			}
			form.querySelectorAll('[data-volski-requires]').forEach(function (wrap) {
				var parent = null;
				form.querySelectorAll('[data-volski-opt]').forEach(function (el) { if (el.getAttribute('data-volski-opt') === wrap.getAttribute('data-volski-requires')) parent = el; });
				var on = parent && (parent.type === 'checkbox' ? parent.checked : (parseInt(parent.value, 10) || 0) > 0);
				wrap.hidden = !on;
				if (!on) wrap.querySelectorAll('input').forEach(function (i) { if (i.type === 'checkbox') i.checked = false; else i.value = 0; });
			});
			if (!out) return;
			var t = 0;
			form.querySelectorAll('[data-prix]').forEach(function (el) {
				var p = parseFloat(el.getAttribute('data-prix')) || 0;
				if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked) t += p; }
				else {
					var max = parseInt(el.max, 10) || 0, q = Math.max(0, parseInt(el.value, 10) || 0);
					t += p * (max ? Math.min(q, max) : q);
				}
			});
			out.textContent = fmt(t);
		};
		form.addEventListener('change', sync);
		form.addEventListener('input', sync);
		sync();
	});
})();
