import { useQueryClient } from '@tanstack/react-query'
import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { useLogout } from '../api/generated/auth/auth'
import { getGetMeQueryKey } from '../api/generated/me/me'
import { useSession } from '../auth/session'
import { de } from '../i18n/de'

const navLink = ({ isActive }: { isActive: boolean }) =>
  `inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium ${isActive ? 'bg-pine-100 text-pine-900' : 'text-stone-700 hover:bg-stone-100'}`

/** Frame of every signed-in page. */
export function AppLayout() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const { user } = useSession()
  const logout = useLogout({
    mutation: {
      onSettled: () => {
        // Drop everything that belonged to the previous user before the next one signs in.
        queryClient.clear()
        queryClient.setQueryData(getGetMeQueryKey(), null)
        void queryClient.invalidateQueries({ queryKey: getGetMeQueryKey() })
        void navigate('/login')
      },
    },
  })

  return (
    <div className="min-h-dvh">
      <header className="border-b border-stone-200 bg-white">
        <div className="mx-auto flex max-w-3xl flex-wrap items-center gap-x-2 gap-y-1 px-4 py-2">
          <Link to="/" className="mr-auto text-lg font-bold text-pine-700">
            {de.app.name}
          </Link>
          <nav className="flex items-center gap-1">
            <NavLink to="/" end className={navLink}>
              {de.nav.groups}
            </NavLink>
            {user?.isAdmin && (
              <NavLink to="/benutzer" className={navLink}>
                {de.nav.users}
              </NavLink>
            )}
            <NavLink to="/profil" className={navLink}>
              {de.nav.profile}
            </NavLink>
            <button
              type="button"
              className="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-stone-700 hover:bg-stone-100"
              onClick={() => logout.mutate()}
              disabled={logout.isPending}
            >
              {de.nav.logout}
            </button>
          </nav>
        </div>
      </header>
      <main className="mx-auto max-w-3xl px-4 py-8">
        <Outlet />
      </main>
    </div>
  )
}

/** Frame of the login and registration pages. */
export function GuestLayout() {
  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-4 py-10">
      <p className="mb-6 text-center text-3xl font-bold text-pine-700">{de.app.name}</p>
      <Outlet />
    </main>
  )
}
