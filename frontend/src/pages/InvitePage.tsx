import {useQueryClient} from '@tanstack/react-query'
import {Link, Navigate, useNavigate, useParams} from 'react-router'
import {getListGroupsQueryKey} from '../api/generated/groups/groups'
import {
    getListInvitationsQueryKey,
    useAcceptInviteLink,
    usePreviewInviteLink
} from '../api/generated/invitations/invitations'
import {Notice} from '../components/ui'
import {InvitationOverlay} from '../groups/InvitationOverlay'
import {de} from '../i18n/de'
import {HomePage} from './HomePage'

const t = de.invitation

/**
 * Landing page of a share link. Only reachable signed in: guests pass through login or
 * registration first and are brought back here afterwards.
 */
export function InvitePage() {
    const {token = ''} = useParams()
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const preview = usePreviewInviteLink(token, {query: {retry: false}})
    const accept = useAcceptInviteLink({
        mutation: {
            onSuccess: (group) => {
                void queryClient.invalidateQueries({queryKey: getListGroupsQueryKey()})
                void queryClient.invalidateQueries({queryKey: getListInvitationsQueryKey()})
                void navigate(`/gruppen/${group.id}`, {replace: true})
            },
        },
    })

    if (preview.isPending) return <p role="status" className="text-stone-500">{de.app.loading}</p>
    if (preview.isError) {
        return (
            <div className="space-y-4">
                <Notice tone="error">{t.expired}</Notice>
                <Link to="/" className="inline-flex min-h-11 items-center font-medium text-pine-700 underline">
                    {de.errors.backHome}
                </Link>
            </div>
        )
    }
    if (preview.data.alreadyJoined) return <Navigate to={`/gruppen/${preview.data.groupId}`} replace/>

    const leave = () => void navigate('/', {replace: true})

    return (
        <>
            <HomePage/>
            <InvitationOverlay
                invitation={preview.data}
                open
                onOpenChange={(open) => !open && leave()}
                onJoin={() => accept.mutate({token})}
                joining={accept.isPending}
                error={accept.error}
                secondaryLabel={t.cancel}
                onSecondary={leave}
            />
        </>
    )
}
