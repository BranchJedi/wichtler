import type {AuthError, ProblemDetails} from './generated/model'

/** Any non-2xx answer of the API. `body` is a problem document, or the firewall's `{ code, message }`. */
export class ApiError<TBody = ProblemDetails | AuthError> extends Error {
    readonly status: number
    readonly body: TBody | undefined

    constructor(status: number, body: TBody | undefined) {
        super(`API request failed with status ${status}`)
        this.name = 'ApiError'
        this.status = status
        this.body = body
    }
}

// orval uses this as the error type of the generated hooks.
export type ErrorType<TBody> = ApiError<TBody>

async function readBody(response: Response): Promise<unknown> {
    if (response.status === 204) return undefined
    const text = await response.text()
    if (text === '') return undefined
    try {
        return JSON.parse(text)
    } catch {
        return undefined
    }
}

let refreshInFlight: Promise<boolean> | null = null

/** Several requests may hit an expired token at once; the single-use refresh token must be spent only once. */
function refreshSession(): Promise<boolean> {
    refreshInFlight ??= fetch('/api/auth/refresh', {method: 'POST'})
        .then((response) => response.ok)
        .catch(() => false)
        .finally(() => {
            refreshInFlight = null
        })
    return refreshInFlight
}

/**
 * Fetch wrapper behind every generated API function. The session lives in HttpOnly cookies,
 * so there is no token to attach; an expired access token is renewed once, transparently.
 */
export async function customFetch<T>(url: string, options: RequestInit = {}): Promise<T> {
    let response = await fetch(url, options)

    if (response.status === 401 && !url.startsWith('/api/auth/') && (await refreshSession())) {
        response = await fetch(url, options)
    }

    const body = await readBody(response)
    if (!response.ok) {
        throw new ApiError(response.status, body as ProblemDetails | AuthError | undefined)
    }
    return body as T
}
