const monthFormat = new Intl.DateTimeFormat('de-CH', {month: 'short'})
const weekdayFormat = new Intl.DateTimeFormat('de-CH', {weekday: 'short'})

/**
 * The date as a leaf of a tear-off calendar. Decorative: the date is always written out next
 * to it as well.
 */
export function CalendarLeaf({isoDate, className = ''}: { isoDate: string; className?: string }) {
    const [year, month, day] = isoDate.split('-').map(Number)
    const date = new Date(year, month - 1, day)
    return (
        <div aria-hidden="true"
             className={`w-20 shrink-0 -rotate-3 overflow-hidden rounded-xl border border-line bg-paper-000 text-center text-ink shadow-card ${className}`}>
            <div className="bg-wein-700 py-1 text-xs font-bold tracking-[0.14em] text-on-dark uppercase">
                {monthFormat.format(date).replace('.', '')}
            </div>
            <div className="pt-1.5 font-display text-4xl leading-none font-semibold">{day}</div>
            <div className="pb-1.5 text-xs font-semibold text-ink-muted">{weekdayFormat.format(date)}</div>
        </div>
    )
}
