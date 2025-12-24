const API_BASE = (document.body.dataset.apiBase || '').replace(/\/+$/, '');
const API_BASE_PREFIX = API_BASE ? `${API_BASE}/` : '';
const API_ORIGIN = (() => {
	try {
		return API_BASE ? new URL(API_BASE, window.location.origin).origin : window.location.origin;
	} catch (err) {
		console.warn('Unable to parse API base URL', err);
		return window.location.origin;
	}
})();

const DEFAULT_SITE_TITLE = (() => {
	const el = document.querySelector('[data-brand-title]');
	const text = el?.textContent?.trim();
	return text || 'Home Library';
})();

const DEFAULT_SITE_SUBTITLE = (() => {
	const el = document.querySelector('[data-brand-subtitle]');
	const text = el?.textContent?.trim();
	return text || 'Curated by the Self family';
})();

const DEFAULT_SW_NAME = 'HomeLib Console';
const DEFAULT_SW_VERSION = '1.0';
const DEFAULT_SW_URL = '';

const state = {
	books: [],
	filteredBooks: [],
	checkedOut: [],
	users: [],
	sessionUser: null,
	dataLoadedRole: null,
	bookFilterText: '',
	bookFilterStatus: 'all',
	activePanel: null,
	pendingPanel: null,
	selectedBook: null,
	bookFiles: { files: [] },
	bookHistory: { events: [] },
	bookEditMode: false,
	bookDeleteConfirm: false,
	myHistoryEvents: [],
	selectedUser: null,
	userLoans: [],
	userHistory: { events: [] },
	userResetPreview: null,
	userDeleteConfirm: false,
	pendingFiles: {
		cover: [],
		return: []
	},
	settings: {
		siteTitle: DEFAULT_SITE_TITLE,
		siteSubtitle: DEFAULT_SITE_SUBTITLE,
		swName: DEFAULT_SW_NAME,
		swVersion: DEFAULT_SW_VERSION,
		swURL: DEFAULT_SW_URL
	}
};

const BOOK_CONDITIONS = ['New', 'Like New', 'Very Good', 'Good', 'Acceptable', 'Poor'];
const DEFAULT_BOOK_CONDITION = 'Good';

const bookSearchInstances = {};
const BOOK_SEARCH_LIMIT = 8;
let detailUploadInFlight = false;

document.addEventListener('DOMContentLoaded', () => {
	initAuth();
	applyRoleVisibility();
	initNav();
	initFilters();
	initForms();
	initBookSearch();
	initFileBuckets();
	initDetailDrawer();
	initUserDrawer();
	initUserDirectory();
	initDueSoonList();
	initMyHistoryPanel();
	initImageLightbox();
	initSettingsPanel();
	applySettingsBranding();
	renderMyHistory();
});

async function bootstrap() {
	if (!state.sessionUser) {
		return;
	}
	try {
		const tasks = [loadSettings(), loadBooks(), loadMyHistory()];
		if (isLibrarian()) {
			tasks.push(loadCheckedOut(), loadUsers());
		} else {
			state.checkedOut = [];
			state.users = [];
		}
		await Promise.all(tasks);
		if (isLibrarian()) {
			renderDashboard();
			renderCheckedOut();
			renderUsers();
		} else {
			renderBooks();
		}
	} catch (err) {
		handleError(err);
	}
}

function initNav() {
	const navButtons = document.querySelectorAll('[data-panel-target]');
	navButtons.forEach((btn) => {
		btn.addEventListener('click', () => {
			if (!canAccessElement(btn)) {
				pushToast('You do not have access to that panel.', 'error');
				return;
			}
			showPanel(btn.dataset.panelTarget);
		});
	});
	const navToggles = document.querySelectorAll('[data-nav-toggle]');
	navToggles.forEach((toggle) => {
		toggle.addEventListener('click', () => {
			if (!canAccessElement(toggle)) {
				pushToast('You do not have access to that panel.', 'error');
				return;
			}
			toggleNavGroup(toggle);
		});
		const group = toggle.closest('.nav-group');
		const isOpen = group?.classList.contains('is-open');
		toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
	});
}

function showPanel(panelName) {
	const resolvedName = resolvePanelName(panelName);
	const panels = document.querySelectorAll('.panel');
	panels.forEach((panel) => {
		panel.classList.toggle('is-active', panel.dataset.panel === resolvedName);
	});
	const navButtons = document.querySelectorAll('.nav-link');
	navButtons.forEach((btn) => {
		const matches = btn.dataset.panelTarget === resolvedName;
		btn.classList.toggle('is-active', matches);
	});
	syncNavGroupToPanel(resolvedName);
	const title = document.getElementById('panelTitle');
	if (title) {
		title.textContent = panelLabels[resolvedName] || 'History';
	}
	state.activePanel = resolvedName;
	if (state.pendingPanel === resolvedName) {
		state.pendingPanel = null;
	}
}

function toggleNavGroup(toggle, forcedState) {
	const group = toggle.closest('.nav-group');
	if (!group) {
		return;
	}
	const nextState = typeof forcedState === 'boolean' ? forcedState : !group.classList.contains('is-open');
	setNavGroupOpen(group, nextState);
}

function setNavGroupOpen(group, shouldOpen) {
	group.classList.toggle('is-open', shouldOpen);
	const toggle = group.querySelector('[data-nav-toggle]');
	if (toggle) {
		toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
		const submenuId = toggle.dataset.navToggle;
		const submenu = submenuId ? document.getElementById(submenuId) : group.querySelector('.nav-submenu');
		if (submenu) {
			submenu.hidden = !shouldOpen;
		}
	}
}

function syncNavGroupToPanel(panelName) {
	if (!panelName) {
		return;
	}
	const activeLink = document.querySelector(`.nav-link[data-panel-target="${panelName}"]`);
	if (!activeLink) {
		return;
	}
	const group = activeLink.closest('.nav-group');
	if (!group) {
		return;
	}
	setNavGroupOpen(group, true);
}

function resolvePanelName(panelName) {
	const requested = panelName ? document.querySelector(`.panel[data-panel="${panelName}"]`) : null;
	if (requested && canAccessElement(requested)) {
		return requested.dataset.panel;
	}
	return findDefaultPanel() || 'books';
}

function findDefaultPanel() {
	const panels = Array.from(document.querySelectorAll('.panel'));
	const allowed = panels.find((panel) => canAccessElement(panel));
	return allowed ? allowed.dataset.panel : null;
}

const panelLabels = {
	dashboard: 'Dashboard',
	books: 'Library catalog',
	checkedOut: 'Loaned Books',
	newBook: 'Add a book',
	checkOut: 'Check out a book',
	checkIn: 'Check in a book',
	account: 'Account Settings',
	users: 'Readers and librarians',
	settings: 'Library settings'
};

function initFilters() {
	const search = document.getElementById('bookSearch');
	const status = document.getElementById('bookStatusFilter');
	if (search) {
		search.addEventListener('input', () => {
			state.bookFilterText = search.value.trim();
			applyBookFilters();
		});
	}
	if (status) {
		status.addEventListener('change', () => {
			state.bookFilterStatus = status.value;
			applyBookFilters();
		});
	}
}

function initForms() {
	const newBookForm = document.getElementById('newBookForm');
	const checkOutForm = document.getElementById('checkOutForm');
	const checkInForm = document.getElementById('checkInForm');
	const newUserForm = document.getElementById('newUserForm');
	const changePasswordForm = document.getElementById('changePasswordForm');
	const profileForm = document.getElementById('profileForm');

	if (newBookForm) {
		newBookForm.addEventListener('submit', handleNewBook);
		newBookForm.addEventListener('reset', () => {
			clearFileBucket('cover');
		});
	}
	if (checkOutForm) {
		checkOutForm.addEventListener('submit', handleCheckOut);
	}
	if (checkInForm) {
		checkInForm.addEventListener('submit', handleCheckIn);
		checkInForm.addEventListener('reset', () => {
			clearFileBucket('return');
		});
	}
	if (newUserForm) {
		newUserForm.addEventListener('submit', handleNewUser);
	}
	if (changePasswordForm) {
		changePasswordForm.addEventListener('submit', handlePasswordChange);
	}
	if (profileForm) {
		profileForm.addEventListener('submit', handleProfileUpdate);
	}

	const newUserNameInput = newUserForm?.querySelector('input[name="name"]');
	const usernameInput = newUserForm?.querySelector('input[name="username"]');
	if (newUserNameInput && usernameInput) {
		newUserNameInput.addEventListener('input', () => {
			if (!usernameInput.dataset.touched) {
				usernameInput.value = slugify(newUserNameInput.value);
			}
		});
		usernameInput.addEventListener('input', () => {
			usernameInput.dataset.touched = 'true';
		});
	}

	const profileNameInput = profileForm?.querySelector('input[name="name"]');
	profileNameInput?.addEventListener('input', () => {
		profileNameInput.dataset.dirty = 'true';
	});

	syncAccountForms({ force: true });

	const checkInUserSelect = document.getElementById('checkInUserSelect');
}

function initDetailDrawer() {
	const drawer = document.getElementById('bookDetailDrawer');
	const closeBtn = document.getElementById('closeBookDetail');
	const uploadInput = document.getElementById('detailAttachmentInput');
	closeBtn?.addEventListener('click', () => toggleDrawer(false));
	drawer?.addEventListener('click', (event) => {
		if (event.target === drawer) {
			toggleDrawer(false);
			return;
		}
		const previewTrigger = event.target.closest('[data-image-preview]');
		if (previewTrigger) {
			event.preventDefault();
			handleDetailImagePreview(previewTrigger);
			return;
		}
		const readerTrigger = event.target.closest('[data-history-user]');
		if (readerTrigger) {
			event.preventDefault();
			const userId = Number(readerTrigger.dataset.historyUser);
			if (userId > 0) {
				openUserDetail(userId);
			}
			return;
		}
		const actionTrigger = event.target.closest('[data-detail-action]');
		if (actionTrigger) {
			event.preventDefault();
			handleBookDetailAction(actionTrigger);
		}
	});
	uploadInput?.addEventListener('change', (event) => {
		if (!event.target.files || !event.target.files.length) {
			return;
		}
		handleDetailAttachmentUpload(event.target.files);
		event.target.value = '';
	});
}

function initUserDrawer() {
	const drawer = document.getElementById('userDetailDrawer');
	const closeBtn = document.getElementById('closeUserDetail');
	closeBtn?.addEventListener('click', () => toggleUserDrawer(false));
	drawer?.addEventListener('click', (event) => {
		if (event.target === drawer) {
			toggleUserDrawer(false);
			return;
		}
		const actionTrigger = event.target.closest('[data-user-action]');
		if (actionTrigger) {
			event.preventDefault();
			handleUserDrawerAction(actionTrigger);
			return;
		}
		const loanTrigger = event.target.closest('[data-loan-book]');
		if (loanTrigger) {
			event.preventDefault();
			const bookId = Number(loanTrigger.dataset.loanBook);
			beginCheckInFromLoan(bookId);
		}
	});
}

function initUserDirectory() {
	const list = document.getElementById('userList');
	if (!list) return;
	list.addEventListener('click', (event) => {
		const trigger = event.target.closest('[data-user-id]');
		if (!trigger) {
			return;
		}
		event.preventDefault();
		const userId = Number(trigger.dataset.userId);
		if (userId) {
			openUserDetail(userId);
		}
	});
}

