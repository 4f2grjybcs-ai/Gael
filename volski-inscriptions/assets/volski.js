// Formulaire Vol & Ski : champ « passager » selon la catégorie, tarifs liés (ex. +1 au souper),
// et total en direct. Les cartes de tarifs peuvent être hors du <form> (attribut form="volski-form").
(function () {
	var form = document.getElementById('volski-form');
	if (!form) return;
	var outs = document.querySelectorAll('[data-volski-total]');
	var cur = (outs[0] && outs[0].getAttribute('data-currency')) || 'CHF';
	var fmt = function (v) {
		var r = Math.round(v * 100) / 100;
		return (r % 1 ? r.toFixed(2) : String(r)).replace(/\B(?=(\d{3})+(?!\d))/g, "'") + ' ' + cur;
	};
	var fields = function () { return Array.prototype.slice.call(form.elements); };
	var isOn = function (el) { return el.type === 'checkbox' || el.type === 'radio' ? el.checked : (parseInt(el.value, 10) || 0) > 0; };
	var maxOf = function (el) { return parseInt(el.getAttribute('data-max') || el.getAttribute('max'), 10) || 0; };

	// Cartes à quantité : 0 → 1 → … → max → 0 (ou 1 pour un tarif obligatoire).
	var paint = function (card) {
		var input = card.querySelector('.volski-qty'), q = parseInt(input.value, 10) || 0, max = maxOf(input);
		card.classList.toggle('is-on', q > 0);
		card.querySelector('.volski-tcard-qty').textContent = q > 0 ? '×' + q : '+';
		card.setAttribute('aria-label', card.getAttribute('data-label') + ' : ' + (q > 0 ? q + ' sur ' + max : 'aucun') + '. Toucher pour changer.');
	};
	document.querySelectorAll('[data-volski-cycle]').forEach(function (card) {
		paint(card);
		card.addEventListener('click', function () {
			var input = card.querySelector('.volski-qty');
			var min = parseInt(input.getAttribute('data-min'), 10) || 0, max = maxOf(input), q = parseInt(input.value, 10) || 0;
			input.value = q >= max ? min : q + 1;
			paint(card);
			sync();
		});
	});

	var sync = function () {
		var field = form.querySelector('[data-volski-passager]');
		if (field) {
			var cat = form.querySelector('[data-volski-cat]:checked');
			field.hidden = !(cat && cat.getAttribute('data-passager') === '1');
		}
		// Un tarif lié n'apparaît que si l'autre est choisi.
		document.querySelectorAll('[data-volski-requires]').forEach(function (wrap) {
			var need = wrap.getAttribute('data-volski-requires'), on = false;
			fields().forEach(function (el) { if (el.getAttribute('data-volski-opt') === need && isOn(el)) on = true; });
			wrap.hidden = !on;
			if (!on) {
				wrap.querySelectorAll('input').forEach(function (i) { if (i.type === 'checkbox') i.checked = false; else i.value = 0; });
				if (wrap.hasAttribute('data-volski-cycle')) paint(wrap);
			}
		});
		var t = 0;
		fields().forEach(function (el) {
			if (!el.hasAttribute('data-prix')) return;
			var p = parseFloat(el.getAttribute('data-prix')) || 0;
			if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked) t += p; }
			else {
				var max = maxOf(el), q = Math.max(0, parseInt(el.value, 10) || 0);
				t += p * (max ? Math.min(q, max) : q);
			}
		});
		outs.forEach(function (o) { o.textContent = fmt(t); });
	};
	document.addEventListener('change', sync);
	document.addEventListener('input', sync);
	sync();
})();
