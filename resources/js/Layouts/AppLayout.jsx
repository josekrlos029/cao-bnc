import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Bars3Icon, XMarkIcon, SunIcon, MoonIcon } from '@heroicons/react/24/outline';
import { useTheme } from '../hooks/useTheme';

export default function AppLayout({ children, header }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const { auth, url } = usePage().props;
    const { theme, toggleTheme } = useTheme();

    const navigation = [
        { name: 'Dashboard', href: '/dashboard' },
        { name: 'Transacciones', href: '/transactions' },
        { name: 'Reportes', href: '/reports' },
        { name: 'Bots', href: '/bots' },
        { name: 'Configuraciones', href: '/settings' },
    ].map(item => ({
        ...item,
        current: url.startsWith(item.href),
    }));

    return (
        <>
            <Head title={header || 'Palladium BNC Bot'} />
            
            <div className="min-h-screen bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
                {/* Mobile sidebar */}
                <div className={`fixed inset-0 z-50 lg:hidden ${sidebarOpen ? 'block' : 'hidden'}`}>
                    <div className="fixed inset-0 bg-gray-600 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-90" onClick={() => setSidebarOpen(false)} />
                    <div className="fixed inset-y-0 left-0 flex w-64 flex-col bg-white dark:bg-gray-800 shadow-xl">
                        <div className="flex h-16 items-center justify-between px-4 border-b border-gray-200 dark:border-gray-700">
                            <h1 className="text-xl font-bold text-gray-900 dark:text-white">Palladium BNC Bot</h1>
                            <button
                                type="button"
                                className="text-gray-400 hover:text-gray-600 dark:text-gray-300 dark:hover:text-gray-100 transition-colors"
                                onClick={() => setSidebarOpen(false)}
                            >
                                <XMarkIcon className="h-6 w-6" />
                            </button>
                        </div>
                        <nav className="flex-1 space-y-1 px-2 py-4">
                            {navigation.map((item) => (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className={`${
                                        item.current
                                            ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'
                                    } group flex items-center px-2 py-2 text-sm font-medium rounded-md transition-colors`}
                                >
                                    {item.name}
                                </Link>
                            ))}
                        </nav>
                    </div>
                </div>

                {/* Desktop sidebar */}
                <div className="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col">
                    <div className="flex flex-col flex-grow bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700">
                        <div className="flex h-16 items-center px-4 border-b border-gray-200 dark:border-gray-700">
                            <h1 className="text-xl font-bold text-gray-900 dark:text-white">Palladium BNC Bot</h1>
                        </div>
                        <nav className="flex-1 space-y-1 px-2 py-4">
                            {navigation.map((item) => (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className={`${
                                        item.current
                                            ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'
                                    } group flex items-center px-2 py-2 text-sm font-medium rounded-md transition-colors`}
                                >
                                    {item.name}
                                </Link>
                            ))}
                        </nav>
                    </div>
                </div>

                {/* Main content */}
                <div className="lg:pl-64">
                    {/* Top bar */}
                    <div className="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8 transition-colors">
                        <button
                            type="button"
                            className="-m-2.5 p-2.5 text-gray-700 dark:text-gray-300 lg:hidden hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                            onClick={() => setSidebarOpen(true)}
                        >
                            <Bars3Icon className="h-6 w-6" />
                        </button>

                        <div className="flex flex-1 gap-x-4 self-stretch lg:gap-x-6">
                            <div className="flex flex-1"></div>
                            <div className="flex items-center gap-x-4 lg:gap-x-6">
                                <button
                                    type="button"
                                    onClick={toggleTheme}
                                    className="p-2 rounded-md text-gray-400 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 dark:focus:ring-indigo-400 transition-colors"
                                    aria-label="Toggle theme"
                                >
                                    {theme === 'dark' ? (
                                        <SunIcon className="h-5 w-5" />
                                    ) : (
                                        <MoonIcon className="h-5 w-5" />
                                    )}
                                </button>
                                <div className="relative">
                                    <div className="flex items-center space-x-4">
                                        <span className="text-sm text-gray-700 dark:text-gray-300">{auth.user.name}</span>
                                        <Link
                                            href="/logout"
                                            method="post"
                                            className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 transition-colors"
                                        >
                                            Logout
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Page content */}
                    <main className="py-6">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            {children}
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
