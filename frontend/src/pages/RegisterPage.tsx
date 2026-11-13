import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { Link, useLocation } from 'react-router'
import { z } from 'zod'
import { applyServerErrors } from '../api/errors'
import { useRegister } from '../api/generated/auth/auth'
import { nameSchema, newPasswordSchema, usernameSchema } from '../auth/schemas'
import { isInvited } from '../auth/invite'
import { useSetSessionUser } from '../auth/session'
import { Button, Card, Field, Notice } from '../components/ui'
import { de } from '../i18n/de'

const schema = z
  .object({
    firstName: nameSchema,
    lastName: nameSchema,
    username: usernameSchema,
    password: newPasswordSchema,
    passwordRepeat: z.string(),
  })
  .refine((values) => values.password === values.passwordRepeat, {
    path: ['passwordRepeat'],
    message: de.validation.passwordMismatch,
  })
type Values = z.infer<typeof schema>

export function RegisterPage() {
  const setSessionUser = useSetSessionUser()
  const location = useLocation()
  const { register, handleSubmit, setError, formState } = useForm<Values>({ resolver: zodResolver(schema) })
  const registration = useRegister({
    mutation: {
      onSuccess: setSessionUser,
      onError: (error) => applyServerErrors(error, setError, ['firstName', 'lastName', 'username', 'password']),
    },
  })
  const { errors } = formState

  return (
    <Card title={de.register.title}>
      <form
        className="space-y-4"
        noValidate
        onSubmit={handleSubmit(({ firstName, lastName, username, password }) =>
          registration.mutate({ data: { firstName, lastName, username, password } }),
        )}
      >
        {isInvited(location.state) && <Notice tone="info">{de.invitation.loginHint}</Notice>}
        {errors.root && <Notice tone="error">{errors.root.message}</Notice>}
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={de.fields.firstName} autoComplete="given-name" error={errors.firstName?.message} {...register('firstName')} />
          <Field label={de.fields.lastName} autoComplete="family-name" error={errors.lastName?.message} {...register('lastName')} />
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
        <Field
          label={de.fields.password}
          type="password"
          autoComplete="new-password"
          hint={de.hints.password}
          error={errors.password?.message}
          {...register('password')}
        />
        <Field
          label={de.fields.passwordRepeat}
          type="password"
          autoComplete="new-password"
          error={errors.passwordRepeat?.message}
          {...register('passwordRepeat')}
        />
        <Button type="submit" className="w-full" busy={registration.isPending}>
          {de.register.submit}
        </Button>
      </form>
      <p className="mt-6 text-center text-sm text-stone-600">
        {de.register.hasAccount}{' '}
        <Link to="/login" state={location.state} className="font-medium text-pine-700 underline">
          {de.register.toLogin}
        </Link>
      </p>
    </Card>
  )
}
