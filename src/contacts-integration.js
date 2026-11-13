/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Reidel CRM — Nextcloud Contacts 7.x Integration
 *
 * Injects the CRM panel into the NC Contacts contact-detail view.
 * Verified against Nextcloud 30 + Contacts 7.3.x.
 *
 * Contact detection (priority order):
 *   1. URL change  — NC Contacts 7.x uses history.pushState on every click
 *   2. MutationObserver on the app root — catches async contact-detail render
 *   3. Title poll  — fallback for edge cases
 *
 * Contact identifier (priority order):
 *   1. Email extracted from rendered DOM  → `email` prop
 *   2. vcardUID decoded from URL segment  → `vcardUid` prop
 */

import './contacts-integration.css'
import { createApp } from 'vue'
import CrmContactPanel from './components/CrmContactPanel.vue'

// ─── State ───────────────────────────────────────────────────────────────────
let mountedApp       = null
let mountPoint       = null
let activeIdentifier = null
let isMounting       = false
let debounceTimer    = null
let lastUrl          = window.location.href
// Monotonically-increasing counter — each mount gets a unique generation so
// stale email-poll closures from previous mounts never overwrite a newer one.
let mountGeneration  = 0

// ─── vcardUID from URL ────────────────────────────────────────────────────────
// NC Contacts URL: /apps/contacts/{addressbook}/{base64EncodedUID}
// The UID is base64-encoded and may include a ~{addressbook} suffix.
function getVcardUidFromUrl() {
	const m = window.location.pathname.match(/\/apps\/contacts\/[^/]+\/([^/]+)$/)
	if (!m) return null
	const raw = decodeURIComponent(m[1])
	if (raw.length < 5) return null
	try {
		const decoded = atob(raw)
		if (decoded && /^[\x20-\x7E]+$/.test(decoded)) {
			// Strip addressbook suffix: "crm-abc123~contacts" → "crm-abc123"
			return decoded.replace(/~[^~]*$/, '').trim()
		}
	} catch (_) {}
	return raw.replace(/~[^~]*$/, '').trim()
}

