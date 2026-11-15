import type {
    ButtonHTMLAttributes,
    InputHTMLAttributes,
    ReactNode,
    SelectHTMLAttributes,
    TextareaHTMLAttributes
} from 'react'
import {useId} from 'react'
import giftUrl from '../assets/illustrations/geschenk.svg'
import parcelUrl from '../assets/illustrations/paeckchen.svg'
import sealUrl from '../assets/illustrations/siegel.svg'
import starUrl from '../assets/illustrations/stern.svg'
import gnomeUrl from '../assets/illustrations/wichtel.svg'
import listUrl from '../assets/illustrations/wunschzettel.svg'

export type Feature = 'wishes' | 'members' | 'exclusions' | 'draw' | 'assignment' | 'admin'

/** Every feature of a group has its own colour and drawing, so it is recognised before it is read. */
const features: Record<Feature, { band: string; icon: string }> = {
    wishes: {band: 'bg-gold-100 text-ink', icon: listUrl},
    members: {band: 'bg-tanne-100 text-tanne-900', icon: gnomeUrl},
    exclusions: {band: 'bg-wein-100 text-wein-800', icon: sealUrl},
    draw: {band: 'bg-tanne-900 text-on-dark', icon: giftUrl},
    assignment: {band: 'bg-wein-700 text-on-dark', icon: parcelUrl},
    admin: {band: 'bg-paper-200 text-ink', icon: starUrl},
}

type CardProps = { title?: string; framed?: boolean; feature?: Feature; children: ReactNode }

export function Card({title, framed = false, feature, children}: CardProps) {
    if (feature) {
        const {band, icon} = features[feature]
        return (
            <section className="overflow-hidden rounded-3xl border border-line bg-paper-000 shadow-card">
                <div className={`flex items-center gap-3 px-5 py-3 sm:px-6 ${band}`}>
                    <span className="-my-1 inline-flex size-12 shrink-0 -rotate-6 items-center justify-center rounded-full bg-paper-000 shadow-card">
                        <img src={icon} alt="" className="size-9"/>
                    </span>
                    <h2 className="text-2xl font-semibold">{title}</h2>
                </div>
                <div className="p-5 sm:p-6">{children}</div>
            </section>
        )
    }
    return (
        <section
            className={`rounded-3xl border border-line bg-paper-000 shadow-card ${framed ? 'framed p-8' : 'p-6'}`}>
            {title && <h2 className="mb-4 text-xl font-semibold">{title}</h2>}
            {children}
        </section>
    )
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: 'primary' | 'ghost' | 'danger' | 'festive';
    busy?: boolean
}

export function Button({variant = 'primary', busy = false, className = '', children, disabled, ...props}: ButtonProps) {
    // Buttons are round and sit on a small ledge that gives way when pressed.
    const styles = {
        primary: 'bg-tanne-700 text-on-dark shadow-[0_3px_0_var(--color-tanne-900)] hover:bg-tanne-800',
        danger: 'bg-wein-700 text-on-dark shadow-[0_3px_0_#4d0e17] hover:bg-wein-800',
        festive: 'bg-gold-500 text-ink shadow-[0_3px_0_var(--color-gold-700)] hover:bg-[#d4ae5c]',
        ghost: 'border border-line-strong bg-paper-000 text-ink shadow-[0_3px_0_var(--color-line)] hover:bg-paper-200',
    }[variant]
    return (
        <button
            className={`inline-flex min-h-11 items-center justify-center rounded-full px-5 text-[15px] font-semibold transition-[background-color,transform,box-shadow] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tanne-700 enabled:active:translate-y-[2px] enabled:active:shadow-none disabled:cursor-not-allowed disabled:border-line disabled:bg-paper-200 disabled:text-ink-muted disabled:shadow-none ${styles} ${className}`}
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
                className={`block min-h-11 w-full rounded-xl border-[1.5px] bg-paper-000 px-3.5 text-base outline-none placeholder:text-ink-muted/70 focus:ring-4 focus:ring-gold-500/35 disabled:bg-paper-200 disabled:text-ink-muted ${error ? 'border-wein-700' : 'border-line-strong focus:border-tanne-700'}`}
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

const control = 'block w-full rounded-xl border-[1.5px] bg-paper-000 px-3.5 text-base outline-none placeholder:text-ink-muted/70 focus:ring-4 focus:ring-gold-500/35 disabled:bg-paper-200 disabled:text-ink-muted'

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

const avatarTones = ['bg-tanne-100 text-tanne-900', 'bg-wein-100 text-wein-800', 'bg-gold-100 text-gold-700']

/** Initials in a coloured disc. The colour follows the name, so a person keeps theirs. */
export function Avatar({firstName, lastName, muted = false}: { firstName: string; lastName: string; muted?: boolean }) {
    const initials = `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase()
    const tone = avatarTones[(firstName.length + lastName.length * 2) % avatarTones.length]
    return (
        <span aria-hidden="true"
              className={`inline-flex size-11 shrink-0 items-center justify-center rounded-full font-display text-lg font-semibold ${muted ? 'border border-dashed border-line-strong bg-paper-100 text-ink-muted' : tone}`}>
            {initials}
        </span>
    )
}
