import { defineConfig } from 'orval'

export default defineConfig({
  wichtler: {
    input: './openapi.json',
    output: {
      mode: 'tags-split',
      target: './src/api/generated',
      schemas: './src/api/generated/model',
      client: 'react-query',
      httpClient: 'fetch',
      clean: true,
      override: {
        mutator: { path: './src/api/http.ts', name: 'customFetch' },
        // Hooks resolve to the response body; failures are thrown as ApiError by the mutator.
        fetch: { includeHttpResponseReturnType: false },
      },
    },
  },
})
