import { BrowserRouter, Route, Routes } from 'react-router'
import { RequireAdmin, RequireGuest, RequireUser } from './auth/guards'
import { AppLayout, GuestLayout } from './components/Layout'
import { GroupPage } from './pages/GroupPage'
import { HomePage } from './pages/HomePage'
import { InvitePage } from './pages/InvitePage'
import { LoginPage } from './pages/LoginPage'
import { NotFoundPage } from './pages/NotFoundPage'
import { ProfilePage } from './pages/ProfilePage'
import { RegisterPage } from './pages/RegisterPage'
import { UsersPage } from './pages/UsersPage'

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route element={<RequireGuest />}>
          <Route element={<GuestLayout />}>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/registrieren" element={<RegisterPage />} />
          </Route>
        </Route>
        <Route element={<RequireUser />}>
          <Route element={<AppLayout />}>
            <Route path="/" element={<HomePage />} />
            <Route path="/gruppen/:id" element={<GroupPage />} />
            <Route path="/einladung/:token" element={<InvitePage />} />
            <Route path="/profil" element={<ProfilePage />} />
            <Route element={<RequireAdmin />}>
              <Route path="/benutzer" element={<UsersPage />} />
            </Route>
          </Route>
        </Route>
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
