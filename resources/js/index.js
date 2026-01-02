/**
 * @file resources/js/index.js
 *
 * Entry point for DNB plugin Vue components
 */

import DNBSubmissionsTable from './Components/DNBSubmissionsTable.vue';
import DNBHelpPanel from './Components/DNBHelpPanel.vue';

// Register components globally via pkp registry
pkp.registry.registerComponent('dnb-submissions-table', DNBSubmissionsTable);
pkp.registry.registerComponent('dnb-help-panel', DNBHelpPanel);
