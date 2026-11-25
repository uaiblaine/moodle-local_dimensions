module.exports = {
  rootDir: '/private/tmp/claude-501/-Users-uaiblaine-dev/e1be1b71-acfa-40d7-a9d9-6076e98b79fb/scratchpad/s6',
  roots: ['/Users/uaiblaine/dev/wt-local_dimensions-hubv2-s6/js/esm/tests'],
  testEnvironment: 'jsdom',
  testEnvironmentOptions: {url: 'https://example.com/'},
  testMatch: ['**/esm/tests/**/*.test.{ts,tsx}'],
  moduleNameMapper: {'^@moodle/lms/core/stringUtils$': '/private/tmp/claude-501/-Users-uaiblaine-dev/e1be1b71-acfa-40d7-a9d9-6076e98b79fb/scratchpad/s6/stubs/stringUtils.ts'},
  transform: {'^.+\\.(ts|tsx)$': ['ts-jest', {isolatedModules: true, tsconfig: {jsx: 'react-jsx', esModuleInterop: true, target: 'ES2022', module: 'commonjs'}}]},
};
