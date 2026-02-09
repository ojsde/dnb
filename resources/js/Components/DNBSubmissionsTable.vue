<template>
	<div class="dnbSubmissionsTable">
		<form ref="exportForm" id="exportXmlForm" class="pkp_form dnb_form" action="" method="post">
			<!-- Action Buttons - Before Table -->
			<div class="dnb-button-container">
				<PkpButton id="dnb_deposit" @click="handleAction('deposit')"
					:disabled="selectedSubmissions.length === 0 || isSubmitting" class="bg-default">
					<span v-if="isSubmitting" class="dnb-spinner"></span>
					{{ data.i18n.deposit }}
				</PkpButton>
				<PkpButton id="dnb_export" @click="handleAction('export')"
					:disabled="selectedSubmissions.length === 0 || isSubmitting" class="bg-default">
					<span v-if="isSubmitting" class="dnb-spinner"></span>
					{{ data.i18n.export }}
				</PkpButton>
				<PkpButton id="dnb_mark" @click="handleAction('mark')"
					:disabled="selectedSubmissions.length === 0 || isSubmitting" class="bg-default">
					<span v-if="isSubmitting" class="dnb-spinner"></span>
					{{ data.i18n.markRegistered }}
				</PkpButton>
				<PkpButton id="dnb_mark_exclude" @click="handleAction('exclude')"
					:disabled="selectedSubmissions.length === 0 || isSubmitting" class="bg-default">
					<span v-if="isSubmitting" class="dnb-spinner"></span>
					{{ data.i18n.exclude }}
				</PkpButton>
				<PkpButton @click="toggleSelectAll" :disabled="paginatedItems.length === 0 || isSubmitting"
					class="bg-default">
					<template v-if="areAllVisibleSelected && paginatedItems.length > 0">
						{{ data.i18n.selectNone }}
					</template>
					<template v-else>
						{{ data.i18n.selectAll }}
					</template>
				</PkpButton>
				<PkpButton @click="deselectAll" :disabled="selectedSubmissions.length === 0 || isSubmitting"
					class="bg-default">
					{{ data.i18n.deselectAll }}
				</PkpButton>
				<PkpButton v-if="data.canClearFailedJobs" @click="handleClearFailedJobs"
					:disabled="isSubmitting || isClearingFailedJobs" class="bg-default">
					<span v-if="isClearingFailedJobs" class="dnb-spinner"></span>
					{{ data.i18n.clearFailedJobs }}
				</PkpButton>
			</div>

			<notification v-if="actionMessage" :type="actionMessageType" style="margin-bottom: 1rem;">
				<icon v-if="actionMessageType === 'warning'" icon="exclamation-triangle" :inline="true" />
				{{ actionMessage }}
			</notification>

			<!-- Error notification -->
			<!-- Loop through error messages -->
			<notification type="warning" v-for="(error, index) in data.errors" :key="index" style="margin-bottom: 1rem;">
				<icon icon="exclamation-triangle" :inline="true" />
				{{ error }}
			</notification>

			<!-- Item Count Display -->
			<div style="margin-bottom: 0.5rem; color: #666; font-size: 0.875rem;">
				{{ itemCountText }}
			</div>

			<PkpTable>
				<template #top-controls>
					<!-- Search and Filter Controls -->
					<div class="dnb-top-controls" style="display: flex; flex-direction: column; gap: 1rem; width: 100%;">
						<!-- Search Field -->
						<div style="width: 100%; max-width: 28rem;">
							<div @keydown.enter.prevent="addSearchFilter">
								<PkpSearch :searchPhrase="searchPhrase" :searchLabel="searchLabel"
									@search-phrase-changed="setSearchPhrase" style="width:unset;" />
							</div>
							<!-- Active Search Filter Chips -->
							<div v-if="activeSearchFilters.length > 0"
								style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;" role="group"
								aria-label="Active search filters">
								<div v-for="(filter, index) in activeSearchFilters" :key="index"
									class="dnb-search-filter-chip" role="status"
									:aria-label="`Search filter: ${filter}`">
									<span class="dnb-chip-label">{{ filter }}</span>
									<button type="button" class="dnb-chip-remove" @click="removeSearchFilter(index)"
										:aria-label="`Remove search filter: ${filter}`">
										×
									</button>
								</div>
							</div>
						</div>
						<!-- Status Filter Buttons -->
						<div class="dnb-filter-buttons">
							<button type="button" @click="setStatusFilter(null)"
								:class="['dnb-filter-btn', 'dnb-filter-all', activeStatusFilter === null ? 'dnb-filter-active' : '']">
								{{ data.i18n.filterAll }}
								<span class="pkpBadge dnb-filter-badge">{{ statusCounts.all }}</span>
							</button>
							<button type="button" @click="setStatusFilter(data.constants.EXPORT_STATUS_NOT_DEPOSITED)"
								:class="['dnb-filter-btn', 'dnb-filter-not-deposited', activeStatusFilter === data.constants.EXPORT_STATUS_NOT_DEPOSITED ? 'dnb-filter-active' : '']">
								{{ data.i18n.filterNotDeposited }}
								<span class="pkpBadge dnb-filter-badge">{{ statusCounts.notDeposited }}</span>
							</button>
							<button type="button" @click="setStatusFilter(data.constants.DNB_STATUS_DEPOSITED)"
								:class="['dnb-filter-btn', 'dnb-filter-deposited', activeStatusFilter === data.constants.DNB_STATUS_DEPOSITED ? 'dnb-filter-active' : '']">
								{{ data.i18n.filterDeposited }}
								<span class="pkpBadge dnb-filter-badge">{{ statusCounts.deposited }}</span>
							</button>
							<button type="button" @click="setStatusFilter(data.constants.DNB_EXPORT_STATUS_QUEUED)"
								:class="['dnb-filter-btn', 'dnb-filter-queued', activeStatusFilter === data.constants.DNB_EXPORT_STATUS_QUEUED ? 'dnb-filter-active' : '']">
								{{ data.i18n.filterQueued }}
								<span class="pkpBadge dnb-filter-badge">{{ statusCounts.queued }}</span>
							</button>
							<button type="button" @click="setStatusFilter(data.constants.DNB_EXPORT_STATUS_FAILED)"
								:class="['dnb-filter-btn', 'dnb-filter-failed', activeStatusFilter === data.constants.DNB_EXPORT_STATUS_FAILED ? 'dnb-filter-active' : '']">
								{{ data.i18n.filterFailed }}
								<span class="pkpBadge dnb-filter-badge">{{ statusCounts.failed }}</span>
							</button>
							<button type="button"
								@click="setStatusFilter(data.constants.EXPORT_STATUS_MARKEDREGISTERED)"
								:class="['dnb-filter-btn', 'dnb-filter-marked', activeStatusFilter === data.constants.EXPORT_STATUS_MARKEDREGISTERED ? 'dnb-filter-active' : '']">
								{{ data.i18n.filterMarkedRegistered }}
								<span class="pkpBadge dnb-filter-badge">{{ statusCounts.markedRegistered }}</span>
							</button>
							<button type="button"
								@click="setStatusFilter(data.constants.DNB_EXPORT_STATUS_MARKEXCLUDED)"
								:class="['dnb-filter-btn', 'dnb-filter-excluded', activeStatusFilter === data.constants.DNB_EXPORT_STATUS_MARKEXCLUDED ? 'dnb-filter-active' : '']">
								{{ data.i18n.filterExcluded }}
								<span class="pkpBadge dnb-filter-badge">{{ statusCounts.excluded }}</span>
							</button>
						</div>
					</div>
				</template>

				<PkpTableHeader>
					<PkpTableColumn id="checkbox">
						<span class="sr-only">Select submission</span>
					</PkpTableColumn>
					<PkpTableColumn id="id" style="text-align: center;">
						{{ t('common.id') }}
					</PkpTableColumn>
					<PkpTableColumn id="details">
						{{ t('common.details') }}
					</PkpTableColumn>
					<PkpTableColumn id="status">
						{{ t('common.status') }}
					</PkpTableColumn>
				</PkpTableHeader>

				<PkpTableBody> <!-- Empty State -->
					<PkpTableRow v-if="paginatedItems.length === 0">
						<PkpTableCell colspan="4" style="text-align: center; padding: 2rem; color: #666;">
							<div>
								<p style="margin: 0; font-size: 1rem;">{{ noResultsText }}</p>
								<p v-if="hasActiveFilters" style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">
									{{ data.i18n.filterHint }}
								</p>
							</div>
						</PkpTableCell>
					</PkpTableRow>

					<!-- Data Rows -->
					<PkpTableRow v-for="item in paginatedItems" :key="item.id">
						<!-- Checkbox Column -->
						<PkpTableCell>
							<input type="checkbox" name="selectedSubmissions[]" :value="item.id"
								v-model="selectedSubmissions"
								:aria-label="`Select submission ${item.id}: ${item.publication.fullTitle}`" />
						</PkpTableCell>

						<!-- ID Column -->
						<PkpTableCell style="text-align: center;">
							<span class="pkpBadge">{{ item.id }}</span>
						</PkpTableCell>

						<!-- Details Column -->
						<PkpTableCell>
								<div class="flex flex-col gap-1 flex-1">
									<span class="dnb_authors">
										{{ item.publication.authorsString }}
									</span>
									<a :href="item.urlWorkflow" class="font-semibold">
										{{ item.publication.fullTitle }}
									</a>
									<a v-if="item.issueUrl" :href="item.issueUrl" class="text-sm">
										{{ item.issueTitle }}
									</a>
									<div v-if="item.supplementariesNotAssignable"
										class="flex items-center gap-1 text-negative">
										<Icon icon="exclamation-triangle" :inline="true" />
										<span class="text-sm">{{ item.supplementariesNotAssignable }}</span>
									</div>
									<div v-if="item.supplementaryNotAssignable">
										<span class="text-sm text-negative">
											{{ item.supplementariesNotAssignable }}
										</span>
									</div>
								</div>
								<div v-if="item.lastError" class="dnb-error">{{item.lastError}}</div>
						</PkpTableCell>

						<!-- Status Column -->
						<PkpTableCell>
							<span
								v-if="item.dnbStatusConst && item.dnbStatusConst === data.constants.DNB_STATUS_DEPOSITED"
								class="pkpBadge dnb_deposited">
								{{ item.dnbStatus }}
							</span>
							<span
								v-if="item.dnbStatusConst && item.dnbStatusConst === data.constants.DNB_EXPORT_STATUS_QUEUED"
								class="pkpBadge dnb_queued">
								{{ item.dnbStatus }}
							</span>
							<span v-if="item.dnbStatusConst && item.dnbStatusConst === data.constants.EXPORT_STATUS_NOT_DEPOSITED"
								class="pkpBadge dnb_not_deposited">
								{{ item.dnbStatus }}
							</span>
							<span v-if="item.dnbStatusConst && item.dnbStatusConst === data.constants.DNB_EXPORT_STATUS_FAILED"
								class="pkpBadge dnb_failed">
								{{ item.dnbStatus }}
							</span>
							<span v-if="item.dnbStatusConst && item.dnbStatusConst === data.constants.EXPORT_STATUS_MARKEDREGISTERED"
								class="pkpBadge dnb_deposited">
								{{ item.dnbStatus }}
							</span>
							<span v-if="item.dnbStatusConst && item.dnbStatusConst === data.constants.DNB_EXPORT_STATUS_MARKEXCLUDED"
								class="pkpBadge dnb_deposited">
								{{ item.dnbStatus }}
							</span>
						</PkpTableCell>
					</PkpTableRow>
				</PkpTableBody>
			</PkpTable>

			<!-- Pagination Controls -->
			<PkpPagination
				v-if="lastPage > 1"
				:current-page="currentPage"
				:last-page="lastPage"
				@set-page="setPage"
				style="margin-top: 1rem;"
			/>
		</form>
	</div>
