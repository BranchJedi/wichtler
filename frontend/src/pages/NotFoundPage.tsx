import {Link} from 'react-router'
import gnomeUrl from '../assets/illustrations/wichtel.svg'
import {de} from '../i18n/de'

export function NotFoundPage() {
    return (
        <main className="mx-auto max-w-md px-4 py-16 text-center">
            <img src={gnomeUrl} alt="" className="mx-auto mb-6 w-32"/>
            <h1 className="text-3xl font-semibold">{de.errors.notFound}</h1>
            <Link to="/" className="mt-4 inline-block font-medium text-tanne-700 underline">
                {de.errors.backHome}
            </Link>
        </main>
    )
}