function initDueSoonList() {
	const list = document.getElementById('dueSoonList');
	if (!list) return;
	const handleTrigger = (target) => {
		if (!target) {
			return;
		}
		const bookId = Number(target.dataset.dueBook);
		if (Number.isNaN(bookId) || bookId <= 0) {
			return;
		}
		openCheckInFlow(bookId);
	};
	list.addEventListener('click', (event) => {
		const trigger = event.target.closest('[data-due-book]');
		if (!trigger) {
			return;
		}
		event.preventDefault();
		handleTrigger(trigger);
	});
	list.addEventListener('keydown', (event) => {
		if (event.key !== 'Enter' && event.key !== ' ') {
			return;
		}
		const trigger = event.target.closest('[data-due-book]');
		if (!trigger) {
			return;
		}
		event.preventDefault();
		handleTrigger(trigger);
	});
}

function toggleDrawer(show) {
	const drawer = document.getElementById('bookDetailDrawer');
	if (!drawer) return;
	if (show) {
		drawer.classList.add('is-visible');
		drawer.setAttribute('aria-hidden', 'false');
	} else {
		drawer.classList.remove('is-visible');
		drawer.setAttribute('aria-hidden', 'true');
	}
}

function toggleUserDrawer(show) {
	const drawer = document.getElementById('userDetailDrawer');
	if (!drawer) return;
	if (show) {
		drawer.classList.add('is-visible');
		drawer.setAttribute('aria-hidden', 'false');
	} else {
		drawer.classList.remove('is-visible');
		drawer.setAttribute('aria-hidden', 'true');
	}
}

function initImageLightbox() {
	const lightbox = document.getElementById('imageLightbox');
	if (!lightbox) {
		return;
	}
	const closeButtons = lightbox.querySelectorAll('[data-lightbox-close]');
	closeButtons.forEach((btn) => btn.addEventListener('click', closeImageLightbox));
	lightbox.addEventListener('click', (event) => {
		if (event.target === lightbox) {
			closeImageLightbox();
		}
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && lightbox.classList.contains('is-visible')) {
			closeImageLightbox();
		}
	});
}

function openImageLightbox(url, label) {
	const lightbox = document.getElementById('imageLightbox');
	if (!lightbox) {
		return;
	}
	const image = document.getElementById('lightboxImage');
	const caption = document.getElementById('lightboxCaption');
	if (image) {
		image.src = url;
		image.alt = label || 'Book photo';
	}
	if (caption) {
		caption.textContent = label || '';
	}
	lightbox.classList.add('is-visible');
	lightbox.setAttribute('aria-hidden', 'false');
	document.body.classList.add('lightbox-open');
}

function closeImageLightbox() {
	toggleImageLightbox(false);
}

function toggleImageLightbox(show) {
	const lightbox = document.getElementById('imageLightbox');
	if (!lightbox) {
		return;
	}
	if (show) {
		lightbox.classList.add('is-visible');
		lightbox.setAttribute('aria-hidden', 'false');
		document.body.classList.add('lightbox-open');
	} else {
		lightbox.classList.remove('is-visible');
		lightbox.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('lightbox-open');
		const image = document.getElementById('lightboxImage');
		if (image) {
			image.removeAttribute('src');
			image.alt = '';
		}
		const caption = document.getElementById('lightboxCaption');
		if (caption) {
			caption.textContent = '';
		}
	}
}

async function loadBooks() {
	if (!state.sessionUser) {
		state.books = [];
		state.filteredBooks = [];
		return { books: [] };
	}
	const data = await apiRequest('/book/list?status=all');
	state.books = data.books || [];
	applyBookFilters();
	refreshBookSearchSelections();
	return data;
}

async function loadCheckedOut() {
	if (!isLibrarian()) {
		state.checkedOut = [];
		return { books: [] };
	}
	const data = await apiRequest('/book/list?status=out');
	state.checkedOut = data.books || [];
	renderCheckedOut();
	renderDashboard();
	renderUsers();
	return data;
}

async function loadUsers() {
	if (!isLibrarian()) {
		state.users = [];
		return { users: [] };
	}
	const data = await apiRequest('/user/listAll');
	state.users = data.users || [];
	renderUsers();
	renderDashboard();
	return data;
}

async function loadSettings() {
	if (!state.sessionUser) {
		resetSettingsToDefaults();
		return { settings: state.settings };
	}
	const data = await apiRequest('/settings/get');
	const normalized = normalizeSettingsPayload(data?.settings);
	state.settings = normalized;
	applySettingsBranding();
	renderSettingsPanel();
	return { settings: normalized };
}

async function loadMyHistory() {
	if (!state.sessionUser) {
		state.myHistoryEvents = [];
		renderMyHistory();
		return { events: [] };
	}
	try {
		const data = await apiRequest('/user/myHistory');
		state.myHistoryEvents = data?.events || [];
	} catch (err) {
		if (!err || err.status !== 401) {
			handleError(err);
		}
		state.myHistoryEvents = [];
	} finally {
		renderMyHistory();
	}
	return { events: state.myHistoryEvents };
}

function applyBookFilters() {
	const text = state.bookFilterText.toLowerCase();
	const status = state.bookFilterStatus;
	state.filteredBooks = state.books.filter((book) => {
		const matchesText = !text || `${book.title || ''} ${book.author || ''}`.toLowerCase().includes(text);
		const matchesStatus =
			status === 'all' ||
			(status === 'available' && Number(book.inLibrary) === 1) ||
			(status === 'out' && Number(book.inLibrary) === 0);
		return matchesText && matchesStatus;
	});
	renderBooks();
	renderDashboard();
}

function renderBooks() {
	const grid = document.getElementById('booksGrid');
	if (!grid) return;
	if (!state.filteredBooks.length) {
		grid.innerHTML = '<p class="muted">No books found. Try a different filter.</p>';
		return;
	}
	const fragment = document.createDocumentFragment();
	state.filteredBooks.forEach((book) => {
		const card = document.createElement('article');
		card.className = 'book-card';
		const available = Number(book.inLibrary) === 1;
		const showBorrower = isLibrarian();
		const statusDetail = available ? '' : showBorrower ? `With ${escapeHtml(book.borrowedBy || 'Unknown')}` : '';
		card.innerHTML = `
			<div>
				<h4>${escapeHtml(book.title || 'Untitled')}</h4>
				<p>${escapeHtml(book.author || 'Unknown author')}</p>
			</div>
			<div class="status-pill ${available ? 'available' : 'out'}">
				${available ? 'Available' : 'Checked out'}
			</div>
			<p>${statusDetail}</p>
		`; 
		const actions = document.createElement('div');
		actions.className = 'card-actions';
		const detailBtn = document.createElement('button');
		detailBtn.className = 'ghost';
		detailBtn.textContent = 'View details';
		detailBtn.addEventListener('click', () => openBookDetail(book.id));
		actions.appendChild(detailBtn);
		if (isLibrarian()) {
			if (available) {
				const checkoutBtn = document.createElement('button');
				checkoutBtn.textContent = 'Check out';
				checkoutBtn.addEventListener('click', () => {
					prefillCheckout(book.id);
					showPanel('checkOut');
				});
				actions.appendChild(checkoutBtn);
			} else {
				const checkinBtn = document.createElement('button');
				checkinBtn.textContent = 'Check in';
				checkinBtn.addEventListener('click', () => {
					prefillCheckin(book.id);
					showPanel('checkIn');
				});
				actions.appendChild(checkinBtn);
			}
		}
		card.appendChild(actions);
		fragment.appendChild(card);
	});
	grid.innerHTML = '';
	grid.appendChild(fragment);
}

function renderCheckedOut() {
	const grid = document.getElementById('checkedOutGrid');
	if (!grid) return;
	if (!state.checkedOut.length) {
		grid.innerHTML = '<p class="muted">All books are currently home.</p>';
		return;
	}
	const fragment = document.createDocumentFragment();
	state.checkedOut.forEach((book) => {
		const card = document.createElement('article');
		card.className = 'book-card';
		card.innerHTML = `
			<div>
				<h4>${escapeHtml(book.title)}</h4>
				<p>${escapeHtml(book.author || '')}</p>
			</div>
			<p>Borrowed by <strong>${escapeHtml(book.borrowedBy || 'Unknown')}</strong></p>
			<p>Due ${book.returnBy ? formatDateOnly(book.returnBy) : 'No due date'}</p>
		`;
		const actions = document.createElement('div');
		actions.className = 'card-actions';
		const detailBtn = document.createElement('button');
		detailBtn.className = 'ghost';
		detailBtn.textContent = 'Details';
		detailBtn.addEventListener('click', () => openBookDetail(book.id));
		actions.appendChild(detailBtn);
		const checkinBtn = document.createElement('button');
		checkinBtn.textContent = 'Check in';
		checkinBtn.addEventListener('click', () => {
			prefillCheckin(book.id);
			showPanel('checkIn');
		});
		actions.appendChild(checkinBtn);
		card.appendChild(actions);
		fragment.appendChild(card);
	});
	grid.innerHTML = '';
	grid.appendChild(fragment);
}

function prefillCheckout(bookId) {
	setBookSearchSelection('checkout', bookId);
}

function prefillCheckin(bookId) {
	setBookSearchSelection('checkin', bookId);
}

function setCheckInConditionFromBook(book) {
	const select = document.getElementById('checkInConditionSelect');
	if (!select) {
		return;
	}
	const target = book ? normalizeConditionLabel(book.condition) : DEFAULT_BOOK_CONDITION;
	select.value = target;
	if (select.value !== target) {
		select.value = DEFAULT_BOOK_CONDITION;
	}
}

function openCheckInFlow(bookId) {
	if (!isLibrarian()) {
		pushToast('Only librarians can manage check-ins.', 'error');
		return;
	}
	showPanel('checkIn');
	if (bookId) {
		prefillCheckin(bookId);
	}
	const focusTarget = document.getElementById('checkInUserSelect');
	focusTarget?.focus();
}

function initBookSearch() {
	const combos = document.querySelectorAll('[data-book-search]');
	combos.forEach((combo) => {
		const key = combo.dataset.bookSearch;
		const target = combo.dataset.bookTarget;
		if (!key || !target) {
			return;
		}
		const input = combo.querySelector('input[type="search"]');
		const results = combo.querySelector('.combo-results');
		const hidden = document.getElementById(target);
		if (!input || !results || !hidden) {
			return;
		}
		const filter = combo.dataset.bookFilter || 'all';
		const instance = {
			key,
			combo,
			input,
			results,
			hidden,
			filter,
			activeIndex: -1,
			matches: []
		};
		bookSearchInstances[key] = instance;

		input.addEventListener('input', () => handleBookSearchInput(instance));
		input.addEventListener('focus', () => handleBookSearchInput(instance, { preserveSelection: true }));
		input.addEventListener('keydown', (event) => handleBookSearchKeydown(event, instance));
		input.addEventListener('blur', () => setTimeout(() => closeBookSearchResults(instance), 120));

		results.addEventListener('mousedown', (event) => event.preventDefault());
		results.addEventListener('click', (event) => {
			const option = event.target.closest('[data-book-option]');
			if (!option) return;
			selectBookInSearch(instance, option.dataset.bookOption);
		});
	});

	document.addEventListener('click', (event) => {
		Object.values(bookSearchInstances).forEach((instance) => {
			if (!instance.combo.contains(event.target)) {
				closeBookSearchResults(instance);
			}
		});
	});
}