</template>

<script setup>
// ==========================================
// Imports
// ==========================================
import { ref, computed } from 'vue';
const { useModal } = pkp.modules.useModal;
const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();
const { openDialog } = useModal();

// ==========================================
// Props & Emits
// ==========================================
const props = defineProps({
	data: { type: Object, required: true },
	actionUrls: { type: Object, required: true },
});

const emit = defineEmits(['set']);

// ==========================================
// Data
// ==========================================
const exportForm = ref(null);
const selectedSubmissions = ref([]);
const searchPhrase = ref('');
const activeSearchFilters = ref([]);
const activeStatusFilter = ref(null);
const filteredItems = ref([]);
const isSubmitting = ref(false);
const currentPage = ref(1);
const isClearingFailedJobs = ref(false);
const actionMessage = ref('');
const actionMessageType = ref('success');
let debounceTimeout = null;

// Validation constants
const MAX_SEARCH_LENGTH = 100;
const MAX_FILTER_COUNT = 10;

// Initialize filtered items with all items
filteredItems.value = props.data.items;

// ==========================================
// Computed Properties
// ==========================================

/**
 * Get items per page from OJS settings or use default
 */
const itemsPerPage = computed(() => {
	return props.data.itemsPerPage || 20;
});

/**
 * Get items for current page
 */
