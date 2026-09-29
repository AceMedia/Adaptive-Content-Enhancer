/* WordPress settings form with the same persistent save rhythm as Ace Crawl. */
document.addEventListener('DOMContentLoaded', () => {
	const form = document.getElementById('ace-content-settings-form');
	const button = document.getElementById('ace-content-save');
	const auto = document.getElementById('ace-content-auto-save');
	const message = document.querySelector('.ace-content-savebar__message');
	const config = window.aceContentSettings;
	if (!form || !button || !auto || !message || !config) return;
	document.querySelector('.ace-content-settings')?.classList.add('ace-content-js');
	document.querySelector('.ace-content-fallback-save')?.setAttribute('hidden', 'hidden');

	const snapshot = () => {
		const entries = Array.from(new FormData(form).entries());
		return JSON.stringify(entries);
	};
	let saved = snapshot();
	let busy = false;
	let timer;
	try { auto.checked = localStorage.getItem('ace_content_auto_save') === '1'; } catch (error) { auto.checked = false; }

	const refresh = (schedule = false) => {
		const dirty = snapshot() !== saved;
		button.disabled = busy || !dirty;
		button.textContent = busy ? config.saving : dirty ? config.save : config.saved;
		if (schedule && dirty && auto.checked && !busy) {
			clearTimeout(timer);
			timer = setTimeout(save, 1200);
		}
	};

	async function save() {
		clearTimeout(timer);
		if (busy || snapshot() === saved) return;
		busy = true;
		refresh();
		const submitted = snapshot();
		const body = new FormData(form);
		body.set('action', 'ace_content_save_settings');
		body.set('nonce', config.nonce);
		try {
			const response = await fetch(config.url, { method: 'POST', credentials: 'same-origin', body });
			const result = await response.json();
			if (!response.ok || !result.success) throw new Error(result.data?.message || config.error);
			saved = submitted;
			message.textContent = result.data?.message || config.saved;
		} catch (error) {
			message.textContent = error.message || config.error;
		} finally {
			busy = false;
			refresh(snapshot() !== submitted);
		}
	}

	form.addEventListener('input', () => refresh(true));
	form.addEventListener('change', () => refresh(true));
	button.addEventListener('click', save);
	auto.addEventListener('change', () => {
		try { localStorage.setItem('ace_content_auto_save', auto.checked ? '1' : '0'); } catch (error) { /* Optional preference. */ }
		refresh(true);
	});
	window.addEventListener('beforeunload', (event) => {
		if (snapshot() !== saved) {
			event.preventDefault();
			event.returnValue = '';
		}
	});
});
