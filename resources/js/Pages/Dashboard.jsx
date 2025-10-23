import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import BotConfiguration from '@/Components/BotConfiguration';

export default function Dashboard({ stats }) {
    const [activeTab, setActiveTab] = useState('overview');

    const statCards = [
        {
            name: 'Total Transactions',
            value: stats.total_transactions,
            change: '+12%',
            changeType: 'positive',
        },
        {
            name: 'Active Bots',
            value: stats.active_bots,
            change: '+2',
            changeType: 'positive',
        },
        {
            name: 'Total Volume',
            value: `$${stats.total_volume.toLocaleString()}`,
            change: '+8.2%',
            changeType: 'positive',
        },
        {
            name: 'Profit/Loss',
            value: `$${stats.profit_loss.toLocaleString()}`,
            change: '+5.4%',
            changeType: 'positive',
        },
    ];

    return (
        <AppLayout header="Dashboard">
            <Head title="Dashboard" />
            
            <div className="space-y-6">
                {/* Tab Navigation */}
                <div className="border-b border-gray-200">
                    <nav className="-mb-px flex space-x-8">
                        <button
                            onClick={() => setActiveTab('overview')}
                            className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                activeTab === 'overview'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                        >
                            Resumen
                        </button>
                        <button
                            onClick={() => setActiveTab('bot-config')}
                            className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                activeTab === 'bot-config'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                        >
                            Configuración del Bot
                        </button>
                    </nav>
                </div>

                {/* Tab Content */}
                {activeTab === 'overview' && (
                    <div className="space-y-6">
                {/* Stats Grid */}
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {statCards.map((card) => (
                        <div key={card.name} className="bg-white overflow-hidden shadow rounded-lg">
                            <div className="p-5">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <div className="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                            <span className="text-white text-sm font-medium">
                                                {card.name.charAt(0)}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt className="text-sm font-medium text-gray-500 truncate">
                                                {card.name}
                                            </dt>
                                            <dd className="flex items-baseline">
                                                <div className="text-2xl font-semibold text-gray-900">
                                                    {card.value}
                                                </div>
                                                <div className={`ml-2 flex items-baseline text-sm font-semibold ${
                                                    card.changeType === 'positive' ? 'text-green-600' : 'text-red-600'
                                                }`}>
                                                    {card.change}
                                                </div>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Main Content Area */}
                <div className="bg-white shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Welcome to your Binance Trading Dashboard
                        </h3>
                        <div className="text-sm text-gray-500 space-y-2">
                            <p>This is your central hub for managing your Binance trading activities.</p>
                            <p>Here you can:</p>
                            <ul className="list-disc list-inside ml-4 space-y-1">
                                <li>Track your transaction history</li>
                                <li>Monitor your trading bots</li>
                                <li>Analyze your trading performance</li>
                                <li>Configure automated trading strategies</li>
                            </ul>
                            <p className="mt-4 text-blue-600">
                                Start by connecting your Binance API keys in the Settings section.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Quick Actions */}
                <div className="bg-white shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Quick Actions
                        </h3>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <button className="relative group bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg border border-gray-200 hover:border-gray-300">
                                <div>
                                    <span className="rounded-lg inline-flex p-3 bg-blue-50 text-blue-700 ring-4 ring-white">
                                        📊
                                    </span>
                                </div>
                                <div className="mt-4">
                                    <h3 className="text-lg font-medium">
                                        <span className="absolute inset-0" aria-hidden="true" />
                                        View Transactions
                                    </h3>
                                    <p className="mt-2 text-sm text-gray-500">
                                        Check your recent trading activity
                                    </p>
                                </div>
                            </button>

                            <button className="relative group bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg border border-gray-200 hover:border-gray-300">
                                <div>
                                    <span className="rounded-lg inline-flex p-3 bg-green-50 text-green-700 ring-4 ring-white">
                                        🤖
                                    </span>
                                </div>
                                <div className="mt-4">
                                    <h3 className="text-lg font-medium">
                                        <span className="absolute inset-0" aria-hidden="true" />
                                        Manage Bots
                                    </h3>
                                    <p className="mt-2 text-sm text-gray-500">
                                        Configure and monitor your trading bots
                                    </p>
                                </div>
                            </button>

                            <button className="relative group bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg border border-gray-200 hover:border-gray-300">
                                <div>
                                    <span className="rounded-lg inline-flex p-3 bg-purple-50 text-purple-700 ring-4 ring-white">
                                        ⚙️
                                    </span>
                                </div>
                                <div className="mt-4">
                                    <h3 className="text-lg font-medium">
                                        <span className="absolute inset-0" aria-hidden="true" />
                                        Settings
                                    </h3>
                                    <p className="mt-2 text-sm text-gray-500">
                                        Configure your API keys and preferences
                                    </p>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
                    </div>
                )}

                {activeTab === 'bot-config' && (
                    <BotConfiguration />
                )}
            </div>
        </AppLayout>
    );
}