function handleBookSearchInput(instance, options = {}) {
	const { preserveSelection = false } = options;
	if (!preserveSelection) {
		instance.hidden.value = '';
		instance.input.dataset.selection = '';
	}
	const query = instance.input.value.trim().toLowerCase();
	const matches = state.books
		.filter((book) => matchesBookSearchFilter(book, instance.filter))
		.filter((book) => matchesBookSearchQuery(book, query))
		.slice(0, BOOK_SEARCH_LIMIT);
	renderBookSearchResults(instance, matches, query);
}

function renderBookSearchResults(instance, matches, query) {
	instance.matches = matches;
	instance.activeIndex = -1;
	if (!matches.length) {
		if (query === '') {
			closeBookSearchResults(instance);
			return;
		}
		instance.results.innerHTML = '<div class="combo-empty">No matches found</div>';
		instance.results.classList.add('is-open');
		return;
	}
	const markup = matches
		.map((book, index) => {
			const meta = formatBookSearchMeta(book);
			return `<button type="button" class="combo-option" data-book-option="${book.id}" data-index="${index}">
				<strong>${escapeHtml(book.title || 'Untitled')}</strong>
				<span>${escapeHtml(meta)}</span>
			</button>`;
		})
		.join('');
	instance.results.innerHTML = markup;
	instance.results.classList.add('is-open');
}

function closeBookSearchResults(instance) {
	instance.results.classList.remove('is-open');
	instance.results.innerHTML = '';
	instance.activeIndex = -1;
}

function handleBookSearchKeydown(event, instance) {
	const key = event.key;
	if (key !== 'ArrowDown' && key !== 'ArrowUp' && key !== 'Enter' && key !== 'Escape') {
		return;
	}
	const options = Array.from(instance.results.querySelectorAll('[data-book-option]'));
	if (key === 'ArrowDown' || key === 'ArrowUp') {
		event.preventDefault();
		if (!instance.results.classList.contains('is-open')) {
			handleBookSearchInput(instance, { preserveSelection: true });
			return;
		}
		if (!options.length) {
			return;
		}
		if (key === 'ArrowDown') {
			instance.activeIndex = (instance.activeIndex + 1) % options.length;
		} else {
			instance.activeIndex = instance.activeIndex <= 0 ? options.length - 1 : instance.activeIndex - 1;
		}
		options.forEach((option, idx) => option.classList.toggle('is-active', idx === instance.activeIndex));
		const active = options[instance.activeIndex];
		if (active) {
			active.scrollIntoView({ block: 'nearest' });
		}
		return;
	}
	if (key === 'Escape') {
		closeBookSearchResults(instance);
		return;
	}
	if (!options.length) {
		return;
	}
	const targetIndex = instance.activeIndex >= 0 ? instance.activeIndex : options.length === 1 ? 0 : -1;
	if (targetIndex >= 0) {
		event.preventDefault();
		const option = options[targetIndex];
		selectBookInSearch(instance, option.dataset.bookOption);
	}
}

function selectBookInSearch(instance, bookId) {
	setBookSearchSelection(instance.key, bookId);
	closeBookSearchResults(instance);
}

function setBookSearchSelection(key, bookId) {
	const instance = bookSearchInstances[key];
	if (!instance) {
		return;
	}
	if (!bookId) {
		instance.hidden.value = '';
		instance.input.value = '';
		instance.input.dataset.selection = '';
		applyBookSelectionHooks(key, null);
		return;
	}
	const book = getBookById(bookId);
	if (!book) {
		instance.hidden.value = '';
		instance.input.value = '';
		instance.input.dataset.selection = '';
		applyBookSelectionHooks(key, null);
		return;
	}
	instance.hidden.value = String(book.id);
	instance.input.value = formatBookSearchValue(book);
	instance.input.dataset.selection = String(book.id);
	applyBookSelectionHooks(key, book);
}

function applyBookSelectionHooks(key, book) {
	if (key === 'checkin') {
		setCheckInConditionFromBook(book);
	}
}

function refreshBookSearchSelections() {
	Object.keys(bookSearchInstances).forEach((key) => {
		const instance = bookSearchInstances[key];
		if (!instance) {
			return;
		}
		const current = instance.hidden.value;
		if (!current) {
			instance.input.value = '';
			instance.input.dataset.selection = '';
			return;
		}
		setBookSearchSelection(key, current);
	});
}

function matchesBookSearchFilter(book, filter) {
	if (filter === 'available') {
		return Number(book.inLibrary) === 1;
	}
	if (filter === 'checkedOut') {
		return Number(book.inLibrary) === 0;
	}
	return true;
}

function matchesBookSearchQuery(book, query) {
	if (!query) {
		return true;
	}
	const haystack = `${book.title || ''} ${book.author || ''} ${book.isbn || ''}`.toLowerCase();
	return haystack.includes(query);
}

function formatBookSearchValue(book) {
	const title = book.title || 'Untitled';
	const author = book.author ? ` · ${book.author}` : '';
	return `${title}${author}`;
}

function formatBookSearchMeta(book) {
	const author = book.author || 'Unknown author';
	const status = Number(book.inLibrary) === 1 ? 'Available' : isLibrarian() ? `With ${book.borrowedBy || 'Unknown'}` : 'Checked out';
	return `${author} · ${status}`;
}

function getBookById(bookId) {
	const target = Number(bookId);
	if (!target) {
		return null;
	}
	return state.books.find((book) => Number(book.id) === target) || null;
}

function initFileBuckets() {
	const fileInputs = document.querySelectorAll('input[type="file"][data-file-bucket]');
	fileInputs.forEach((input) => {
		const bucket = input.dataset.fileBucket;
		if (!bucket) {
			return;
		}
		input.addEventListener('change', () => {
			if (!input.files || !input.files.length) {
				return;
			}
			addFilesToBucket(bucket, input.files);
			input.value = '';
		});
	});

	const trays = document.querySelectorAll('[data-file-tray]');
	trays.forEach((tray) => {
		const bucket = tray.dataset.fileTray;
		renderFileTray(bucket);
		tray.addEventListener('click', (event) => {
			const pill = event.target.closest('[data-remove-file]');
			if (!pill) {
				return;
			}
			event.preventDefault();
			const idx = Number(pill.dataset.index);
			removeFileFromBucket(bucket, idx);
		});
	});
}

function addFilesToBucket(bucket, filesList) {
	const files = Array.from(filesList || []);
	if (!files.length) {
		return;
	}
	if (!state.pendingFiles[bucket]) {
		state.pendingFiles[bucket] = [];
	}
	state.pendingFiles[bucket] = state.pendingFiles[bucket].concat(files);
	renderFileTray(bucket);
}

function getBucketFiles(bucket, fallbackInput = null) {
	const pending = state.pendingFiles[bucket] || [];
	if (pending.length) {
		return pending.slice();
	}
	if (fallbackInput && fallbackInput.files && fallbackInput.files.length) {
		return Array.from(fallbackInput.files);
	}
	return [];
}

function removeFileFromBucket(bucket, index) {
	if (!state.pendingFiles[bucket] || isNaN(index)) {
		return;
	}
	state.pendingFiles[bucket].splice(index, 1);
	renderFileTray(bucket);
}

function clearFileBucket(bucket) {
	if (!state.pendingFiles[bucket]) {
		state.pendingFiles[bucket] = [];
	} else {
		state.pendingFiles[bucket] = [];
	}
	renderFileTray(bucket);
}

function renderFileTray(bucket) {
	const tray = document.querySelector(`[data-file-tray="${bucket}"]`);
	if (!tray) {
		return;
	}
	const files = state.pendingFiles[bucket] || [];
	if (!files.length) {
		tray.innerHTML = '<p class="muted">No photos yet.</p>';
		return;
	}
	const markup = files
		.map((file, index) => {
			const label = escapeHtml(file.name || `Photo ${index + 1}`);
			const size = file.size ? `<small>${formatBytes(file.size)}</small>` : '';
			return `<button type="button" class="attachment-pill" data-remove-file="${bucket}" data-index="${index}">
				<span>${label}</span>
				${size}
			</button>`;
		})
		.join('');
	tray.innerHTML = markup;
}

async function openBookDetail(bookId) {
	const body = document.getElementById('bookDetailBody');
	if (body) {
		body.innerHTML = '<p>Loading...</p>';
	}
	toggleDrawer(true);
	try {
		const detailRequests = [
			apiRequest(`/book/details?id=${bookId}`),
			apiRequest(`/files/forBook?bookId=${bookId}`)
		];
		if (isLibrarian()) {
			detailRequests.push(apiRequest(`/book/history?bookId=${bookId}`));
		} else {
			detailRequests.push(Promise.resolve({ events: [] }));
		}
		const [book, files, history] = await Promise.all(detailRequests);
		state.selectedBook = book;
		state.bookFiles = files;
		state.bookHistory = history;
		state.bookEditMode = false;
		state.bookDeleteConfirm = false;
		renderBookDetail();
	} catch (err) {
		handleError(err);
		toggleDrawer(false);
	}
}

function renderBookDetail() {
	const body = document.getElementById('bookDetailBody');
	if (!body || !state.selectedBook) return;
	const book = state.selectedBook;
	const fileBundle = state.bookFiles?.files || [];
	const history = state.bookHistory?.events || [];
	const isLibrarianView = isLibrarian();
	const editing = isLibrarianView && state.bookEditMode;
	const hasFiles = fileBundle.length > 0;
	const emptyCopy = isLibrarianView ? 'No attachments yet.' : 'No cover photos yet.';
	const uploadButton = isLibrarianView ? '<button type="button" class="ghost" data-detail-action="add-cover-photo">Upload photo</button>' : '';
	const gallery = hasFiles
		? `<div class="gallery">${fileBundle.map((file) => renderFileFigure(file)).join('')}</div>`
		: `<div class="empty-attachments">
			<p class="muted">${emptyCopy}</p>
			${uploadButton}
		</div>`;
	const timeline = isLibrarianView
		? history.length
			? `<ul class="timeline">${history.map((event) => renderHistoryEvent(event)).join('')}</ul>`
			: '<p class="muted">No checkout history recorded.</p>'
		: '<p class="muted">Borrowing history is available to librarians only.</p>';
	const managementButtons = [];
	if (isLibrarianView) {
		managementButtons.push(`<button type="button" class="ghost" data-detail-action="${editing ? 'cancel-edit-book' : 'begin-edit-book'}">
			${editing ? 'Cancel editing' : 'Edit book'}
		</button>`);
		managementButtons.push(
			'<button type="button" class="danger" data-detail-action="prompt-delete-book">Delete book</button>'
		);
	}
	const managementControls = managementButtons.length ? `<div class="detail-actions">${managementButtons.join('')}</div>` : '';
	const deleteWarning = state.bookDeleteConfirm
		? `<div class="detail-alert detail-alert--danger">
			<p>Deleting this book will remove all checkout history and associated photos. This cannot be undone.</p>
			<div class="detail-actions">
				<button type="button" class="danger" data-detail-action="confirm-delete-book">Delete permanently</button>
				<button type="button" class="ghost" data-detail-action="cancel-delete-book">Cancel</button>
			</div>
		</div>`
		: '';
	const editForm = editing ? renderBookEditForm(book) : '';
	const isbnValue = book.isbn ? escapeHtml(book.isbn) : '—';
	let metaHtml = `
		<div><p>Status</p><strong>${Number(book.inLibrary) === 1 ? 'Available' : 'Checked out'}</strong></div>
		<div><p>Date added</p><strong>${formatDate(book.dateAdded)}</strong></div>
		<div><p>Condition</p><strong>${escapeHtml(book.condition || 'Unknown')}</strong></div>
		<div><p>ISBN</p><strong>${isbnValue}</strong></div>
	`;
	if (isLibrarianView) {
		metaHtml += `
			<div><p>Borrowed by</p><strong>${escapeHtml(book.borrowedBy || '—')}</strong></div>
			<div><p>Due date</p><strong>${book.returnBy ? formatDateOnly(book.returnBy) : '—'}</strong></div>
		`;
	}
	body.innerHTML = `
		<div class="detail-header">
			<h3>${escapeHtml(book.title || 'Untitled')}</h3>
			<p>${escapeHtml(book.author || 'Unknown author')}</p>
		</div>
		${managementControls}
		${deleteWarning}
		${editForm}
		<div class="meta-grid">
			${metaHtml}
		</div>
		<h4>Files</h4>
		${gallery}
		<h4>History</h4>
		${timeline}
	`;
	wireBookDetailForm();
}

