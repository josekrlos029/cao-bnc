import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

export default function Exchanges({ binance, bybit, okx }) {
    const [activeTab, setActiveTab] = useState('binance');
    const [loading, setLoading] = useState({});
    const [messages, setMessages] = useState({});
    const [formData, setFormData] = useState({
        binance: {
            api_key: '',
            secret_key: '',
            testnet: binance?.is_testnet || false,
        },
        bybit: {
            api_key: '',
            secret_key: '',
            testnet: bybit?.is_testnet || false,
        },
        okx: {
            api_key: '',
            secret_key: '',
            testnet: okx?.is_testnet || false,
        },
    });

    const handleInputChange = (exchange, field, value) => {
        setFormData(prev => ({
            ...prev,
            [exchange]: {
                ...prev[exchange],
                [field]: value,
            },
        }));
        // Clear messages when user types
        if (messages[exchange]) {
            setMessages(prev => ({
                ...prev,
                [exchange]: null,
            }));
        }
    };

    const handleTestConnection = async (exchange) => {
        setLoading(prev => ({ ...prev, [exchange]: true }));
        setMessages(prev => ({ ...prev, [exchange]: null }));

        try {
            const response = await fetch(`/settings/exchanges/${exchange}/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(formData[exchange]),
            });

            const data = await response.json();

            setMessages(prev => ({
                ...prev,
                [exchange]: {
                    type: data.success ? 'success' : 'error',
                    text: data.message,
                },
            }));
        } catch (error) {
            setMessages(prev => ({
                ...prev,
                [exchange]: {
                    type: 'error',
                    text: 'Error al probar la conexión.',
                },
            }));
        } finally {
            setLoading(prev => ({ ...prev, [exchange]: false }));
        }
    };

    const handleSave = async (exchange) => {
        setLoading(prev => ({ ...prev, [`${exchange}_save`]: true }));
        setMessages(prev => ({ ...prev, [exchange]: null }));

        try {
            const response = await fetch(`/settings/exchanges/${exchange}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(formData[exchange]),
            });

            const data = await response.json();

            if (data.success) {
                setMessages(prev => ({
                    ...prev,
                    [exchange]: {
                        type: 'success',
                        text: data.message,
                    },
                }));
                // Reload page to get updated data
                router.reload();
            } else {
                setMessages(prev => ({
                    ...prev,
                    [exchange]: {
                        type: 'error',
                        text: data.message,
                    },
                }));
            }
        } catch (error) {
            setMessages(prev => ({
                ...prev,
                [exchange]: {
                    type: 'error',
                    text: 'Error al guardar las credenciales.',
                },
            }));
        } finally {
            setLoading(prev => ({ ...prev, [`${exchange}_save`]: false }));
        }
    };

    const handleDelete = async (exchange) => {
        if (!confirm('¿Estás seguro de que deseas eliminar estas credenciales?')) {
            return;
        }

        setLoading(prev => ({ ...prev, [`${exchange}_delete`]: true }));

        try {
            const response = await fetch(`/settings/exchanges/${exchange}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            const data = await response.json();

            if (data.success) {
                setMessages(prev => ({
                    ...prev,
                    [exchange]: {
                        type: 'success',
                        text: data.message,
                    },
                }));
                // Clear form data
                setFormData(prev => ({
                    ...prev,
                    [exchange]: {
                        api_key: '',
                        secret_key: '',
                        testnet: false,
                    },
                }));
                // Reload page to get updated data
                router.reload();
            } else {
                setMessages(prev => ({
                    ...prev,
                    [exchange]: {
                        type: 'error',
                        text: data.message,
                    },
                }));
            }
        } catch (error) {
            setMessages(prev => ({
                ...prev,
                [exchange]: {
                    type: 'error',
                    text: 'Error al eliminar las credenciales.',
                },
            }));
        } finally {
            setLoading(prev => ({ ...prev, [`${exchange}_delete`]: false }));
        }
    };

    const ExchangeForm = ({ exchange, data, label }) => {
        const hasCredentials = data?.has_credentials || false;
        const isActive = data?.is_active || false;

        return (
            <div className="bg-white shadow rounded-lg p-6">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-medium text-gray-900">{label}</h3>
                    {hasCredentials && (
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                            isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }`}>
                            {isActive ? 'Activo' : 'Inactivo'}
                        </span>
                    )}
                </div>

                {hasCredentials && (
                    <div className="mb-4 p-3 bg-gray-50 rounded-md">
                        <div className="text-sm text-gray-600">
                            <p><strong>Testnet:</strong> {data.is_testnet ? 'Sí' : 'No'}</p>
                            {data.last_used_at && (
                                <p><strong>Último uso:</strong> {new Date(data.last_used_at).toLocaleString()}</p>
                            )}
                            {data.last_error && (
                                <p className="text-red-600"><strong>Último error:</strong> {data.last_error}</p>
                            )}
                        </div>
                    </div>
                )}

                {messages[exchange] && (
                    <div className={`mb-4 p-3 rounded-md ${
                        messages[exchange].type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'
                    }`}>
                        {messages[exchange].text}
                    </div>
                )}

                <div className="space-y-4">
                    <div>
                        <label htmlFor={`${exchange}_api_key`} className="block text-sm font-medium text-gray-700">
                            API Key
                        </label>
                        <input
                            type="password"
                            id={`${exchange}_api_key`}
                            value={formData[exchange].api_key}
                            onChange={(e) => handleInputChange(exchange, 'api_key', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            placeholder="Ingresa tu API Key"
                        />
                    </div>

                    <div>
                        <label htmlFor={`${exchange}_secret_key`} className="block text-sm font-medium text-gray-700">
                            Secret Key
                        </label>
                        <input
                            type="password"
                            id={`${exchange}_secret_key`}
                            value={formData[exchange].secret_key}
                            onChange={(e) => handleInputChange(exchange, 'secret_key', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            placeholder="Ingresa tu Secret Key"
                        />
                    </div>

                    <div className="flex items-center">
                        <input
                            type="checkbox"
                            id={`${exchange}_testnet`}
                            checked={formData[exchange].testnet}
                            onChange={(e) => handleInputChange(exchange, 'testnet', e.target.checked)}
                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label htmlFor={`${exchange}_testnet`} className="ml-2 block text-sm text-gray-900">
                            Usar Testnet (entorno de pruebas)
                        </label>
                    </div>

                    <div className="flex space-x-3">
                        <button
                            type="button"
                            onClick={() => handleTestConnection(exchange)}
                            disabled={!formData[exchange].api_key || !formData[exchange].secret_key || loading[exchange]}
                            className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {loading[exchange] ? 'Probando...' : 'Probar Conexión'}
                        </button>

                        <button
                            type="button"
                            onClick={() => handleSave(exchange)}
                            disabled={!formData[exchange].api_key || !formData[exchange].secret_key || loading[`${exchange}_save`]}
                            className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {loading[`${exchange}_save`] ? 'Guardando...' : 'Guardar'}
                        </button>

                        {hasCredentials && (
                            <button
                                type="button"
                                onClick={() => handleDelete(exchange)}
                                disabled={loading[`${exchange}_delete`]}
                                className="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {loading[`${exchange}_delete`] ? 'Eliminando...' : 'Eliminar'}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        );
    };

    return (
        <AppLayout header="Configuración de Exchanges">
            <Head title="Configuración de Exchanges" />

            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-bold text-gray-900">Credenciales de Exchanges</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Configura tus credenciales API para acceder a los datos de Binance, Bybit y OKX.
                    </p>
                </div>

                {/* Tab Navigation */}
                <div className="border-b border-gray-200">
                    <nav className="-mb-px flex space-x-8">
                        <button
                            onClick={() => setActiveTab('binance')}
                            className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                activeTab === 'binance'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                        >
                            Binance
                        </button>
                        <button
                            onClick={() => setActiveTab('bybit')}
                            className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                activeTab === 'bybit'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                        >
                            Bybit
                        </button>
                        <button
                            onClick={() => setActiveTab('okx')}
                            className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                activeTab === 'okx'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                        >
                            OKX
                        </button>
                    </nav>
                </div>

                {/* Tab Content */}
                {activeTab === 'binance' && (
                    <ExchangeForm exchange="binance" data={binance} label="Binance" />
                )}
                {activeTab === 'bybit' && (
                    <ExchangeForm exchange="bybit" data={bybit} label="Bybit" />
                )}
                {activeTab === 'okx' && (
                    <ExchangeForm exchange="okx" data={okx} label="OKX" />
                )}
            </div>
        </AppLayout>
    );
}

