import {zodResolver} from '@hookform/resolvers/zod'
import {keepPreviousData, useQueryClient} from '@tanstack/react-query'
import {useDeferredValue, useState} from 'react'
import {useForm} from 'react-hook-form'
import {z} from 'zod'
import {applyServerErrors, errorMessage} from '../api/errors'
import {getListUsersQueryKey, useListUsers, useResetUserPassword, useUpdateUser} from '../api/generated/admin/admin'
import type {AdminUserResponse, TemporaryPasswordResponse} from '../api/generated/model'
import {ApiError} from '../api/http'
import {nameSchema, usernameSchema} from '../auth/schemas'
import {useSession} from '../auth/session'
import {ConfirmDialog} from '../components/ConfirmDialog'
import {Overlay} from '../components/Overlay'
import {Badge, Button, Card, Field, Notice} from '../components/ui'
import {de} from '../i18n/de'

const t = de.users
const fullName = (user: AdminUserResponse) => `${user.firstName} ${user.lastName}`

/**
 * The Wichtler admin's help desk. There are no e-mails, so a forgotten username or password
 * can only be recovered here: look the person up, tell them their username, or hand them a
 * temporary password.
 */
export function UsersPage() {
    const {user: me} = useSession()
    const queryClient = useQueryClient()
    const [search, setSearch] = useState('')
    const query = useDeferredValue(search.trim())
    const users = useListUsers({q: query}, {query: {placeholderData: keepPreviousData}})

    const [editing, setEditing] = useState<AdminUserResponse | null>(null)
    const [resetting, setResetting] = useState<AdminUserResponse | null>(null)
    const [temporary, setTemporary] = useState<TemporaryPasswordResponse | null>(null)

    const refresh = () => void queryClient.invalidateQueries({queryKey: getListUsersQueryKey()})
    const reset = useResetUserPassword({
        mutation: {
            onSuccess: (result) => {
                refresh()
                setResetting(null)
                setTemporary(result)
            },
        },
    })

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold">{t.title}</h1>
                <p className="mt-1 text-sm text-stone-500">{t.intro}</p>
            </div>

            <Card>
                <Field
                    label={t.search}
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    autoComplete="off"
                    autoCapitalize="none"
                    spellCheck={false}
                />

                {users.isPending && <p role="status" className="mt-4 text-stone-500">{de.app.loading}</p>}
                {users.isError && <div className="mt-4"><Notice tone="error">{t.loadFailed}</Notice></div>}
                {users.data?.length === 0 && <p className="mt-4 text-stone-700">{t.empty}</p>}

                <ul className="mt-2 divide-y divide-stone-200">
                    {users.data?.map((user) => (
                        <li key={user.id} className="flex flex-wrap items-center gap-x-3 gap-y-2 py-3">
                            <div className="mr-auto min-w-0">
                                <p className="font-medium">{fullName(user)}</p>
                                <p className="text-sm text-stone-500">{user.username}</p>
                            </div>
                            {user.isAdmin && <Badge tone="done">{t.adminBadge}</Badge>}
                            {user.mustChangePassword && <Badge tone="ready">{t.temporaryBadge}</Badge>}
                            {user.id === me?.id ? (
                                <p className="w-full text-sm text-stone-500">{t.self}</p>
                            ) : (
                                <div className="flex w-full flex-wrap gap-2 sm:w-auto">
                                    <Button variant="ghost" onClick={() => setEditing(user)}>
                                        {t.edit}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        onClick={() => {
                                            reset.reset()
                                            setResetting(user)
                                        }}
                                    >
                                        {t.reset}
                                    </Button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
                {users.data?.length === 100 && <p className="mt-3 text-sm text-stone-500">{t.truncated}</p>}
            </Card>

            <p>
                <a href="/api/doc" target="_blank" rel="noopener noreferrer"
                   className="inline-flex min-h-11 items-center text-sm font-medium text-pine-700 underline">
                    {t.apiDocs}
                </a>
            </p>

            <Overlay open={editing !== null} onOpenChange={(open) => !open && setEditing(null)} title={t.editTitle}
                     description={t.editDescription}>
                {editing && (
                    <EditUserForm
                        user={editing}
                        onCancel={() => setEditing(null)}
                        onSaved={() => {
                            refresh()
                            setEditing(null)
                        }}
                    />
                )}
            </Overlay>

            <ConfirmDialog
                open={resetting !== null}
                onOpenChange={(open) => !open && setResetting(null)}
                title={t.resetTitle}
                description={resetting ? t.resetDescription(fullName(resetting)) : ''}
                confirmLabel={t.resetConfirm}
                cancelLabel={t.cancel}
                danger
                busy={reset.isPending}
                error={reset.error}
                onConfirm={() => resetting && reset.mutate({id: String(resetting.id)})}
            />

            <Overlay
                open={temporary !== null}
                onOpenChange={(open) => !open && setTemporary(null)}
                title={t.temporaryTitle}
                description={temporary ? t.temporaryDescription(fullName(temporary.user), temporary.user.username) : ''}
            >
                {/* Lives in this component's state only and is gone as soon as the overlay closes. */}
                {temporary &&
                    <TemporaryPassword password={temporary.temporaryPassword} onDone={() => setTemporary(null)}/>}
            </Overlay>
        </div>
    )
}

const editSchema = z.object({firstName: nameSchema, lastName: nameSchema, username: usernameSchema})
type EditValues = z.infer<typeof editSchema>

function EditUserForm({user, onCancel, onSaved}: {
    user: AdminUserResponse;
    onCancel: () => void;
    onSaved: () => void
}) {
    const {register, handleSubmit, setError, formState} = useForm<EditValues>({
        resolver: zodResolver(editSchema),
        defaultValues: {firstName: user.firstName, lastName: user.lastName, username: user.username},
    })
    const update = useUpdateUser({
        mutation: {
            onSuccess: onSaved,
            onError: (error) => {
                if (error instanceof ApiError && error.status !== 422) {
                    setError('root', {type: 'server', message: errorMessage(error)})
                    return
                }
                applyServerErrors(error, setError, ['firstName', 'lastName', 'username'])
            },
        },
    })
    const {errors} = formState

    return (
        <form className="space-y-4" noValidate
              onSubmit={handleSubmit((data) => update.mutate({id: String(user.id), data}))}>
            {errors.root && <Notice tone="error">{errors.root.message}</Notice>}
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label={de.fields.firstName} autoComplete="off"
                       error={errors.firstName?.message} {...register('firstName')} />
                <Field label={de.fields.lastName} autoComplete="off"
                       error={errors.lastName?.message} {...register('lastName')} />
            </div>
            <Field
                label={de.fields.username}
                autoComplete="off"
                autoCapitalize="none"
                autoCorrect="off"
                spellCheck={false}
                hint={de.hints.username}
                error={errors.username?.message}
                {...register('username')}
            />
            <div className="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                <Button type="button" variant="ghost" onClick={onCancel}>
                    {t.cancel}
                </Button>
                <Button type="submit" busy={update.isPending}>
                    {t.save}
                </Button>
            </div>
        </form>
    )
}

function TemporaryPassword({password, onDone}: { password: string; onDone: () => void }) {
    const [copied, setCopied] = useState(false)
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(password)
            setCopied(true)
        } catch {
            // No clipboard access: the field stays selectable.
            setCopied(false)
        }
    }

    return (
        <div className="space-y-4">
            <Field
                label={t.temporaryLabel}
                value={password}
                readOnly
                autoComplete="off"
                spellCheck={false}
                onFocus={(event) => event.target.select()}
            />
            <Notice tone="info">{t.temporaryOnce}</Notice>
            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button type="button" variant="ghost" onClick={() => void copy()}>
                    {copied ? t.copied : t.copy}
                </Button>
                <Button type="button" onClick={onDone}>
                    {t.done}
                </Button>
            </div>
        </div>
    )
}
