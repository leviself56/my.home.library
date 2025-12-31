<?php
require_once(__DIR__.'/_library/core.php');
if (!homelib_config_ready()) {
	header('Location: install.php');
	exit;
}
if (!defined('HOMELIB_API_BASE')) {
	$scriptDir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
	if ($scriptDir === '.' || $scriptDir === DIRECTORY_SEPARATOR) {
		$scriptDir = '';
	}
	$relativeApi = rtrim($scriptDir, '/') . '/api';
	if ($relativeApi === '/api') {
		define('HOMELIB_API_BASE', '/api');
	} else {
		define('HOMELIB_API_BASE', $relativeApi);
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Home Library Console</title>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=Playfair+Display:ital,wght@0,600;1,500&display=swap" rel="stylesheet" />
	<link rel="stylesheet" href="assets/css/app.css" />
</head>
<body data-api-base="<?php echo htmlspecialchars(HOMELIB_API_BASE, ENT_QUOTES); ?>">
	<div class="app-shell">
		<aside class="nav-panel">
			<div class="brand">
				<div>
					<h1 id="brandTitle" data-brand-title>Home Library</h1>
					<p id="brandSubtitle" data-brand-subtitle>Carefully Curated</p>
				</div>
			</div>
			<nav class="nav-links">
				<button class="nav-link" data-panel-target="dashboard" data-role-required="librarian">Dashboard</button>
				<div class="nav-group">
					<button class="nav-link nav-link--toggle" type="button" data-nav-toggle="navBooksMenu" aria-controls="navBooksMenu" aria-expanded="false">
						<span>Books</span>
						<span class="nav-link__chevron" aria-hidden="true"></span>
					</button>
					<div class="nav-submenu" id="navBooksMenu" hidden>
						<button class="nav-link nav-link--sub" data-panel-target="books">All Books</button>
						<button class="nav-link nav-link--sub" data-panel-target="newBook" data-role-required="librarian">Add Book</button>
						<button class="nav-link nav-link--sub" data-panel-target="checkIn" data-role-required="librarian">Check In Book</button>
						<button class="nav-link nav-link--sub" data-panel-target="checkOut" data-role-required="librarian">Check Out Book</button>
						<button class="nav-link nav-link--sub" data-panel-target="checkedOut" data-role-required="librarian">Loaned Books</button>
					</div>
				</div>
				<div class="nav-group" data-role-required="authenticated">
					<button class="nav-link nav-link--toggle" type="button" data-nav-toggle="navSystemMenu" aria-controls="navSystemMenu" aria-expanded="false">
						<span>System</span>
						<span class="nav-link__chevron" aria-hidden="true"></span>
					</button>
					<div class="nav-submenu" id="navSystemMenu" hidden>
						<button class="nav-link nav-link--sub" data-panel-target="account" data-role-required="authenticated">My Account</button>
						<button class="nav-link nav-link--sub" data-panel-target="myHistory" data-role-required="authenticated">My History</button>
						<button class="nav-link nav-link--sub" data-panel-target="users" data-role-required="librarian">Readers &amp; Librarians</button>
						<button class="nav-link nav-link--sub" data-panel-target="settings" data-role-required="librarian">Settings</button>
					</div>
				</div>
			</nav>
			<div class="nav-footer">
				<p id="navPoweredBy">Powered by HomeLib Console v1.0</p>
			</div>
		</aside>
		<main class="content-panel">
			<header class="top-bar">
				<div>
					<p class="eyebrow">Console</p>
					<h2 id="panelTitle">Dashboard</h2>
				</div>
				<div class="top-actions">
					<div class="top-actions__buttons" data-role-required="librarian">
						<button type="button" class="ghost" data-panel-target="newBook">+ Quick add</button>
						<button type="button" class="ghost" data-panel-target="checkOut">Start checkout</button>
					</div>
					<div class="session-chip" id="sessionChip">
						<div>
							<p class="session-chip__role" id="sessionRole">Guest</p>
							<strong id="sessionName">Not signed in</strong>
						</div>
						<button type="button" class="ghost session-chip__login" id="loginButton">Sign in</button>
						<button type="button" class="ghost session-chip__logout is-hidden" id="logoutButton">Sign out</button>
					</div>
				</div>
			</header>

			<section class="panel is-active" data-panel="dashboard" data-role-required="librarian">
				<div class="metrics-grid" id="dashboardMetrics">
					<div class="metric-card">
						<p>Total books</p>
						<strong id="metricBooks">0</strong>
					</div>
					<div class="metric-card">
						<p>Available now</p>
						<strong id="metricAvailable">0</strong>
					</div>
					<div class="metric-card">
						<p>Checked out</p>
						<strong id="metricCheckedOut">0</strong>
					</div>
					<div class="metric-card">
						<p>Readers</p>
						<strong id="metricUsers">0</strong>
					</div>
				</div>
				<div class="dash-panels">
					<div>
						<h3>Due soon</h3>
						<ul id="dueSoonList" class="simple-list"></ul>
					</div>
					<div>
						<h3>Recent activity</h3>
						<ul id="recentActivity" class="simple-list"></ul>
					</div>
				</div>
			</section>

			<section class="panel" data-panel="books">
				<div class="section-head">
					<div>
					</div>
					<div class="section-controls">
						<input type="search" id="bookSearch" placeholder="Search title or author" />
						<select id="bookStatusFilter">
							<option value="all">All statuses</option>
							<option value="available">Available</option>
							<option value="out">Checked out</option>
						</select>
					</div>
				</div>
				<div id="booksGrid" class="cards-grid"></div>
			</section>

			<section class="panel" data-panel="checkedOut" data-role-required="librarian">
				<div class="section-head">
					<button class="ghost" data-panel-target="checkIn" data-role-required="librarian">Record a return</button>
				</div>
				<div id="checkedOutGrid" class="cards-grid"></div>
			</section>

			<section class="panel" data-panel="newBook" data-role-required="librarian">
				<form id="newBookForm" class="form-grid">
					<label>Title<input type="text" name="title" required /></label>
					<label>Author<input type="text" name="author" /></label>
					<label>ISBN<input type="text" name="isbn" /></label>
					<label class="full-row">Photos
						<input type="file" name="coverFiles" accept="image/*" capture="environment" multiple data-file-bucket="cover" />
						<div class="attachment-tray" data-file-tray="cover" aria-live="polite"></div>
					</label>
					<button type="submit">Save book</button>
				</form>
			</section>

			<section class="panel" data-panel="checkOut" data-role-required="librarian">
				<form id="checkOutForm" class="checkout-form">
					<input type="hidden" name="bookId" id="checkOutBookId" required />
					<div class="checkout-layout">
						<div class="checkout-card">
							<p class="checkout-step">Step 1</p>
							<h4>Select the book</h4>
							<div class="combo" data-book-search="checkout" data-book-filter="available" data-book-target="checkOutBookId">
								<input type="search" placeholder="Search title or author" autocomplete="off" />
								<div class="combo-results" role="listbox"></div>
							</div>
							<label class="checkout-field">Return by window
								<select name="returnWindow" id="returnWindowSelect">
									<option value="">Select a timeframe</option>
									<option value="1">1 day</option>
									<option value="2">2 days</option>
									<option value="3">3 days</option>
									<option value="7">1 week</option>
									<option value="14">2 weeks</option>
								</select>
							</label>
						</div>
						<div class="checkout-card">
							<p class="checkout-step">Step 2</p>
							<h4>Choose who is borrowing</h4>
							<label class="checkout-field">Borrower
								<select id="checkOutUserSelect" class="combo-select" required>
									<option value="">Select saved user</option>
								</select>
							</label>
							<label class="checkout-field">Checkout note
								<textarea name="outComment" rows="3" placeholder="Optional reminder about condition or plans"></textarea>
							</label>
							<p class="muted">Readers and librarians alike can take books home. Check out notes are visible to readers.</p>
						</div>
					</div>
					<div class="checkout-actions">
						<div>
							<h5>Ready to hand it off?</h5>
							<p class="muted">We’ll log the checkout so the librarians know who has the book.</p>
						</div>
						<button type="submit">Confirm checkout</button>
					</div>
				</form>
			</section>

			<section class="panel" data-panel="checkIn" data-role-required="librarian">
				<form id="checkInForm" class="form-grid">
					<label>Book
						<input type="hidden" name="bookId" id="checkInBookId" required />
						<div class="combo" data-book-search="checkin" data-book-filter="checkedOut" data-book-target="checkInBookId">
							<input type="search" placeholder="Search title or author" autocomplete="off" />
							<div class="combo-results" role="listbox"></div>
						</div>
					</label>
					<label>Received by
						<select id="checkInUserSelect" class="combo-select" required>
							<option value="">Select saved user</option>
						</select>
					</label>
					<label>Condition
						<select name="condition" id="checkInConditionSelect">
							<option value="New">New</option>
							<option value="Like New">Like New</option>
							<option value="Very Good">Very Good</option>
							<option value="Good" selected>Good</option>
							<option value="Acceptable">Acceptable</option>
							<option value="Poor">Poor</option>
						</select>
					</label>
					<label class="full-row">Return note<textarea name="inComment" rows="3"></textarea></label>
					<label class="full-row">Return photos
						<input type="file" name="returnFiles" accept="image/*" capture="environment" multiple data-file-bucket="return" />
						<div class="attachment-tray" data-file-tray="return" aria-live="polite"></div>
					</label>
					<button type="submit">Complete check-in</button>
				</form>
			</section>

			<section class="panel" data-panel="account" data-role-required="authenticated">
				<div class="account-forms">
					<div>
						<h4>Profile details</h4><br />
						<form id="profileForm" class="form-grid form-grid--narrow">
							<label>Display name
								<input type="text" name="name" autocomplete="name" required />
							</label>
							<button type="submit">Save profile</button>
						</form>
					</div>
					<div>
						<h4>Change password</h4><br />
						<form id="changePasswordForm" class="form-grid form-grid--narrow">
							<label>Current password
								<input type="password" name="currentPassword" autocomplete="current-password" required />
							</label>
							<label>New password
								<input type="password" name="newPassword" autocomplete="new-password" required minlength="6" />
							</label>
							<label>Confirm new password
								<input type="password" name="confirmPassword" autocomplete="new-password" required minlength="6" />
							</label>
							<button type="submit">Update password</button>
						</form>
					</div>
				</div>
			</section>

			<section class="panel" data-panel="myHistory" data-role-required="authenticated">
				<div id="myHistoryTimeline"></div>
			</section>

			<section class="panel" data-panel="users" data-role-required="librarian">
				<div class="two-column">
					<div>
						<h4>Add user</h4>
						<form id="newUserForm" class="form-grid">
							<label>Name<input type="text" name="name" required /></label>
							<label>Username<input type="text" name="username" required /></label>
							<label>Password<input type="password" name="password" required minlength="6" autocomplete="new-password" /></label>
							<label>Type
								<select name="type">
									<option value="user">Reader</option>
									<option value="librarian">Librarian</option>
								</select>
							</label>
							<button type="submit">Save user</button>
						</form>
					</div>
					<div>
						<h4>Directory</h4>
						<ul id="userList" class="user-list"></ul>
					</div>
				</div>
			</section>

			<section class="panel" data-panel="settings" data-role-required="librarian">
				<div class="section-head">
					<div>
					</div>
				</div>
				<form id="settingsForm" class="form-grid settings-form">
					<label class="full-row">Site title
						<input type="text" name="siteTitle" maxlength="120" required />
					</label>
					<label class="full-row">Site subtitle
						<input type="text" name="siteSubtitle" maxlength="200" />
					</label>				<label class="full-row" style="display: flex; align-items: center; gap: 8px;">
					<input type="checkbox" name="publicLibrary" id="publicLibraryCheckbox" style="width: auto; margin: 0;" />
					<span>Public Library (allow unauthenticated access to book catalog)</span>
				</label>					<div class="settings-actions full-row">
						<button type="submit">Save settings</button>
					</div>
				</form>
			</section>
		</main>
	</div>

	<div id="bookDetailDrawer" class="drawer" aria-hidden="true">
		<div class="drawer__panel">
			<button class="drawer__close" id="closeBookDetail">Close</button>
			<div id="bookDetailBody"></div>
		</div>
	</div>

	<div id="userDetailDrawer" class="drawer" aria-hidden="true">
		<div class="drawer__panel">
			<button class="drawer__close" id="closeUserDetail">Close</button>
			<div id="userDetailBody"></div>
		</div>
	</div>

	<div id="imageLightbox" class="lightbox" aria-hidden="true">
		<div class="lightbox__panel" role="dialog" aria-modal="true" aria-label="Image preview">
			<button type="button" class="lightbox__close" data-lightbox-close>Close</button>
			<figure class="lightbox__figure">
				<img id="lightboxImage" src="" alt="" />
				<figcaption id="lightboxCaption"></figcaption>
			</figure>
		</div>
	</div>

	<input type="file" id="detailAttachmentInput" accept="image/*" multiple hidden />

	<div id="toastHost" class="toast-host"></div>

	<div id="authOverlay" class="auth-overlay" aria-hidden="true">
		<div class="auth-card">
			<h2>Welcome back</h2>
			<p id="authMessage" class="auth-message">Sign in to continue.</p>
			<form id="loginForm" class="auth-form">
				<label>Username
					<input type="text" name="username" autocomplete="username" required />
				</label>
				<label>Password
					<input type="password" name="password" autocomplete="current-password" required />
				</label>
				<button type="submit">Sign in</button>
			</form>
			<p id="authError" class="auth-error is-hidden" role="alert"></p>
		</div>
	</div>

	<script type="module" src="assets/js/app.js"></script>
</body>
</html>
