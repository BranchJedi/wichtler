import sealUrl from '../assets/illustrations/siegel.svg'
import {Divider, Eyebrow} from '../components/ui'
import {de} from '../i18n/de'
import {Countdown} from './Countdown'

const t = de.welcome

function SectionTitle({id, eyebrow, title}: { id: string; eyebrow: string; title: string }) {
    return (
        <div>
            <Eyebrow>{eyebrow}</Eyebrow>
            <h2 id={id} className="mt-1 text-3xl font-semibold text-balance">{title}</h2>
        </div>
    )
}

/** Everything the login page tells a visitor below the form. */
export function WelcomeInfo() {
    return (
        <div className="mt-12 space-y-12">
            <Countdown/>

            <section aria-labelledby="steps-title" className="space-y-6">
                <SectionTitle id="steps-title" eyebrow={t.steps.eyebrow} title={t.steps.title}/>
                <ol className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {t.steps.items.map((step, index) => (
                        <li key={step.title} className="border-t border-line pt-3">
                            <span aria-hidden="true"
                                  className="font-display text-4xl font-semibold text-gold-700">{index + 1}</span>
                            <h3 className="mt-1 text-lg font-semibold">{step.title}</h3>
                            <p className="mt-1 text-sm text-ink-muted">{step.text}</p>
                        </li>
                    ))}
                </ol>
            </section>

            <Divider/>

            <section aria-labelledby="promises-title" className="space-y-6">
                <SectionTitle id="promises-title" eyebrow={t.promises.eyebrow} title={t.promises.title}/>
                <ul className="grid gap-x-10 gap-y-6 sm:grid-cols-2">
                    {t.promises.items.map((promise) => (
                        <li key={promise.title} className="flex gap-4">
                            <img src={sealUrl} alt="" className="mt-1 size-9 shrink-0"/>
                            <div className="min-w-0">
                                <h3 className="text-lg font-semibold">{promise.title}</h3>
                                <p className="mt-1 text-ink-muted">{promise.text}</p>
                            </div>
                        </li>
                    ))}
                </ul>
            </section>

            <Divider/>

            <section aria-labelledby="faq-title" className="grid gap-x-12 gap-y-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <SectionTitle id="faq-title" eyebrow={t.faq.eyebrow} title={t.faq.title}/>
                <div className="divide-y divide-line border-y border-line">
                    {t.faq.items.map((item) => (
                        <details key={item.q} className="group">
                            <summary
                                className="flex min-h-12 cursor-pointer list-none items-center gap-4 py-3 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tanne-700 [&::-webkit-details-marker]:hidden">
                                <span className="mr-auto">{item.q}</span>
                                <span aria-hidden="true"
                                      className="font-display text-2xl leading-none text-wein-700 transition-transform group-open:rotate-45">+</span>
                            </summary>
                            <p className="max-w-prose pb-4 text-ink-muted">{item.a}</p>
                        </details>
                    ))}
                </div>
            </section>
        </div>
    )
}
