// Accounts and groups: registration, login, profile, creating, editing and deleting a group.
// Run through e2e/run.sh; arguments: <base-url> <out-dir> <admin-user> <admin-password>
import { start } from './harness.mjs'

const [base, out] = process.argv.slice(2)
const t = await start(base, out, 9333)
const { js, goto, wait, shot, step } = t

const user = 'e2e.' + Date.now().toString(36)
const pw = 'e2e-' + Math.random().toString(36).slice(2) + '-pw'
const pw2 = pw + '-neu'

try {
  await step('guest is sent to the login', async () => {
    await goto('/')
    await wait('login form', `location.pathname === '/login' && __t.text().includes('Anmelden')`)
  })

  await step('registration shows client-side validation', async () => {
    await goto('/registrieren')
    await js(`(__t.fill('Vorname', 'E2E'), __t.fill('Nachname', 'Test'), __t.fill('Username', 'x'), __t.fill('Passwort', 'kurz'), __t.fill('Passwort wiederholen', 'anders'), __t.click('Registrieren'))`)
    await wait('field errors', `__t.text().includes('mindestens 10 Zeichen lang') && __t.text().includes('stimmen nicht überein') && __t.text().includes('3 bis 30 Zeichen lang sein')`)
  })

  await step('registration signs in and lands on the overview', async () => {
    await js(`(__t.fill('Username', '${user}'), __t.fill('Passwort', '${pw}'), __t.fill('Passwort wiederholen', '${pw}'), __t.click('Registrieren'))`)
    await wait('overview', `location.pathname === '/' && __t.text().includes('Hallo E2E') && __t.text().includes('noch in keiner Wichtelgruppe')`)
  })

  await step('session survives a reload (HttpOnly cookies)', async () => {
    await goto('/')
    await wait('overview after reload', `location.pathname === '/' && __t.text().includes('Hallo E2E')`)
    if (await js(`document.cookie`)) throw new Error('cookies readable by script: ' + (await js('document.cookie')))
  })

  await step('create group overlay with budget', async () => {
    await js(`__t.click('Neue Gruppe')`)
    await wait('dialog', `!!__t.dialog() && __t.text().includes('Neue Wichtelgruppe')`)
    await js(`__t.click('Gruppe erstellen')`)
    await wait('title required', `__t.text().includes('Dieses Feld ist erforderlich.')`)
    await js(`(__t.fill('Titel', 'E2E Familie'), __t.fill('Beschreibung', 'Bei Oma'), __t.byLabel('Budget festlegen').click())`)
    await wait('slider visible', `document.querySelectorAll('[role=slider]').length === 2 && __t.text().includes('20') && __t.text().includes('40')`)
    await shot('group-form-mobile')
    await js(`__t.click('Gruppe erstellen')`)
    await wait('group page', `/^\\/gruppen\\/\\d+$/.test(location.pathname) && __t.text().includes('E2E Familie') && __t.text().includes('Offen') && __t.text().includes('Gruppenadmin') && !__t.dialog()`)
    const text = await js(`__t.text()`)
    for (const needle of ['Bei Oma', '1 Teilnehmer', 'mindestens 3', 'CHF']) if (!text.includes(needle)) throw new Error('missing on group page: ' + needle + '\n' + text)
    await shot('group-page-mobile')
  })

  await step('edit overlay renames the group', async () => {
    await js(`__t.click('Bearbeiten')`)
    await wait('edit dialog prefilled', `!!__t.dialog() && __t.byLabel('Titel').value === 'E2E Familie' && __t.byLabel('Budget festlegen').checked`)
    await js(`(__t.fill('Titel', 'E2E Umbenannt'), __t.click('Speichern'))`)
    await wait('renamed', `!__t.dialog() && document.querySelector('h1').textContent === 'E2E Umbenannt'`)
  })

  await step('overview lists the group', async () => {
    await js(`__t.click('← Alle Gruppen')`)
    await wait('card', `location.pathname === '/' && __t.text().includes('E2E Umbenannt') && __t.text().includes('Offen')`)
    await shot('overview-mobile')
    await js(`[...document.querySelectorAll('a')].find((a) => a.textContent.includes('E2E Umbenannt')).click()`)
    await wait('group page again', `/^\\/gruppen\\/\\d+$/.test(location.pathname) && document.querySelector('h1')?.textContent === 'E2E Umbenannt'`)
  })

  await step('delete needs the typed title', async () => {
    await js(`__t.click('Löschen')`)
    await wait('delete dialog', `!!__t.dialog() && __t.text().includes('endgültig gelöscht')`)
    if (!(await js(`__t.isDisabled('Endgültig löschen')`))) throw new Error('confirm enabled without typing')
    await js(`__t.fill('Tippe zur Bestätigung', 'falsch')`)
    if (!(await js(`__t.isDisabled('Endgültig löschen')`))) throw new Error('confirm enabled with wrong title')
    await shot('delete-overlay-mobile')
    await js(`__t.fill('Tippe zur Bestätigung', 'E2E Umbenannt')`)
    await wait('enabled', `!__t.isDisabled('Endgültig löschen')`)
    await js(`__t.click('Endgültig löschen')`)
    await wait('back on empty overview', `location.pathname === '/' && __t.text().includes('noch in keiner Wichtelgruppe')`)
  })

  await step('foreign or missing group shows a message', async () => {
    await goto('/gruppen/999999')
    await wait('not found', `__t.text().includes('gibt es nicht oder du bist kein Mitglied')`)
  })

  await step('profile: wrong current password is shown at the field', async () => {
    await goto('/profil')
    await wait('profile', `__t.byLabel('Username').value === '${user}'`)
    await js(`(__t.fill('Aktuelles Passwort', 'falsch-falsch'), __t.fill('Neues Passwort', '${pw2}'), __t.fill('Neues Passwort wiederholen', '${pw2}'), __t.click('Passwort ändern'))`)
    await wait('server error', `__t.text().includes('Das aktuelle Passwort ist nicht korrekt.')`)
  })

  await step('profile: password change works', async () => {
    await js(`(__t.fill('Aktuelles Passwort', '${pw}'), __t.click('Passwort ändern'))`)
    await wait('changed', `__t.text().includes('Dein Passwort wurde geändert')`)
  })

  await step('logout returns to the login', async () => {
    await js(`__t.click('Abmelden')`)
    await wait('login', `location.pathname === '/login'`)
    await goto('/profil')
    await wait('still guest', `location.pathname === '/login'`)
  })

  await step('login: old password fails, new one works and returns to the wanted page', async () => {
    await js(`(__t.fill('Username', '${user}'), __t.fill('Passwort', '${pw}'), __t.click('Anmelden'))`)
    await wait('error', `__t.text().includes('Fehlerhafte Zugangsdaten')`)
    await js(`(__t.fill('Passwort', '${pw2}'), __t.click('Anmelden'))`)
    await wait('profile after login', `location.pathname === '/profil' && __t.byLabel('Username').value === '${user}'`)
  })
} catch {
  await shot('failure-01')
} finally {
  await t.finish()
}