const paginatedItems = computed(() => {
	const start = (currentPage.value - 1) * itemsPerPage.value;
	const end = start + itemsPerPage.value;
	return filteredItems.value.slice(start, end);
});

/**
 * Total number of pages
 */
const lastPage = computed(() => {
	return Math.ceil(filteredItems.value.length / itemsPerPage.value);
});

/**
 * Check if all visible items on current page are selected
 */
const areAllVisibleSelected = computed(() => {
	if (paginatedItems.value.length === 0) return false;
	const visibleIds = paginatedItems.value.map(item => item.id);
	return visibleIds.every(id => selectedSubmissions.value.includes(id));
});

/**
 * Display text showing filtered vs total count with pagination
 */
const itemCountText = computed(() => {
	const filtered = filteredItems.value.length;
	const total = props.data.items.length;
	
	if (filtered === 0) {
		return props.data.i18n.noResults || 'No items found';
	}
	
	const start = (currentPage.value - 1) * itemsPerPage.value + 1;
	const end = Math.min(start + itemsPerPage.value - 1, filtered);
	
	if (filtered === total) {
		// No filters applied - show simple count with pagination
		if (filtered <= itemsPerPage.value) {
			// Single page - use original count display
			const pluralMatch = props.data.i18n.itemCount?.match(/\{[^}]+plural[^}]+one \{([^}]+)\} other \{([^}]+)\}\}/);
			if (pluralMatch) {
				const [, singular, plural] = pluralMatch;
				const word = total === 1 ? singular : plural;
				return props.data.i18n.itemCount
					.replace(/\{[^}]+plural[^}]+one \{[^}]+\} other \{[^}]+\}\}/, word)
					.replace('{$count}', total);
			}
			return `${total} items`;
		}
		// Multiple pages - show range
		return `Showing ${start}–${end} of ${total}`;
	}
	
	// Filters applied - show filtered count
	return props.data.i18n.itemCountFiltered
		? props.data.i18n.itemCountFiltered
			.replace('{$filtered}', `${start}–${end}`)
			.replace('{$total}', total)
		: `Showing ${start}–${end} of ${filtered} (filtered from ${total} total)`;
});

