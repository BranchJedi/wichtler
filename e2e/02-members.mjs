// Members, invitations by username and the three share-link scenarios.
// Run through e2e/run.sh; arguments: <base-url> <out-dir> <admin-user> <admin-password>
import { start } from './harness.mjs'

const [base, out] = process.argv.slice(2)
const t = await start(base, out)
const { js, goto, wait, shot, step } = t

const run = Date.now().toString(36)
const pw = 'e2e-' + Math.random().toString(36).slice(2) + '-pw'
const owner = `e2e.owner.${run}`
const viaLink = `e2e.link.${run}`
const viaName = `e2e.name.${run}`

const login = async (username) => {
  await goto('/login')
  await wait('login form', `location.pathname === '/login'`)
  await js(`(__t.fill('Username', '${username}'), __t.fill('Passwort', '${pw}'), __t.click('Anmelden'))`)
}
const logout = async () => {
  await js(`__t.click('Abmelden')`)
  await wait('logged out', `location.pathname === '/login'`)
}
const registerForm = (first, username) =>
  `(__t.fill('Vorname', '${first}'), __t.fill('Nachname', 'E2E'), __t.fill('Username', '${username}'), __t.fill('Passwort', '${pw}'), __t.fill('Passwort wiederholen', '${pw}'), __t.click('Registrieren'))`

let groupPath, oldLink, newLink

