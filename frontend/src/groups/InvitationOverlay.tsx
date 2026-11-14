import type {ReactNode} from 'react'
import type {InvitationResponse} from '../api/generated/model'
import {errorMessage} from '../api/errors'
import {Overlay} from '../components/Overlay'
import {Button, Notice} from '../components/ui'
import {de} from '../i18n/de'
import {formatBudget, formatEventDate} from './format'

const t = de.invitation

type Props = {
    invitation: InvitationResponse | undefined
    open: boolean
    onOpenChange: (open: boolean) => void
    onJoin: () => void
    joining: boolean
    error: unknown
    /** "Ablehnen" for an invitation addressed to the user, "Abbrechen" for a share link. */
    secondaryLabel: string
    onSecondary: () => void
    secondaryBusy?: boolean
}

/** Shows what one is about to join. Used for invitations by username and for share links. */
export function InvitationOverlay({
                                      invitation,
                                      open,
                                      onOpenChange,
                                      onJoin,
                                      joining,
                                      error,
                                      secondaryLabel,
                                      onSecondary,
                                      secondaryBusy
                                  }: Props) {
    const owner = invitation ? `${invitation.owner.firstName} ${invitation.owner.lastName}` : ''
    const rows: [string, ReactNode][] = invitation
        ? [
            [de.group.eventDate, formatEventDate(invitation.eventDate)],
            [de.group.year, invitation.year],
            [de.group.budget, formatBudget(invitation)],
            [de.group.minWishes, de.group.minWishesValue(invitation.minWishAmount)],
            [de.group.owner, `${owner} (${invitation.owner.username})`],
            [de.members.title, de.group.participants(invitation.participantCount)],
        ]
        : []

    return (
        <Overlay open={open} onOpenChange={onOpenChange} title={t.overlayTitle}
                 description={invitation ? t.overlayDescription(owner) : ''}>
            {invitation && (
                <div className="space-y-4">
                    <h3 className="text-xl font-bold">{invitation.title}</h3>
                    {invitation.description &&
                        <p className="whitespace-pre-line text-stone-700">{invitation.description}</p>}
                    <dl className="grid gap-3 sm:grid-cols-2">
                        {rows.map(([term, value]) => (
                            <div key={term}>
                                <dt className="text-sm text-stone-500">{term}</dt>
                                <dd className="font-medium">{value}</dd>
                            </div>
                        ))}
                    </dl>
                    {error ? <Notice tone="error">{errorMessage(error)}</Notice> : null}
                    <div className="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="ghost" busy={secondaryBusy} onClick={onSecondary}>
                            {secondaryLabel}
                        </Button>
                        <Button type="button" busy={joining} onClick={onJoin}>
                            {t.join}
                        </Button>
                    </div>
                </div>
            )}
        </Overlay>
    )
}