function renderBookEditForm(book) {
	const condition = normalizeConditionLabel(book.condition);
	return `
		<form id="editBookForm" class="detail-form stacked-form">
			<input type="hidden" name="bookId" value="${Number(book.id) || ''}" />
			<label>
				<span>Title</span>
				<input type="text" name="title" value="${escapeHtml(book.title || '')}" required />
			</label>
			<label>
				<span>Author</span>
				<input type="text" name="author" value="${escapeHtml(book.author || '')}" />
			</label>
			<label>
				<span>ISBN</span>
				<input type="text" name="isbn" value="${escapeHtml(book.isbn || '')}" />
			</label>
			<label>
				<span>Condition</span>
				<select name="condition">${renderBookConditionOptions(condition)}</select>
			</label>
			<div class="detail-actions">
				<button type="submit">Save changes</button>
				<button type="button" class="ghost" data-detail-action="cancel-edit-book">Discard</button>
			</div>
		</form>
	`;
}

function renderBookConditionOptions(selected) {
	const choice = selected || DEFAULT_BOOK_CONDITION;
	return BOOK_CONDITIONS.map((option) => {
		const selectedAttr = option === choice ? ' selected' : '';
		return `<option value="${escapeHtml(option)}"${selectedAttr}>${escapeHtml(option)}</option>`;
	}).join('');
}

function wireBookDetailForm() {
	const form = document.getElementById('editBookForm');
	if (!form) {
		return;
	}
	form.addEventListener('submit', handleBookEditSubmit);
}

function handleBookDetailAction(trigger) {
	if (!trigger) {
		return;
	}
	const action = trigger.dataset.detailAction;
	switch (action) {
		case 'add-cover-photo':
			beginDetailAttachmentFlow();
			break;
		case 'begin-edit-book':
			startBookEdit();
			break;
		case 'cancel-edit-book':
			cancelBookEdit();
			break;
		case 'prompt-delete-book':
			state.bookDeleteConfirm = true;
			state.bookEditMode = false;
			renderBookDetail();
			break;
		case 'cancel-delete-book':
			state.bookDeleteConfirm = false;
			renderBookDetail();
			break;
		case 'confirm-delete-book':
			confirmBookDeletion(trigger);
			break;
		default:
			break;
	}
}

function handleDetailImagePreview(trigger) {
	if (!trigger) {
		return;
	}
	const url = trigger.dataset.imagePreview;
	if (!url) {
		return;
	}
	const label = trigger.dataset.imageLabel || 'Book photo';
	openImageLightbox(url, label);
}

function startBookEdit() {
	if (!isLibrarian() || !state.selectedBook) {
		return;
	}
	state.bookEditMode = true;
	state.bookDeleteConfirm = false;
	renderBookDetail();
	const titleInput = document.querySelector('#editBookForm input[name="title"]');
	titleInput?.focus();
}

function cancelBookEdit() {
	state.bookEditMode = false;
	renderBookDetail();
}

async function handleBookEditSubmit(event) {
	event.preventDefault();
	if (!state.selectedBook) {
		return;
	}
	const form = event.currentTarget;
	const formData = new FormData(form);
	const payload = {
		bookId: Number(formData.get('bookId')),
		title: (formData.get('title') || '').toString().trim(),
		author: (formData.get('author') || '').toString().trim(),
		isbn: (formData.get('isbn') || '').toString().trim(),
		condition: normalizeConditionLabel(formData.get('condition') || state.selectedBook.condition || DEFAULT_BOOK_CONDITION)
	};
	if (!payload.bookId) {
		return handleError(new Error('Missing book selection.'));
	}
	if (!payload.title) {
		return handleError(new Error('Please provide a title.'));
	}
	if (!payload.author) {
		payload.author = null;
	}
	if (!payload.isbn) {
		payload.isbn = null;
	}
	let shouldRefreshDetail = false;
	try {
		setFormLoading(form, true);
		const updated = await apiRequest('/book/update', {
			method: 'POST',
			body: payload
		});
		if (!updated) {
			throw new Error('Unable to update book.');
		}
		state.selectedBook = updated;
		state.bookEditMode = false;
		state.bookDeleteConfirm = false;
		applyUpdatedBookToState(updated);
		pushToast('Book updated', 'success');
		shouldRefreshDetail = true;
	} catch (err) {
		handleError(err);
	} finally {
		setFormLoading(form, false);
	}
	if (shouldRefreshDetail) {
		renderBookDetail();
	}
}

async function confirmBookDeletion(trigger) {
	if (!isLibrarian() || !state.selectedBook) {
		return;
	}
	const bookId = Number(state.selectedBook.id);
	if (!bookId) {
		return;
	}
	const button = trigger instanceof HTMLElement ? trigger : null;
	setDetailDeleteLoading(true, button);
	try {
		await apiRequest('/book/delete', {
			method: 'POST',
			body: { bookId }
		});
		pushToast('Book deleted', 'success');
		state.bookDeleteConfirm = false;
		state.selectedBook = null;
		toggleDrawer(false);
		await Promise.all([loadBooks(), loadCheckedOut()]);
	} catch (err) {
		handleError(err);
	} finally {
		setDetailDeleteLoading(false, button);
	}
}

function setDetailDeleteLoading(isLoading, button = null) {
	const target = button || document.querySelector('[data-detail-action="confirm-delete-book"]');
	if (!target) {
		return;
	}
	if (!target.dataset.defaultLabel) {
		target.dataset.defaultLabel = target.textContent;
	}
	target.disabled = isLoading;
	target.textContent = isLoading ? 'Deleting...' : target.dataset.defaultLabel;
}

function applyUpdatedBookToState(updatedBook) {
	if (!updatedBook || !updatedBook.id) {
		return;
	}
	replaceBookInCollection(state.books, updatedBook);
	replaceBookInCollection(state.checkedOut, updatedBook);
	applyBookFilters();
	renderCheckedOut();
}

function replaceBookInCollection(collection, updatedBook) {
	if (!Array.isArray(collection)) {
		return;
	}
	const id = Number(updatedBook.id);
	const index = collection.findIndex((book) => Number(book.id) === id);
	if (index === -1) {
		return;
	}
	collection[index] = Object.assign({}, collection[index], updatedBook);
}

function renderFileFigure(file) {
	const isImage = /\.(jpe?g|png|gif|webp|svg)$/i.test(file.filename || '');
	const url = buildFileUrl(file);
	const escapedUrl = escapeHtml(url);
	if (isImage) {
		const label = escapeHtml(file.filename || 'Book photo');
		return `<figure>
			<button type="button" data-image-preview="${escapedUrl}" data-image-label="${label}">
				<img src="${escapedUrl}" alt="${label}" loading="lazy" />
			</button>
		</figure>`;
	}
	return `<figure style="display:flex;align-items:center;justify-content:center;padding:12px;">
		<a href="${escapedUrl}" target="_blank" rel="noopener">${escapeHtml(file.filename)}</a>
	</figure>`;
}

async function openUserDetail(userId) {
	const body = document.getElementById('userDetailBody');
	if (body) {
		body.innerHTML = '<p>Loading user...</p>';
	}
	toggleUserDrawer(true);
	try {
		const [details, history] = await Promise.all([
			apiRequest(`/user/details?id=${userId}`),
			apiRequest(`/user/history?id=${userId}`)
		]);
		state.selectedUser = details.user || null;
		state.userLoans = details.current_loans || [];
		state.userHistory = history || { events: [] };
		state.userResetPreview = null;
		state.userDeleteConfirm = false;
		renderUserDetail();
	} catch (err) {
		handleError(err);
		toggleUserDrawer(false);
	}
}

function renderUserDetail() {
	const body = document.getElementById('userDetailBody');
	if (!body) return;
	const user = state.selectedUser;
	if (!user) {
		body.innerHTML = '<p>Select a user to see details.</p>';
		return;
	}
	const canResetPassword = isLibrarian();
	const resetPreview = state.userResetPreview;
	const showPreview =
		canResetPassword &&
		resetPreview &&
		Number(resetPreview.userId) === Number(user.id) &&
		resetPreview.password;
	const role = user.type === 'librarian' ? 'Librarian' : 'Reader';
	const loans = state.userLoans || [];
	const history = state.userHistory?.events || [];
	const loansMarkup = loans.length
		? `<ul class="loan-list">${loans.map((loan) => renderLoanRow(loan, user)).join('')}</ul>`
		: '<p class="muted">No active checkouts.</p>';
	const historyMarkup = history.length
		? `<ul class="timeline">${history.map((event) => renderUserHistoryEvent(event)).join('')}</ul>`
		: '<p class="muted">No checkout history recorded.</p>';
	const viewerId = Number(state.sessionUser?.id) || 0;
	const targetUserId = Number(user.id) || 0;
	const isSelf = viewerId > 0 && viewerId === targetUserId;
	const actionButtons = [];
	if (canResetPassword) {
		actionButtons.push(`
			<button type="button" class="ghost" data-user-action="reset-password" data-user-id="${targetUserId || ''}">
				Reset password
			</button>
		`);
	}
	if (canResetPassword && !isSelf) {
		actionButtons.push(`
			<button type="button" class="danger" data-user-action="prompt-delete-user" data-user-id="${targetUserId || ''}">
				Delete user
			</button>
		`);
	}
	const managementControls = actionButtons.length ? `<div class="detail-actions">${actionButtons.join('')}</div>` : '';
	const deleteWarning = state.userDeleteConfirm && canResetPassword && !isSelf
		? `<div class="detail-alert detail-alert--danger">
			<p>Deleting this user removes their account but keeps their checkout history. This cannot be undone.</p>
			<div class="detail-actions">
				<button type="button" class="danger" data-user-action="confirm-delete-user" data-user-id="${targetUserId || ''}">Delete permanently</button>
				<button type="button" class="ghost" data-user-action="cancel-delete-user">Cancel</button>
			</div>
		</div>`
		: '';
	const previewBlock = showPreview
		? `<div class="detail-alert" data-reset-preview>
			<p>Temporary password generated. Share it with ${escapeHtml(user.name || 'this user')}:</p>
			<code>${escapeHtml(resetPreview.password)}</code>
			<p class="muted">They should sign in and change it after first use.</p>
		</div>`
		: '';
	body.innerHTML = `
		<div class="detail-header">
			<h3>${escapeHtml(user.name || 'Unknown user')}</h3>
			<p>${escapeHtml(user.username || '—')} · ${role}</p>
		</div>
		<div class="meta-grid">
			<div><p>Role</p><strong>${role}</strong></div>
			<div><p>Username</p><strong>${escapeHtml(user.username || '—')}</strong></div>
			<div><p>Member since</p><strong>${user.created_datetime ? formatDate(user.created_datetime) : '—'}</strong></div>
			<div><p>Active loans</p><strong>${loans.length}</strong></div>
		</div>
		${managementControls}
		${deleteWarning}
		${previewBlock}
		<h4>Currently borrowed</h4>
		${loansMarkup}
		<h4>History</h4>
		${historyMarkup}
	`;
	const historyContainer = body.querySelector('.timeline');
	if (historyContainer && !historyContainer.dataset.historyBookBound) {
		historyContainer.addEventListener('click', (event) => {
			const trigger = event.target.closest('[data-history-book]');
			if (!trigger) {
				return;
			}
			event.preventDefault();
			const bookId = Number(trigger.dataset.historyBook);
			if (bookId > 0) {
				toggleUserDrawer(false);
				openBookDetail(bookId);
			}
		});
		historyContainer.dataset.historyBookBound = 'true';
	}
}

