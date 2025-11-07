import { Head, Link } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Welcome({ canLogin, canRegister, laravelVersion, phpVersion }) {
    return (
        <GuestLayout>
            <Head title="Welcome" />
            
            <div className="text-center">
                <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-6">
                    Welcome to Palladium BNC Bot
                </h1>

                
                <div className="space-y-4">
                    {canLogin && (
                        <Link
                            href="/login"
                            className="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 transition-colors shadow-md hover:shadow-lg"
                        >
                            Login
                        </Link>
                    )}
                    
                    {canRegister && (
                        <Link
                            href="/register"
                            className="inline-block ml-4 bg-gray-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors shadow-md hover:shadow-lg"
                        >
                            Register
                        </Link>
                    )}
                </div>
                
                <div className="mt-12 text-sm text-gray-500 dark:text-gray-400">
                    <p>Laravel v{laravelVersion} | PHP v{phpVersion}</p>
                </div>
            </div>
        </GuestLayout>
    );
}
