import {zodResolver} from '@hookform/resolvers/zod'
import {useState} from 'react'
import {useForm} from 'react-hook-form'
import {Link, useLocation} from 'react-router'
import {z} from 'zod'
import {useLogin} from '../api/generated/auth/auth'
import {isInvited} from '../auth/invite'
import {useSetSessionUser} from '../auth/session'
import {Button, Card, Field, Notice} from '../components/ui'
import {de} from '../i18n/de'
import {GuestStage} from '../welcome/GuestStage'
import {WelcomeInfo} from '../welcome/WelcomeInfo'

const schema = z.object({
    username: z.string().trim().min(1, de.validation.required),
    password: z.string().min(1, de.validation.required),
})
type Values = z.infer<typeof schema>

export function LoginPage() {
    const setSessionUser = useSetSessionUser()
    const location = useLocation()
    const [year] = useState(() => new Date().getFullYear())
    const login = useLogin({mutation: {onSuccess: setSessionUser}})
    const {register, handleSubmit, formState} = useForm<Values>({resolver: zodResolver(schema)})

    // The firewall explains a 401 itself, in German: wrong credentials or a temporary lock.
    const failure = login.error
        ? login.error.status === 401
            ? (login.error.body?.message ?? de.login.failed)
            : de.errors.generic
        : null

    return (
        <>
            <GuestStage eyebrow={de.welcome.eyebrow(year)} title={de.welcome.title}
                        lead={de.welcome.lead}>
        <Card title={de.login.title} framed>
            <form className="space-y-4" noValidate onSubmit={handleSubmit((data) => login.mutate({data}))}>
                {isInvited(location.state) && <Notice tone="info">{de.invitation.loginHint}</Notice>}
                {failure && <Notice tone="error">{failure}</Notice>}
                <Field
                    label={de.fields.username}
                    autoComplete="username"
                    autoCapitalize="none"
                    autoCorrect="off"
                    spellCheck={false}
                    error={formState.errors.username?.message}
                    {...register('username')}
                />
                <Field
                    label={de.fields.password}
                    type="password"
                    autoComplete="current-password"
                    error={formState.errors.password?.message}
                    {...register('password')}
                />
                <Button type="submit" className="w-full" busy={login.isPending}>
                    {de.login.submit}
                </Button>
            </form>
            <p className="mt-6 text-center text-sm text-ink-muted">
                {de.login.noAccount}{' '}
                <Link to="/registrieren" state={location.state} className="font-semibold text-tanne-700 underline">
                    {de.login.toRegister}
                </Link>
            </p>
        </Card>
            </GuestStage>
            <WelcomeInfo/>
        </>
    )
}
