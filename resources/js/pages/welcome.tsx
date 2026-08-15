import { Link } from '@inertiajs/react';


export default function Welcome() {

    return (
        <div>
            <Link href={route('login')}>
                Log in
            </Link>
        </div>
    );
}