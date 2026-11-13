import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { UserResponse } from '../api/generated/model'
import { RequireAdmin, RequireGuest, RequireUser } from './guards'
import { isInvited } from './invite'

let sessionUser: UserResponse | null | undefined
vi.mock('./session', () => ({ useSession: () => ({ user: sessionUser }) }))

const anna: UserResponse = { id: 2, firstName: 'Anna', lastName: 'Muster', username: 'anna', isAdmin: false, mustChangePassword: false }

function Where() {
  const location = useLocation()
  return (
    <p>
      at {location.pathname} from {(location.state as { from?: string } | null)?.from ?? 'nowhere'} invited {String(isInvited(location.state))}
    </p>
  )
}

function renderAt(path: string, state?: unknown) {
  render(
    <MemoryRouter initialEntries={[{ pathname: path, state }]}>
      <Routes>
        <Route element={<RequireGuest />}>
          <Route path="/login" element={<Where />} />
          <Route path="/registrieren" element={<Where />} />
        </Route>
        <Route element={<RequireUser />}>
          <Route path="/" element={<Where />} />
          <Route path="/profil" element={<Where />} />
          <Route path="/einladung/:token" element={<Where />} />
          <Route element={<RequireAdmin />}>
            <Route path="/benutzer" element={<Where />} />
          </Route>
        </Route>
      </Routes>
    </MemoryRouter>,
  )
}

describe('route guards', () => {
  beforeEach(() => {
    sessionUser = null
  })

  // The three share-link scenarios hinge on this pair of redirects.
  it('sends a guest who opens a share link to the login and remembers the link', () => {
    renderAt('/einladung/abc123')

    expect(screen.getByText('at /login from /einladung/abc123 invited true')).toBeTruthy()
  })

  it('brings the user back to the share link after login or registration', () => {
    sessionUser = anna
    renderAt('/registrieren', { from: '/einladung/abc123' })

    expect(screen.getByText(/^at \/einladung\/abc123/)).toBeTruthy()
  })

  it('shows a signed-in user the share link directly', () => {
    sessionUser = anna
    renderAt('/einladung/abc123')

    expect(screen.getByText(/^at \/einladung\/abc123/)).toBeTruthy()
  })

  it('does not call an ordinary login an invitation', () => {
    renderAt('/profil')

    expect(screen.getByText('at /login from /profil invited false')).toBeTruthy()
  })

  it('sends signed-in users away from the login to the overview', () => {
    sessionUser = anna
    renderAt('/login')

    expect(screen.getByText(/^at \/ from/)).toBeTruthy()
  })

  it('shows nothing of the app while the session is still being checked', () => {
    sessionUser = undefined
    renderAt('/')

    expect(screen.queryByText(/^at /)).toBeNull()
    expect(screen.getByRole('status')).toBeTruthy()
  })

  it('keeps a user with a temporary password on the profile', () => {
    sessionUser = { ...anna, mustChangePassword: true }
    renderAt('/einladung/abc123')

    expect(screen.getByText(/^at \/profil/)).toBeTruthy()
  })

  it('keeps everyone but the Wichtler admin out of the user administration', () => {
    sessionUser = anna
    renderAt('/benutzer')
    expect(screen.getByText(/^at \/ from/)).toBeTruthy()
  })

  it('lets the Wichtler admin into the user administration', () => {
    sessionUser = { ...anna, isAdmin: true }
    renderAt('/benutzer')
    expect(screen.getByText(/^at \/benutzer/)).toBeTruthy()
  })
})
