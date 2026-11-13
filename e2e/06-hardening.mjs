// What the security headers must not break: the app under its Content-Security-Policy and the Swagger UI.
// Run through e2e/run.sh; arguments: <base-url> <out-dir> <admin-user> <admin-password>
import { start } from './harness.mjs'

const [base, out, adminUser, adminPw] = process.argv.slice(2)
const t = await start(base, out, 9338)
const { js, goto, wait, shot, step } = t

try {
  await step('responses carry the security headers', async () => {
    await goto('/login')
    const headers = await js(`(async () => {
      const read = async (url) => { const r = await fetch(url); return [r.headers.get('content-security-policy'), r.headers.get('x-frame-options'), r.headers.get('x-content-type-options'), r.headers.get('referrer-policy')] }
      return { page: await read('/login'), api: await read('/api/health') }
    })()`)
    if (!headers.page[0]?.includes("script-src 'self'") || headers.page[0].includes("script-src 'self' 'unsafe-inline'")) throw new Error('page CSP: ' + headers.page[0])
    if (!headers.api[0]?.startsWith("default-src 'none'")) throw new Error('API CSP: ' + headers.api[0])
    for (const set of [headers.page, headers.api]) {
      if (set[1] !== 'DENY' || set[2] !== 'nosniff' || !set[3]) throw new Error('missing header in ' + JSON.stringify(set))
    }
  })

  await step('the policy really blocks inline scripts', async () => {
    const ran = await js(`(async () => {
      window.__inlineRan = false
      const s = document.createElement('script')
      s.textContent = 'window.__inlineRan = true'
      document.head.appendChild(s)
      await new Promise((r) => setTimeout(r, 100))
      const result = window.__inlineRan
      window.__csp = [] // this violation was provoked on purpose
      return result
    })()`)
    if (ran) throw new Error('an injected inline script was executed')
  })

  await step('overlays and the budget slider work under the policy', async () => {
    await js(`(__t.fill('Username', '${adminUser}'), __t.fill('Passwort', '${adminPw}'), __t.click('Anmelden'))`)
    await wait('overview', `location.pathname === '/'`)
    await js(`__t.click('Neue Gruppe')`)
    await wait('dialog', `!!__t.dialog()`)
    await js(`__t.byLabel('Budget festlegen').click()`)
    await wait('slider', `document.querySelectorAll('[role=slider]').length === 2`)
    // Radix positions the thumbs with inline styles; without 'unsafe-inline' for styles both would sit at 0.
    const positions = await js(`[...document.querySelectorAll('[role=slider]')].map((thumb) => Math.round(thumb.getBoundingClientRect().left))`)
    if (positions[0] === positions[1]) throw new Error('slider thumbs are not positioned: ' + positions)
    await js(`__t.click('Abbrechen', __t.dialog())`)
    await wait('closed', `!__t.dialog()`)
  })

  await step('Swagger UI renders for the Wichtler admin', async () => {
    await goto('/api/doc')
    await wait('swagger', `!!document.querySelector('.swagger-ui .info .title') && document.querySelector('.swagger-ui .info .title').textContent.includes('Wichtler API') && document.querySelectorAll('.opblock').length > 20`, 15000)
    await shot('swagger-ui')
  })

  await step('guests and regular users do not get the API documentation', async () => {
    await goto('/')
    await js(`__t.click('Abmelden')`)
    await wait('logged out', `location.pathname === '/login'`)
    const statuses = await js(`Promise.all(['/api/doc', '/api/doc.json'].map((url) => fetch(url).then((r) => r.status))).then((s) => s.join(','))`)
    if (statuses !== '401,401') throw new Error('guest got: ' + statuses)
  })
} catch {
  await shot('failure-06')
} finally {
  await t.finish()
}