/**
 * Search label from i18n
 */
const searchLabel = computed(() => {
	return props.data.i18n.searchLabel || 'Search';
});

/**
 * Check if any filters are active
 */
const hasActiveFilters = computed(() => {
	return activeStatusFilter.value !== null || activeSearchFilters.value.length > 0 || searchPhrase.value.trim() !== '';
});

/**
 * Empty state message text
 */
const noResultsText = computed(() => {
	if (hasActiveFilters.value) {
		return props.data.i18n.noResultsFiltered;
	}
	return props.data.i18n.noResults;
});

/**
 * Counts for status filter badges
 */
const statusCounts = computed(() => {
	const counts = {
		all: props.data.items.length,
		notDeposited: 0,
		deposited: 0,
		queued: 0,
		failed: 0,
		markedRegistered: 0,
		excluded: 0,
	};

	props.data.items.forEach((item) => {
		switch (item.dnbStatusConst) {
			case props.data.constants.EXPORT_STATUS_NOT_DEPOSITED:
				counts.notDeposited += 1;
				break;
			case props.data.constants.DNB_STATUS_DEPOSITED:
				counts.deposited += 1;
				break;
			case props.data.constants.DNB_EXPORT_STATUS_QUEUED:
				counts.queued += 1;
				break;
			case props.data.constants.DNB_EXPORT_STATUS_FAILED:
				counts.failed += 1;
				break;
			case props.data.constants.EXPORT_STATUS_MARKEDREGISTERED:
				counts.markedRegistered += 1;
				break;
			case props.data.constants.DNB_EXPORT_STATUS_MARKEXCLUDED:
				counts.excluded += 1;
				break;
			default:
				break;
		}
	});

	return counts;
});

