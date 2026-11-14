import {useState} from 'react'
import type {AssignmentResponse} from '../api/generated/model'
import {Button, Notice} from '../components/ui'
import {de} from '../i18n/de'

const t = de.assignment

type Props = {
    /** Fetches the assignment. Called only when the user asks to see it. */
    load: () => Promise<AssignmentResponse>
    revealLabel: string
    /** Text in front of the name, e.g. "Du beschenkst". */
    lead: string
}

/**
 * A draw result behind a blur. Until the button is pressed the result is not on the page at
 * all: what is blurred is a dummy, and the real data is only requested by the click. It lives
 * in this component's state alone, so hiding it or leaving the page discards it.
 */
export function AssignmentReveal({load, revealLabel, lead}: Props) {
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

    if (assignment === null) {
        return (
            <div className="space-y-3">
                <div className="relative overflow-hidden rounded-xl border border-stone-200 bg-stone-50 p-4">
                    <div aria-hidden="true" className="pointer-events-none blur-md select-none"
                         data-testid="assignment-dummy">
                        <p className="text-sm text-stone-500">{lead}</p>
                        <p className="text-xl font-bold">Xxxxxxx Xxxxxxxxxx</p>
                        <p className="mt-2 text-sm text-stone-700">Xxxxxxxxx xxxxxx · Xxxxxxx xxxx · Xxxxxxxxxxxx</p>
                    </div>
                    <p className="sr-only">{t.hidden}</p>
                </div>
                {failed && <Notice tone="error">{t.loadFailed}</Notice>}
                <Button onClick={() => void reveal()} busy={loading}>
                    {revealLabel}
                </Button>
            </div>
        )
    }

    return (
        <div className="space-y-3">
            <div className="rounded-xl border border-pine-600/40 bg-pine-50 p-4" data-testid="assignment-revealed">
                <p className="text-sm text-stone-600">{lead}</p>
                <p className="text-xl font-bold">
                    {assignment.receiverFirstName} {assignment.receiverLastName}
                </p>
                <p className="text-sm text-stone-500">{assignment.receiverUsername}</p>

                <h4 className="mt-4 text-sm font-semibold text-stone-700">{t.wishes}</h4>
                {assignment.wishes.length === 0 ? (
                    <p className="text-sm text-stone-600">{t.noWishes}</p>
                ) : (
                    <ul className="mt-1 divide-y divide-pine-600/20">
                        {assignment.wishes.map((wish) => (
                            <li key={wish.id} className="py-2">
                                <p className="font-medium">{wish.title}</p>
                                {wish.description &&
                                    <p className="mt-1 whitespace-pre-line text-sm text-stone-700">{wish.description}</p>}
                                {wish.url && (
                                    <a href={wish.url} target="_blank" rel="noopener noreferrer"
                                       className="mt-1 inline-block text-sm font-medium break-all text-pine-700 underline">
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
