import {zodResolver} from '@hookform/resolvers/zod'
import {useForm} from 'react-hook-form'
import {z} from 'zod'
import {applyServerErrors} from '../api/errors'
import type {UserResponse} from '../api/generated/model'
import {useChangeMyPassword, useUpdateMe} from '../api/generated/me/me'
import {nameSchema, newPasswordSchema, usernameSchema} from '../auth/schemas'
import {useSession, useSetSessionUser} from '../auth/session'
import {Button, Card, Field, Notice} from '../components/ui'
import {de} from '../i18n/de'

const detailsSchema = z.object({firstName: nameSchema, lastName: nameSchema, username: usernameSchema})
type Details = z.infer<typeof detailsSchema>

function DetailsForm({user}: { user: UserResponse }) {
    const setSessionUser = useSetSessionUser()
    const {register, handleSubmit, setError, reset, formState} = useForm<Details>({
        resolver: zodResolver(detailsSchema),
        defaultValues: {firstName: user.firstName, lastName: user.lastName, username: user.username},
    })
    const update = useUpdateMe({
        mutation: {
            onSuccess: (updated) => {
                setSessionUser(updated)
                reset({firstName: updated.firstName, lastName: updated.lastName, username: updated.username})
            },
            onError: (error) => applyServerErrors(error, setError, ['firstName', 'lastName', 'username']),
        },
    })
    const {errors} = formState

    return (
        <form className="space-y-4" noValidate onSubmit={handleSubmit((data) => update.mutate({data}))}>
            {errors.root && <Notice tone="error">{errors.root.message}</Notice>}
            {update.isSuccess && !formState.isDirty && <Notice tone="success">{de.profile.saved}</Notice>}
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label={de.fields.firstName} autoComplete="given-name"
                       error={errors.firstName?.message} {...register('firstName')} />
                <Field label={de.fields.lastName} autoComplete="family-name"
                       error={errors.lastName?.message} {...register('lastName')} />
            </div>
            <Field
                label={de.fields.username}
                autoComplete="username"
                autoCapitalize="none"
                autoCorrect="off"
                spellCheck={false}
                hint={de.hints.username}
                error={errors.username?.message}
                {...register('username')}
            />
            <Button type="submit" busy={update.isPending}>
                {de.profile.save}
            </Button>
        </form>
    )
}

const passwordSchema = z
    .object({
        currentPassword: z.string().min(1, de.validation.required),
        newPassword: newPasswordSchema,
        newPasswordRepeat: z.string(),
    })
    .refine((values) => values.newPassword === values.newPasswordRepeat, {
        path: ['newPasswordRepeat'],
        message: de.validation.passwordMismatch,
    })
type PasswordValues = z.infer<typeof passwordSchema>

function PasswordForm({username}: { username: string }) {
    const setSessionUser = useSetSessionUser()
    const {register, handleSubmit, setError, reset, formState} = useForm<PasswordValues>({
        resolver: zodResolver(passwordSchema),
    })
    const change = useChangeMyPassword({
        mutation: {
            onSuccess: (updated) => {
                setSessionUser(updated)
                reset()
            },
            onError: (error) => applyServerErrors(error, setError, ['currentPassword', 'newPassword']),
        },
    })
    const {errors} = formState

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={handleSubmit(({currentPassword, newPassword}) => change.mutate({
                data: {
                    currentPassword,
                    newPassword
                }
            }))}
        >
            {errors.root && <Notice tone="error">{errors.root.message}</Notice>}
            {change.isSuccess && !formState.isDirty && <Notice tone="success">{de.profile.passwordChanged}</Notice>}
            {/* Lets password managers attach the new password to the right account. */}
            <input type="text" name="username" autoComplete="username" value={username} readOnly hidden/>
            <Field
                label={de.fields.currentPassword}
                type="password"
                autoComplete="current-password"
                error={errors.currentPassword?.message}
                {...register('currentPassword')}
            />
            <Field
                label={de.fields.newPassword}
                type="password"
                autoComplete="new-password"
                hint={de.hints.password}
                error={errors.newPassword?.message}
                {...register('newPassword')}
            />
            <Field
                label={de.fields.newPasswordRepeat}
                type="password"
                autoComplete="new-password"
                error={errors.newPasswordRepeat?.message}
                {...register('newPasswordRepeat')}
            />
            <Button type="submit" busy={change.isPending}>
                {de.profile.changePassword}
            </Button>
        </form>
    )
}

export function ProfilePage() {
    const {user} = useSession()
    if (!user) return null

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-bold">{de.profile.title}</h1>
                {user.isAdmin && (
                    <span
                        className="rounded-full bg-pine-100 px-3 py-1 text-xs font-semibold text-pine-900">{de.profile.adminBadge}</span>
                )}
            </div>
            {user.mustChangePassword && <Notice tone="info">{de.profile.mustChangePassword}</Notice>}
            <Card title={de.profile.detailsTitle}>
                <DetailsForm user={user}/>
            </Card>
            <Card title={de.profile.passwordTitle}>
                <PasswordForm username={user.username}/>
            </Card>
        </div>
    )
}