function renderMyHistory() {
	const container = document.getElementById('myHistoryTimeline');
	if (!container) {
		return;
	}
	if (!state.sessionUser) {
		container.innerHTML = '<p class="muted">Sign in to view your reading history.</p>';
		return;
	}
	const events = state.myHistoryEvents || [];
	if (!events.length) {
		container.innerHTML = '<p class="muted">You have not borrowed any books yet.</p>';
		return;
	}
	const items = events.map((event) => renderSelfHistoryEvent(event)).join('');
	container.innerHTML = `<ul class="timeline">${items}</ul>`;
}

function initMyHistoryPanel() {
	const container = document.getElementById('myHistoryTimeline');
	if (!container) {
		return;
	}
	container.addEventListener('click', (event) => {
		const trigger = event.target.closest('[data-history-book]');
		if (!trigger) {
			return;
		}
		event.preventDefault();
		const bookId = Number(trigger.dataset.historyBook);
		if (!bookId) {
			return;
		}
		openBookDetail(bookId);
	});
}

function renderSelfHistoryEvent(event) {
	const status = event?.status === 'returned' ? 'Returned' : 'Checked out';
	const titleContent = renderHistoryBookLink(event?.book);
	const authorLine = event?.book?.author ? `<p>Author: ${escapeHtml(event.book.author)}</p>` : '';
	const createdLine = event?.created_datetime ? escapeHtml(formatDate(event.created_datetime)) : 'Date unknown';
	const dueLine = event?.dueDate ? `<p>Due: ${escapeHtml(formatDateOnly(event.dueDate))}</p>` : '';
	const noteLine = event?.outComment ? `<p>Checkout note: ${escapeHtml(event.outComment)}</p>` : '';
	const returnLine =
		event?.status === 'returned'
			? `<p>Returned on ${escapeHtml(formatDate(event.receivedDateTime))}</p>`
			: '<p>Still checked out</p>';
	return `
		<li>
			<p><strong>${status}</strong> · ${createdLine}</p>
			<p>Book: ${titleContent}</p>
			${authorLine}
			${dueLine}
			${noteLine}
			${returnLine}
		</li>
	`;
}

function handleUserDrawerAction(trigger) {
	if (!trigger) {
		return;
	}
	const action = trigger.dataset.userAction;
	const userId = Number(trigger.dataset.userId || state.selectedUser?.id || 0);
	if (!userId) {
		return;
	}
	switch (action) {
		case 'reset-password':
			resetUserPassword(userId, trigger);
			break;
		case 'prompt-delete-user':
			state.userDeleteConfirm = true;
			renderUserDetail();
			break;
		case 'cancel-delete-user':
			state.userDeleteConfirm = false;
			renderUserDetail();
			break;
		case 'confirm-delete-user':
			deleteUserAccount(userId, trigger);
			break;
		default:
			break;
	}
}

async function resetUserPassword(userId, trigger) {
	if (!userId) {
		return;
	}
	const button = trigger instanceof HTMLElement ? trigger : null;
	if (button) {
		button.disabled = true;
	}
	try {
		const data = await apiRequest('/user/resetPassword', {
			method: 'POST',
			body: { userId }
		});
		if (data?.user) {
			state.selectedUser = data.user;
			const index = state.users.findIndex((entry) => Number(entry.id) === Number(data.user.id));
			if (index !== -1) {
				state.users[index] = data.user;
				renderUsers();
			}
		}
		if (data?.password) {
			state.userResetPreview = { userId, password: data.password };
		} else {
			state.userResetPreview = null;
		}
		renderUserDetail();
		pushToast('Password reset', 'success');
	} catch (err) {
		handleError(err);
	} finally {
		if (button) {
			button.disabled = false;
		}
	}
}

async function deleteUserAccount(userId, trigger) {
	if (!userId) {
		return;
	}
	const button = trigger instanceof HTMLElement ? trigger : null;
	if (button) {
		button.disabled = true;
	}
	try {
		await apiRequest('/user/delete', {
			method: 'POST',
			body: { userId }
		});
		state.users = state.users.filter((entry) => Number(entry.id) !== Number(userId));
		renderUsers();
		renderDashboard();
		if (state.selectedUser && Number(state.selectedUser.id) === Number(userId)) {
			state.selectedUser = null;
			state.userLoans = [];
			state.userHistory = { events: [] };
			state.userResetPreview = null;
			state.userDeleteConfirm = false;
			toggleUserDrawer(false);
		} else {
			state.userDeleteConfirm = false;
			renderUserDetail();
		}
		pushToast('User deleted', 'success');
	} catch (err) {
		handleError(err);
	} finally {
		if (button) {
			button.disabled = false;
		}
	}
}

function renderLoanRow(loan, user) {
	const checkoutLine = loan.checkedOutAt ? `Checked out ${formatDate(loan.checkedOutAt)}` : 'Checkout date unknown';
	const dueLine = loan.dueDate ? `Due ${formatDateOnly(loan.dueDate)}` : 'No due date on file';
	const authorLine = loan.author ? `<p>${escapeHtml(loan.author)}</p>` : '';
	const bookIdAttr = Number(loan.bookId) ? ` data-loan-book="${Number(loan.bookId)}"` : '';
	const borrowerName = user?.name ? escapeHtml(user.name) : '';
	const borrowerAttr = borrowerName ? ` data-loan-user="${borrowerName}"` : '';
	return `
		<li>
			<button type="button" class="loan-link"${bookIdAttr}${borrowerAttr}>
				<div class="loan-link__head">
					<div>
						<strong>${escapeHtml(loan.title || 'Untitled')}</strong>
						${authorLine}
					</div>
					<span class="loan-link__hint">Go to check in</span>
				</div>
				<p>${checkoutLine}</p>
				<p>${dueLine}</p>
				${loan.outComment ? `<p>Note: ${escapeHtml(loan.outComment)}</p>` : ''}
			</button>
		</li>
	`;
}

	function beginCheckInFromLoan(bookId) {
	toggleUserDrawer(false);
	openCheckInFlow(bookId);
}

function beginDetailAttachmentFlow() {
	if (detailUploadInFlight) {
		return;
	}
	const input = document.getElementById('detailAttachmentInput');
	if (!input) {
		return;
	}
	input.value = '';
	input.click();
}

async function handleDetailAttachmentUpload(fileList) {
	const bookId = state.selectedBook?.id;
	if (!bookId) {
		return handleError(new Error('Select a book before uploading.'));
	}
	const files = Array.from(fileList || []);
	if (!files.length) {
		return;
	}
	if (detailUploadInFlight) {
		return;
	}
	detailUploadInFlight = true;
	setDetailUploadState(true);
	try {
		const fileIds = await uploadFiles(files);
		if (!fileIds.length) {
			throw new Error('Unable to upload files');
		}
		await apiRequest('/book/addFiles', {
			method: 'POST',
			body: {
				bookId,
				file_ids: fileIds
			}
		});
		pushToast('Photo added to book', 'success');
		await openBookDetail(bookId);
	} catch (err) {
		handleError(err);
	} finally {
		detailUploadInFlight = false;
		setDetailUploadState(false);
	}
}

function setDetailUploadState(isLoading) {
	const button = document.querySelector('[data-detail-action="add-cover-photo"]');
	if (!button) {
		return;
	}
	if (!button.dataset.defaultLabel) {
		button.dataset.defaultLabel = button.textContent;
	}
	button.disabled = isLoading;
	button.textContent = isLoading ? 'Uploading...' : button.dataset.defaultLabel;
}

