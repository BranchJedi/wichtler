import { describe, expect, it, vi } from 'vitest'
import { applyServerErrors, errorMessage } from './errors'
import { ApiError } from './http'

const validation = (violations: { propertyPath: string; title: string }[]) =>
  new ApiError(422, { type: 'https://symfony.com/errors/validation', title: 'Validation Failed', status: 422, violations })

describe('applyServerErrors', () => {
  it('puts each violation next to its field', () => {
    const setError = vi.fn()

    applyServerErrors(validation([{ propertyPath: 'username', title: 'Dieser Username ist bereits vergeben.' }]), setError, ['firstName', 'username'])

    expect(setError).toHaveBeenCalledTimes(1)
    expect(setError).toHaveBeenCalledWith('username', { type: 'server', message: 'Dieser Username ist bereits vergeben.' })
  })

  it('collects violations of unknown fields as a form-level message', () => {
    const setError = vi.fn()

    applyServerErrors(validation([{ propertyPath: 'budgetMax', title: 'Zu klein.' }, { propertyPath: 'title', title: 'Fehlt.' }]), setError, ['title'])

    expect(setError).toHaveBeenCalledWith('title', { type: 'server', message: 'Fehlt.' })
    expect(setError).toHaveBeenCalledWith('root', { type: 'server', message: 'Zu klein.' })
  })

  it('explains throttling and falls back to a generic message otherwise', () => {
    const setError = vi.fn()

    applyServerErrors(new ApiError(429, undefined), setError, [])
    applyServerErrors(new Error('network down'), setError, [])

    expect(setError.mock.calls[0][1].message).toContain('Zu viele Anfragen')
    expect(setError.mock.calls[1][1].message).toContain('nicht geklappt')
  })
})

describe('errorMessage', () => {
  it('passes on the explanation the server wrote for the user', () => {
    const refusal = new ApiError(409, { type: 'about:blank', title: 'Conflict', status: 409, detail: 'Die Gruppe ist ausgelost.' })

    expect(errorMessage(refusal)).toBe('Die Gruppe ist ausgelost.')
  })

  it('never shows raw technical errors', () => {
    expect(errorMessage(new TypeError('Failed to fetch'))).toContain('nicht geklappt')
    expect(errorMessage(new ApiError(500, undefined))).toContain('nicht geklappt')
  })
})
