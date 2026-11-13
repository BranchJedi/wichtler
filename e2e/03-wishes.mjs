// Wish list and the group admin's draw checklist.
// Run through e2e/run.sh; arguments: <base-url> <out-dir> <admin-user> <admin-password>
import { start } from './harness.mjs'

const [base, out] = process.argv.slice(2)
const t = await start(base, out, 9335)
const { js, goto, wait, shot, step } = t

const run = Date.now().toString(36)
const pw = 'e2e-' + Math.random().toString(36).slice(2) + '-pw'
const owner = `e2e.owner.${run}`
const full = `e2e.full.${run}`
const partial = `e2e.part.${run}`

const login = async (username) => {
  await goto('/login')
  await wait('login form', `location.pathname === '/login'`)
  await js(`(__t.fill('Username', '${username}'), __t.fill('Passwort', '${pw}'), __t.click('Anmelden'))`)
  await wait('signed in', `location.pathname === '/'`)
}
const logout = async () => {
  await js(`__t.click('Abmelden')`)
  await wait('logged out', `location.pathname === '/login'`)
}
const addWish = async (title, extra = '') => {
  await js(`__t.click('Wunsch hinzufügen')`)
  await wait('wish dialog', `!!__t.dialog() && __t.dialog().textContent.includes('Neuer Wunsch')`)
  await js(`(__t.fill('Wunsch', '${title}')${extra}, __t.click('Speichern', __t.dialog()))`)
}
// Registers a participant through the API, joins via the share link and enters wishes; ends signed out.
const apiParticipant = (first, username, token, wishes) => js(`(async () => {
  const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined })
  const r1 = await post('/api/auth/register', { firstName: '${first}', lastName: 'E2E', username: '${username}', password: '${pw}' })
  const r2 = await post('/api/invite-links/${token}/accept')
  const group = await r2.json()
  const statuses = [r1.status, r2.status]
  for (let i = 1; i <= ${wishes}; i++) statuses.push((await post('/api/groups/' + group.id + '/wishes', { title: 'Geheimwunsch ${first} ' + i })).status)
  statuses.push((await post('/api/auth/logout')).status)
  return statuses.join(',')
})()`)

let groupPath, token

