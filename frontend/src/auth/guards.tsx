import {Navigate, Outlet, useLocation} from 'react-router'
import {de} from '../i18n/de'
import {useSession} from './session'

function Loading() {
    return (
        <p role="status" className="p-8 text-center text-ink-muted">
            {de.app.loading}
        </p>
    )
}

/** Signed-in area. Guests are sent to the login and return to where they wanted to go. */
export function RequireUser() {
    const {user} = useSession()
    const location = useLocation()

    if (user === undefined) return <Loading/>
    if (user === null) return <Navigate to="/login" replace state={{from: location.pathname}}/>
    // After an admin reset nothing else is reachable until a new password is set.
    if (user.mustChangePassword && location.pathname !== '/profil') return <Navigate to="/profil" replace/>
    return <Outlet/>
}

/** Pages of the Wichtler admin. Everyone else is sent to the overview; the API enforces the same. */
export function RequireAdmin() {
    const {user} = useSession()
    return user?.isAdmin ? <Outlet/> : <Navigate to="/" replace/>
}

/** Login and registration. Signed-in users are sent on to the app. */
export function RequireGuest() {
    const {user} = useSession()
    const location = useLocation()

    if (user === undefined) return <Loading/>
    if (user !== null) {
        const from = (location.state as { from?: string } | null)?.from
        return <Navigate to={from ?? '/'} replace/>
    }
    return <Outlet/>
}
