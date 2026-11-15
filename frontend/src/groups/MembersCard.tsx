import {useQueryClient} from '@tanstack/react-query'
import {useState} from 'react'
import {useNavigate} from 'react-router'
import {getGetDrawStatusQueryKey} from '../api/generated/draw/draw'
import {getGetGroupQueryKey, getListGroupsQueryKey} from '../api/generated/groups/groups'
import {
    getGetInviteLinkQueryKey,
    getListMembersQueryKey,
    useGetInviteLink,
    useInviteMember,
    useLeaveGroup,
    useListMembers,
    useRegenerateInviteLink,
    useRemoveMember,
    useUpdateMember,
} from '../api/generated/members/members'
import type {GroupResponse, MemberResponse} from '../api/generated/model'
import {ApiError} from '../api/http'
import {errorMessage} from '../api/errors'
import {ConfirmDialog} from '../components/ConfirmDialog'
import {Avatar, Badge, Button, Card, Field, Notice} from '../components/ui'
import {de} from '../i18n/de'

const t = de.members
const fullName = (member: MemberResponse) => `${member.firstName} ${member.lastName}`

/** Everything that changes who is in the group also changes its counters and status. */
function useRefreshGroup(groupId: string) {
    const queryClient = useQueryClient()
    return () => {
        void queryClient.invalidateQueries({queryKey: getListMembersQueryKey(groupId)})
        void queryClient.invalidateQueries({queryKey: getGetGroupQueryKey(groupId)})
        void queryClient.invalidateQueries({queryKey: getListGroupsQueryKey()})
        void queryClient.invalidateQueries({queryKey: getGetDrawStatusQueryKey(groupId)})
    }
}

