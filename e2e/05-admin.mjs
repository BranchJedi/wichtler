// The Wichtler admin's help desk and the forced password change.
// Run through e2e/run.sh; arguments: <base-url> <out-dir> <admin-user> <admin-password>
import { start } from './harness.mjs'

const [base, out, adminUser, adminPw] = process.argv.slice(2)
const t = await start(base, out, 9337)
const { js, goto, wait, shot, step } = t

const run = Date.now().toString(36)
const pw = 'e2e-' + Math.random().toString(36).slice(2) + '-pw'
const newPw = pw + '-neu'
const nina = `e2e.nina.${run}`
const renamed = `e2e.nina2.${run}`

const loginForm = (username, password) => js(`(__t.fill('Username', '${username}'), __t.fill('Passwort', '${password}'), __t.click('Anmelden'))`)
const logout = async () => {
  await js(`__t.click('Abmelden')`)
  await wait('logged out', `location.pathname === '/login'`)
}
const row = (text) => `[...document.querySelectorAll('main li')].find((li) => li.textContent.includes('${text}'))`
let temporary

try {
  await step('a regular user exists and has no admin area', async () => {
    await goto('/registrieren')
    await js(`(__t.fill('Vorname', 'Nina'), __t.fill('Nachname', 'Vergesslich'), __t.fill('Username', '${nina}'), __t.fill('Passwort', '${pw}'), __t.fill('Passwort wiederholen', '${pw}'), __t.click('Registrieren'))`)
    await wait('overview', `location.pathname === '/' && __t.has('Hallo Nina')`)
    if (await js(`[...document.querySelectorAll('nav a')].some((a) => a.textContent === 'Benutzer')`)) throw new Error('regular user sees the admin navigation')
    await goto('/benutzer')
    await wait('sent away', `location.pathname === '/' && __t.has('Wichtelgruppen')`)
    const status = await js(`fetch('/api/admin/users').then((r) => r.status)`)
    if (status !== 403) throw new Error('admin API for regular user: ' + status)
    await logout()
  })

  await step('admin finds the user by name', async () => {
    await goto('/login')
    await loginForm(adminUser, adminPw)
    await wait('overview', `location.pathname === '/'`)
    await js(`__t.click('Benutzer')`)
    await wait('users page', `location.pathname === '/benutzer' && __t.has('${nina}') && __t.has('Das bist du.')`)
    await js(`__t.fill('Suchen nach Name oder Username', 'nina vergess')`)
    await wait('filtered', `document.querySelectorAll('main li').length === 1 && __t.has('Nina Vergesslich') && __t.has('${nina}')`)
    await shot('users-mobile')
  })

  await step('admin corrects the username in an overlay', async () => {
    await js(`__t.click('Bearbeiten', ${row(nina)})`)
    await wait('prefilled', `!!__t.dialog() && __t.byLabel('Username').value === '${nina}' && __t.dialog().textContent.includes('überall abgemeldet')`)
    await js(`(__t.fill('Username', '${adminUser}'), __t.click('Speichern', __t.dialog()))`)
    await wait('taken', `__t.dialog().textContent.includes('Dieser Username ist bereits vergeben.')`)
    await js(`(__t.fill('Username', '${renamed}'), __t.click('Speichern', __t.dialog()))`)
    await wait('renamed', `!__t.dialog() && __t.has('${renamed}')`)
  })

  await step('reset is confirmed, then the temporary password is shown exactly once', async () => {
    await js(`__t.click('Passwort zurücksetzen', ${row(renamed)})`)
    await wait('confirm', `!!__t.dialog() && __t.dialog().textContent.includes('Nina Vergesslich erhält ein temporäres Passwort')`)
    await js(`__t.click('Zurücksetzen', __t.dialog())`)
    await wait('shown', `!!__t.dialog() && __t.dialog().textContent.includes('nur dieses eine Mal') && /^[a-zA-Z2-9]{4}(-[a-zA-Z2-9]{4}){3}$/.test(__t.byLabel('Temporäres Passwort').value)`)
    temporary = await js(`__t.byLabel('Temporäres Passwort').value`)
    await js(`__t.click('Fertig', __t.dialog())`)
    await wait('closed', `!__t.dialog() && ${row(renamed)}.textContent.includes('Temporäres Passwort')`)
    if (await js(`document.documentElement.outerHTML.includes('${'${temporary}'}')`.replace('${temporary}', temporary))) throw new Error('temporary password still on the page')
    if (await js(`[...document.querySelectorAll('a')].find((a) => a.textContent === 'API-Dokumentation')?.getAttribute('href')`) !== '/api/doc') throw new Error('API docs link missing')
    await logout()
  })

  await step('old password is dead; the temporary one leads straight to the password change', async () => {
    await loginForm(renamed, pw)
    await wait('refused', `__t.has('Fehlerhafte Zugangsdaten')`)
    await loginForm(renamed, temporary)
    await wait('profile with notice', `location.pathname === '/profil' && __t.has('Dein Passwort wurde zurückgesetzt. Bitte wähle jetzt ein neues Passwort.')`)
    await js(`__t.click('Gruppen')`)
    await wait('kept on profile', `location.pathname === '/profil'`)
    const status = await js(`fetch('/api/groups').then((r) => r.status)`)
    if (status !== 403) throw new Error('API open before the password change: ' + status)
    await shot('forced-password-change-mobile')
  })

  await step('after choosing a new password everything is open again', async () => {
    await js(`(__t.fill('Aktuelles Passwort', '${'${temporary}'}'), __t.fill('Neues Passwort', '${newPw}'), __t.fill('Neues Passwort wiederholen', '${newPw}'), __t.click('Passwort ändern'))`.replace('${temporary}', temporary))
    await wait('changed', `__t.has('Dein Passwort wurde geändert') && !__t.has('Dein Passwort wurde zurückgesetzt')`)
    await js(`__t.click('Gruppen')`)
    await wait('overview', `location.pathname === '/' && __t.has('noch in keiner Wichtelgruppe')`)
    await logout()
    await loginForm(renamed, newPw)
    await wait('signed in with the new password', `location.pathname === '/' && __t.has('Hallo Nina')`)
  })
} catch {
  await shot('failure-05')
} finally {
  await t.finish()
}
