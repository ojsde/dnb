<template>
	<div class="dnbCatalogTable">
		<div class="dnbCatalogTable__caption">
			{{ t('plugins.importexport.dnb.dnbCatalogInfo.ISSNTableCaption') }}
		</div>
		<PkpTable>
			<PkpTableHeader>
				<PkpTableColumn v-for="(label, index) in headerLabels" :key="index">
					{{ label }}
				</PkpTableColumn>
			</PkpTableHeader>
			<PkpTableBody>
				<PkpTableRow v-if="rows.length === 0">
					<PkpTableCell :colspan="Math.max(headerLabels.length, 1)" style="text-align: center; padding: 1.5rem;">
						{{ t('common.none') }}
					</PkpTableCell>
				</PkpTableRow>
				<PkpTableRow v-for="(row, rowIndex) in rows" :key="rowIndex">
					<PkpTableCell v-for="(value, colIndex) in rowValues(row)" :key="colIndex">
						<span v-if="isHtml(value)" v-html="value"></span>
						<span v-else>{{ value }}</span>
					</PkpTableCell>
				</PkpTableRow>
			</PkpTableBody>
		</PkpTable>
	</div>
</template>

<script setup>
import { computed } from 'vue';

const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();

const props = defineProps({
	data: { type: Object, required: true },
});

const rawRows = computed(() => Array.isArray(props.data.items) ? props.data.items : []);
const headerRow = computed(() => rawRows.value.length > 0 ? rawRows.value[0] : null);
const headerLabels = computed(() => headerRow.value ? Object.keys(headerRow.value) : []);
const rows = computed(() => rawRows.value.slice(1));

function rowValues(row) {
	if (!row || typeof row !== 'object') {
		return [];
	}
	return Object.values(row);
}

function isHtml(value) {
	return typeof value === 'string' && /<\/?[a-z][\s\S]*>/i.test(value);
}
</script>
