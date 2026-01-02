import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
	plugins: [vue()],
	build: {
		outDir: 'build',
		emptyOutDir: false,
		lib: {
			entry: path.resolve(__dirname, 'resources/js/index.js'),
			name: 'dnbPlugin',
			formats: ['iife'],
			fileName: () => 'dnb.iife.js',
		},
		rollupOptions: {
			external: ['vue'],
			output: {
				globals: {
					vue: 'pkp.modules.vue',
				},
			},
		},
	},
	resolve: {
		alias: {
			'@': path.resolve(__dirname, 'resources/js'),
		},
	},
});