function renderHistoryFiles(event) {
	if (!event || !event.in_file_ids || !event.in_file_ids.length) {
		return '';
	}
	return `<div class="file-chips">${event.in_file_ids
		.map((id) => `<a href="${buildFileUrl({ id })}" target="_blank">File #${id}</a>`)
		.join('')}</div>`;
}

function renderHistoryReader(event) {
	const label = escapeHtml(event?.checkedOutBy || 'Unknown');
	const userId = Number(event?.checkedOutByUserId);
	if (Number.isFinite(userId) && userId > 0) {
		return `<a href="#" class="history-link" data-history-user="${userId}">${label}</a>`;
	}
	return label;
}

function renderHistoryBookLink(book) {
	const title = escapeHtml(book?.title || 'Untitled');
	const bookId = Number(book?.id) || 0;
	if (bookId > 0) {
		return `<a href="#" class="history-link" data-history-book="${bookId}">${title}</a>`;
	}
	return title;
}

function renderHistoryEvent(event) {
	const files = renderHistoryFiles(event);
	const reader = renderHistoryReader(event);
	return `
		<li>
			<p><strong>${event.status === 'returned' ? 'Returned' : 'Checked out'}</strong> · ${formatDate(event.created_datetime)}</p>
			<p>Reader: ${reader}</p>
			${event.outComment ? `<p>Checkout note: ${escapeHtml(event.outComment)}</p>` : ''}
			<p>Due: ${event.dueDate ? formatDateOnly(event.dueDate) : '—'}</p>
			${event.receivedDateTime ? `<p>Received by ${escapeHtml(event.receivedBy || 'Unknown')} on ${formatDate(event.receivedDateTime)}</p>` : ''}
			${event.inComment ? `<p>Return note: ${escapeHtml(event.inComment)}</p>` : ''}
			${files}
		</li>
	`;
}

function renderUserHistoryEvent(event) {
	const files = renderHistoryFiles(event);
	const bookLink = renderHistoryBookLink(event.book);
	const authorLine = event.book && event.book.author ? `<p>Author: ${escapeHtml(event.book.author)}</p>` : '';
	const statusLabel = event.status === 'returned' ? 'Returned' : 'Checked out';
	const returnLine = event.receivedDateTime
		? `<p>Returned by ${escapeHtml(event.receivedBy || 'Unknown')} on ${formatDate(event.receivedDateTime)}</p>`
		: '<p>Not returned yet.</p>';
	return `
		<li>
			<p><strong>${statusLabel}</strong> · ${formatDate(event.created_datetime)}</p>
			<p>Book: ${bookLink}</p>
			${authorLine}
			${event.outComment ? `<p>Checkout note: ${escapeHtml(event.outComment)}</p>` : ''}
			<p>Due: ${event.dueDate ? formatDateOnly(event.dueDate) : '—'}</p>
			${returnLine}
			${event.inComment ? `<p>Return note: ${escapeHtml(event.inComment)}</p>` : ''}
			${files}
		</li>
	`;
}

function renderDashboard() {
	const totalBooks = state.books.length;
	const available = state.books.filter((b) => Number(b.inLibrary) === 1).length;
	const checkedOut = state.books.filter((b) => Number(b.inLibrary) === 0).length;
	const dueSoon = [...state.checkedOut]
		.filter((book) => book.returnBy)
		.sort((a, b) => new Date(a.returnBy) - new Date(b.returnBy))
		.slice(0, 4);

	setText('metricBooks', totalBooks);
	setText('metricAvailable', available);
	setText('metricCheckedOut', checkedOut);
	setText('metricUsers', state.users.length);

	const dueList = document.getElementById('dueSoonList');
	if (dueList) {
		if (!isLibrarian()) {
			dueList.innerHTML = '<li>Due reminders are limited to librarians.</li>';
		} else {
			dueList.innerHTML = dueSoon.length
				? dueSoon.map((book) => renderDueSoonItem(book)).join('')
				: '<li>No upcoming returns.</li>';
		}
	}

	const recent = document.getElementById('recentActivity');
	if (recent) {
		if (!isLibrarian()) {
			recent.innerHTML = '<li>Checkout activity is limited to librarians.</li>';
		} else {
			recent.innerHTML = state.checkedOut.length
				? state.checkedOut
						.slice(0, 4)
						.map((book) => {
							const borrower = escapeHtml(book.borrowedBy || 'Unknown');
							return `<li>${borrower} · ${escapeHtml(book.title)}</li>`;
						})
						.join('')
				: '<li>No active checkouts.</li>';
		}
	}
}

function renderDueSoonItem(book) {
	const title = escapeHtml(book.title || 'Untitled');
	const dueLabel = escapeHtml(book.returnBy ? formatDateOnly(book.returnBy) : 'No due date');
	const borrower = isLibrarian() ? escapeHtml(book.borrowedBy || 'Unknown') : 'Checked out';
	const bookId = Number(book.id) || '';
	return `
		<li class="due-card" data-due-book="${bookId}" tabindex="0">
			<div class="due-card__title">${title} · due ${dueLabel}</div>
			<div class="due-card__meta">${borrower}</div>
		</li>
	`;
}

function countActiveLoansForUser(user) {
	if (!user) {
		return 0;
	}
	const matches = Array.from(
		new Set(
			[user.name, user.username]
				.map((value) => (value || '').trim().toLowerCase())
				.filter((value) => value)
		)
	);
	const userId = Number(user.id) || null;
	return state.checkedOut.filter((book) => {
		if (userId && Number(book.borrowedByUserId) === userId) {
			return true;
		}
		const borrower = (book.borrowedBy || '').trim().toLowerCase();
		return borrower && matches.includes(borrower);
	}).length;
}

function renderUsers() {
	const list = document.getElementById('userList');
	if (!list) return;
	if (!state.users.length) {
		list.innerHTML = '<li>No users yet.</li>';
		return;
	}
	list.innerHTML = state.users
		.map((user) => {
			const typeLabel = user.type === 'librarian' ? 'Librarian' : 'Reader';
			const activeLoans = countActiveLoansForUser(user);
			const countLabel = `${activeLoans} active ${activeLoans === 1 ? 'loan' : 'loans'}`;
			return `<li>
				<button type="button" class="user-card" data-user-id="${user.id}">
					<div class="user-card__row">
						<strong>${escapeHtml(user.name)}</strong>
						<span class="user-card__stat">${countLabel}</span>
					</div>
					<p>${escapeHtml(user.name || '—')} · ${typeLabel}</p>
				</button>
			</li>`;
		})
		.join('');

	const checkOutUserSelect = document.getElementById('checkOutUserSelect');
	const checkInUserSelect = document.getElementById('checkInUserSelect');
	fillUserSelect(checkOutUserSelect, (user) => user.type === 'user' || user.type === 'librarian');
	fillUserSelect(checkInUserSelect, (user) => user.type === 'librarian');
}

function fillUserSelect(select, filterFn = null) {
	if (!select) return;
	const preserve = select.value;
	select.innerHTML = '<option value="">Select saved user</option>';
	state.users.forEach((user) => {
		if (filterFn && !filterFn(user)) {
			return;
		}
		const option = document.createElement('option');
		option.value = user.id;
		option.dataset.name = user.name;
		option.dataset.username = user.username;
		const typeLabel = user.type === 'librarian' ? 'Librarian' : 'Reader';
		option.textContent = `${user.name} (${typeLabel})`;
		select.appendChild(option);
	});
	if (preserve) {
		select.value = preserve;
	}
	if (!select.value && select.id === 'checkInUserSelect') {
		const defaultReceiver = state.users.find((user) => Number(user.id) === 1 && user.type === 'librarian');
		if (defaultReceiver) {
			select.value = String(defaultReceiver.id);
		}
	}
}

function initSettingsPanel() {
	const form = document.getElementById('settingsForm');
	if (form) {
		form.addEventListener('submit', handleSettingsSave);
		form.addEventListener('input', () => updateSettingsPreviewFromForm(form));
	}
	renderSettingsPanel();
}

function renderSettingsPanel() {
	const form = document.getElementById('settingsForm');
	const titleValue = (state.settings?.siteTitle || '').trim() || DEFAULT_SITE_TITLE;
	const subtitleValue = normalizeSubtitleValue(state.settings?.siteSubtitle);
	if (form) {
		const titleInput = form.querySelector('input[name="siteTitle"]');
		const subtitleInput = form.querySelector('input[name="siteSubtitle"]');
		if (titleInput && document.activeElement !== titleInput) {
			titleInput.value = titleValue;
		}
		if (subtitleInput && document.activeElement !== subtitleInput) {
			subtitleInput.value = subtitleValue;
		}
	}
	setSettingsPreview(titleValue, subtitleValue);
}

async function handleSettingsSave(event) {
	event.preventDefault();
	if (!isLibrarian()) {
		return handleError(new Error('Only librarians can update settings.'));
	}
	const form = event.currentTarget;
	const formData = new FormData(form);
	const siteTitle = (formData.get('siteTitle') || '').toString().trim();
	const siteSubtitle = (formData.get('siteSubtitle') || '').toString();
	if (!siteTitle) {
		return handleError(new Error('Site title is required.'));
	}
	const payload = {
		settings: {
			siteTitle,
			siteSubtitle
		}
	};
	try {
		setFormLoading(form, true);
		const response = await apiRequest('/settings/save', { method: 'POST', body: payload });
		const normalized = normalizeSettingsPayload(response?.settings || payload.settings);
		state.settings = normalized;
		applySettingsBranding();
		renderSettingsPanel();
		pushToast('Settings updated', 'success');
	} catch (err) {
		handleError(err);
	} finally {
		setFormLoading(form, false);
	}
}

function updateSettingsPreviewFromForm(form) {
	if (!form) {
		return;
	}
	const titleInput = form.querySelector('input[name="siteTitle"]');
	const subtitleInput = form.querySelector('input[name="siteSubtitle"]');
	const draftTitle = titleInput ? titleInput.value.trim() : undefined;
	const draftSubtitle = subtitleInput ? subtitleInput.value : undefined;
	setSettingsPreview(draftTitle === undefined ? undefined : draftTitle || DEFAULT_SITE_TITLE, draftSubtitle);
}

function setSettingsPreview(titleValue, subtitleValue) {
	const previewTitle = document.getElementById('settingsPreviewTitle');
	const previewSubtitle = document.getElementById('settingsPreviewSubtitle');
	const resolvedTitle = titleValue !== undefined ? titleValue : (state.settings?.siteTitle || '').trim() || DEFAULT_SITE_TITLE;
	const resolvedSubtitle =
		subtitleValue !== undefined
			? subtitleValue
			: normalizeSubtitleValue(state.settings?.siteSubtitle);
	if (previewTitle) {
		previewTitle.textContent = resolvedTitle;
	}
	if (previewSubtitle) {
		const subtitleText = resolvedSubtitle === undefined ? DEFAULT_SITE_SUBTITLE : resolvedSubtitle;
		previewSubtitle.textContent = subtitleText;
	}
}

function applySettingsBranding() {
	const title = (state.settings?.siteTitle || '').trim() || DEFAULT_SITE_TITLE;
	const subtitle = normalizeSubtitleValue(state.settings?.siteSubtitle);
	const brandTitle = document.querySelector('[data-brand-title]');
	const brandSubtitle = document.querySelector('[data-brand-subtitle]');
	if (brandTitle) {
		brandTitle.textContent = title;
	}
	if (brandSubtitle) {
		const subtitleText = subtitle === undefined ? DEFAULT_SITE_SUBTITLE : subtitle;
		brandSubtitle.textContent = subtitleText;
	}
	document.title = `${title} Console`;
	renderPoweredBy();
	setSettingsPreview(title, subtitle);
}

function normalizeSettingsPayload(raw = {}) {
	const hasTitle = Object.prototype.hasOwnProperty.call(raw, 'siteTitle');
	const hasSubtitle = Object.prototype.hasOwnProperty.call(raw, 'siteSubtitle');
	const hasSwName = Object.prototype.hasOwnProperty.call(raw, 'swName');
	const hasSwVersion = Object.prototype.hasOwnProperty.call(raw, 'swVersion');
	const hasSwUrl = Object.prototype.hasOwnProperty.call(raw, 'swURL');
	const siteTitleSource = hasTitle ? raw.siteTitle : state.settings?.siteTitle;
	const subtitleSource = hasSubtitle ? raw.siteSubtitle : state.settings?.siteSubtitle;
	const swNameSource = hasSwName ? raw.swName : state.settings?.swName;
	const swVersionSource = hasSwVersion ? raw.swVersion : state.settings?.swVersion;
	const swUrlSource = hasSwUrl ? raw.swURL : state.settings?.swURL;
	const siteTitle = (siteTitleSource || '').toString().trim() || DEFAULT_SITE_TITLE;
	const siteSubtitle = normalizeSubtitleValue(
		subtitleSource,
		DEFAULT_SITE_SUBTITLE
	);
	const swName = normalizeSoftwareName(swNameSource);
	const swVersion = normalizeSoftwareVersion(swVersionSource);
	const swURL = normalizeSoftwareUrl(swUrlSource);
	return { siteTitle, siteSubtitle, swName, swVersion, swURL };
}

function normalizeSubtitleValue(value, fallback = DEFAULT_SITE_SUBTITLE) {
	if (value === undefined) {
		return fallback;
	}
	if (value === null) {
		return '';
	}
	return value.toString().trim();
}

function normalizeSoftwareName(value, fallback = DEFAULT_SW_NAME) {
	if (value === undefined) {
		return fallback;
	}
	const normalized = (value ?? '').toString().trim();
	return normalized || fallback;
}

function normalizeSoftwareVersion(value, fallback = DEFAULT_SW_VERSION) {
	if (value === undefined) {
		return fallback;
	}
	if (value === null) {
		return '';
	}
	const normalized = value.toString().trim();
	return normalized || fallback;
}

function normalizeSoftwareUrl(value, fallback = DEFAULT_SW_URL) {
	if (value === undefined) {
		return fallback;
	}
	const normalized = (value ?? '').toString().trim();
	if (!normalized) {
		return fallback;
	}
	return /^https?:\/\//i.test(normalized) ? normalized : fallback;
}

function renderPoweredBy() {
	const target = document.getElementById('navPoweredBy');
	if (!target) {
		return;
	}
	const swName = normalizeSoftwareName(state.settings?.swName);
	const swVersion = normalizeSoftwareVersion(state.settings?.swVersion);
	const swUrl = normalizeSoftwareUrl(state.settings?.swURL);
	target.textContent = 'Powered by ';
	if (swUrl) {
		const link = document.createElement('a');
		link.href = swUrl;
		link.target = '_blank';
		link.rel = 'noopener noreferrer';
		link.textContent = swName;
		target.appendChild(link);
	} else {
		target.appendChild(document.createTextNode(swName));
	}
	if (swVersion) {
		target.appendChild(document.createTextNode(` v${swVersion}`));
	}
}

function resetSettingsToDefaults() {
	state.settings = {
		siteTitle: DEFAULT_SITE_TITLE,
		siteSubtitle: DEFAULT_SITE_SUBTITLE,
		swName: DEFAULT_SW_NAME,
		swVersion: DEFAULT_SW_VERSION,
		swURL: DEFAULT_SW_URL
	};
	applySettingsBranding();
	renderSettingsPanel();
}

async function handleNewBook(event) {
	event.preventDefault();
	const form = event.currentTarget;
	const formData = new FormData(form);
	const payload = {
		title: formData.get('title').trim(),
		author: formData.get('author').trim() || null,
		isbn: formData.get('isbn').trim() || null
	};
	if (!payload.title) {
		return handleError(new Error('Please provide a title.'));
	}
	try {
		setFormLoading(form, true);
		const coverInput = form.querySelector('input[name="coverFiles"]');
		const files = getBucketFiles('cover', coverInput);
		const fileIds = await uploadFiles(files);
		if (fileIds.length) {
			payload.file_ids = fileIds;
		}
		await apiRequest('/book/new', { method: 'POST', body: payload });
		pushToast('Book added', 'success');
		form.reset();
		clearFileBucket('cover');
		await Promise.all([loadBooks(), loadCheckedOut()]);
		showPanel('books');
	} catch (err) {
		handleError(err);
	} finally {
		setFormLoading(form, false);
	}
}

async function handleCheckOut(event) {
	event.preventDefault();
	const form = event.currentTarget;
	const formData = new FormData(form);
	const payload = {
		bookId: Number(formData.get('bookId')),
		borrowedByUserId: 0,
		returnBy: null,
		outComment: formData.get('outComment')?.trim() || null
	};
	const borrowerId = Number(form.querySelector('#checkOutUserSelect')?.value || 0);
	if (borrowerId > 0) {
		payload.borrowedByUserId = borrowerId;
	}
	const returnWindow = formData.get('returnWindow');
	const computedReturn = computeReturnDate(returnWindow);
	if (computedReturn) {
		payload.returnBy = computedReturn;
	}
	if (!payload.bookId) {
		return handleError(new Error('Please choose a book.'));
	}
	if (!payload.borrowedByUserId) {
		return handleError(new Error('Select a reader to complete the checkout.'));
	}
	try {
		setFormLoading(form, true);
		await apiRequest('/book/checkOut', { method: 'POST', body: payload });
		pushToast('Checkout recorded', 'success');
		form.reset();
		setBookSearchSelection('checkout', null);
		await Promise.all([loadBooks(), loadCheckedOut()]);
		showPanel('checkedOut');
	} catch (err) {
		handleError(err);
	} finally {
		setFormLoading(form, false);
	}
}

async function handleCheckIn(event) {
	event.preventDefault();
	const form = event.currentTarget;
	const formData = new FormData(form);
	const payload = {
		bookId: Number(formData.get('bookId')),
		receivedByUserId: 0,
		inComment: formData.get('inComment')?.trim() || null,
		condition: normalizeConditionLabel(formData.get('condition'))
	};
	const receiverId = Number(form.querySelector('#checkInUserSelect')?.value || 0);
	if (receiverId > 0) {
		payload.receivedByUserId = receiverId;
	}
	if (!payload.bookId) {
		return handleError(new Error('Please choose a book to check in.'));
	}
	if (!payload.receivedByUserId) {
		return handleError(new Error('Select who received the book.'));
	}
	try {
		setFormLoading(form, true);
		const returnInput = form.querySelector('input[name="returnFiles"]');
		const files = getBucketFiles('return', returnInput);
		const fileIds = await uploadFiles(files);
		if (fileIds.length) {
			payload.file_ids = fileIds;
		}
		await apiRequest('/book/checkIn', { method: 'POST', body: payload });
		pushToast('Book checked in', 'success');
		form.reset();
		setBookSearchSelection('checkin', null);
		clearFileBucket('return');
		await Promise.all([loadBooks(), loadCheckedOut()]);
		showPanel('books');
	} catch (err) {
		handleError(err);
	} finally {
		setFormLoading(form, false);
	}
}

async function handleNewUser(event) {
	event.preventDefault();
	const form = event.currentTarget;
	const formData = new FormData(form);
	const payload = {
		name: formData.get('name').trim(),
		username: formData.get('username').trim(),
		type: formData.get('type') || 'user',
		password: (formData.get('password') || '').toString().trim()
	};
	if (!payload.password || payload.password.length < 6) {
		return handleError(new Error('Password must be at least 6 characters.'));
	}
	try {
		setFormLoading(form, true);
		await apiRequest('/user/new', { method: 'POST', body: payload });
		pushToast('User added', 'success');
		form.reset();
		await loadUsers();
	} catch (err) {
		handleError(err);
	} finally {
		setFormLoading(form, false);
	}
}

async function handleProfileUpdate(event) {
	event.preventDefault();
	const form = event.currentTarget;
	const formData = new FormData(form);
	const name = (formData.get('name') || '').toString().trim();
	if (!name) {
		return handleError(new Error('Please enter your name.'));
	}
	try {
		setFormLoading(form, true);
		const data = await apiRequest('/user/profile', {
			method: 'POST',
			body: { name }
		});
		if (data?.user) {
			setSessionUser(data.user);
		} else {
			await refreshSession({ silent: true });
		}
		const input = form.querySelector('input[name="name"]');
		if (input) {
			delete input.dataset.dirty;
		}
		syncAccountForms({ force: true });
		pushToast('Profile updated', 'success');
	} catch (err) {
		handleError(err);
	} finally {
		setFormLoading(form, false);
	}
}

async function handlePasswordChange(event) {
	event.preventDefault();
	const form = event.currentTarget;
	const formData = new FormData(form);
	const currentPassword = (formData.get('currentPassword') || '').toString().trim();
	const newPassword = (formData.get('newPassword') || '').toString().trim();
	const confirmPassword = (formData.get('confirmPassword') || '').toString().trim();
	if (!currentPassword || !newPassword) {
		return handleError(new Error('Enter both your current and new password.'));
	}
	if (newPassword.length < 6) {
		return handleError(new Error('New password must be at least 6 characters.'));
	}
	if (newPassword !== confirmPassword) {
		return handleError(new Error('New passwords do not match.'));
	}
	try {
		setFormLoading(form, true);
		const data = await apiRequest('/user/changePassword', {
			method: 'POST',
			body: {
				currentPassword,
				newPassword
			}
		});
		if (data?.user) {
			setSessionUser(data.user);
		} else {
			await refreshSession({ silent: true });
		}
		pushToast('Password updated', 'success');
		form.reset();
	} catch (err) {
		handleError(err);
	} finally {
		setFormLoading(form, false);
	}
}

function initAuth() {
	updateSessionUi();
	const form = document.getElementById('loginForm');
	form?.addEventListener('submit', handleLoginSubmit);
	const logoutBtn = document.getElementById('logoutButton');
	logoutBtn?.addEventListener('click', handleLogout);
	showAuthOverlay('Sign in to continue.');
	refreshSession({ silent: true });
}

async function refreshSession(options = {}) {
	const { silent = false } = options;
	try {
		const data = await apiRequest('/auth/me');
		setSessionUser(data.user || null);
		hideAuthOverlay();
	} catch (err) {
		if (err.status === 401) {
			if (!silent) {
				setAuthError('Please sign in to continue.');
			}
			return;
		}
		if (!silent) {
			handleError(err);
		}
	}
}

async function handleLoginSubmit(event) {
	event.preventDefault();
	const form = event.currentTarget;
	const formData = new FormData(form);
	const payload = {
		username: formData.get('username').trim(),
		password: formData.get('password')
	};
	if (!payload.username || !payload.password) {
		setAuthError('Enter your username and password.');
		return;
	}
	setAuthError('');
	try {
		setFormLoading(form, true);
		const data = await apiRequest('/auth/login', { method: 'POST', body: payload });
		form.reset();
		setSessionUser(data.user || null);
		hideAuthOverlay();
		pushToast('Signed in', 'success');
	} catch (err) {
		setAuthError(err.message || 'Unable to sign in.');
	} finally {
		setFormLoading(form, false);
	}
}

async function handleLogout(event) {
	if (event) {
		event.preventDefault();
	}
	try {
		await apiRequest('/auth/logout', { method: 'POST' });
	} catch (err) {
		if (err.status && err.status !== 401) {
			handleError(err);
		}
	} finally {
		setSessionUser(null);
		showAuthOverlay('Signed out. Please sign in again.');
	}
}

function setSessionUser(user) {
	state.sessionUser = user;
	const loadKey = user ? `${user.type}:${user.id}` : null;
	document.body.classList.toggle('is-authenticated', !!user);
	document.body.dataset.role = getCurrentRole();
	updateSessionUi();
	if (!user) {
		state.dataLoadedRole = null;
		clearAppState();
		applyRoleVisibility();
		return;
	}
	applyRoleVisibility();
	restorePendingPanel();
	if (state.dataLoadedRole !== loadKey) {
		state.dataLoadedRole = loadKey;
		bootstrap();
	}
}

function updateSessionUi() {
	const nameEl = document.getElementById('sessionName');
	const roleEl = document.getElementById('sessionRole');
	const logoutBtn = document.getElementById('logoutButton');
	if (nameEl) {
		nameEl.textContent = state.sessionUser?.name || 'Not signed in';
	}
	if (roleEl) {
		const roleLabel = state.sessionUser ? (state.sessionUser.type === 'librarian' ? 'Librarian' : 'Reader') : 'Guest';
		roleEl.textContent = roleLabel;
	}
	if (logoutBtn) {
		const loggedIn = !!state.sessionUser;
		logoutBtn.disabled = !loggedIn;
		logoutBtn.classList.toggle('is-hidden', !loggedIn);
	}
	syncAccountForms();
}

function syncAccountForms(options = {}) {
	const { force = false } = options;
	const profileForm = document.getElementById('profileForm');
	const nameInput = profileForm?.querySelector('input[name="name"]');
	if (!nameInput) {
		return;
	}
	const loggedIn = !!state.sessionUser;
	nameInput.disabled = !loggedIn;
	const desiredName = state.sessionUser?.name || '';
	if (!loggedIn) {
		if (force || !nameInput.dataset.dirty) {
			nameInput.value = '';
			delete nameInput.dataset.dirty;
		}
		return;
	}
	if (force || !nameInput.dataset.dirty) {
		nameInput.value = desiredName;
		if (force) {
			delete nameInput.dataset.dirty;
		}
	}
}

function showAuthOverlay(message = 'Sign in to continue.') {
	const overlay = document.getElementById('authOverlay');
	if (!overlay) {
		return;
	}
	setAuthMessage(message);
	setAuthError('');
	overlay.classList.add('is-visible');
	overlay.setAttribute('aria-hidden', 'false');
	const usernameInput = overlay.querySelector('input[name="username"]');
	if (usernameInput) {
		setTimeout(() => usernameInput.focus(), 120);
	}
}

function hideAuthOverlay() {
	const overlay = document.getElementById('authOverlay');
	if (!overlay) {
		return;
	}
	overlay.classList.remove('is-visible');
	overlay.setAttribute('aria-hidden', 'true');
}

function setAuthMessage(message) {
	const messageEl = document.getElementById('authMessage');
	if (messageEl) {
		messageEl.textContent = message || 'Sign in to continue.';
	}
}

function setAuthError(message) {
	const errorEl = document.getElementById('authError');
	if (errorEl) {
		errorEl.textContent = message || '';
		errorEl.classList.toggle('is-hidden', !message);
	}
}

function clearAppState() {
	state.books = [];
	state.filteredBooks = [];
	state.checkedOut = [];
	state.users = [];
	state.selectedBook = null;
	state.bookFiles = { files: [] };
	state.bookHistory = { events: [] };
	state.selectedUser = null;
	state.userLoans = [];
	state.userHistory = { events: [] };
	state.myHistoryEvents = [];
	state.bookDeleteConfirm = false;
	state.userDeleteConfirm = false;
	state.bookFilterText = '';
	state.bookFilterStatus = 'all';
	Object.keys(state.pendingFiles).forEach((bucket) => {
		clearFileBucket(bucket);
	});
	resetSettingsToDefaults();
	setCheckInConditionFromBook(null);
	applyBookFilters();
	renderCheckedOut();
	renderUsers();
	renderMyHistory();
	renderDashboard();
	toggleDrawer(false);
	toggleUserDrawer(false);
}

function handleUnauthorized() {
	const hadSession = !!state.sessionUser;
	setSessionUser(null);
	const message = hadSession ? 'Your session expired. Please sign in again.' : 'Please sign in to continue.';
	showAuthOverlay(message);
	if (hadSession) {
		pushToast('Session expired', 'error');
	}
}

function getCurrentRole() {
	return state.sessionUser?.type ? state.sessionUser.type.toLowerCase() : 'guest';
}

function isLibrarian() {
	return getCurrentRole() === 'librarian';
}

function canAccessElement(el) {
	if (!el) {
		return true;
	}
	const required = (el.dataset.roleRequired || '')
		.split(',')
		.map((entry) => entry.trim().toLowerCase())
		.filter((entry) => entry);
	if (!required.length) {
		return true;
	}
	const role = getCurrentRole();
	const isLoggedIn = !!state.sessionUser;
	return required.some((token) => {
		if (token === 'authenticated') {
			return isLoggedIn;
		}
		return role === token;
	});
}

function applyRoleVisibility() {
	const gated = document.querySelectorAll('[data-role-required]');
	gated.forEach((el) => {
		const allowed = canAccessElement(el);
		el.classList.toggle('is-hidden', !allowed);
		if (el.classList.contains('nav-link') && !allowed) {
			el.classList.remove('is-active');
		}
	});
	const activePanel = document.querySelector('.panel.is-active');
	const desiredName = activePanel ? activePanel.dataset.panel : null;
	const canStay = activePanel ? canAccessElement(activePanel) : false;
	if (desiredName && canStay) {
		showPanel(desiredName);
		return;
	}
	if (desiredName && !canStay) {
		state.pendingPanel = desiredName;
	}
	const fallback = findDefaultPanel() || 'books';
	showPanel(fallback);
}

function restorePendingPanel() {
	const pending = state.pendingPanel;
	if (!pending) {
		return;
	}
	const panel = document.querySelector(`.panel[data-panel="${pending}"]`);
	if (panel && canAccessElement(panel)) {
		showPanel(pending);
	}
}

async function uploadFiles(fileList) {
	if (!fileList || !fileList.length) {
		return [];
	}
	const ids = [];
	for (const file of fileList) {
		const dataUrl = await fileToDataUrl(file);
		const uploaded = await apiRequest('/files/new', {
			method: 'POST',
			body: {
				content_base64: dataUrl,
				filename: file.name
			}
		});
		ids.push(uploaded.id);
	}
	return ids;
}

function fileToDataUrl(file) {
	return new Promise((resolve, reject) => {
		const reader = new FileReader();
		reader.onload = () => resolve(reader.result);
		reader.onerror = reject;
		reader.readAsDataURL(file);
	});
}

async function apiRequest(path, { method = 'GET', body } = {}) {
	const options = {
		method,
		headers: {
			Accept: 'application/json'
		},
		credentials: 'include'
	};
	if (body && method !== 'GET') {
		options.headers['Content-Type'] = 'application/json';
		options.body = JSON.stringify(body);
	}
	const targetUrl = buildApiUrl(path);
	let response;
	try {
		response = await fetch(targetUrl, options);
	} catch (networkError) {
		const error = new Error('Network error. Please try again.');
		error.status = 0;
		throw error;
	}
	let payload = null;
	try {
		payload = await response.json();
	} catch (parseError) {
		payload = { success: response.ok, data: null };
	}
	if (!response.ok || (payload && payload.success === false)) {
		const message = payload?.error || `Request failed (${response.status})`;
		const error = new Error(message);
		error.status = response.status;
		error.payload = payload;
		if (response.status === 401) {
			handleUnauthorized();
		}
		throw error;
	}
	return payload?.data ?? null;
}

function handleError(error) {
	if (error?.status === 401) {
		return;
	}
	console.error(error);
	pushToast(error?.message || 'Something went wrong', 'error');
}

function pushToast(message, type = 'info') {
	const host = document.getElementById('toastHost');
	if (!host) return;
	const toast = document.createElement('div');
	toast.className = `toast ${type === 'error' ? 'error' : type === 'success' ? 'success' : ''}`;
	toast.textContent = message;
	host.appendChild(toast);
	setTimeout(() => toast.remove(), 4500);
}

function setFormLoading(form, isLoading) {
	const button = form?.querySelector('button[type="submit"]');
	if (!button) return;
	if (!button.dataset.defaultLabel) {
		button.dataset.defaultLabel = button.textContent;
	}
	button.disabled = isLoading;
	button.textContent = isLoading ? 'Working...' : button.dataset.defaultLabel;
}

function formatDate(value) {
	if (!value) return '—';
	const date = new Date(value.replace(' ', 'T'));
	return isNaN(date) ? value : date.toLocaleString();
}

function formatDateOnly(value) {
	if (!value) return '—';
	const normalized = String(value).trim();
	if (normalized === '') {
		return '—';
	}
	const dateOnlyMatch = normalized.match(/^(\d{4})-(\d{2})-(\d{2})$/);
	if (dateOnlyMatch) {
		const [, year, month, day] = dateOnlyMatch;
		const localDate = new Date(Number(year), Number(month) - 1, Number(day));
		return isNaN(localDate) ? normalized : localDate.toLocaleDateString();
	}
	const date = new Date(normalized.replace(' ', 'T'));
	return isNaN(date) ? normalized : date.toLocaleDateString();
}

function computeReturnDate(windowValue) {
	const days = Number(windowValue);
	if (!days || Number.isNaN(days)) {
		return null;
	}
	const due = new Date();
	due.setHours(12, 0, 0, 0);
	due.setDate(due.getDate() + days);
	return formatDateForApi(due);
}

function formatDateForApi(date) {
	if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
		return null;
	}
	const year = date.getFullYear();
	const month = String(date.getMonth() + 1).padStart(2, '0');
	const day = String(date.getDate()).padStart(2, '0');
	return `${year}-${month}-${day}`;
}

function setText(id, value) {
	const el = document.getElementById(id);
	if (el) {
		el.textContent = value;
	}
}

function escapeHtml(value) {
	return (value || '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');
}

function slugify(value) {
	return value
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '')
		.slice(0, 32);
}

function formatBytes(bytes) {
	if (!bytes || isNaN(bytes)) {
		return '';
	}
	const units = ['B', 'KB', 'MB', 'GB'];
	let idx = 0;
	let value = bytes;
	while (value >= 1024 && idx < units.length - 1) {
		value /= 1024;
		idx += 1;
	}
	return `${value.toFixed(value >= 10 || idx === 0 ? 0 : 1)}${units[idx]}`;
}

function normalizeConditionLabel(value) {
	if (!value) {
		return DEFAULT_BOOK_CONDITION;
	}
	const lower = String(value).trim().toLowerCase();
	const match = BOOK_CONDITIONS.find((option) => option.toLowerCase() === lower);
	return match || DEFAULT_BOOK_CONDITION;
}

function buildFileUrl(file) {
	if (!file) {
		return '';
	}
	const candidate = file.public_url || file.show_url || file.file_url || (file.id ? `files/show/?id=${file.id}` : '');
	const relative = String(candidate || '').trim();
	if (!relative) {
		return '';
	}
	if (/^https?:/i.test(relative)) {
		return relative;
	}
	if (relative.startsWith('/')) {
		return `${API_ORIGIN}${relative}`;
	}
	if (relative.startsWith('api/')) {
		try {
			return new URL(relative, window.location.href).href;
		} catch (err) {
			console.warn('Unable to resolve file URL', err);
		}
	}
	return buildApiUrl(relative);
}

function buildApiUrl(path) {
	const normalized = normalizeApiPath(path);
	if (!normalized) {
		return API_BASE_PREFIX || window.location.origin;
	}
	if (API_BASE_PREFIX) {
		return `${API_BASE_PREFIX}${normalized}`;
	}
	return `/${normalized}`;
}

function normalizeApiPath(path) {
	if (!path) {
		return '';
	}
	let clean = String(path).trim();
	if (clean === '') {
		return '';
	}
	const hashIndex = clean.indexOf('#');
	if (hashIndex !== -1) {
		clean = clean.slice(0, hashIndex);
	}
	let query = '';
	const queryIndex = clean.indexOf('?');
	if (queryIndex !== -1) {
		query = clean.slice(queryIndex);
		clean = clean.slice(0, queryIndex);
	}
	clean = clean.replace(/^\/+/, '').replace(/\/+$/, '');
	if (clean === '') {
		return query.startsWith('?') ? query : `?${query}`;
	}
	const hasExtension = /\.[a-z0-9]+$/i.test(clean);
	const suffix = hasExtension ? '' : '/';
	return `${clean}${suffix}${query}`;
}
