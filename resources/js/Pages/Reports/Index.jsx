import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
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
    ComposedChart,
} from 'recharts';

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];

export default function Reports({ dateRange, currency = 'USD', metrics, charts }) {
    const [startDate, setStartDate] = useState(dateRange.start_date);
    const [endDate, setEndDate] = useState(dateRange.end_date);

    const handleDateChange = () => {
        router.get('/reports', {
            start_date: startDate,
            end_date: endDate,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Mapeo de códigos de moneda a símbolos comunes
    const getCurrencySymbol = (currencyCode) => {
        const symbols = {
            'USD': '$',
            'EUR': '€',
            'COP': '$',
            'ARS': '$',
            'MXN': '$',
            'BRL': 'R$',
            'GBP': '£',
            'JPY': '¥',
            'CNY': '¥',
        };
        return symbols[currencyCode] || currencyCode;
    };

    const formatCurrency = (value) => {
        if (!value || isNaN(value)) return `${getCurrencySymbol(currency)}0.00`;
        
        // Para algunas monedas como JPY, no usar decimales
        const useDecimals = !['JPY'].includes(currency);
        
        try {
            return new Intl.NumberFormat('es-ES', {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: useDecimals ? 2 : 0,
                maximumFractionDigits: useDecimals ? 2 : 0,
            }).format(value);
        } catch (e) {
            // Fallback si la moneda no es válida para Intl.NumberFormat
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

    // Asegurar que los arrays de datos existan
    const safeCharts = {
        volume_by_day: charts.volume_by_day || [],
        buy_vs_sell_by_day: charts.buy_vs_sell_by_day || [],
        operations_by_type: charts.operations_by_type || [],
        operations_by_payment_method: charts.operations_by_payment_method || [],
        operations_by_transaction_type: charts.operations_by_transaction_type || [],
        operations_by_status: charts.operations_by_status || [],
        operations_by_asset: charts.operations_by_asset || [],
        operations_by_month: charts.operations_by_month || [],
        top_counter_parties: charts.top_counter_parties || [],
        operations_by_hour: charts.operations_by_hour || [],
        profitability_by_asset: charts.profitability_by_asset || [],
    };

    return (
        <AppLayout header="Reportes">
            <Head title="Reportes" />
            
            <div className="space-y-6">
                {/* Filtros de fecha */}
                <div className="bg-white shadow rounded-lg p-4">
                    <div className="flex items-center gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Fecha Inicio
                            </label>
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                className="border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Fecha Fin
                            </label>
                            <input
                                type="date"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                                className="border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>
                        <div className="flex items-end">
                            <button
                                onClick={handleDateChange}
                                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                Aplicar Filtros
                            </button>
                        </div>
                    </div>
                </div>

                {/* Métricas principales */}
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <div className="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                        <span className="text-white text-sm font-medium">V</span>
                                    </div>
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">
                                            Volumen Total
                                        </dt>
                                        <dd className="text-2xl font-semibold text-gray-900">
                                            {formatCurrency(metrics.total_volume)}
                                        </dd>
                                        <dd className="text-xs text-gray-400 mt-1">
                                            {currency}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <div className="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                        <span className="text-white text-sm font-medium">P</span>
                                    </div>
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">
                                            Precio Promedio
                                        </dt>
                                        <dd className="text-2xl font-semibold text-gray-900">
                                            {formatCurrency(metrics.avg_price)}
                                        </dd>
                                        <dd className="text-xs text-gray-400 mt-1">
                                            {currency}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <div className="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                        <span className="text-white text-sm font-medium">O</span>
                                    </div>
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">
                                            Operaciones
                                        </dt>
                                        <dd className="text-2xl font-semibold text-gray-900">
                                            {formatNumber(metrics.total_operations)}
                                        </dd>
                                        <dd className="text-sm text-gray-500">
                                            {metrics.completed_operations} completadas
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <div className="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                        <span className="text-white text-sm font-medium">C</span>
                                    </div>
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">
                                            Comisiones Totales
                                        </dt>
                                        <dd className="text-2xl font-semibold text-gray-900">
                                            {formatCurrency(metrics.total_commissions)}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Gráfico 1: Volumen por día */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">
                        Volumen Total por Día
                    </h3>
                    <ResponsiveContainer width="100%" height={300}>
                        <AreaChart data={safeCharts.volume_by_day}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis 
                                dataKey="date" 
                                tickFormatter={(value) => new Date(value).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' })}
                            />
                            <YAxis tickFormatter={(value) => `$${formatNumber(value)}`} />
                            <Tooltip 
                                formatter={(value) => formatCurrency(value)}
                                labelFormatter={(label) => new Date(label).toLocaleDateString('es-ES')}
                            />
                            <Area 
                                type="monotone" 
                                dataKey="volume" 
                                stroke="#3b82f6" 
                                fill="#3b82f6" 
                                fillOpacity={0.6}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>

                {/* Gráfico 2: Compras vs Ventas por día */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">
                        Comparación de Compras vs Ventas por Día
                    </h3>
                    <ResponsiveContainer width="100%" height={300}>
                        <ComposedChart data={safeCharts.buy_vs_sell_by_day}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis 
                                dataKey="date" 
                                tickFormatter={(value) => new Date(value).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' })}
                            />
                            <YAxis yAxisId="left" tickFormatter={(value) => `$${formatNumber(value)}`} />
                            <YAxis yAxisId="right" orientation="right" />
                            <Tooltip 
                                formatter={(value, name) => {
                                    if (name.includes('volume')) return formatCurrency(value);
                                    return formatNumber(value);
                                }}
                                labelFormatter={(label) => new Date(label).toLocaleDateString('es-ES')}
                            />
                            <Legend />
                            <Bar yAxisId="left" dataKey="buy_volume" fill="#10b981" name="Volumen Compras" />
                            <Bar yAxisId="left" dataKey="sell_volume" fill="#ef4444" name="Volumen Ventas" />
                            <Line yAxisId="right" type="monotone" dataKey="buy_count" stroke="#84cc16" name="Cantidad Compras" strokeWidth={2} />
                            <Line yAxisId="right" type="monotone" dataKey="sell_count" stroke="#f59e0b" name="Cantidad Ventas" strokeWidth={2} />
                        </ComposedChart>
                    </ResponsiveContainer>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Gráfico 3: Operaciones por tipo (BUY/SELL) */}
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">
                            Operaciones por Tipo (Compra/Venta)
                        </h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <PieChart>
                                <Pie
                                    data={safeCharts.operations_by_type}
                                    cx="50%"
                                    cy="50%"
                                    labelLine={false}
                                    label={({ type, count, percent }) => `${type}: ${count} (${(percent * 100).toFixed(0)}%)`}
                                    outerRadius={80}
                                    fill="#8884d8"
                                    dataKey="count"
                                >
                                    {safeCharts.operations_by_type.map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip />
                            </PieChart>
                        </ResponsiveContainer>
                        <div className="mt-4 space-y-2">
                            {safeCharts.operations_by_type.map((item, index) => (
                                <div key={item.type} className="flex justify-between items-center">
                                    <span className="text-sm text-gray-600">{item.type}:</span>
                                    <div className="flex items-center gap-4">
                                        <span className="text-sm font-medium">{formatNumber(item.count)} ops</span>
                                        <span className="text-sm text-gray-500">{formatCurrency(item.volume)}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Gráfico 4: Operaciones por método de pago */}
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">
                            Operaciones por Método de Pago
                        </h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={safeCharts.operations_by_payment_method}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="method" angle={-45} textAnchor="end" height={100} />
                                <YAxis />
                                <Tooltip formatter={(value) => formatNumber(value)} />
                                <Legend />
                                <Bar dataKey="count" fill="#3b82f6" name="Cantidad" />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Gráfico 5: Operaciones por tipo de transacción */}
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">
                            Operaciones por Tipo de Transacción
                        </h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <PieChart>
                                <Pie
                                    data={safeCharts.operations_by_transaction_type}
                                    cx="50%"
                                    cy="50%"
                                    labelLine={false}
                                    label={({ type, count, percent }) => `${type}: ${(percent * 100).toFixed(0)}%`}
                                    outerRadius={80}
                                    fill="#8884d8"
                                    dataKey="count"
                                >
                                    {safeCharts.operations_by_transaction_type.map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>

                    {/* Gráfico 6: Operaciones por estado */}
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">
                            Distribución por Estado
                        </h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <PieChart>
                                <Pie
                                    data={safeCharts.operations_by_status}
                                    cx="50%"
                                    cy="50%"
                                    labelLine={false}
                                    label={({ status, count, percent }) => `${status}: ${(percent * 100).toFixed(0)}%`}
                                    outerRadius={80}
                                    fill="#8884d8"
                                    dataKey="count"
                                >
                                    {safeCharts.operations_by_status.map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                {/* Gráfico 7: Operaciones por activo */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">
                        Top 10 Activos por Volumen
                    </h3>
                    <ResponsiveContainer width="100%" height={400}>
                        <BarChart data={safeCharts.operations_by_asset} layout="vertical">
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis type="number" tickFormatter={(value) => `$${formatNumber(value)}`} />
                            <YAxis dataKey="asset" type="category" width={80} />
                            <Tooltip 
                                formatter={(value, name) => {
                                    if (name === 'volume' || name === 'avg_price' || name === 'total_fees') {
                                        return formatCurrency(value);
                                    }
                                    return formatNumber(value);
                                }}
                            />
                            <Legend />
                            <Bar dataKey="volume" fill="#3b82f6" name="Volumen Total" />
                            <Bar dataKey="total_fees" fill="#ef4444" name="Comisiones Totales" />
                        </BarChart>
                    </ResponsiveContainer>
                    <div className="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {safeCharts.operations_by_asset.map((item) => (
                            <div key={item.asset} className="bg-gray-50 p-3 rounded">
                                <div className="font-medium text-gray-900">{item.asset}</div>
                                <div className="text-sm text-gray-600">
                                    {formatNumber(item.count)} operaciones
                                </div>
                                <div className="text-sm text-gray-600">
                                    Precio promedio: {formatCurrency(item.avg_price)}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Gráfico 8: Comisiones por compra vs venta */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">
                        Comisiones por Tipo de Operación
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div className="bg-blue-50 p-4 rounded-lg">
                            <div className="text-sm font-medium text-gray-600">Comisiones por Compra</div>
                            <div className="text-2xl font-bold text-blue-600">
                                {formatCurrency(metrics.buy_commissions)}
                            </div>
                        </div>
                        <div className="bg-red-50 p-4 rounded-lg">
                            <div className="text-sm font-medium text-gray-600">Comisiones por Venta</div>
                            <div className="text-2xl font-bold text-red-600">
                                {formatCurrency(metrics.sell_commissions)}
                            </div>
                        </div>
                    </div>
                    <ResponsiveContainer width="100%" height={200}>
                        <BarChart data={[
                            { name: 'Compras', value: metrics.buy_commissions },
                            { name: 'Ventas', value: metrics.sell_commissions },
                        ]}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="name" />
                            <YAxis tickFormatter={(value) => `$${formatNumber(value)}`} />
                            <Tooltip formatter={(value) => formatCurrency(value)} />
                            <Bar dataKey="value" fill="#3b82f6" />
                        </BarChart>
                    </ResponsiveContainer>
                </div>

                {/* Gráfico 9: Operaciones por mes */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">
                        Resumen Mensual
                    </h3>
                    <ResponsiveContainer width="100%" height={300}>
                        <ComposedChart data={safeCharts.operations_by_month}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="month" />
                            <YAxis yAxisId="left" tickFormatter={(value) => `$${formatNumber(value)}`} />
                            <YAxis yAxisId="right" orientation="right" />
                            <Tooltip 
                                formatter={(value, name) => {
                                    if (name === 'volume' || name === 'fees') return formatCurrency(value);
                                    return formatNumber(value);
                                }}
                            />
                            <Legend />
                            <Bar yAxisId="left" dataKey="volume" fill="#3b82f6" name="Volumen" />
                            <Bar yAxisId="left" dataKey="fees" fill="#ef4444" name="Comisiones" />
                            <Line yAxisId="right" type="monotone" dataKey="count" stroke="#10b981" name="Cantidad" strokeWidth={2} />
                        </ComposedChart>
                    </ResponsiveContainer>
                </div>

                {/* Gráfico 10: Operaciones por hora del día */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">
                        Distribución de Operaciones por Hora del Día
                    </h3>
                    <ResponsiveContainer width="100%" height={300}>
                        <BarChart data={safeCharts.operations_by_hour}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis 
                                dataKey="hour" 
                                label={{ value: 'Hora del día', position: 'insideBottom', offset: -5 }}
                            />
                            <YAxis yAxisId="left" />
                            <YAxis yAxisId="right" orientation="right" tickFormatter={(value) => `$${formatNumber(value)}`} />
                            <Tooltip 
                                formatter={(value, name) => {
                                    if (name === 'volume') return formatCurrency(value);
                                    return formatNumber(value);
                                }}
                            />
                            <Legend />
                            <Bar yAxisId="left" dataKey="count" fill="#8b5cf6" name="Cantidad de Operaciones" />
                            <Line yAxisId="right" type="monotone" dataKey="volume" stroke="#f59e0b" name="Volumen" strokeWidth={2} />
                        </BarChart>
                    </ResponsiveContainer>
                </div>

                {/* Gráfico 11: Rentabilidad por activo */}
                {safeCharts.profitability_by_asset.length > 0 && (
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">
                            Rentabilidad por Activo (Margen Promedio)
                        </h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={safeCharts.profitability_by_asset}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="asset" />
                                <YAxis label={{ value: 'Margen (%)', angle: -90, position: 'insideLeft' }} />
                                <Tooltip 
                                    formatter={(value, name) => {
                                        if (name === 'margin_percentage') return `${value}%`;
                                        return formatCurrency(value);
                                    }}
                                />
                                <Legend />
                                <Bar dataKey="margin_percentage" fill="#10b981" name="Margen %" />
                            </BarChart>
                        </ResponsiveContainer>
                        <div className="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {safeCharts.profitability_by_asset.map((item) => (
                                <div key={item.asset} className="bg-gray-50 p-3 rounded">
                                    <div className="font-medium text-gray-900">{item.asset}</div>
                                    <div className="text-sm text-gray-600">
                                        Precio compra: {formatCurrency(item.avg_buy_price)}
                                    </div>
                                    <div className="text-sm text-gray-600">
                                        Precio venta: {formatCurrency(item.avg_sell_price)}
                                    </div>
                                    <div className={`text-sm font-bold ${item.margin_percentage >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                        Margen: {item.margin_percentage}%
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Gráfico 12: Top contrapartes */}
                {safeCharts.top_counter_parties.length > 0 && (
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">
                            Top 10 Contrapartes (Más Operaciones)
                        </h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={safeCharts.top_counter_parties} layout="vertical">
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis type="number" />
                                <YAxis dataKey="counter_party" type="category" width={150} />
                                <Tooltip 
                                    formatter={(value, name) => {
                                        if (name === 'volume') return formatCurrency(value);
                                        return formatNumber(value);
                                    }}
                                />
                                <Legend />
                                <Bar dataKey="count" fill="#3b82f6" name="Cantidad de Operaciones" />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                )}

                {/* Métricas adicionales */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">
                        Métricas Adicionales
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className="bg-blue-50 p-4 rounded-lg">
                            <div className="text-sm font-medium text-gray-600">Tasa de Éxito</div>
                            <div className="text-2xl font-bold text-blue-600">
                                {metrics.success_rate}%
                            </div>
                            <div className="text-xs text-gray-500 mt-1">
                                {metrics.completed_operations} de {metrics.total_operations} operaciones
                            </div>
                        </div>
                        <div className="bg-green-50 p-4 rounded-lg">
                            <div className="text-sm font-medium text-gray-600">Comisiones por Compra</div>
                            <div className="text-2xl font-bold text-green-600">
                                {formatCurrency(metrics.buy_commissions)}
                            </div>
                        </div>
                        <div className="bg-red-50 p-4 rounded-lg">
                            <div className="text-sm font-medium text-gray-600">Comisiones por Venta</div>
                            <div className="text-2xl font-bold text-red-600">
                                {formatCurrency(metrics.sell_commissions)}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

