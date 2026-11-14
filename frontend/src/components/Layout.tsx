import {useQueryClient} from '@tanstack/react-query'
import {Link, NavLink, Outlet, useNavigate} from 'react-router'
import {useLogout} from '../api/generated/auth/auth'
import {getGetMeQueryKey} from '../api/generated/me/me'
import {useSession} from '../auth/session'
import starUrl from '../assets/illustrations/stern.svg'
import twigUrl from '../assets/illustrations/tannenzweig.svg'
import {de} from '../i18n/de'

const navLink = ({isActive}: { isActive: boolean }) =>
    `inline-flex min-h-11 items-center rounded-[10px] px-3 text-[15px] font-semibold ${isActive ? 'bg-tanne-100 text-tanne-700' : 'text-ink hover:bg-paper-200'}`

/** Frame of every signed-in page. */
export function AppLayout() {
    const queryClient = useQueryClient()
    const navigate = useNavigate()
    const {user} = useSession()
    const logout = useLogout({
        mutation: {
            onSettled: () => {
                // Drop everything that belonged to the previous user before the next one signs in.
                queryClient.clear()
                queryClient.setQueryData(getGetMeQueryKey(), null)
                void queryClient.invalidateQueries({queryKey: getGetMeQueryKey()})
                void navigate('/login')
            },
        },
    })

    return (
        <div className="min-h-dvh">
            <header className="border-t-4 border-b border-t-wein-700 border-b-line bg-paper-000">
                <div className="mx-auto flex max-w-3xl flex-wrap items-center gap-x-2 gap-y-1 px-4 py-2">
                    <Wordmark className="mr-auto"/>
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
                            className="inline-flex min-h-11 items-center rounded-[10px] px-3 text-[15px] font-semibold text-ink hover:bg-paper-200"
                            onClick={() => logout.mutate()}
                            disabled={logout.isPending}
                        >
                            {de.nav.logout}
                        </button>
                    </nav>
                </div>
            </header>
            <main className="mx-auto max-w-3xl px-4 py-8">
                <Outlet/>
            </main>
        </div>
    )
}

/** Frame of the login and registration pages. */
export function GuestLayout() {
    return (
        <div className="flex min-h-dvh flex-col border-t-4 border-t-wein-700">
            <header className="mx-auto flex w-full max-w-5xl items-center px-4 py-4 sm:px-6">
                <Wordmark/>
            </header>
            <main className="mx-auto w-full max-w-5xl flex-1 px-4 pt-4 pb-16 sm:px-6 sm:pt-8">
                <Outlet/>
            </main>
            <footer className="bg-tanne-900 text-on-dark">
                <div className="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-6 sm:px-6">
                    <img src={twigUrl} alt="" className="w-28"/>
                    <p className="text-sm">{de.welcome.footer}</p>
                </div>
            </footer>
        </div>
    )
}

/** The name of the app with its star, linking home. */
function Wordmark({className = ''}: { className?: string }) {
    return (
        <Link to="/"
              className={`inline-flex min-h-11 items-center gap-2 font-display text-2xl font-semibold text-wein-700 ${className}`}>
            <img src={starUrl} alt="" className="size-6"/>
            {de.app.name}
        </Link>
    )
}
