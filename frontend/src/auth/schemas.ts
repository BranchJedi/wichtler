import { z } from 'zod'
import { de } from '../i18n/de'

// Mirror the server-side rules (App\Validator\Username, App\Validator\Password) for instant feedback.
// The server stays the authority and reports anything these miss.
export const nameSchema = z.string().trim().min(1, de.validation.required).max(80, de.validation.maxLength)
export const usernameSchema = z
  .string()
  .trim()
  .regex(/^[a-z0-9._-]{3,30}$/i, de.validation.username)
export const newPasswordSchema = z.string().min(10, de.validation.passwordLength)
