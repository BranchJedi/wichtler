import {useEffect, useState} from 'react'
import twigUrl from '../assets/illustrations/tannenzweig.svg'
import {Eyebrow} from '../components/ui'
import {de} from '../i18n/de'
import {christmasCountdown} from './christmas'

const t = de.welcome.countdown

function Cell({value, unit}: { value: number; unit: string }) {
    return (
        <div className="grid min-w-18 justify-items-center rounded-md border border-line bg-paper-200 px-2 py-3">
            <span className="font-display text-4xl font-semibold text-wein-700 tabular-nums">
                {String(value).padStart(2, '0')}
            </span>
            <span className="text-xs font-bold tracking-[0.12em] text-ink-muted uppercase">{unit}</span>
        </div>
    )
}

/** Days, hours and minutes until Christmas Eve. Changes once a minute and is still otherwise. */
export function Countdown() {
    const [now, setNow] = useState(() => new Date())
    useEffect(() => {
        const timer = setInterval(() => setNow(new Date()), 20_000)
        return () => clearInterval(timer)
    }, [])
    const countdown = christmasCountdown(now)

    return (
        <section aria-labelledby="countdown-title"
                 className="flex flex-wrap items-center gap-x-10 gap-y-6 rounded-2xl border border-line bg-paper-000 p-6 shadow-card sm:p-8">
            <div className="min-w-0 flex-1 basis-72">
                <Eyebrow>{t.eyebrow}</Eyebrow>
                <h2 id="countdown-title" className="mt-1 text-2xl font-semibold">
                    {countdown.state === 'christmas' ? t.today : t.title(countdown.days)}
                </h2>
                <p className="mt-2 max-w-prose text-ink-muted">{countdown.state === 'christmas' ? t.todayText : t.text}</p>
            </div>
            {countdown.state === 'counting' ? (
                <div className="flex gap-2" role="timer" aria-live="off">
                    <Cell value={countdown.days} unit={t.days}/>
                    <Cell value={countdown.hours} unit={t.hours}/>
                    <Cell value={countdown.minutes} unit={t.minutes}/>
                </div>
            ) : (
                <img src={twigUrl} alt="" className="w-48"/>
            )}
        </section>
    )
}
