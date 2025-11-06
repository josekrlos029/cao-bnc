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
                    <h2 className="text-2xl font-bold text-gray-900">Configuraciones</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Administra la configuración de tu cuenta y preferencias del sistema.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {settingsSections.map((section) => (
                        <Link
                            key={section.name}
                            href={section.href}
                            className="relative group bg-white p-6 rounded-lg border border-gray-200 hover:border-blue-300 hover:shadow-md transition-all focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500"
                        >
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center gap-3 mb-3">
                                        <span className="text-3xl">{section.icon}</span>
                                        <h3 className="text-lg font-medium text-gray-900">
                                            {section.name}
                                        </h3>
                                    </div>
                                    <p className="text-sm text-gray-500 mb-4">
                                        {section.description}
                                    </p>
                                    <div className="flex items-center justify-between">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            section.status === 'Disponible' 
                                                ? 'bg-green-100 text-green-800' 
                                                : 'bg-gray-100 text-gray-800'
                                        }`}>
                                            {section.status}
                                        </span>
                                        <span className="text-blue-600 group-hover:text-blue-800 text-sm font-medium">
                                            Configurar →
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>

                {/* Información adicional */}
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <span className="text-2xl">ℹ️</span>
                        </div>
                        <div className="ml-3">
                            <h3 className="text-sm font-medium text-blue-800">
                                Acerca de las configuraciones
                            </h3>
                            <div className="mt-2 text-sm text-blue-700">
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

