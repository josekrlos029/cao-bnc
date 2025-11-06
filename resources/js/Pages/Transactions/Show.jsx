import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function TransactionsShow({ transaction }) {
    const formatNumber = (num) => {
        if (!num && num !== 0) return '-';
        return new Intl.NumberFormat('es-CO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 8
        }).format(num);
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        
        // Parsear la fecha como UTC para evitar conversión de zona horaria
        if (typeof dateString === 'string' && dateString.includes('T')) {
            const isoDate = new Date(dateString);
            // Usar UTC para formatear sin conversión
            const year = isoDate.getUTCFullYear();
            const month = isoDate.getUTCMonth();
            const day = isoDate.getUTCDate();
            const hours = isoDate.getUTCHours();
            const minutes = isoDate.getUTCMinutes();
            
            const monthNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                               'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            
            const formattedDate = `${day} de ${monthNames[month]} de ${year}`;
            const period = hours >= 12 ? 'p. m.' : 'a. m.';
            const displayHours = hours === 0 ? 12 : (hours > 12 ? hours - 12 : hours);
            
            return `${formattedDate}, ${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`;
        }
        
        // Fallback
        const date = new Date(dateString);
        return date.toLocaleDateString('es-CO', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'UTC'
        });
    };

    const getStatusBadgeColor = (status) => {
        switch (status) {
            case 'completed': return 'bg-green-100 text-green-800';
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            case 'processing': return 'bg-blue-100 text-blue-800';
            case 'cancelled': return 'bg-gray-100 text-gray-800';
            case 'failed': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getTypeBadgeColor = (type) => {
        switch (type) {
            case 'spot_trade': return 'bg-blue-100 text-blue-800';
            case 'p2p_order': return 'bg-green-100 text-green-800';
            case 'deposit': return 'bg-yellow-100 text-yellow-800';
            case 'withdrawal': return 'bg-red-100 text-red-800';
            case 'manual_entry': return 'bg-purple-100 text-purple-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getOrderTypeBadge = (orderType) => {
        if (!orderType) return null;
        const isBuy = orderType === 'BUY';
        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                isBuy 
                    ? 'bg-green-100 text-green-800 border border-green-300' 
                    : 'bg-red-100 text-red-800 border border-red-300'
            }`}>
                {isBuy ? '↑ COMPRA' : '↓ VENTA'}
            </span>
        );
    };

    return (
        <AppLayout header="Detalle de Transacción">
            <Head title="Detalle de Transacción" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Detalle de Transacción</h1>
                        <p className="mt-1 text-sm text-gray-600">Información completa de la transacción</p>
                    </div>
                    <Link
                        href="/transactions"
                        className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                    >
                        ← Volver a la lista
                    </Link>
                </div>

                {/* Transaction Details */}
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <div className="px-6 py-5 border-b border-gray-200 bg-gray-50">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-3">
                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getTypeBadgeColor(transaction.transaction_type)}`}>
                                    {transaction.transaction_type.replace('_', ' ').toUpperCase()}
                                </span>
                                {getOrderTypeBadge(transaction.order_type)}
                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeColor(transaction.status)}`}>
                                    {transaction.status.toUpperCase()}
                                </span>
                            </div>
                            <div className="flex space-x-2">
                                <Link
                                    href={`/transactions/${transaction.id}/edit`}
                                    className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700"
                                >
                                    ✏️ Editar
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div className="px-6 py-5">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Información Básica */}
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 mb-4">Información Básica</h3>
                                <dl className="space-y-3">
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Número de Orden</dt>
                                        <dd className="mt-1 text-sm text-gray-900 font-mono">{transaction.order_number || '-'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Tipo de Transacción</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{transaction.transaction_type.replace('_', ' ').toUpperCase()}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Activo</dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            {transaction.asset_type}
                                            {transaction.fiat_type && (
                                                <span className="text-gray-500"> / {transaction.fiat_type}</span>
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Exchange</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{transaction.exchange || 'binance'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Estado</dt>
                                        <dd className="mt-1">
                                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeColor(transaction.status)}`}>
                                                {transaction.status.toUpperCase()}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            {/* Información Financiera */}
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 mb-4">Información Financiera</h3>
                                <dl className="space-y-3">
                                    {transaction.quantity && (
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Cantidad</dt>
                                            <dd className="mt-1 text-sm text-gray-900 font-semibold">{formatNumber(transaction.quantity)} {transaction.asset_type}</dd>
                                        </div>
                                    )}
                                    {transaction.price && (
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Precio Unitario</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{formatNumber(transaction.price)} {transaction.fiat_type || ''}</dd>
                                        </div>
                                    )}
                                    {transaction.total_price && (
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Precio Total</dt>
                                            <dd className="mt-1 text-sm text-gray-900 font-semibold">{formatNumber(transaction.total_price)} {transaction.fiat_type || ''}</dd>
                                        </div>
                                    )}
                                    {transaction.commission > 0 && (
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Comisión</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{formatNumber(transaction.commission)}</dd>
                                        </div>
                                    )}
                                    {transaction.taker_fee > 0 && (
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Taker Fee</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{formatNumber(transaction.taker_fee)}</dd>
                                        </div>
                                    )}
                                    {transaction.network_fee > 0 && (
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Network Fee</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{formatNumber(transaction.network_fee)}</dd>
                                        </div>
                                    )}
                                </dl>
                            </div>
                        </div>

                        {/* Información de Pago y Counter Party */}
                        {(transaction.payment_method || transaction.counter_party || transaction.counter_party_dni || transaction.dni_type) && (
                            <div className="mt-6 pt-6 border-t border-gray-200">
                                <h3 className="text-lg font-semibold text-gray-900 mb-4">Información de Pago y Counter Party</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <dl className="space-y-3">
                                        {transaction.payment_method && (
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Método de Pago</dt>
                                                <dd className="mt-1 text-sm text-gray-900">{transaction.payment_method}</dd>
                                            </div>
                                        )}
                                        {transaction.account_number && (
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Número de Cuenta</dt>
                                                <dd className="mt-1 text-sm text-gray-900">{transaction.account_number}</dd>
                                            </div>
                                        )}
                                    </dl>
                                    <dl className="space-y-3">
                                        {transaction.counter_party && (
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Counter Party</dt>
                                                <dd className="mt-1 text-sm text-gray-900 font-semibold">{transaction.counter_party}</dd>
                                            </div>
                                        )}
                                        {transaction.dni_type && (
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Tipo de Identificación</dt>
                                                <dd className="mt-1 text-sm text-gray-900">{transaction.dni_type}</dd>
                                            </div>
                                        )}
                                        {transaction.counter_party_dni && (
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Número de Documento</dt>
                                                <dd className="mt-1 text-sm text-gray-900 font-mono">{transaction.counter_party_dni}</dd>
                                            </div>
                                        )}
                                    </dl>
                                </div>
                            </div>
                        )}

                        {/* Notas */}
                        {transaction.notes && (
                            <div className="mt-6 pt-6 border-t border-gray-200">
                                <h3 className="text-lg font-semibold text-gray-900 mb-2">Notas</h3>
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">{transaction.notes}</p>
                            </div>
                        )}

                        {/* Información del Sistema */}
                        <div className="mt-6 pt-6 border-t border-gray-200">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">Información del Sistema</h3>
                            <dl className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">ID de Transacción</dt>
                                    <dd className="mt-1 text-sm text-gray-900 font-mono">#{transaction.id}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Fuente</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {transaction.is_manual_entry ? 'Entrada Manual' : 'Sincronizado'}
                                    </dd>
                                </div>
                                {transaction.binance_create_time && (
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Fecha de Creación (Binance)</dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            {formatDate(transaction.binance_create_time)}
                                        </dd>
                                    </div>
                                )}
                                {transaction.binance_update_time && (
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Fecha de Actualización (Binance)</dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            {formatDate(transaction.binance_update_time)}
                                        </dd>
                                    </div>
                                )}
                                {transaction.last_synced_at && (
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Última Sincronización</dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            {formatDate(transaction.last_synced_at)}
                                        </dd>
                                    </div>
                                )}
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Creado en el Sistema</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {formatDate(transaction.created_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Actualizado en el Sistema</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {formatDate(transaction.updated_at)}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

