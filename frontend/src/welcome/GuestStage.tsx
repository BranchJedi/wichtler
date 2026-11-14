import type {ReactNode} from 'react'
import giftUrl from '../assets/illustrations/geschenk.svg'
import parcelUrl from '../assets/illustrations/paeckchen.svg'
import gnomeUrl from '../assets/illustrations/wichtel.svg'
import {Eyebrow} from '../components/ui'

type Props = {
    eyebrow: string
    title: string
    lead: string
    /** Extra content under the lead, e.g. a short list. */
    extra?: ReactNode
    /** The form card. */
    children: ReactNode
}

/**
 * Top of the login and registration pages: what the app is about on the left, the form on the
 * right. On phones the form follows the title directly and the drawing comes last.
 */
export function GuestStage({eyebrow, title, lead, extra, children}: Props) {
    return (
        <div className="grid items-start gap-x-12 gap-y-8 lg:grid-cols-[minmax(0,1fr)_26rem]">
            <div className="lg:pt-6">
                <Eyebrow>{eyebrow}</Eyebrow>
                <h1 className="mt-2 text-4xl leading-[1.08] font-semibold tracking-tight text-balance sm:text-[44px]">
                    {title}
                </h1>
                <p className="mt-4 max-w-prose text-lg text-ink-muted">{lead}</p>
                {extra}
            </div>
            <div className="lg:row-span-2">{children}</div>
            <div aria-hidden="true" className="flex items-end justify-center gap-2 lg:justify-start">
                <img src={parcelUrl} alt="" className="w-20 sm:w-24"/>
                <img src={gnomeUrl} alt="" className="w-32 sm:w-40"/>
                <img src={giftUrl} alt="" className="w-24 sm:w-32"/>
            </div>
        </div>
    )
}
