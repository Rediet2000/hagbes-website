/* GWM Home (Dark) — nav interactions */
(function () {
	'use strict';

	/* --- Hero video: gentle autoplay nudge (Themify lazy-load is excluded via
	   the data-tf-not-load marker, so the browser handles autoplay natively). --- */
	function startHeroVideo() {
		var v = document.querySelector('.gwm-hero__video');
		if (!v) { return; }
		v.muted = true;
		v.playsInline = true;
		if (v.paused) {
			var p = v.play();
			if (p && p.catch) { p.catch(function () {}); }
		}
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', startHeroVideo);
	} else {
		startHeroVideo();
	}
	window.addEventListener('load', startHeroVideo);

	var nav = document.getElementById('gwmNav');
	var burger = document.getElementById('gwmBurger');
	var drawer = document.getElementById('gwmDrawer');
	if (!nav) { return; }

	/* Transparent -> solid on scroll */
	function onScroll() {
		if (window.scrollY > 40) {
			nav.classList.add('is-solid');
		} else {
			nav.classList.remove('is-solid');
		}
	}
	onScroll();
	window.addEventListener('scroll', onScroll, { passive: true });

	/* Mobile drawer toggle */
	function setOpen(open) {
		nav.classList.toggle('is-open', open);
		if (burger) { burger.setAttribute('aria-expanded', open ? 'true' : 'false'); }
		if (drawer) { drawer.setAttribute('aria-hidden', open ? 'false' : 'true'); }
	}
	if (burger) {
		burger.addEventListener('click', function () {
			setOpen(!nav.classList.contains('is-open'));
		});
	}
	if (drawer) {
		drawer.querySelectorAll('a').forEach(function (a) {
			a.addEventListener('click', function () { setOpen(false); });
		});
	}

	/* Smooth scroll for same-page anchors, accounting for fixed nav */
	document.querySelectorAll('.gwm a[href^="#"]').forEach(function (a) {
		a.addEventListener('click', function (e) {
			var id = a.getAttribute('href');
			if (id.length < 2) { return; }
			var target = document.querySelector(id);
			if (!target) { return; }
			e.preventDefault();
			var top = target.getBoundingClientRect().top + window.scrollY - 60;
			window.scrollTo({ top: top, behavior: 'smooth' });
		});
	});
})();