try {
  await step('owner registers and creates a group', async () => {
    await goto('/registrieren')
    await js(registerForm('Olga', owner))
    await wait('overview', `location.pathname === '/' && __t.has('Hallo Olga')`)
    await js(`__t.click('Neue Gruppe')`)
    await wait('dialog', `!!__t.dialog()`)
    await js(`(__t.fill('Titel', 'E2E Wichteln'), __t.click('Gruppe erstellen'))`)
    await wait('group page', `/^\\/gruppen\\/\\d+$/.test(location.pathname) && !__t.dialog()`)
    groupPath = await js(`location.pathname`)
  })

  await step('member list shows the owner; unknown username is refused', async () => {
    await wait('members', `__t.row('${owner}').textContent.includes('(du)') && __t.row('${owner}').textContent.includes('Gruppenadmin')`)
    await js(`(__t.fill('Per Username einladen', 'gibt.es.nicht'), __t.click('Einladen'))`)
    await wait('error', `__t.has('Diesen Username gibt es nicht.')`)
    await wait('share link', `__t.byLabel('Share-Link').value.includes('/einladung/')`)
    oldLink = new URL(await js(`__t.byLabel('Share-Link').value`)).pathname
    if (oldLink.length !== '/einladung/'.length + 43) throw new Error('unexpected link ' + oldLink)
    await shot('members-owner-mobile')
  })

  await step('scenario 3: no account -> register -> confirm overlay -> joined', async () => {
    await logout()
    await goto(oldLink)
    await wait('login with hint', `location.pathname === '/login' && __t.has('Du wurdest zu einer Wichtelgruppe eingeladen')`)
    if (await js(`__t.has('E2E Wichteln')`)) throw new Error('group leaked to a guest')
    await js(`__t.click('Registrieren')`)
    await wait('register with hint', `location.pathname === '/registrieren' && __t.has('Du wurdest zu einer Wichtelgruppe eingeladen')`)
    await js(registerForm('Lina', viaLink))
    await wait('invitation overlay', `location.pathname === '${oldLink}' && !!__t.dialog() && __t.dialog().textContent.includes('E2E Wichteln') && __t.dialog().textContent.includes('Olga E2E')`)
    await shot('invitation-overlay-mobile')
    await js(`__t.click('Beitreten', __t.dialog())`)
    await wait('joined', `location.pathname === '${groupPath}' && __t.has('${viaLink}') && __t.has('2 Teilnehmer') && __t.has('Teilnehmer') && !__t.has('Per Username einladen')`)
  })

  await step('scenario 1: existing account, signed out -> login -> straight into the group (already a member)', async () => {
    await logout()
    await goto(oldLink)
    await wait('login', `location.pathname === '/login'`)
    await js(`(__t.fill('Username', '${viaLink}'), __t.fill('Passwort', '${pw}'), __t.click('Anmelden'))`)
    await wait('group', `location.pathname === '${groupPath}' && __t.has('E2E Wichteln')`)
  })

  await step('third account exists (registered through the API)', async () => {
    await logout()
    const status = await js(`fetch('/api/auth/register', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ firstName: 'Nora', lastName: 'E2E', username: '${viaName}', password: '${pw}' }) }).then((r) => r.status)`)
    if (status !== 201) throw new Error('register status ' + status)
    await js(`fetch('/api/auth/logout', { method: 'POST' }).then((r) => r.status)`)
  })

  await step('owner invites by username, deactivates and reactivates a participant', async () => {
    await login(owner)
    await wait('overview', `location.pathname === '/'`)
    await goto(groupPath)
    await wait('members', `__t.has('${viaLink}')`)
    await js(`(__t.fill('Per Username einladen', '${viaName.toUpperCase()}'), __t.click('Einladen'))`)
    await wait('invited', `__t.row('${viaName}').textContent.includes('Eingeladen') && __t.has('Nora E2E wurde eingeladen')`)
    await js(`__t.click('Inaktiv setzen', __t.row('${viaLink}'))`)
    await wait('inactive', `__t.row('${viaLink}').textContent.includes('Inaktiv') && __t.has('1 Teilnehmer')`)
    await shot('members-manage-mobile')
    await js(`__t.click('Aktivieren', __t.row('${viaLink}'))`)
    await wait('active again', `__t.has('2 Teilnehmer') && !__t.row('${viaLink}').textContent.includes('Inaktiv setzenInaktiv')`)
  })

  await step('invited user sees the invitation, confirms it in the overlay and joins', async () => {
    await logout()
    await login(viaName)
    await wait('pending invitation', `location.pathname === '/' && __t.has('Offene Einladungen') && __t.has('E2E Wichteln') && __t.has('noch in keiner Wichtelgruppe')`)
    await js(`__t.click('Ansehen')`)
    await wait('overlay', `!!__t.dialog() && __t.dialog().textContent.includes('Ablehnen')`)
    await js(`__t.click('Beitreten', __t.dialog())`)
    await wait('joined', `location.pathname === '${groupPath}' && __t.has('3 Teilnehmer')`)
  })

  await step('participant leaves the group after confirming', async () => {
    await js(`__t.click('Gruppe verlassen')`)
    await wait('confirm', `!!__t.dialog() && __t.dialog().textContent.includes('E2E Wichteln')`)
    await js(`__t.click('Verlassen', __t.dialog())`)
    await wait('gone', `location.pathname === '/' && __t.has('noch in keiner Wichtelgruppe') && !__t.has('Offene Einladungen')`)
  })

  await step('owner creates a new share link and removes a participant', async () => {
    await logout()
    await login(owner)
    await wait('overview', `location.pathname === '/'`)
    await goto(groupPath)
    await wait('link', `__t.byLabel('Share-Link').value.endsWith('${oldLink}')`)
    await js(`__t.click('Neuen Link erzeugen')`)
    await wait('confirm', `!!__t.dialog() && __t.dialog().textContent.includes('funktioniert danach nicht mehr')`)
    await js(`__t.click('Neuen Link erzeugen', __t.dialog())`)
    await wait('new link', `!__t.dialog() && __t.byLabel('Share-Link').value.includes('/einladung/') && !__t.byLabel('Share-Link').value.endsWith('${oldLink}')`)
    newLink = new URL(await js(`__t.byLabel('Share-Link').value`)).pathname
    await js(`__t.click('Entfernen', __t.row('${viaLink}'))`)
    await wait('confirm remove', `!!__t.dialog() && __t.dialog().textContent.includes('Lina E2E wird aus der Gruppe entfernt')`)
    await js(`__t.click('Entfernen', __t.dialog())`)
    await wait('removed', `!__t.dialog() && !__t.has('${viaLink}') && __t.has('1 Teilnehmer')`)
  })

  await step('old link is dead; scenario 2: signed in -> overlay -> cancel does not join', async () => {
    await logout()
    await login(viaName)
    await wait('overview', `location.pathname === '/'`)
    await goto(oldLink)
    await wait('expired', `__t.has('Diese Einladung ist abgelaufen oder ungültig.')`)
    await goto(newLink)
    await wait('overlay', `!!__t.dialog() && __t.dialog().textContent.includes('E2E Wichteln')`)
    await js(`__t.click('Abbrechen', __t.dialog())`)
    await wait('not joined', `location.pathname === '/' && !__t.dialog() && __t.has('noch in keiner Wichtelgruppe')`)
    await goto(newLink)
    await wait('overlay again', `!!__t.dialog()`)
    await js(`__t.click('Beitreten', __t.dialog())`)
    await wait('joined', `location.pathname === '${groupPath}' && __t.has('2 Teilnehmer')`)
  })
} catch {
  await shot('failure-02')
} finally {
  await t.finish()
}
