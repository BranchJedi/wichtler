import {Link} from 'react-router'
import {de} from '../i18n/de'

export function NotFoundPage() {
    return (
        <main className="mx-auto max-w-md px-4 py-16 text-center">
            <h1 className="text-2xl font-bold">{de.errors.notFound}</h1>
            <Link to="/" className="mt-4 inline-block font-medium text-pine-700 underline">
                {de.errors.backHome}
            </Link>
        </main>
    )
}
