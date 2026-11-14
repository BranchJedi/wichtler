import type {
    ButtonHTMLAttributes,
    InputHTMLAttributes,
    ReactNode,
    SelectHTMLAttributes,
    TextareaHTMLAttributes
} from 'react'
import {useId} from 'react'
import starUrl from '../assets/illustrations/stern.svg'

export function Card({title, framed = false, children}: { title?: string; framed?: boolean; children: ReactNode }) {
    return (
        <section
            className={`rounded-2xl border border-line bg-paper-000 shadow-card ${framed ? 'framed p-8' : 'p-6'}`}>
            {title && <h2 className="mb-4 text-xl font-semibold">{title}</h2>}
            {children}
        </section>
    )
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: 'primary' | 'ghost' | 'danger';
    busy?: boolean
}

export function Button({variant = 'primary', busy = false, className = '', children, disabled, ...props}: ButtonProps) {
    const styles = {
        primary: 'bg-tanne-700 text-on-dark hover:bg-tanne-800 disabled:bg-paper-200 disabled:text-ink-muted',
        danger: 'bg-wein-700 text-on-dark hover:bg-wein-800 disabled:bg-paper-200 disabled:text-ink-muted',
        ghost: 'border border-line-strong bg-paper-000 text-ink hover:bg-paper-200 disabled:text-ink-muted',
    }[variant]
    return (
        <button
            className={`inline-flex min-h-11 items-center justify-center rounded-[10px] px-4 text-[15px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tanne-700 disabled:cursor-not-allowed ${styles} ${className}`}
            disabled={disabled || busy}
            aria-busy={busy}
            {...props}
        >
            {children}
        </button>
    )
}

type FieldProps = InputHTMLAttributes<HTMLInputElement> & { label: string; error?: string; hint?: string }

export function Field({label, error, hint, ...props}: FieldProps) {
    const id = useId()
    const describedBy = [error ? `${id}-error` : null, hint ? `${id}-hint` : null].filter(Boolean).join(' ')
    return (
        <div>
            <label htmlFor={id} className="mb-1 block text-sm font-semibold text-ink">
                {label}
            </label>
            <input
                id={id}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy || undefined}
                className={`block min-h-11 w-full rounded-[10px] border bg-paper-000 px-3 text-base outline-none focus:ring-2 focus:ring-tanne-700/30 disabled:bg-paper-200 disabled:text-ink-muted ${error ? 'border-wein-700' : 'border-line-strong focus:border-tanne-700'}`}
                {...props}
            />
            {hint && !error && (
                <p id={`${id}-hint`} className="mt-1 text-sm text-ink-muted">
                    {hint}
                </p>
            )}
            {error && (
                <p id={`${id}-error`} className="mt-1 text-sm text-wein-700">
                    {error}
                </p>
            )}
        </div>
    )
}

export function Notice({tone, children}: { tone: 'error' | 'success' | 'info'; children: ReactNode }) {
    const styles = {
        error: 'border-wein-700 bg-wein-100 text-wein-700',
        success: 'border-tanne-700 bg-tanne-50 text-tanne-700',
        info: 'border-gold-700 bg-gold-100 text-ink',
    }[tone]
    return (
        <p role={tone === 'error' ? 'alert' : 'status'} className={`rounded-md border px-3 py-2 text-sm ${styles}`}>
            {children}
        </p>
    )
}

const control = 'block w-full rounded-[10px] border bg-paper-000 px-3 text-base outline-none focus:ring-2 focus:ring-tanne-700/30 disabled:bg-paper-200 disabled:text-ink-muted'

type TextAreaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & { label: string; error?: string }

export function TextArea({label, error, ...props}: TextAreaProps) {
    const id = useId()
    return (
        <div>
            <label htmlFor={id} className="mb-1 block text-sm font-semibold text-ink">
                {label}
            </label>
            <textarea
                id={id}
                rows={3}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${id}-error` : undefined}
                className={`${control} py-2 ${error ? 'border-wein-700' : 'border-line-strong focus:border-tanne-700'}`}
                {...props}
            />
            {error && (
                <p id={`${id}-error`} className="mt-1 text-sm text-wein-700">
                    {error}
                </p>
            )}
        </div>
    )
}

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & { label: string; error?: string }

export function Select({label, error, children, ...props}: SelectProps) {
    const id = useId()
    return (
        <div>
            <label htmlFor={id} className="mb-1 block text-sm font-semibold text-ink">
                {label}
            </label>
            <select
                id={id}
                aria-invalid={error ? true : undefined}
                className={`${control} min-h-11 ${error ? 'border-wein-700' : 'border-line-strong focus:border-tanne-700'}`}
                {...props}
            >
                {children}
            </select>
            {error && <p className="mt-1 text-sm text-wein-700">{error}</p>}
        </div>
    )
}

export function Badge({tone, children}: { tone: 'neutral' | 'ready' | 'done' | 'admin'; children: ReactNode }) {
    const styles = {
        neutral: 'bg-paper-200 text-ink',
        ready: 'bg-gold-100 text-gold-700',
        done: 'bg-tanne-100 text-tanne-700',
        admin: 'bg-wein-700 text-on-dark',
    }[tone]
    return <span
        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold tracking-wide ${styles}`}>{children}</span>
}

/** Uppercase line above a title. */
export function Eyebrow({children}: { children: ReactNode }) {
    return <p className="eyebrow">{children}</p>
}

/** Ornamental rule between sections: a hairline with the star in its middle. */
export function Divider() {
    return (
        <div aria-hidden="true" className="flex items-center gap-4">
            <span className="h-px flex-1 bg-line"/>
            <img src={starUrl} alt="" className="size-5"/>
            <span className="h-px flex-1 bg-line"/>
        </div>
    )
}
