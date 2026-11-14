import {useState} from 'react'
import type {AssignmentResponse} from '../api/generated/model'
import {Button, Notice} from '../components/ui'
import {de} from '../i18n/de'
import {GiftBox} from './GiftBox'

const t = de.assignment

type Props = {
    /** Fetches the assignment. Called only when the user asks to see it. */
    load: () => Promise<AssignmentResponse>
    revealLabel: string
    /** Text in front of the name, e.g. "Du beschenkst". */
    lead: string
    /** Shows the present that opens on reveal. For the participant's own result. */
    festive?: boolean
}

/**
 * A draw result behind a blur. Until the button is pressed the result is not on the page at
 * all: what is blurred is a dummy, and the real data is only requested by the click. It lives
 * in this component's state alone, so hiding it or leaving the page discards it.
 */
export function AssignmentReveal({load, revealLabel, lead, festive = false}: Props) {
    const [assignment, setAssignment] = useState<AssignmentResponse | null>(null)
    const [loading, setLoading] = useState(false)
    const [failed, setFailed] = useState(false)

    const reveal = async () => {
        setLoading(true)
        setFailed(false)
        try {
            setAssignment(await load())
        } catch {
            setFailed(true)
        } finally {
            setLoading(false)
        }
    }

    // The gift tag: a paper label with a punched hole and a stitched inner frame.
    const tag = 'relative min-w-0 flex-1 basis-56 rounded-l-md rounded-r-2xl border border-line bg-paper-000 py-5 pr-5 pl-12 shadow-card before:absolute before:top-1/2 before:left-4 before:size-3.5 before:-translate-y-1/2 before:rounded-full before:border-2 before:border-gold-500 before:bg-paper-100 after:pointer-events-none after:absolute after:inset-1.5 after:rounded-l-sm after:rounded-r-xl after:border after:border-dashed after:border-line'

    if (assignment === null) {
        return (
            <div className="space-y-4">
                <div className="flex flex-wrap items-center gap-4">
                    {festive && <GiftBox open={false}/>}
                    <div className={`${tag} overflow-hidden`}>
                        <div aria-hidden="true" className="pointer-events-none blur-md select-none"
                             data-testid="assignment-dummy">
                            <p className="eyebrow">{lead}</p>
                            <p className="font-display text-3xl font-semibold text-wein-700">Xxxxxxx Xxxxxxxxxx</p>
                            <p className="mt-1 text-sm text-ink-muted">Xxxxxxxxx xxxxxx · Xxxxxxx xxxx</p>
                        </div>
                        <p className="sr-only">{t.hidden}</p>
                    </div>
                </div>
                {failed && <Notice tone="error">{t.loadFailed}</Notice>}
                <Button onClick={() => void reveal()} busy={loading}>
                    {revealLabel}
                </Button>
            </div>
        )
    }

    return (
        <div className="space-y-4" data-testid="assignment-revealed">
            <div className="flex flex-wrap items-center gap-4">
                {festive && <GiftBox open/>}
                <div className={`${tag} animate-tag`}>
                    <p className="eyebrow">{lead}</p>
                    <p className="font-display text-3xl font-semibold break-words text-wein-700">
                        {assignment.receiverFirstName} {assignment.receiverLastName}
                    </p>
                    <p className="mt-1 text-sm break-all text-ink-muted">{assignment.receiverUsername}</p>
                </div>
            </div>

            <div className="rounded-2xl border border-line bg-paper-100 p-4">
                <h4 className="eyebrow font-sans">{t.wishes}</h4>
                {assignment.wishes.length === 0 ? (
                    <p className="mt-1 text-sm text-ink-muted">{t.noWishes}</p>
                ) : (
                    <ul className="mt-1 divide-y divide-line">
                        {assignment.wishes.map((wish) => (
                            <li key={wish.id} className="py-2">
                                <p className="font-semibold">{wish.title}</p>
                                {wish.description &&
                                    <p className="mt-1 whitespace-pre-line text-sm text-ink">{wish.description}</p>}
                                {wish.url && (
                                    <a href={wish.url} target="_blank" rel="noopener noreferrer"
                                       className="mt-1 inline-block text-sm font-semibold break-all text-tanne-700 underline">
                                        {t.link}
                                    </a>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <Button variant="ghost" onClick={() => setAssignment(null)}>
                {t.hide}
            </Button>
        </div>
    )
}
