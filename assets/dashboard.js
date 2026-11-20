/* Local SVG charts. No third-party runtime, HTML injection or order-level data. */
(() => {
	'use strict';
	const root = document.querySelector('#advanced-dashboard-admin-widget .adwc');
	if (!root) return;
	const form = root.querySelector('form');
	const range = form.elements.range;
	const status = root.querySelector('.adwc-status');
	const report = root.querySelector('.adwc-report');
	const chartGroup = report.querySelector('.adwc-charts');
	const counts = root.querySelector('.adwc-counts');
	const operations = root.querySelector('.adwc-operations');
	const metrics = ['net_revenue', 'orders_count', 'num_items_sold'];
	const colors = ['#135e96', '#007c65', '#7149a1'];
	let dateFormat;
	let axisDateFormat;
	let numberFormat;
	let countFormat;
	try {
		dateFormat = new Intl.DateTimeFormat(root.dataset.locale, { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });
		axisDateFormat = new Intl.DateTimeFormat(root.dataset.locale, { day: 'numeric', month: 'short', timeZone: 'UTC' });
		numberFormat = new Intl.NumberFormat(root.dataset.locale, { maximumFractionDigits: 2, notation: 'compact' });
		countFormat = new Intl.NumberFormat(root.dataset.locale);
	} catch (_) {
		dateFormat = new Intl.DateTimeFormat('en', { timeZone: 'UTC' });
		axisDateFormat = new Intl.DateTimeFormat('en', { day: 'numeric', month: 'short', timeZone: 'UTC' });
		numberFormat = new Intl.NumberFormat('en', { maximumFractionDigits: 2 });
		countFormat = new Intl.NumberFormat('en');
	}
	const dateLabel = (date) => dateFormat.format(new Date(`${date}T12:00:00Z`));
	const setStatus = (text, state) => {
		status.textContent = text;
		status.dataset.state = state;
		status.hidden = !text;
	};
	const customRange = () => {
		const custom = range.value === 'custom';
		root.querySelector('.adwc-custom').hidden = !custom;
		['from', 'to'].forEach((name) => {
			form.elements[name].disabled = !custom;
			form.elements[name].required = custom;
		});
	};
	range.addEventListener('change', customRange);
	customRange();

	const svgElement = (tag, attributes = {}, text = '') => {
		const node = document.createElementNS('http://www.w3.org/2000/svg', tag);
		Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, String(value)));
		if (text) node.textContent = text;
		return node;
	};
	const charts = [...root.querySelectorAll('.adwc-chart')].map((section, index) => {
		const plot = section.querySelector('.adwc-plot');
		const svg = plot.querySelector('svg');
		const point = section.querySelector('.adwc-point');
		const announcement = section.querySelector('.adwc-chart-announcement');
		const metric = section.dataset.metric;
		let rows = [];
		let selected = 0;
		let width = 320;
		let marker;
		let dot;
		let x;
		let y;
		const inspect = (position, source) => {
			if (!rows.length) return;
			const next = Math.max(0, Math.min(rows.length - 1, position));
			if (next === selected && source !== 'draw') return;
			selected = next;
			const row = rows[selected];
			marker.setAttribute('x1', x(selected));
			marker.setAttribute('x2', x(selected));
			dot.setAttribute('cx', x(selected));
			dot.setAttribute('cy', y(row[metric]));
			point.textContent = `${dateLabel(row.date)}: ${row.formatted[metric]}`;
			if (source === 'keyboard') announcement.textContent = point.textContent;
		};
		const draw = () => {
			if (!rows.length || report.hidden || chartGroup.hidden) return;
			width = Math.max(260, plot.getBoundingClientRect().width);
			const left = 60, right = width - 14, top = 18, bottom = 142;
			const values = rows.map((row) => row[metric]);
			let low = Math.min(0, ...values), high = Math.max(0, ...values);
			if (low === high) high = low + 1;
			const rawStep = (high - low) / 3;
			const magnitude = 10 ** Math.floor(Math.log10(rawStep));
			const step = Math.max(metric === 'net_revenue' ? 0.01 : 1, [1, 2, 5, 10].find((n) => n * magnitude >= rawStep) * magnitude);
			high = Math.ceil(high / step) * step;
			low = Math.floor(low / step) * step;
			x = (i) => rows.length === 1 ? (left + right) / 2 : left + i * (right - left) / (rows.length - 1);
			y = (value) => bottom - (value - low) * (bottom - top) / (high - low);
			svg.setAttribute('viewBox', `0 0 ${width} 180`);
			svg.replaceChildren();
			const ticks = [];
			for (let value = low; value <= high + step / 100; value += step) ticks.push(value);
			ticks.forEach((value) => {
				svg.append(svgElement('line', { x1: left, x2: right, y1: y(value), y2: y(value), class: 'adwc-grid' }));
				svg.append(svgElement('text', { x: left - 7, y: y(value) + 4, 'text-anchor': 'end' }, numberFormat.format(value)));
			});
			const path = rows.map((row, i) => `${i ? 'L' : 'M'}${x(i)},${y(row[metric])}`).join(' ');
			svg.append(svgElement('path', { d: path, class: 'adwc-line', stroke: colors[index] }));
			[...new Set([0, Math.floor((rows.length - 1) / 2), rows.length - 1])].forEach((i) => {
				const label = axisDateFormat.format(new Date(`${rows[i].date}T12:00:00Z`));
				svg.append(svgElement('text', { x: x(i), y: 167, 'text-anchor': i === 0 ? 'start' : i === rows.length - 1 ? 'end' : 'middle' }, label));
			});
			marker = svgElement('line', { y1: top, y2: bottom, class: 'adwc-marker' });
			dot = svgElement('circle', { r: 4, fill: colors[index], stroke: '#fff', 'stroke-width': 2 });
			svg.append(marker, dot);
			inspect(selected, 'draw');
		};
		let frame;
		let pointerX;
		const cancelHover = () => {
			if (frame === undefined) return;
			cancelAnimationFrame(frame);
			frame = undefined;
		};
		const inspectPointer = (clientX) => {
			if (!rows.length || report.hidden || chartGroup.hidden) return;
			const bounds = plot.getBoundingClientRect();
			if (!bounds.width) return;
			inspect(Math.round(((clientX - bounds.left) * width / bounds.width - 60) / (width - 74) * (rows.length - 1)));
		};
		plot.addEventListener('pointermove', (event) => {
			if (event.pointerType === 'touch') return;
			pointerX = event.clientX;
			if (frame !== undefined) return;
			frame = requestAnimationFrame(() => {
				frame = undefined;
				inspectPointer(pointerX);
			});
		});
		plot.addEventListener('pointerleave', cancelHover);
		plot.addEventListener('keydown', (event) => {
			const target = { ArrowLeft: selected - 1, ArrowRight: selected + 1, Home: 0, End: rows.length - 1 }[event.key];
			if (target !== undefined && rows.length) {
				event.preventDefault();
				cancelHover();
				inspect(target, 'keyboard');
			}
		});
		plot.addEventListener('pointerdown', (event) => {
			if (!rows.length) return;
			cancelHover();
			plot.focus({ preventScroll: true });
			inspectPointer(event.clientX);
		});
		if ('ResizeObserver' in window) new ResizeObserver(draw).observe(plot);
		else window.addEventListener('resize', draw);
		return { update(data) { cancelHover(); rows = data.rows; selected = rows.length - 1; section.querySelector('.adwc-unit').textContent = metric === 'net_revenue' ? ` (${data.currency})` : ''; draw(); } };
	});

	const validData = (data) => data && typeof data.state === 'string' && (data.state !== 'ready' || (
		Array.isArray(data.rows) && data.rows.length > 0 && data.rows.length <= 90 && data.totals && data.totals.formatted &&
		data.rows.every((row) => /^\d{4}-\d{2}-\d{2}$/.test(row.date) && row.formatted && metrics.every((metric) => typeof row[metric] === 'number' && Number.isFinite(row[metric]) && typeof row.formatted[metric] === 'string'))
	));
	let controller;
	let sequence = 0;
	const load = async () => {
		const requestId = ++sequence;
		if (controller) controller.abort();
		controller = new AbortController();
		report.hidden = true;
		if (!operations && counts) counts.hidden = true;
		root.setAttribute('aria-busy', 'true');
		setStatus(status.dataset.loading, 'loading');
		try {
			const url = new URL(root.dataset.endpoint, window.location.href);
			// Keep WordPress's rest_route query parameter when pretty permalinks are disabled.
			for (const [key, value] of new FormData(form)) url.searchParams.set(key, value);
			const response = await fetch(url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': root.dataset.nonce }, signal: controller.signal, cache: 'no-store' });
			const data = await response.json();
			if (requestId !== sequence) return;
			if (!response.ok) throw new Error(typeof data.message === 'string' ? data.message : status.dataset.error);
			if (!validData(data)) throw new Error(status.dataset.error);
			if (data.counts && !operations && counts) {
				root.querySelector('[data-count="processing"]').textContent = countFormat.format(data.counts.processing);
				root.querySelector('[data-count="on_hold"]').textContent = countFormat.format(data.counts.on_hold);
				counts.hidden = false;
			}
			if (data.state !== 'ready') { setStatus(data.message || status.dataset.error, 'unavailable'); return; }
			chartGroup.hidden = data.empty === true;
			report.hidden = false;
			root.querySelector('.adwc-period').textContent = `${dateLabel(data.from)} – ${dateLabel(data.to)} · ${data.timezone}`;
			metrics.forEach((metric) => { root.querySelector(`[data-total="${metric}"]`).textContent = data.totals.formatted[metric]; });
			root.querySelector('.adwc-statuses').textContent = data.statuses.join(' · ');
			const snapshot = root.querySelector('.adwc-snapshot');
			snapshot.textContent = `${snapshot.dataset.label}: ${data.checked_at}`;
			const body = root.querySelector('tbody');
			body.replaceChildren();
			data.rows.forEach((row) => {
				const tr = document.createElement('tr');
				[dateLabel(row.date), ...metrics.map((metric) => row.formatted[metric])].forEach((value, i) => {
					const cell = document.createElement(i === 0 ? 'th' : 'td');
					if (i === 0) cell.scope = 'row';
					cell.textContent = value;
					tr.append(cell);
				});
				body.append(tr);
			});
			if (!chartGroup.hidden) charts.forEach((chart) => chart.update(data));
			setStatus(data.empty ? status.dataset.empty : '', data.empty ? 'empty' : 'ready');
		} catch (error) {
			if (requestId === sequence && error.name !== 'AbortError') {
				report.hidden = true;
				setStatus(error.message || status.dataset.error, 'error');
			}
		} finally {
			if (requestId === sequence) root.setAttribute('aria-busy', 'false');
		}
	};
	const setupOperations = () => {
		if (!operations) return () => {};
		const preferences = operations.querySelector('form');
		const feedback = operations.querySelector('.adwc-operations-status');
		const list = operations.querySelector('.adwc-order-list');
		const disclosure = operations.querySelector('.adwc-queue-details');
		const description = operations.querySelector('.adwc-queue-description');
		let inFlight = false;
		const message = (text, state = '') => { feedback.textContent = text; feedback.dataset.state = state; };
		const render = (data) => {
			if (!data || !Array.isArray(data.rows) || data.rows.length > 10 || !data.counts || !data.preferences) throw new Error(feedback.dataset.error);
			for (const key of ['processing', 'on_hold', 'pending']) {
				if (!Number.isInteger(data.counts[key]) || data.counts[key] < 0) throw new Error(feedback.dataset.error);
				operations.querySelector(`[data-count="${key}"]`).textContent = countFormat.format(data.counts[key]);
			}
			counts.hidden = false;
			preferences.elements.age_hours.value = data.preferences.age_hours;
			preferences.querySelectorAll('input[type=checkbox]').forEach((input) => { input.checked = data.preferences.statuses.includes(input.value); });
			description.textContent = `${data.description.replace(/[.\s]+$/, "")} · ${data.timezone}`;
			list.replaceChildren();
			for (const row of data.rows) {
				const url = new URL(row.edit_url, location.href);
				if (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol) || typeof row.number !== 'string' || typeof row.status !== 'string' || typeof row.created !== 'string' || typeof row.age !== 'string') throw new Error(feedback.dataset.error);
				const item = document.createElement('li');
				const main = document.createElement('div');
				const link = document.createElement('a'); link.href = url.href; link.textContent = `#${row.number}`;
				const created = document.createElement('time'); created.dateTime = row.created_iso; created.textContent = row.created;
				main.append(link, created);
				const statusLabel = document.createElement('span'); statusLabel.className = 'adwc-order-status'; statusLabel.textContent = row.status;
				const age = document.createElement('span'); age.className = 'adwc-order-age'; age.textContent = row.age;
				item.append(main, statusLabel, age); list.append(item);
			}
			list.hidden = !data.rows.length;
			disclosure.hidden = !data.rows.length;
			const summary = disclosure.querySelector('summary');
			summary.textContent = `${summary.dataset.label} (${data.rows.length})`;
			return data.rows.length;
		};
		const request = async (save = false) => {
			if (inFlight) return;
			inFlight = true;
			const button = preferences.querySelector('button'); button.disabled = true;
			operations.setAttribute('aria-busy', 'true');
			message(feedback.dataset.loading);
			list.hidden = true;
			try {
				if (save) {
					const response = await fetch(operations.dataset.preferences, {
						method: 'POST', credentials: 'same-origin', cache: 'no-store',
						headers: { 'X-WP-Nonce': root.dataset.nonce, 'Content-Type': 'application/json' },
						body: JSON.stringify({ age_hours: preferences.elements.age_hours.value, statuses: [...preferences.querySelectorAll('input[type=checkbox]:checked')].map((input) => input.value) })
					});
					const saved = await response.json();
					if (!response.ok) throw new Error(saved.message || feedback.dataset.error);
				}
				const response = await fetch(operations.dataset.endpoint, { credentials: 'same-origin', headers: { 'X-WP-Nonce': root.dataset.nonce }, cache: 'no-store' });
				const data = await response.json();
				if (!response.ok) throw new Error(data.message || feedback.dataset.error);
				const length = render(data);
				message(save ? `${feedback.dataset.saved}${length ? '' : ` ${feedback.dataset.empty}`}` : length ? '' : feedback.dataset.empty, save ? 'saved' : 'ready');
			} catch (error) {
				list.hidden = true;
				counts.hidden = true;
				description.textContent = '';
				message(error.message || feedback.dataset.error, 'error');
			} finally {
				inFlight = false; button.disabled = false; operations.setAttribute('aria-busy', 'false');
			}
		};
		preferences.addEventListener('submit', (event) => { event.preventDefault(); request(true); });
		return () => request();
	};
	const loadOperations = setupOperations();
	let started = false;
	form.addEventListener('submit', (event) => { event.preventDefault(); if (!started) loadOperations(); started = true; load(); });
	// Hidden/collapsed widgets do not request financial data until opened.
	const box = root.closest('.postbox');
	const maybeStart = () => {
		if (!started && root.getClientRects().length && (!box || !box.classList.contains('closed'))) { started = true; load(); loadOperations(); }
	};
	if ('IntersectionObserver' in window) {
		const observer = new IntersectionObserver((entries) => { if (entries.some((entry) => entry.isIntersecting)) maybeStart(); });
		observer.observe(root);
	}
	if (box) new MutationObserver(maybeStart).observe(box, { attributes: true, attributeFilter: ['class', 'style'] });
	maybeStart();
})();
