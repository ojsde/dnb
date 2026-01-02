<template>
	<teleport to="body">
		<div v-if="isOpen" class="dnb-help-overlay" @click="closeHelp">
			<div class="dnb-help-panel" @click.stop>
				<!-- Header -->
				<div class="dnb-help-header">
					<h2>{{ helpTitle }}</h2>
					<button 
						type="button" 
						class="dnb-help-close" 
						@click="closeHelp"
						:aria-label="t('common.close')">
						×
					</button>
				</div>

				<!-- Content -->
				<div class="dnb-help-content" @click="handleContentClick">
					<div v-if="loading" class="dnb-help-loading">
						<div class="dnb-spinner"></div>
						<p>{{ t('common.loading') }}</p>
					</div>
					
					<div v-else-if="error" class="dnb-help-error">
						<p>{{ error }}</p>
					</div>
					
					<div v-else v-html="content" class="dnb-help-markdown"></div>
				</div>

				<!-- Navigation Footer -->
				<div v-if="!loading && !error" class="dnb-help-footer">
					<button 
						v-if="previousTopic"
						type="button"
						class="dnb-help-nav-btn"
						@click="navigateTo(previousTopic)">
						← {{ t('common.previous') }}
					</button>
					<span v-else></span>
					
					<button 
						v-if="nextTopic"
						type="button"
						class="dnb-help-nav-btn"
						@click="navigateTo(nextTopic)">
						{{ t('common.next') }} →
					</button>
				</div>
			</div>
		</div>
	</teleport>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
	helpApiUrl: { type: String, required: true },
	locale: { type: String, required: true }
});

const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();

// State
const isOpen = ref(false);
const loading = ref(false);
const error = ref(null);
const content = ref('');
const helpTitle = ref('Help');
const currentTopic = ref('SUMMARY');
const previousTopic = ref(null);
const nextTopic = ref(null);

/**
 * Open help panel and load initial content
 */
function openHelp(topic = 'SUMMARY') {
	isOpen.value = true;
	loadHelpContent(topic);
}

/**
 * Close help panel
 */
function closeHelp() {
	isOpen.value = false;
}

/**
 * Navigate to a different help topic
 */
function navigateTo(topic) {
	currentTopic.value = topic;
	loadHelpContent(topic);
}

/**
 * Handle clicks on links within the help content
 */
function handleContentClick(event) {
	// Check if the clicked element is a link
	const target = event.target.closest('a');
	if (!target) return;
	
	// Check if it's a relative link (help navigation)
	const href = target.getAttribute('href');
	if (!href || href.startsWith('http://') || href.startsWith('https://') || href.startsWith('#')) {
		return; // Allow external links and anchors to work normally
	}
	
	// Prevent default navigation
	event.preventDefault();
	
	// Extract topic from the relative URL (e.g., "de/intro.md" -> "intro" or "intro.md" -> "intro")
	let topic = href.replace(/\.md$/, '');
	
	// Remove language prefix if present (e.g., "de/intro" -> "intro")
	const lang = props.locale.substring(0, 2);
	if (topic.startsWith(lang + '/')) {
		topic = topic.substring(lang.length + 1);
	}
	
	navigateTo(topic);
}

/**
 * Load help content from API
 */
async function loadHelpContent(topic) {
	loading.value = true;
	error.value = null;
	
	try {
		// Extract language code (first 2 characters)
		const lang = props.locale.substring(0, 2);
		const url = `${props.helpApiUrl}?lang=${lang}&topic=${topic}`;
		
		const response = await fetch(url, {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
			},
			credentials: 'same-origin'
		});
		
		if (!response.ok) {
			throw new Error(`Failed to load help content (${response.status})`);
		}
		
		const data = await response.json();
		
		// Extract title from first H1 in content
		const titleMatch = data.content.match(/<h1[^>]*>(.*?)<\/h1>/i);
		if (titleMatch) {
			helpTitle.value = titleMatch[1].replace(/<[^>]*>/g, '');
			// Remove the h1 from content to avoid duplication
			content.value = data.content.replace(/<h1[^>]*>.*?<\/h1>/i, '');
		} else {
			content.value = data.content;
			helpTitle.value = 'Help';
		}
		
		previousTopic.value = data.previous;
		nextTopic.value = data.next;
		currentTopic.value = topic;
		
	} catch (e) {
		console.error('DNB Help Panel Error:', e);
		error.value = t('common.error.loadingFailed') || 'Failed to load help content';
	} finally {
		loading.value = false;
	}
}

// Expose methods for parent component
defineExpose({
	openHelp,
	closeHelp
});
</script>

<style scoped>
/* Overlay */
.dnb-help-overlay {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: rgba(0, 0, 0, 0.5);
	z-index: 9999;
	display: flex;
	justify-content: flex-end;
}

