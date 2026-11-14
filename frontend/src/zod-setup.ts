import {z} from 'zod'

// zod speeds up validation by compiling schemas with `new Function`. The app's
// Content-Security-Policy forbids that, and merely probing for it is reported as a violation
// on every page. The forms here are tiny, so the interpreted path is all that is needed.
z.config({jitless: true})