export function MembersCard({group}: { group: GroupResponse }) {
    const groupId = String(group.id)
    const navigate = useNavigate()
    const refresh = useRefreshGroup(groupId)
    const members = useListMembers(groupId)
    const [removing, setRemoving] = useState<MemberResponse | null>(null)
    const [leaving, setLeaving] = useState(false)

    const update = useUpdateMember({mutation: {onSuccess: refresh}})
    const remove = useRemoveMember({
        mutation: {
            onSuccess: () => {
                refresh()
                setRemoving(null)
            },
        },
    })
    const leave = useLeaveGroup({
        mutation: {
            onSuccess: () => {
                refresh()
                void navigate('/')
            },
        },
    })

    const canEditMembers = group.canManage && !group.isDrawed
    const canLeave = group.isMember && !group.isOwner && !group.isDrawed

    return (
        <Card title={t.title} feature="members">
            {members.isPending && <p role="status" className="text-ink-muted">{de.app.loading}</p>}
            {members.isError && <Notice tone="error">{t.loadFailed}</Notice>}
            {update.isError && <div className="mb-3"><Notice tone="error">{errorMessage(update.error)}</Notice></div>}

            <ul className="divide-y divide-line">
                {members.data?.map((member) => (
                    <li key={member.id}
                        className={`flex flex-wrap items-center gap-x-3 gap-y-2 py-3 ${member.status === 'active' ? '' : 'text-ink-muted'}`}>
                        <Avatar firstName={member.firstName} lastName={member.lastName}
                                muted={member.status !== 'active'}/>
                        <div className="mr-auto min-w-0">
                            <p className="font-semibold">
                                {fullName(member)}
                                {member.isMe && <span className="font-normal text-ink-muted"> ({t.you})</span>}
                            </p>
                            <p className="text-sm text-ink-muted">{member.username}</p>
                        </div>
                        {member.isOwner && <Badge tone="neutral">{t.status.owner}</Badge>}
                        {member.status === 'invited' && <Badge tone="ready">{t.status.invited}</Badge>}
                        {member.status === 'inactive' && <Badge tone="neutral">{t.status.inactive}</Badge>}
                        {canEditMembers && !member.isOwner && (
                            <div className="flex w-full gap-2 sm:w-auto">
                                {member.status !== 'invited' && (
                                    <Button
                                        variant="ghost"
                                        busy={update.isPending && update.variables?.memberId === String(member.id)}
                                        onClick={() =>
                                            update.mutate({
                                                id: groupId,
                                                memberId: String(member.id),
                                                data: {status: member.status === 'active' ? 'inactive' : 'active'},
                                            })
                                        }
                                    >
                                        {member.status === 'active' ? t.deactivate : t.activate}
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    onClick={() => {
                                        remove.reset()
                                        setRemoving(member)
                                    }}
                                >
                                    {member.status === 'invited' ? t.withdraw : t.remove}
                                </Button>
                            </div>
                        )}
                    </li>
                ))}
            </ul>

            {group.isDrawed && group.canManage && <p className="mt-3 text-sm text-ink-muted">{t.lockedHint}</p>}
            {canEditMembers && <InviteSection groupId={groupId} onInvited={refresh}/>}
            {canLeave && (
                <div className="mt-4 border-t border-line pt-4">
                    <Button
                        variant="ghost"
                        onClick={() => {
                            leave.reset()
                            setLeaving(true)
                        }}
                    >
                        {t.leave}
                    </Button>
                </div>
            )}

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title={removing?.status === 'invited' ? t.withdrawTitle : t.removeTitle}
                description={
                    removing ? (removing.status === 'invited' ? t.withdrawDescription(fullName(removing)) : t.removeDescription(fullName(removing))) : ''
                }
                confirmLabel={removing?.status === 'invited' ? t.withdraw : t.remove}
                cancelLabel={t.cancel}
                danger
                busy={remove.isPending}
                error={remove.error}
                onConfirm={() => removing && remove.mutate({id: groupId, memberId: String(removing.id)})}
            />
            <ConfirmDialog
                open={leaving}
                onOpenChange={setLeaving}
                title={t.leaveTitle}
                description={t.leaveDescription(group.title)}
                confirmLabel={t.leaveConfirm}
                cancelLabel={t.cancel}
                danger
                busy={leave.isPending}
                error={leave.error}
                onConfirm={() => leave.mutate({id: groupId})}
            />
        </Card>
    )
}

function InviteSection({groupId, onInvited}: { groupId: string; onInvited: () => void }) {
    const ti = de.invite
    const queryClient = useQueryClient()
    const [username, setUsername] = useState('')
    const [copied, setCopied] = useState(false)
    const [regenerating, setRegenerating] = useState(false)

    const invite = useInviteMember({
        mutation: {
            onSuccess: () => {
                setUsername('')
                onInvited()
            },
        },
    })
    const link = useGetInviteLink(groupId)
    const regenerate = useRegenerateInviteLink({
        mutation: {
            onSuccess: (fresh) => {
                queryClient.setQueryData(getGetInviteLinkQueryKey(groupId), fresh)
                setRegenerating(false)
                setCopied(false)
            },
        },
    })

    const url = link.data ? `${window.location.origin}/einladung/${link.data.token}` : ''
    const usernameError =
        invite.error instanceof ApiError && invite.error.status === 422 && invite.error.body && 'violations' in invite.error.body
            ? invite.error.body.violations?.[0]?.title
            : invite.error
                ? errorMessage(invite.error)
                : undefined

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url)
            setCopied(true)
        } catch {
            // No clipboard access (e.g. plain http): the field below stays selectable.
            setCopied(false)
        }
    }

    return (
        <div className="mt-5 space-y-5 rounded-2xl border-2 border-dashed border-tanne-700/40 bg-tanne-50 p-4">
            <h3 className="text-lg font-semibold text-tanne-900">{ti.title}</h3>

            <form
                className="flex flex-col gap-2 sm:flex-row sm:items-start"
                onSubmit={(event) => {
                    event.preventDefault()
                    if (username.trim() !== '') invite.mutate({id: groupId, data: {username: username.trim()}})
                }}
            >
                <div className="grow">
                    <Field
                        label={ti.byUsername}
                        value={username}
                        onChange={(event) => {
                            setUsername(event.target.value)
                            invite.reset()
                        }}
                        autoComplete="off"
                        autoCapitalize="none"
                        autoCorrect="off"
                        spellCheck={false}
                        error={usernameError}
                    />
                </div>
                <Button type="submit" className="sm:mt-6" busy={invite.isPending} disabled={username.trim() === ''}>
                    {ti.send}
                </Button>
            </form>
            {invite.isSuccess &&
                <Notice tone="success">{ti.sent(`${invite.data.firstName} ${invite.data.lastName}`)}</Notice>}

            <div>
                <Field label={ti.linkLabel} value={url} readOnly hint={ti.linkHint}
                       onFocus={(event) => event.target.select()}/>
                <div className="mt-2 flex flex-wrap gap-2">
                    <Button type="button" variant="ghost" onClick={() => void copy()} disabled={url === ''}>
                        {copied ? ti.copied : ti.copy}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => {
                            regenerate.reset()
                            setRegenerating(true)
                        }}
                    >
                        {ti.regenerate}
                    </Button>
                </div>
            </div>

            <ConfirmDialog
                open={regenerating}
                onOpenChange={setRegenerating}
                title={ti.regenerateTitle}
                description={ti.regenerateDescription}
                confirmLabel={ti.regenerateConfirm}
                cancelLabel={t.cancel}
                busy={regenerate.isPending}
                error={regenerate.error}
                onConfirm={() => regenerate.mutate({id: groupId})}
            />
        </div>
    )
}