/* Panel */
.dnb-help-panel {
	width: 600px;
	max-width: 90vw;
	height: 100vh;
	background: white;
	box-shadow: -2px 0 8px rgba(0, 0, 0, 0.15);
	display: flex;
	flex-direction: column;
	animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
	from {
		transform: translateX(100%);
	}
	to {
		transform: translateX(0);
	}
}

/* Header */
.dnb-help-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 1.5rem;
	border-bottom: 1px solid #ddd;
	background: #f5f5f5;
}

.dnb-help-header h2 {
	margin: 0;
	font-size: 1.5rem;
	font-weight: 600;
	color: #333;
}

.dnb-help-close {
	background: none;
	border: none;
	font-size: 2rem;
	line-height: 1;
	cursor: pointer;
	color: #666;
	padding: 0;
	width: 2rem;
	height: 2rem;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 4px;
	transition: background-color 0.2s;
}

.dnb-help-close:hover {
	background-color: rgba(0, 0, 0, 0.1);
	color: #333;
}

/* Content */
.dnb-help-content {
	flex: 1;
	overflow-y: auto;
	padding: 2rem;
}

.dnb-help-loading,
.dnb-help-error {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	min-height: 200px;
	color: #666;
}

.dnb-help-loading .dnb-spinner {
	width: 40px;
	height: 40px;
	border: 4px solid #f3f3f3;
	border-top: 4px solid #007bff;
	border-radius: 50%;
	animation: spin 1s linear infinite;
	margin-bottom: 1rem;
}

@keyframes spin {
	0% { transform: rotate(0deg); }
	100% { transform: rotate(360deg); }
}

.dnb-help-error {
	color: #d32f2f;
}

/* Markdown content styling */
.dnb-help-markdown {
	line-height: 1.6;
}

.dnb-help-markdown :deep(h1) {
	font-size: 1.75rem;
	margin-top: 0;
	margin-bottom: 1rem;
	color: #333;
	border-bottom: 2px solid #007bff;
	padding-bottom: 0.5rem;
}

.dnb-help-markdown :deep(h2) {
	font-size: 1.5rem;
	margin-top: 2rem;
	margin-bottom: 1rem;
	color: #333;
}

.dnb-help-markdown :deep(h3) {
	font-size: 1.25rem;
	margin-top: 1.5rem;
	margin-bottom: 0.75rem;
	color: #555;
}

.dnb-help-markdown :deep(p) {
	margin-bottom: 1rem;
}

.dnb-help-markdown :deep(ul),
.dnb-help-markdown :deep(ol) {
	margin-bottom: 1rem;
	padding-left: 2rem;
}

.dnb-help-markdown :deep(li) {
	margin-bottom: 0.5rem;
}

.dnb-help-markdown :deep(code) {
	background-color: #f5f5f5;
	padding: 0.2rem 0.4rem;
	border-radius: 3px;
	font-family: monospace;
	font-size: 0.9em;
}

.dnb-help-markdown :deep(pre) {
	background-color: #f5f5f5;
	padding: 1rem;
	border-radius: 4px;
	overflow-x: auto;
	margin-bottom: 1rem;
}

.dnb-help-markdown :deep(pre code) {
	background: none;
	padding: 0;
}

.dnb-help-markdown :deep(a) {
	color: #007bff;
	text-decoration: none;
}

.dnb-help-markdown :deep(a:hover) {
	text-decoration: underline;
}

.dnb-help-markdown :deep(img) {
	max-width: 100%;
	height: auto;
	margin: 1rem 0;
}

.dnb-help-markdown :deep(table) {
	width: 100%;
	border-collapse: collapse;
	margin-bottom: 1rem;
}

.dnb-help-markdown :deep(th),
.dnb-help-markdown :deep(td) {
	border: 1px solid #ddd;
	padding: 0.5rem;
	text-align: left;
}

.dnb-help-markdown :deep(th) {
	background-color: #f5f5f5;
	font-weight: 600;
}

/* Footer Navigation */
.dnb-help-footer {
	display: flex;
	justify-content: space-between;
	padding: 1rem 1.5rem;
	border-top: 1px solid #ddd;
	background: #f5f5f5;
}

.dnb-help-nav-btn {
	padding: 0.5rem 1rem;
	background-color: #007bff;
	color: white;
	border: none;
	border-radius: 4px;
	cursor: pointer;
	font-size: 0.875rem;
	font-weight: 500;
	transition: background-color 0.2s;
}

.dnb-help-nav-btn:hover {
	background-color: #0056b3;
}

/* Responsive */
@media (max-width: 768px) {
	.dnb-help-panel {
		width: 100vw;
	}
	
	.dnb-help-content {
		padding: 1rem;
	}
}
</style>
