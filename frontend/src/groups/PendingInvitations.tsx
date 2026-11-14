import {useQueryClient} from '@tanstack/react-query'
import {useState} from 'react'
import {useNavigate} from 'react-router'
import {getListGroupsQueryKey} from '../api/generated/groups/groups'
import {
    getListInvitationsQueryKey,
    useAcceptInvitation,
    useDeclineInvitation,
    useListInvitations
} from '../api/generated/invitations/invitations'
import {Button} from '../components/ui'
import {de} from '../i18n/de'
import {formatEventDate} from './format'
import {InvitationOverlay} from './InvitationOverlay'

const t = de.invitation

/** Invitations by username, shown above the user's groups until answered. */
export function PendingInvitations() {
    const queryClient = useQueryClient()
    const navigate = useNavigate()
    const invitations = useListInvitations()
    const [openId, setOpenId] = useState<number | null>(null)
    const open = invitations.data?.find((invitation) => invitation.memberId === openId)

    const refresh = () => {
        void queryClient.invalidateQueries({queryKey: getListInvitationsQueryKey()})
        void queryClient.invalidateQueries({queryKey: getListGroupsQueryKey()})
    }
    const accept = useAcceptInvitation({
        mutation: {
            onSuccess: (group) => {
                refresh()
                void navigate(`/gruppen/${group.id}`)
            },
        },
    })
    const decline = useDeclineInvitation({
        mutation: {
            onSuccess: () => {
                refresh()
                setOpenId(null)
            },
        },
    })

    if (!invitations.data || invitations.data.length === 0) return null

    return (
        <section aria-labelledby="pending-invitations" className="rounded-2xl border border-amber-300 bg-amber-50 p-5">
            <h2 id="pending-invitations" className="font-semibold text-amber-900">
                {t.pendingTitle}
            </h2>
            <ul className="mt-2 divide-y divide-amber-200">
                {invitations.data.map((invitation) => (
                    <li key={invitation.memberId} className="flex flex-wrap items-center gap-3 py-3">
                        <div className="mr-auto">
                            <p className="font-medium">{invitation.title}</p>
                            <p className="text-sm text-stone-600">
                                {formatEventDate(invitation.eventDate)} · {invitation.owner.firstName} {invitation.owner.lastName}
                            </p>
                        </div>
                        <Button
                            onClick={() => {
                                accept.reset()
                                decline.reset()
                                setOpenId(invitation.memberId ?? null)
                            }}
                        >
                            {t.view}
                        </Button>
                    </li>
                ))}
            </ul>

            <InvitationOverlay
                invitation={open}
                open={open !== undefined}
                onOpenChange={(isOpen) => !isOpen && setOpenId(null)}
                onJoin={() => open?.memberId && accept.mutate({memberId: String(open.memberId)})}
                joining={accept.isPending}
                error={accept.error ?? decline.error}
                secondaryLabel={t.decline}
                onSecondary={() => open?.memberId && decline.mutate({memberId: String(open.memberId)})}
                secondaryBusy={decline.isPending}
            />
        </section>
    )
}