// ==========================================
// Methods
// ==========================================

/**
 * Set search phrase (for typing in real-time with debouncing)
 */
function setSearchPhrase(phrase) {
	// Sanitize and validate input
	let sanitized = phrase.trim();

	// Enforce max length
	if (sanitized.length > MAX_SEARCH_LENGTH) {
		sanitized = sanitized.substring(0, MAX_SEARCH_LENGTH);
	}

	searchPhrase.value = sanitized;

	// Clear existing timeout
	if (debounceTimeout) {
		clearTimeout(debounceTimeout);
	}

	// Debounce the filter application (300ms delay)
	debounceTimeout = setTimeout(() => {
		applyFilters();
	}, 300);
}

/**
 * Add search phrase as a filter chip (triggered by Enter key)
 */
function addSearchFilter() {
	const phrase = searchPhrase.value.trim();

	// Validation checks
	if (!phrase) return; // Empty phrase
	if (phrase.length > MAX_SEARCH_LENGTH) return; // Too long
	if (activeSearchFilters.value.includes(phrase)) return; // Duplicate
	if (activeSearchFilters.value.length >= MAX_FILTER_COUNT) {
		// Too many filters - could show a message here
		console.warn(`Maximum of ${MAX_FILTER_COUNT} search filters allowed`);
		return;
	}

	activeSearchFilters.value.push(phrase);
	// Clear the input
	searchPhrase.value = '';
	applyFilters();
}

/**
 * Remove a specific search filter chip by index
 */
function removeSearchFilter(index) {
	activeSearchFilters.value.splice(index, 1);
	applyFilters();
}

/**
 * Set status filter and apply filters
 */
function setStatusFilter(status) {
	activeStatusFilter.value = status;
	applyFilters();
}

/**
 * Apply both search and status filters
 */
function applyFilters() {
	try {
		let items = props.data.items;

		// Apply status filter
		if (activeStatusFilter.value !== null) {
			items = items.filter(item => item.dnbStatusConst === activeStatusFilter.value);
		}

		// Apply search filters - item must match ALL active filters AND live search
		const allSearchTerms = [...activeSearchFilters.value];
		if (searchPhrase.value.trim()) {
			allSearchTerms.push(searchPhrase.value.trim());
		}

		if (allSearchTerms.length > 0) {
			items = items.filter(item => {
				// Item must match ALL search terms
				return allSearchTerms.every(searchTerm => {
					const lowerPhrase = searchTerm.toLowerCase();
					const authorsMatch = item.publication.authorsString?.toLowerCase().includes(lowerPhrase);
					const titleMatch = item.publication.fullTitle?.toLowerCase().includes(lowerPhrase);
					const idMatch = item.id.toString().includes(lowerPhrase);
					const issueMatch = item.issueTitle?.toLowerCase().includes(lowerPhrase);

					return authorsMatch || titleMatch || idMatch || issueMatch;
				});
			});
		}

		filteredItems.value = items;
		
		// Reset to first page when filters change
		currentPage.value = 1;
	} catch (error) {
		console.error('Error applying filters:', error);
		// Fallback to showing all items on error
		filteredItems.value = props.data.items;
		currentPage.value = 1;
	}
}

/**
 * Toggle selection of all submissions on current page
 */
function toggleSelectAll() {
	// Check if all currently visible items on current page are selected
	const visibleIds = paginatedItems.value.map(item => item.id);
	const allVisibleSelected = visibleIds.every(id => selectedSubmissions.value.includes(id));

	if (allVisibleSelected && visibleIds.length > 0) {
		// Deselect all visible items on current page
		selectedSubmissions.value = selectedSubmissions.value.filter(id => !visibleIds.includes(id));
	} else {
		// Select all visible items on current page (merge with existing selections)
		const uniqueSelections = new Set([...selectedSubmissions.value, ...visibleIds]);
		selectedSubmissions.value = Array.from(uniqueSelections);
	}
}

/**
 * Deselect all submissions (clear all selections)
 */
function deselectAll() {
	selectedSubmissions.value = [];
}

/**
 * Handle action button clicks
 * @param {string} action - The action type (deposit, export, mark, exclude)
 */
