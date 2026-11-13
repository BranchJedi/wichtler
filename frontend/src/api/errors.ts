import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { de } from '../i18n/de'
import type { ProblemDetails } from './generated/model'
import { ApiError } from './http'

function isProblem(body: unknown): body is ProblemDetails {
  return typeof body === 'object' && body !== null && 'title' in body && 'status' in body
}

/**
 * Puts the server's field errors next to the matching inputs. Anything that does not belong
 * to a known field ends up as a form-level message.
 */
export function applyServerErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fields: readonly Path<T>[],
): void {
  if (error instanceof ApiError && error.status === 422 && isProblem(error.body)) {
    const unmatched: string[] = []
    for (const violation of error.body.violations ?? []) {
      const field = fields.find((name) => name === violation.propertyPath)
      if (field) {
        setError(field, { type: 'server', message: violation.title })
      } else {
        unmatched.push(violation.title)
      }
    }
    if (unmatched.length > 0) {
      setError('root', { type: 'server', message: unmatched.join(' ') })
    }
    return
  }

  const message = error instanceof ApiError && error.status === 429 ? de.errors.tooManyRequests : de.errors.generic
  setError('root', { type: 'server', message })
}

/** The server explains refusals such as "the group has been drawn" in the problem document. */
export function errorMessage(error: unknown): string {
  if (error instanceof ApiError && error.body && typeof error.body === 'object' && 'detail' in error.body && error.body.detail) {
    return String(error.body.detail)
  }
  return de.errors.generic
}
