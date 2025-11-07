import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Settings() {
    const settingsSections = [
        {
            name: 'Credenciales de Exchanges',
            description: 'Configura tus claves API para Binance, Bybit y OKX',
            href: '/settings/exchanges',
            icon: '🔑',
            color: 'blue',
            status: 'Disponible',
        },
        // Se pueden agregar más secciones aquí en el futuro
        // {
        //     name: 'Notificaciones',
        //     description: 'Configura cómo recibir alertas y notificaciones',
        //     href: '/settings/notifications',
        //     icon: '🔔',
        //     color: 'purple',
        //     status: 'Próximamente',
        // },
    ];

    return (
        <AppLayout header="Configuraciones">
            <Head title="Configuraciones" />

            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Configuraciones</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Administra la configuración de tu cuenta y preferencias del sistema.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {settingsSections.map((section) => (
                        <Link
                            key={section.name}
                            href={section.href}
                            className="relative group bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-500 hover:shadow-md transition-all focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 dark:focus-within:ring-blue-400"
                        >
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center gap-3 mb-3">
                                        <span className="text-3xl">{section.icon}</span>
                                        <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                                            {section.name}
                                        </h3>
                                    </div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                        {section.description}
                                    </p>
                                    <div className="flex items-center justify-between">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            section.status === 'Disponible' 
                                                ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' 
                                                : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200'
                                        }`}>
                                            {section.status}
                                        </span>
                                        <span className="text-blue-600 dark:text-blue-400 group-hover:text-blue-800 dark:group-hover:text-blue-300 text-sm font-medium">
                                            Configurar →
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>

                {/* Información adicional */}
                <div className="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <span className="text-2xl">ℹ️</span>
                        </div>
                        <div className="ml-3">
                            <h3 className="text-sm font-medium text-blue-800 dark:text-blue-200">
                                Acerca de las configuraciones
                            </h3>
                            <div className="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                <p>
                                    Las configuraciones te permiten personalizar cómo funciona el sistema.
                                    Asegúrate de mantener tus credenciales API seguras y actualizadas.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

