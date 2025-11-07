import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import BotConfiguration from '@/Components/BotConfiguration';
import {
    BarChart,
    Bar,
    LineChart,
    Line,
    PieChart,
    Pie,
    Cell,
    AreaChart,
    Area,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from 'recharts';

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

export default function Dashboard({ stats, currency = 'USD', charts, recent_transactions = [] }) {
    const [activeTab, setActiveTab] = useState('overview');

    // Mapeo de códigos de moneda a símbolos
    const getCurrencySymbol = (currencyCode) => {
        const symbols = {
            'USD': '$',
            'EUR': '€',
            'COP': '$',
            'ARS': '$',
            'MXN': '$',
            'BRL': 'R$',
            'GBP': '£',
        };
        return symbols[currencyCode] || currencyCode;
    };

    const formatCurrency = (value) => {
        if (!value || isNaN(value)) return `${getCurrencySymbol(currency)}0.00`;
        const useDecimals = !['JPY'].includes(currency);
        try {
            return new Intl.NumberFormat('es-ES', {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: useDecimals ? 2 : 0,
                maximumFractionDigits: useDecimals ? 2 : 0,
            }).format(value);
        } catch (e) {
            return `${getCurrencySymbol(currency)}${value.toLocaleString('es-ES', {
                minimumFractionDigits: useDecimals ? 2 : 0,
                maximumFractionDigits: useDecimals ? 2 : 0,
            })}`;
        }
    };

    const formatNumber = (value) => {
        if (!value || isNaN(value)) return '0';
        return new Intl.NumberFormat('es-ES').format(value);
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        
        // Parsear la fecha como UTC para evitar conversión de zona horaria
        if (typeof dateString === 'string' && dateString.includes('T')) {
            const isoDate = new Date(dateString);
            // Obtener componentes UTC
            let year = isoDate.getUTCFullYear();
            let month = isoDate.getUTCMonth();
            let day = isoDate.getUTCDate();
            let hours = isoDate.getUTCHours();
            let minutes = isoDate.getUTCMinutes();
            
            // Restar 5 horas (UTC-5)
            hours -= 5;
            
            // Ajustar si las horas son negativas (cambiar al día anterior)
            if (hours < 0) {
                hours += 24;
                day -= 1;
                // Ajustar mes y año si es necesario
                if (day < 1) {
                    month -= 1;
                    if (month < 0) {
                        month = 11;
                        year -= 1;
                    }
                    // Obtener días del mes anterior
                    const daysInPreviousMonth = new Date(year, month + 1, 0).getDate();
                    day = daysInPreviousMonth;
                }
            }
            
            const monthNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                               'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            
            const formattedDate = `${day} de ${monthNames[month]} de ${year}`;
            const period = hours >= 12 ? 'p. m.' : 'a. m.';
            const displayHours = hours === 0 ? 12 : (hours > 12 ? hours - 12 : hours);
            
            return `${formattedDate}, ${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`;
        }
        
        // Fallback
        const date = new Date(dateString);
        // Crear una nueva fecha con offset de -5 horas
        const offsetDate = new Date(date.getTime() - 5 * 60 * 60 * 1000);
        return offsetDate.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'UTC'
        });
    };

    const getStatusColor = (status) => {
        const colors = {
            'completed': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'processing': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'cancelled': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            'failed': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        };
        return colors[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    };

    const getOrderTypeColor = (type) => {
        return type === 'BUY' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
    };

    const statCards = [
        {
            name: 'Total Transacciones',
            value: formatNumber(stats.total_transactions),
            subtitle: `${stats.completed_transactions} completadas`,
            change: stats.success_rate > 0 ? `${stats.success_rate}% éxito` : null,
            changeType: 'info',
            icon: '📊',
            color: 'blue',
        },
        {
            name: 'Volumen Total',
            value: formatCurrency(stats.total_volume),
            subtitle: `Hoy: ${formatCurrency(stats.today_volume)}`,
            change: stats.volume_change_today !== 0 
                ? `${stats.volume_change_today > 0 ? '+' : ''}${stats.volume_change_today.toFixed(1)}% vs ayer`
                : null,
            changeType: stats.volume_change_today >= 0 ? 'positive' : 'negative',
            icon: '💰',
            color: 'green',
        },
        {
            name: 'Bots Activos',
            value: stats.active_bots,
            subtitle: stats.active_bots > 0 ? 'Funcionando' : 'Sin bots activos',
            change: null,
            changeType: 'info',
            icon: '🤖',
            color: 'purple',
        },
        {
            name: 'Comisiones Totales',
            value: formatCurrency(stats.total_commissions),
            subtitle: `Esta semana: ${formatCurrency(stats.this_week_volume)}`,
            change: stats.volume_change_week !== 0
                ? `${stats.volume_change_week > 0 ? '+' : ''}${stats.volume_change_week.toFixed(1)}% vs semana pasada`
                : null,
            changeType: stats.volume_change_week >= 0 ? 'positive' : 'negative',
            icon: '💸',
            color: 'yellow',
        },
    ];

    return (
        <AppLayout header="Dashboard">
            <Head title="Dashboard" />
            
            <div className="space-y-6">
                {/* Tab Navigation */}
                <div className="border-b border-gray-200 dark:border-gray-700">
                    <nav className="-mb-px flex space-x-8">
                        <button
                            onClick={() => setActiveTab('overview')}
                            className={`py-2 px-1 border-b-2 font-medium text-sm transition-colors ${
                                activeTab === 'overview'
                                    ? 'border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'
                            }`}
                        >
                            Resumen
                        </button>
                        <button
                            onClick={() => setActiveTab('bot-config')}
                            className={`py-2 px-1 border-b-2 font-medium text-sm transition-colors ${
                                activeTab === 'bot-config'
                                    ? 'border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'
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
                        <div key={card.name} className="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow border border-gray-200 dark:border-gray-700">
                            <div className="p-5">
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-3 mb-2">
                                            <span className="text-2xl">{card.icon}</span>
                                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                                {card.name}
                                            </dt>
                                        </div>
                                        <dd className="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                                            {card.value}
                                        </dd>
                                        {card.subtitle && (
                                            <dd className="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                                {card.subtitle}
                                            </dd>
                                        )}
                                        {card.change && (
                                            <dd className={`text-xs font-medium ${
                                                card.changeType === 'positive' ? 'text-green-600 dark:text-green-400' : 
                                                card.changeType === 'negative' ? 'text-red-600 dark:text-red-400' : 
                                                'text-blue-600 dark:text-blue-400'
                                            }`}>
                                                {card.change}
                                            </dd>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Gráficos principales */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Gráfico: Transacciones por día */}
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            Actividad de los Últimos 7 Días
                        </h3>
                        <ResponsiveContainer width="100%" height={250}>
                            <AreaChart data={charts.transactions_by_day || []}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis 
                                    dataKey="date" 
                                    tickFormatter={(value) => new Date(value).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' })}
                                />
                                <YAxis yAxisId="left" />
                                <YAxis yAxisId="right" orientation="right" tickFormatter={(value) => formatCurrency(value)} />
                                <Tooltip 
                                    formatter={(value, name) => {
                                        if (name === 'volume') return formatCurrency(value);
                                        return formatNumber(value);
                                    }}
                                    labelFormatter={(label) => new Date(label).toLocaleDateString('es-ES')}
                                />
                                <Legend />
                                <Area yAxisId="left" type="monotone" dataKey="count" stackId="1" stroke="#3b82f6" fill="#3b82f6" fillOpacity={0.6} name="Operaciones" />
                                <Line yAxisId="right" type="monotone" dataKey="volume" stroke="#10b981" strokeWidth={2} name="Volumen" />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>

                    {/* Gráfico: Compras vs Ventas */}
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            Distribución por Tipo de Operación
                        </h3>
                        <ResponsiveContainer width="100%" height={250}>
                            <PieChart>
                                <Pie
                                    data={charts.operations_by_type || []}
                                    cx="50%"
                                    cy="50%"
                                    labelLine={false}
                                    label={({ type, count, percent }) => `${type}: ${count} (${(percent * 100).toFixed(0)}%)`}
                                    outerRadius={80}
                                    fill="#8884d8"
                                    dataKey="count"
                                >
                                    {(charts.operations_by_type || []).map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip />
                            </PieChart>
                        </ResponsiveContainer>
                        <div className="mt-4 space-y-2">
                            {(charts.operations_by_type || []).map((item) => (
                                <div key={item.type} className="flex justify-between items-center text-sm">
                                    <span className={`font-medium ${getOrderTypeColor(item.type)}`}>
                                        {item.type}:
                                    </span>
                                    <div className="flex items-center gap-4">
                                        <span className="text-gray-600 dark:text-gray-300">{formatNumber(item.count)} ops</span>
                                        <span className="text-gray-500 dark:text-gray-400">{formatCurrency(item.volume)}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Top Activos y Estado */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Top 5 Activos */}
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            Top 5 Activos por Volumen
                        </h3>
                        <div className="space-y-3">
                            {(charts.top_assets || []).map((asset, index) => (
                                <div key={asset.asset} className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div className="flex items-center gap-3">
                                        <div className="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center font-bold text-blue-600 dark:text-blue-300">
                                            {index + 1}
                                        </div>
                                        <div>
                                            <div className="font-medium text-gray-900 dark:text-white">{asset.asset}</div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400">{formatNumber(asset.count)} operaciones</div>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="font-semibold text-gray-900 dark:text-white">{formatCurrency(asset.volume)}</div>
                                        <div className="text-xs text-gray-500 dark:text-gray-400">{currency}</div>
                                    </div>
                                </div>
                            ))}
                            {(!charts.top_assets || charts.top_assets.length === 0) && (
                                <div className="text-center text-gray-500 dark:text-gray-400 py-8">
                                    No hay datos de activos disponibles
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Operaciones por Estado */}
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            Estado de Transacciones
                        </h3>
                        <ResponsiveContainer width="100%" height={200}>
                            <PieChart>
                                <Pie
                                    data={charts.operations_by_status || []}
                                    cx="50%"
                                    cy="50%"
                                    labelLine={false}
                                    label={({ status, count, percent }) => `${status}: ${(percent * 100).toFixed(0)}%`}
                                    outerRadius={70}
                                    fill="#8884d8"
                                    dataKey="count"
                                >
                                    {(charts.operations_by_status || []).map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip />
                            </PieChart>
                        </ResponsiveContainer>
                        <div className="mt-4 space-y-2">
                            {(charts.operations_by_status || []).map((item) => (
                                <div key={item.status} className="flex justify-between items-center text-sm">
                                    <span className={`px-2 py-1 rounded text-xs font-medium ${getStatusColor(item.status)}`}>
                                        {item.status}
                                    </span>
                                    <span className="text-gray-600 dark:text-gray-300 font-medium">{formatNumber(item.count)}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Transacciones Recientes */}
                <div className="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
                    <div className="px-4 py-5 sm:p-6 border-b border-gray-200 dark:border-gray-700">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                                Transacciones Recientes
                            </h3>
                            <Link 
                                href="/transactions"
                                className="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium transition-colors"
                            >
                                Ver todas →
                            </Link>
                        </div>
                    </div>
                    <div className="overflow-hidden">
                        {recent_transactions && recent_transactions.length > 0 ? (
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Orden
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Tipo
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Activo
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Operación
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Monto
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Fecha
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    {recent_transactions.map((transaction) => (
                                        <tr key={transaction.id} className="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-white">
                                                {transaction.order_number.substring(0, 8)}...
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {transaction.transaction_type}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                {transaction.asset_type}
                                            </td>
                                            <td className={`px-6 py-4 whitespace-nowrap text-sm font-medium ${getOrderTypeColor(transaction.order_type)}`}>
                                                {transaction.order_type || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {formatCurrency(transaction.total_price || 0)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusColor(transaction.status)}`}>
                                                    {transaction.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {formatDate(transaction.binance_create_time)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <div className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <p>No hay transacciones recientes</p>
                                <Link 
                                    href="/transactions"
                                    className="mt-4 inline-block text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium transition-colors"
                                >
                                    Ver todas las transacciones →
                                </Link>
                            </div>
                        )}
                    </div>
                </div>

                {/* Quick Actions */}
                <div className="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
                    <div className="px-4 py-5 sm:p-6">
                        <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                            Acciones Rápidas
                        </h3>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Link 
                                href="/transactions"
                                className="relative group bg-white dark:bg-gray-700 p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 dark:focus-within:ring-blue-400 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-blue-300 dark:hover:border-blue-500 hover:shadow-md transition-all"
                            >
                                <div>
                                    <span className="rounded-lg inline-flex p-3 bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300 ring-4 ring-white dark:ring-gray-700">
                                        📊
                                    </span>
                                </div>
                                <div className="mt-4">
                                    <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                                        Ver Transacciones
                                    </h3>
                                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        Revisa tu actividad comercial reciente
                                    </p>
                                </div>
                            </Link>

                            <Link 
                                href="/reports"
                                className="relative group bg-white dark:bg-gray-700 p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 dark:focus-within:ring-blue-400 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-green-300 dark:hover:border-green-500 hover:shadow-md transition-all"
                            >
                                <div>
                                    <span className="rounded-lg inline-flex p-3 bg-green-50 dark:bg-green-900 text-green-700 dark:text-green-300 ring-4 ring-white dark:ring-gray-700">
                                        📈
                                    </span>
                                </div>
                                <div className="mt-4">
                                    <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                                        Ver Reportes
                                    </h3>
                                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        Analiza tu rendimiento con gráficos detallados
                                    </p>
                                </div>
                            </Link>

                            <Link 
                                href="/settings/exchanges"
                                className="relative group bg-white dark:bg-gray-700 p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 dark:focus-within:ring-blue-400 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-purple-300 dark:hover:border-purple-500 hover:shadow-md transition-all"
                            >
                                <div>
                                    <span className="rounded-lg inline-flex p-3 bg-purple-50 dark:bg-purple-900 text-purple-700 dark:text-purple-300 ring-4 ring-white dark:ring-gray-700">
                                        ⚙️
                                    </span>
                                </div>
                                <div className="mt-4">
                                    <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                                        Configuración
                                    </h3>
                                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        Configura tus claves API y preferencias
                                    </p>
                                </div>
                            </Link>
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