try {
  await step('owner creates a group; wish list starts empty with the target shown', async () => {
    await goto('/registrieren')
    await js(`(__t.fill('Vorname', 'Olga'), __t.fill('Nachname', 'E2E'), __t.fill('Username', '${owner}'), __t.fill('Passwort', '${pw}'), __t.fill('Passwort wiederholen', '${pw}'), __t.click('Registrieren'))`)
    await wait('overview', `location.pathname === '/'`)
    await js(`__t.click('Neue Gruppe')`)
    await wait('dialog', `!!__t.dialog()`)
    await js(`(__t.fill('Titel', 'E2E Wünsche'), __t.click('Gruppe erstellen'))`)
    await wait('group page', `/^\\/gruppen\\/\\d+$/.test(location.pathname) && !__t.dialog()`)
    groupPath = await js(`location.pathname`)
    await wait('wish card', `__t.has('Meine Wünsche') && __t.has('0 von 3 Wünschen erfasst') && __t.has('Es fehlen noch 3 Wünsche.') && __t.has('Du hast noch keine Wünsche erfasst.')`)
    await wait('share link', `__t.byLabel('Share-Link').value.includes('/einladung/')`)
    token = (await js(`__t.byLabel('Share-Link').value`)).split('/einladung/')[1]
  })

  await step('wish form validates title and link', async () => {
    await js(`__t.click('Wunsch hinzufügen')`)
    await wait('dialog', `!!__t.dialog()`)
    await js(`(__t.fill('Link', 'kein link'), __t.click('Speichern', __t.dialog()))`)
    await wait('errors', `__t.dialog().textContent.includes('Dieses Feld ist erforderlich.') && __t.dialog().textContent.includes('gültigen Link')`)
    await js(`(__t.fill('Wunsch', 'Wollsocken'), __t.fill('Details', 'Grösse 43'), __t.fill('Link', 'https://example.com/socken'), __t.click('Speichern', __t.dialog()))`)
    await wait('saved', `!__t.dialog() && __t.has('Wollsocken') && __t.has('Grösse 43') && __t.has('1 von 3 Wünschen erfasst') && __t.has('Es fehlen noch 2 Wünsche.')`)
    const link = await js(`(() => { const a = [...document.querySelectorAll('a')].find((x) => x.textContent === 'Link öffnen'); return a.href + '|' + a.target + '|' + a.rel })()`)
    if (link !== 'https://example.com/socken|_blank|noopener noreferrer') throw new Error('link attributes: ' + link)
  })

  await step('wish can be edited and deleted after confirmation', async () => {
    await js(`__t.click('Bearbeiten', __t.row('Wollsocken'))`)
    await wait('prefilled', `!!__t.dialog() && __t.byLabel('Wunsch').value === 'Wollsocken' && __t.byLabel('Link').value === 'https://example.com/socken'`)
    await js(`(__t.fill('Wunsch', 'Dicke Wollsocken'), __t.fill('Link', ''), __t.click('Speichern', __t.dialog()))`)
    await wait('edited', `!__t.dialog() && __t.has('Dicke Wollsocken') && !__t.has('Link öffnen')`)
    await js(`__t.click('Löschen', __t.row('Dicke Wollsocken'))`)
    await wait('confirm', `!!__t.dialog() && __t.dialog().textContent.includes('«Dicke Wollsocken» wird von deiner Wunschliste gelöscht.')`)
    await js(`__t.click('Löschen', __t.dialog())`)
    await wait('deleted', `!__t.dialog() && !__t.has('Dicke Wollsocken') && __t.has('0 von 3 Wünschen erfasst')`)
  })

  await step('reaching the minimum turns the progress green; checklist still lacks participants', async () => {
    for (const title of ['Buch', 'Tee', 'Kerze']) {
      await addWish(title)
      await wait('listed ' + title, `!__t.dialog() && __t.has('${title}')`)
    }
    await wait('complete', `__t.has('3 von 3 Wünschen erfasst') && !__t.has('Es fehlen noch')`)
    await wait('checklist', `__t.has('Die Gruppe kann noch nicht ausgelost werden.') && __t.has('Mindestens 3 Teilnehmer (aktuell 1)') && __t.has('Alle Teilnehmer haben mindestens 3 Wünsche')`)
    await shot('wishes-owner-mobile')
  })

  await step('two participants join through the API, one with too few wishes', async () => {
    await logout()
    const a = await apiParticipant('Vera', full, token, 3)
    const b = await apiParticipant('Paul', partial, token, 1)
    if (a !== '201,200,201,201,201,204' || b !== '201,200,201,204') throw new Error(`api statuses ${a} / ${b}`)
  })

  await step('group admin sees who lacks wishes, as counts only', async () => {
    await login(owner)
    await goto(groupPath)
    await wait('checklist', `__t.has('Mindestens 3 Teilnehmer (aktuell 3)') && __t.has('Es fehlen noch Wünsche von:') && [...document.querySelectorAll('li')].some((li) => li.textContent.includes('${partial}') && li.textContent.includes('1 von 3'))`)
    const text = await js(`__t.text()`)
    if (text.includes('Geheimwunsch')) throw new Error('wishes of others visible to the group admin')
    if (await js(`[...document.querySelectorAll('h3 + ul li')].some((li) => li.textContent.includes('${full}'))`)) throw new Error('complete participant listed as missing')
    if (!text.includes('Offen')) throw new Error('status badge should be "Offen"')
    await shot('draw-checklist-mobile')
  })

  await step('participant completes the list: sees no checklist and no foreign wishes, group becomes ready', async () => {
    await logout()
    await login(partial)
    await goto(groupPath)
    await wait('own wishes', `__t.has('1 von 3 Wünschen erfasst') && __t.has('Geheimwunsch Paul 1')`)
    const text = await js(`__t.text()`)
    for (const leak of ['Geheimwunsch Vera', 'Buch', 'Es fehlen noch Wünsche von', 'Mindestens 3 Teilnehmer']) if (text.includes(leak)) throw new Error('participant sees: ' + leak)
    await addWish('Zweiter')
    await wait('two', `!__t.dialog() && __t.has('2 von 3 Wünschen erfasst')`)
    await addWish('Dritter')
    await wait('ready badge', `!__t.dialog() && __t.has('3 von 3 Wünschen erfasst') && __t.has('Bereit zur Auslosung')`)
  })

  await step('group admin: everything ready', async () => {
    await logout()
    await login(owner)
    await wait('overview badge', `__t.has('E2E Wünsche') && __t.has('Bereit zur Auslosung')`)
    await goto(groupPath)
    await wait('ready', `__t.has('Alles bereit: Die Gruppe kann ausgelost werden.') && !__t.has('Es fehlen noch Wünsche von:')`)
  })
} catch {
  await shot('failure-03')
} finally {
  await t.finish()
}