function handleAction(action) {
	if (!exportForm.value || isSubmitting.value) return;

	// Set loading state
	isSubmitting.value = true;

	// Set the form action based on the button clicked
	exportForm.value.action = props.actionUrls[action];

	// Submit the form (page will navigate away or trigger download)
	exportForm.value.submit();

	// Reset submitting state after delay (for file downloads that don't navigate away)
	// If the page actually navigates, this timeout will be cleared automatically
	setTimeout(() => {
		isSubmitting.value = false;
	}, 2000);
}

/**
 * Clear failed DNB export jobs from the queue
 */
async function handleClearFailedJobs() {
	if (!props.actionUrls.clearFailedJobs || isClearingFailedJobs.value) return;

	const confirmMessage = props.data.i18n.clearFailedJobsConfirm || 'Clear failed DNB jobs?';
	openDialog({
		title: props.data.i18n.clearFailedJobs || t('common.confirm'),
		message: confirmMessage,
		actions: [
			{
				label: t('common.confirm'),
				isPrimary: true,
				callback: (close) => {
					close();
					performClearFailedJobs();
				},
			},
			{
				label: t('common.cancel'),
				callback: (close) => close(),
			},
		],
	});
}

async function performClearFailedJobs() {

	isClearingFailedJobs.value = true;
	actionMessage.value = '';
	actionMessageType.value = 'success';

	try {
		const metaCsrf = document.querySelector('meta[name="csrf-token"]');
		const csrfToken = props.data.csrfToken || window.pkp?.currentUser?.csrfToken || metaCsrf?.getAttribute('content') || '';
		const body = new URLSearchParams({ csrfToken });
		const response = await fetch(props.actionUrls.clearFailedJobs, {
			method: 'POST',
			headers: {
				'X-Csrf-Token': csrfToken,
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		});

		const result = await response.json();
		if (result?.status) {
			actionMessageType.value = 'success';
			actionMessage.value = result.content || props.data.i18n.clearFailedJobsSuccess;
		} else {
			actionMessageType.value = 'warning';
			actionMessage.value = result?.content || props.data.i18n.clearFailedJobsError;
		}
	} catch (error) {
		console.error('Failed to clear failed jobs:', error);
		actionMessageType.value = 'warning';
		actionMessage.value = props.data.i18n.clearFailedJobsError;
	} finally {
		isClearingFailedJobs.value = false;
	}
}

/**
 * Handle pagination page change
 * @param {number} page - The new page number
 */
function setPage(page) {
	currentPage.value = page;
	// Scroll to top of table
	document.querySelector('.dnbSubmissionsTable')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<style>
/* CSS Variables */
:root {
	--dnb-gap-sm: 0.5rem;
	--dnb-gap-md: 1rem;
	--dnb-padding-btn: 0.375rem 0.75rem;
	--dnb-border-radius: 0.25rem;
	--dnb-font-size-sm: 0.875rem;
	--dnb-font-size-base: 1rem;
	--dnb-font-size-lg: 1.25rem;
	--dnb-chip-max-width: 200px;
	--dnb-chip-remove-size: 1.25rem;
	--dnb-color-primary: #007bff;
	--dnb-color-primary-hover: #0056b3;
	--dnb-color-text: #333;
	--dnb-color-muted: #666;
	--dnb-color-border: #ddd;
	--dnb-color-border-hover: #ccc;
	--dnb-color-bg: #fff;
	--dnb-color-bg-hover: #f5f5f5;
	--dnb-color-deposited-bg: #d4edda;
	--dnb-color-deposited-text: #155724;
	--dnb-color-not-deposited-bg: #f8d7da;
	--dnb-color-not-deposited-text: #721c24;
	--dnb-color-queued-bg: #fcdab5;
	--dnb-color-queued-text: #92400e;
	--dnb-color-failed-bg: #f8d7da;
	--dnb-color-failed-text: #721c24;
	--dnb-transition-speed: 0.2s;
}

.dnbSubmissionsTable {
	margin-top: var(--dnb-gap-md);
	width: 100%;
}

.dnbSubmissionsTable .dnb-controls-wrapper {
	max-width: 100% !important;
	width: 100% !important;
}

/* Allow the table top-controls container to shrink with the viewport */
.dnbSubmissionsTable .flex-shrink-0 {
	flex: 1 1 auto;
	min-width: 0;
}

.dnbSubmissionsTable .dnb-top-controls {
	max-width: 100%;
	width: 100%;
}

.dnbSubmissionsTable .dnb-button-container {
	display: flex !important;
	flex-wrap: wrap !important;
	gap: var(--dnb-gap-sm);
	row-gap: var(--dnb-gap-sm);
	max-width: 100% !important;
	width: 100% !important;
	margin-bottom: var(--dnb-gap-md);
}

.dnbSubmissionsTable .dnb-button-container .pkpButton {
	display: block !important;
	flex: 0 0 auto !important;
	max-width: 100% !important;
	white-space: nowrap;
}

.dnb_authors {
	color: var(--dnb-color-muted);
	font-size: 0.9em;
}

.dnb-error {
	color: red;
}

.dnb_deposited {
	background-color: var(--dnb-color-deposited-bg);
	color: var(--dnb-color-deposited-text);
	border-color: var(--dnb-color-deposited-text);
}

.dnb_queued {
	background-color: var(--dnb-color-queued-bg);
	color: var(--dnb-color-queued-text);
	border-color: var(--dnb-color-queued-text);
}

.dnb_failed {
	background-color: var(--dnb-color-failed-bg);
	color: var(--dnb-color-failed-text);
	border-color: var(--dnb-color-failed-text);
}

.dnb_not_deposited {
	background-color: var(--dnb-color-not-deposited-bg);
	color: var(--dnb-color-not-deposited-text);
	border-color: var(--dnb-color-not-deposited-text);
}

.dnb-filter-buttons {
	display: flex;
	flex-wrap: wrap;
	gap: var(--dnb-gap-sm);
	row-gap: var(--dnb-gap-sm);
	align-items: center;
	max-width: 100%;
	white-space: normal;
}

.dnb-filter-buttons .dnb-filter-btn {
	padding: var(--dnb-padding-btn);
	font-size: var(--dnb-font-size-sm);
	border: 1px solid var(--dnb-color-border);
	border-radius: var(--dnb-border-radius);
	background-color: var(--dnb-color-bg);
	color: var(--dnb-color-text);
	cursor: pointer;
	transition: all var(--dnb-transition-speed);
	font-weight: 400;
	flex: 0 1 auto;
	white-space: normal;
	display: inline-flex;
	align-items: center;
	gap: 0.35rem;
}

.dnb-filter-buttons .dnb-filter-btn:hover {
	background-color: var(--dnb-color-bg-hover);
	border-color: var(--dnb-color-border-hover);
}

.dnb-filter-buttons .dnb-filter-btn.dnb-filter-active {
	background-color: var(--dnb-color-primary);
	color: #fff;
	border-color: var(--dnb-color-primary);
	font-weight: 500;
}

.dnb-filter-buttons .dnb-filter-btn.dnb-filter-active:hover {
	background-color: var(--dnb-color-primary-hover);
	border-color: var(--dnb-color-primary-hover);
}

.dnb-filter-badge {
	margin-left: 0.35rem;
	color: currentColor;
}

.dnb-search-filter-chip {
	display: inline-flex;
	align-items: center;
	gap: var(--dnb-gap-sm);
	padding: var(--dnb-padding-btn);
	background-color: var(--dnb-color-primary);
	color: #fff;
	border-radius: var(--dnb-border-radius);
	font-size: var(--dnb-font-size-sm);
	font-weight: 500;
}

.dnb-chip-label {
	max-width: var(--dnb-chip-max-width);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.dnb-chip-remove {
	background: none;
	border: none;
	color: #fff;
	font-size: var(--dnb-font-size-lg);
	line-height: 1;
	cursor: pointer;
	padding: 0;
	margin: 0;
	width: var(--dnb-chip-remove-size);
	height: var(--dnb-chip-remove-size);
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	transition: background-color var(--dnb-transition-speed);
}

.dnb-chip-remove:hover {
	background-color: rgba(255, 255, 255, 0.2);
}

/* Loading spinner */
.dnb-spinner {
	display: inline-block;
	width: 0.875rem;
	height: 0.875rem;
	margin-right: 0.5rem;
	border: 2px solid rgba(255, 255, 255, 0.3);
	border-top-color: #fff;
	border-radius: 50%;
	animation: dnb-spin 0.6s linear infinite;
}

@keyframes dnb-spin {
	to {
		transform: rotate(360deg);
	}
}
</style>