// ─── Email extraction ─────────────────────────────────────────────────────────
const EMAIL_RE = /[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/

function extractEmail() {
	const scopes = ['.contact-details', '.app-content-details', '.content.app-contacts', '#content-vue']

	// 1. mailto links (view mode)
	for (const scope of scopes) {
		const el = document.querySelector(`${scope} a[href^="mailto:"]`) || document.querySelector('a[href^="mailto:"]')
		if (el) {
			const e = el.href.replace(/^mailto:/i, '').split('?')[0].trim()
			if (EMAIL_RE.test(e)) return e
		}
	}

	// 2. email inputs (edit mode)
	for (const scope of [...scopes, '']) {
		const inp = document.querySelector(`${scope} input[type="email"]`.trim())
		if (inp?.value && EMAIL_RE.test(inp.value)) return inp.value.trim()
	}

	// 3. email-class inputs
	const emailInp = document.querySelector('[class*="email"] input, [class*="property--email"] input')
	if (emailInp?.value && EMAIL_RE.test(emailInp.value)) return emailInp.value.trim()

	// 4. all inputs in detail panels
	for (const inp of document.querySelectorAll('.contact-details input, .app-content-details input')) {
		if (inp.value && EMAIL_RE.test(inp.value)) return inp.value.trim()
	}

	// 5. selected contact list item text (NC Contacts sidebar shows email)
	const selected = document.querySelector(
		'.contacts-list .list-item.active, .contacts-list li.active, [class*="contact"][class*="active"]'
	)
	if (selected) {
		const m = selected.textContent?.match(EMAIL_RE)
		if (m) return m[0].trim()
	}

	// 6. full text scan (last resort)
	const pane = document.querySelector('.contact-details, .app-content-details, .content.app-contacts')
	if (pane) {
		const m = pane.textContent?.match(EMAIL_RE)
		if (m) return m[0].trim()
	}
	return null
}

// ─── Contact name from DOM ───────────────────────────────────────────────────
function extractContactName() {
	const firstInp = document.querySelector('input[placeholder*="irst"], input[aria-label*="irst"]')
	const lastInp  = document.querySelector('input[placeholder*="ast"],  input[aria-label*="ast"]')
	const first = firstInp?.value?.trim() || ''
	const last  = lastInp?.value?.trim()  || ''
	if (first || last) return { firstName: first, lastName: last }

	const title = document.title.replace(/\s*[-|].*$/, '').trim()
	if (title && title !== 'Contacts') {
		const parts = title.split(/\s+/)
		return { firstName: parts[0] || '', lastName: parts.slice(1).join(' ') || '' }
	}
	return null
}

// ─── Injection target ─────────────────────────────────────────────────────────
// Returns the element to APPEND the panel into.
// For NC Contacts detail panels we prefer the PARENT of .contact-details so the
// panel is a sibling (not a child) of the narrow detail column, giving it the
// full width of the parent container.
function findInjectionTarget() {
	// NC Contacts 7.x primary target — inject INSIDE .contact-details so we don't
	// break the parent's row layout (which holds the right sidebar).
	const cd = document.querySelector('.contact-details')
	if (cd) return cd

	const acd = document.querySelector('.app-content-details')
	if (acd) return acd

	// Generic fallbacks
	const ac = document.querySelector('.content.app-contacts')
	if (ac) {
		const children = Array.from(ac.children)
		return children.length >= 2 ? children[children.length - 1] : ac
	}

	return (
		document.getElementById('content-vue') ||
		document.getElementById('app-content-vue') ||
		document.getElementById('app-content') ||
		document.querySelector('.app-content') ||
		document.querySelector('main')
	)
}

// ─── Container layout helpers ─────────────────────────────────────────────────
// Store original styles so we can restore them when the panel unmounts.
let _savedContainerStyles  = null
let _savedDetailsStyles    = null
let _layoutContainer       = null

/**
 * Force the injection container into column-flex layout so the CRM panel
 * stacks below the native contact fields and spans the full available width.
 *
 * Uses style.setProperty('…', '…', 'important') which takes precedence over
 * any author stylesheet — even !important rules — and is independent of the
 * container's class name, so it works across every NC Contacts version.
 */
function applyContainerLayout(container) {
	_layoutContainer = container

	// ── Save originals for clean restore ─────────────────────────────────
	const s = container.style
	_savedContainerStyles = {
		display:       s.display,
		flexDirection: s.flexDirection,
		overflowY:     s.overflowY,
		maxWidth:      s.maxWidth,
		width:         s.width,
		height:        s.height,
		boxSizing:     s.boxSizing,
	}

	// ── Apply column-flex layout to the container ─────────────────────────
	s.setProperty('display',         'flex',        'important')
	s.setProperty('flex-direction',  'column',      'important')
	s.setProperty('overflow-y',      'auto',        'important')
	s.setProperty('overflow-x',      'hidden',      'important')
	s.setProperty('max-width',       'none',        'important')
	s.setProperty('width',           '100%',        'important')
	s.setProperty('height',          '100%',        'important')
	s.setProperty('box-sizing',      'border-box',  'important')

	// ── Release .contact-details from its own scroll/height constraints ───
	// NC Contacts gives .contact-details overflow-y:auto + max-height:100%
	// so it scrolls independently.  In column-flex mode the PARENT scrolls
	// the whole page, so we need .contact-details to size to its content.
	const cd = container.querySelector(':scope > .contact-details')
	if (cd) {
		const ds = cd.style
		_savedDetailsStyles = {
			overflowY:  ds.overflowY,
			maxHeight:  ds.maxHeight,
			height:     ds.height,
			flex:       ds.flex,
			width:      ds.width,
			minWidth:   ds.minWidth,
		}
		ds.setProperty('overflow-y',  'visible',  'important')
		ds.setProperty('max-height',  'none',     'important')
		ds.setProperty('height',      'auto',     'important')
		ds.setProperty('flex',        'none',     'important')
		ds.setProperty('width',       '100%',     'important')
		ds.setProperty('min-width',   '0',        'important')
	}
}

/** Restore the container and .contact-details to their original styles. */
function restoreContainerLayout() {
	if (_layoutContainer && _savedContainerStyles) {
		const s = _layoutContainer.style
		for (const [prop, val] of Object.entries(_savedContainerStyles)) {
			// Convert camelCase key back to kebab-case for setProperty
			const kebab = prop.replace(/([A-Z])/g, '-$1').toLowerCase()
			s.setProperty(kebab, val)
		}
		// Remove the properties we set (in case the element had none before)
		if (!_savedContainerStyles.display)       s.removeProperty('display')
		if (!_savedContainerStyles.flexDirection)  s.removeProperty('flex-direction')
		if (!_savedContainerStyles.overflowY)      s.removeProperty('overflow-y')
		if (!_savedContainerStyles.overflowX)      s.removeProperty('overflow-x')
		if (!_savedContainerStyles.maxWidth)       s.removeProperty('max-width')
		if (!_savedContainerStyles.width)          s.removeProperty('width')
		if (!_savedContainerStyles.height)         s.removeProperty('height')
		if (!_savedContainerStyles.boxSizing)      s.removeProperty('box-sizing')
	}
	if (_layoutContainer && _savedDetailsStyles) {
		const cd = _layoutContainer.querySelector(':scope > .contact-details')
		if (cd) {
			const ds = cd.style
			for (const [prop, val] of Object.entries(_savedDetailsStyles)) {
				const kebab = prop.replace(/([A-Z])/g, '-$1').toLowerCase()
				ds.setProperty(kebab, val)
			}
			if (!_savedDetailsStyles.overflowY) ds.removeProperty('overflow-y')
			if (!_savedDetailsStyles.maxHeight)  ds.removeProperty('max-height')
			if (!_savedDetailsStyles.height)     ds.removeProperty('height')
			if (!_savedDetailsStyles.flex)       ds.removeProperty('flex')
			if (!_savedDetailsStyles.width)      ds.removeProperty('width')
			if (!_savedDetailsStyles.minWidth)   ds.removeProperty('min-width')
		}
	}
	_layoutContainer      = null
	_savedContainerStyles = null
	_savedDetailsStyles   = null
}

// ─── Mount / unmount ──────────────────────────────────────────────────────────
function unmountPanel() {
	mountGeneration++ // invalidates any in-flight email-poll closures
	restoreContainerLayout()
	if (mountedApp) { try { mountedApp.unmount() } catch (_) {} }
	mountedApp = null
	if (mountPoint?.parentNode) mountPoint.parentNode.removeChild(mountPoint)
	mountPoint       = null
	activeIdentifier = null
	isMounting       = false
}

function mountPanel(container, email, vcardUid) {
	if (document.getElementById('reidel-crm-panel')) return

	mountPoint = document.createElement('div')
	mountPoint.id = 'reidel-crm-panel'
	// The mount point is a plain block element — flex sizing is handled by the
	// container's column-flex layout applied by applyContainerLayout().
	// Do NOT set flex:0 0 100% here: in a column-flex context flex-basis refers
	// to the *block axis* (height), not inline width, which would cause the panel
	// to try to claim 100% of the container height instead of its natural height.
	const mp = mountPoint.style
	mp.setProperty('display',      'block',       'important')
	mp.setProperty('width',        '100%',        'important')
	mp.setProperty('max-width',    '100%',        'important')
	mp.setProperty('min-width',    '0',           'important')
	mp.setProperty('box-sizing',   'border-box',  'important')
	mp.setProperty('flex-shrink',  '0',           'important')
	mp.setProperty('align-self',   'stretch',     'important')

	container.appendChild(mountPoint)
	applyContainerLayout(container)

	const name = extractContactName()
	isMounting = true
	// Capture the generation for this mount so the email-poll below can detect
	// if a newer mount has started before the poll fires.
	const myGeneration = ++mountGeneration
	try {
		const app = createApp(CrmContactPanel, {
			email:     email    || null,
			vcardUid:  vcardUid || null,
			firstName: name?.firstName || null,
			lastName:  name?.lastName  || null,
		})
		app.mount(mountPoint)
		mountedApp = app
	} catch (err) {
		console.error('[Reidel CRM] Mount failed:', err)
		isMounting = false
		return
	}

	setTimeout(() => { isMounting = false }, 1200)

	// Poll for email after mount (async DAV load) — only when no email was
	// available at mount time.  Always guard with the generation counter so a
	// stale poll from a previous contact never replaces a newer mount.
	if (!email) {
		let attempts = 0
		const poll = setInterval(() => {
			// Another contact was selected — abandon this poll entirely.
			if (myGeneration !== mountGeneration) { clearInterval(poll); return }
			attempts++
			const found = extractEmail()
			if (found && mountPoint) {
				clearInterval(poll)
				// Still our generation: update the mounted component's email prop
				// by replacing the app in-place (same mount point).
				try { mountedApp?.unmount() } catch (_) {}
				mountedApp = null
				activeIdentifier = found
				const app2 = createApp(CrmContactPanel, {
					email:     found,
					vcardUid:  vcardUid || null,
					firstName: extractContactName()?.firstName || null,
					lastName:  extractContactName()?.lastName  || null,
				})
				app2.mount(mountPoint)
				mountedApp = app2
			}
			if (attempts >= 12) clearInterval(poll)
		}, 500)
	}
}

// ─── Core check ───────────────────────────────────────────────────────────────
function checkForContact() {
	if (isMounting) return

	const vcardUid   = getVcardUidFromUrl()
	const email      = extractEmail()
	// Prefer the vcardUid extracted from the URL — it updates immediately when
	// NC Contacts navigates to a new contact via history.pushState, whereas the
	// DOM email is extracted from rendered markup that may still reflect the
	// *previous* contact while the contact-details panel re-renders asynchronously.
	const identifier = vcardUid || email

	if (!identifier) {
		if (activeIdentifier) unmountPanel()
		return
	}

	if (identifier === activeIdentifier && document.getElementById('reidel-crm-panel')) return

	const container = findInjectionTarget()
	if (!container) {
		// Retry once when the contact-detail panel renders asynchronously
		setTimeout(checkForContact, 800)
		return
	}

	unmountPanel()
	activeIdentifier = identifier
	mountPanel(container, email, vcardUid)
}

function onDomChanged() {
	if (isMounting) return
	clearTimeout(debounceTimer)
	debounceTimer = setTimeout(checkForContact, 250)
}

// ─── URL change detection (NC Contacts uses history.pushState) ────────────────
function setupUrlWatcher() {
	const wrap = (original) => function(...args) {
		original.apply(this, args)
		if (window.location.href !== lastUrl) {
			lastUrl = window.location.href
			onDomChanged()
		}
	}
	history.pushState    = wrap(history.pushState)
	history.replaceState = wrap(history.replaceState)
	window.addEventListener('popstate', () => { lastUrl = window.location.href; onDomChanged() })
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────
function init() {
	if (!window.location.pathname.includes('/apps/contacts')) return

	setupUrlWatcher()

	// MutationObserver on app root — detects async contact-detail renders
	const root = (
		document.getElementById('content-vue') ||
		document.getElementById('app-content-vue') ||
		document.getElementById('content') ||
		document.getElementById('app-content') ||
		document.querySelector('.app-content') ||
		document.body
	)
	new MutationObserver((muts) => {
		if (isMounting) return
		const selfOnly = muts.every(m => mountPoint && (m.target === mountPoint || mountPoint.contains(m.target)))
		if (!selfOnly) onDomChanged()
	}).observe(root, { childList: true, subtree: true, attributes: false })

	// Title poll (secondary)
	let lastTitle = document.title
	setInterval(() => {
		if (document.title !== lastTitle) { lastTitle = document.title; onDomChanged() }
	}, 400)

	// Initial checks with staggered timing for slow DAV fetches
	setTimeout(checkForContact, 800)
	setTimeout(checkForContact, 2000)
	setTimeout(checkForContact, 4000)
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init)
} else {
	init()
}
