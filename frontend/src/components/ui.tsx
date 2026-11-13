import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react'
import { useId } from 'react'

export function Card({ title, children }: { title?: string; children: ReactNode }) {
  return (
    <section className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
      {title && <h2 className="mb-4 text-lg font-semibold">{title}</h2>}
      {children}
    </section>
  )
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'ghost' | 'danger'; busy?: boolean }

export function Button({ variant = 'primary', busy = false, className = '', children, disabled, ...props }: ButtonProps) {
  const styles = {
    primary: 'bg-pine-600 text-white hover:bg-pine-700 disabled:bg-stone-300',
    danger: 'bg-berry-600 text-white hover:bg-berry-700 disabled:bg-stone-300',
    ghost: 'border border-stone-300 bg-white text-stone-700 hover:bg-stone-100 disabled:text-stone-400',
  }[variant]
  return (
    <button
      className={`inline-flex min-h-11 items-center justify-center rounded-lg px-4 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pine-600 disabled:cursor-not-allowed ${styles} ${className}`}
      disabled={disabled || busy}
      aria-busy={busy}
      {...props}
    >
      {children}
    </button>
  )
}

type FieldProps = InputHTMLAttributes<HTMLInputElement> & { label: string; error?: string; hint?: string }

export function Field({ label, error, hint, ...props }: FieldProps) {
  const id = useId()
  const describedBy = [error ? `${id}-error` : null, hint ? `${id}-hint` : null].filter(Boolean).join(' ')
  return (
    <div>
      <label htmlFor={id} className="mb-1 block text-sm font-medium text-stone-700">
        {label}
      </label>
      <input
        id={id}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy || undefined}
        className={`block min-h-11 w-full rounded-lg border bg-white px-3 text-base outline-none focus:ring-2 focus:ring-pine-600/30 disabled:bg-stone-100 disabled:text-stone-500 ${error ? 'border-berry-600' : 'border-stone-300 focus:border-pine-600'}`}
        {...props}
      />
      {hint && !error && (
        <p id={`${id}-hint`} className="mt-1 text-sm text-stone-500">
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} className="mt-1 text-sm text-berry-700">
          {error}
        </p>
      )}
    </div>
  )
}

export function Notice({ tone, children }: { tone: 'error' | 'success' | 'info'; children: ReactNode }) {
  const styles = {
    error: 'border-berry-600/30 bg-red-50 text-berry-700',
    success: 'border-pine-600/30 bg-pine-50 text-pine-900',
    info: 'border-amber-300 bg-amber-50 text-amber-900',
  }[tone]
  return (
    <p role={tone === 'error' ? 'alert' : 'status'} className={`rounded-lg border px-3 py-2 text-sm ${styles}`}>
      {children}
    </p>
  )
}

const control = 'block w-full rounded-lg border bg-white px-3 text-base outline-none focus:ring-2 focus:ring-pine-600/30 disabled:bg-stone-100 disabled:text-stone-500'

type TextAreaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & { label: string; error?: string }

export function TextArea({ label, error, ...props }: TextAreaProps) {
  const id = useId()
  return (
    <div>
      <label htmlFor={id} className="mb-1 block text-sm font-medium text-stone-700">
        {label}
      </label>
      <textarea
        id={id}
        rows={3}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        className={`${control} py-2 ${error ? 'border-berry-600' : 'border-stone-300 focus:border-pine-600'}`}
        {...props}
      />
      {error && (
        <p id={`${id}-error`} className="mt-1 text-sm text-berry-700">
          {error}
        </p>
      )}
    </div>
  )
}

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & { label: string; error?: string }

export function Select({ label, error, children, ...props }: SelectProps) {
  const id = useId()
  return (
    <div>
      <label htmlFor={id} className="mb-1 block text-sm font-medium text-stone-700">
        {label}
      </label>
      <select
        id={id}
        aria-invalid={error ? true : undefined}
        className={`${control} min-h-11 ${error ? 'border-berry-600' : 'border-stone-300 focus:border-pine-600'}`}
        {...props}
      >
        {children}
      </select>
      {error && <p className="mt-1 text-sm text-berry-700">{error}</p>}
    </div>
  )
}

export function Badge({ tone, children }: { tone: 'neutral' | 'ready' | 'done' | 'admin'; children: ReactNode }) {
  const styles = {
    neutral: 'bg-stone-100 text-stone-700',
    ready: 'bg-amber-100 text-amber-900',
    done: 'bg-pine-100 text-pine-900',
    admin: 'bg-berry-600 text-white',
  }[tone]
  return <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${styles}`}>{children}</span>
}
