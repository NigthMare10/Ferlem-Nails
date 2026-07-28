import vue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';

export default [
    { ignores: ['node_modules/**', 'public/build/**', 'vendor/**'] },
    ...vue.configs['flat/essential'],
    {
        files: ['resources/js/**/*.ts'],
        languageOptions: { parser: tseslint.parser, parserOptions: { sourceType: 'module' } },
    },
    {
        files: ['resources/js/**/*.vue'],
        languageOptions: {
            parserOptions: { parser: tseslint.parser, extraFileExtensions: ['.vue'], sourceType: 'module' },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'error',
            'vue/valid-v-slot': ['error', { allowModifiers: true }],
        },
    },
];
