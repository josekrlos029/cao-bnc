import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function TransactionsIndex({ 
    transactions, 
    stats, 
    enrichmentStats,
    filterOptions,
    filters 
}) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedExchange, setSelectedExchange] = useState(filters.exchange || '');
    const [selectedType, setSelectedType] = useState(filters.transaction_type || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [isSyncing, setIsSyncing] = useState(false);
    const [syncDialogOpen, setSyncDialogOpen] = useState(false);
    const [syncMessage, setSyncMessage] = useState(null);
    const [syncMessageType, setSyncMessageType] = useState(null);
    const [viewMode, setViewMode] = useState('table'); // 'table' or 'cards'
    const [syncMode, setSyncMode] = useState('preset'); // 'preset' or 'custom'
    const [customStartDate, setCustomStartDate] = useState('');
    const [customEndDate, setCustomEndDate] = useState('');

    // Polling automático cuando hay enriquecimiento activo
    useEffect(() => {
        if (enrichmentStats && enrichmentStats.has_active_enrichment) {
            const interval = setInterval(() => {
                // Recargar la página cada 5 segundos si hay enriquecimiento activo
                router.reload({ only: ['transactions', 'enrichmentStats', 'stats'] });
            }, 5000);

            return () => clearInterval(interval);
        }
    }, [enrichmentStats?.has_active_enrichment]);

    const handleSearch = () => {
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (selectedExchange) params.append('exchange', selectedExchange);
        if (selectedType) params.append('transaction_type', selectedType);
        if (selectedStatus) params.append('status', selectedStatus);
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        
        window.location.href = `/transactions?${params.toString()}`;
    };

    const handleSearchWithExchange = (exchange) => {
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (exchange) params.append('exchange', exchange);
        if (selectedType) params.append('transaction_type', selectedType);
        if (selectedStatus) params.append('status', selectedStatus);
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        
        window.location.href = `/transactions?${params.toString()}`;
    };

    const handleExportExcel = () => {
        // Construir la URL con los filtros actuales
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (selectedExchange) params.append('exchange', selectedExchange);
        if (selectedType) params.append('transaction_type', selectedType);
        if (selectedStatus) params.append('status', selectedStatus);
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        
        // También obtener los filtros de la URL si existen (asset_type, fiat_type)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('asset_type')) params.append('asset_type', urlParams.get('asset_type'));
        if (urlParams.get('fiat_type')) params.append('fiat_type', urlParams.get('fiat_type'));
        
        // Abrir la URL en una nueva ventana para descargar el archivo
        window.open(`/transactions/export/excel?${params.toString()}`, '_blank');
    };

    const handleSync = async (days = null, startDate = null, endDate = null) => {
        setIsSyncing(true);
        setSyncMessage(null);
        setSyncMessageType(null);
        
        try {
            const body = {};
            if (startDate && endDate) {
                body.start_date = startDate;
                body.end_date = endDate;
            } else if (days) {
                body.days = days;
            } else {
                body.days = 7; // Default fallback
            }
            
            const response = await fetch('/transactions/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(body)
            });
            
            const result = await response.json();
            if (result.success) {
                setSyncMessage(result.message || 'Sincronización iniciada correctamente. Las transacciones aparecerán en unos momentos. Los datos se están enriqueciendo en background.');
                setSyncMessageType('success');
                setSyncDialogOpen(false);
                setSyncMode('preset');
                setCustomStartDate('');
                setCustomEndDate('');
                
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                setSyncMessage(result.message || 'Error al iniciar la sincronización');
                setSyncMessageType('error');
            }
        } catch (error) {
            setSyncMessage('Error de conexión al iniciar sincronización');
            setSyncMessageType('error');
        } finally {
            setIsSyncing(false);
        }
    };
    
    const handleCustomSync = () => {
        if (!customStartDate || !customEndDate) {
            setSyncMessage('Por favor, selecciona ambas fechas (desde y hasta)');
            setSyncMessageType('error');
            return;
        }
        
        if (new Date(customStartDate) > new Date(customEndDate)) {
            setSyncMessage('La fecha de inicio debe ser anterior a la fecha de fin');
            setSyncMessageType('error');
            return;
        }
        
        handleSync(null, customStartDate, customEndDate);
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

    const getEnrichmentBadgeColor = (enrichmentStatus) => {
        if (!enrichmentStatus) return null;
        switch (enrichmentStatus) {
            case 'completed': return 'bg-green-100 text-green-800';
            case 'processing': return 'bg-blue-100 text-blue-800';
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            case 'failed': return 'bg-red-100 text-red-800';
            default: return null;
        }
    };

    const getEnrichmentBadgeText = (enrichmentStatus) => {
        if (!enrichmentStatus) return null;
        switch (enrichmentStatus) {
            case 'completed': return 'Enriquecido';
            case 'processing': return 'Enriqueciendo...';
            case 'pending': return 'Pendiente';
            case 'failed': return 'Error';
            default: return null;
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

    const formatNumber = (num) => {
        if (!num && num !== 0) return '-';
        return new Intl.NumberFormat('es-CO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 8
        }).format(num);
    };

    const formatCurrency = (num, currency = 'USD') => {
        if (!num && num !== 0) return '-';
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        
        // Parsear la fecha como UTC para evitar conversión de zona horaria
        const date = new Date(dateString);
        
        // Si la fecha viene como string ISO con Z, extraer los componentes directamente
        // para evitar conversión de zona horaria
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
        
        // Fallback al método anterior si no es formato ISO
        return date.toLocaleDateString('es-CO', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'UTC'
        });
    };

    const getExchangeName = (exchange) => {
        if (!exchange) return 'Binance'; // Default para transacciones antiguas
        return exchange.charAt(0).toUpperCase() + exchange.slice(1);
    };

    const getExchangeBadgeColor = (exchange) => {
        const exchangeLower = (exchange || 'binance').toLowerCase();
        switch (exchangeLower) {
            case 'binance': return 'bg-yellow-100 text-yellow-800 border-yellow-300';
            case 'bybit': return 'bg-blue-100 text-blue-800 border-blue-300';
            case 'okx': return 'bg-purple-100 text-purple-800 border-purple-300';
            default: return 'bg-gray-100 text-gray-800 border-gray-300';
        }
    };

    const getExchangeRowColor = (exchange) => {
        const exchangeLower = (exchange || 'binance').toLowerCase();
        switch (exchangeLower) {
            case 'binance': return 'border-l-4 border-l-yellow-500';
            case 'bybit': return 'border-l-4 border-l-blue-500';
            case 'okx': return 'border-l-4 border-l-purple-500';
            default: return 'border-l-4 border-l-gray-500';
        }
    };

    const getExchangeLogo = (exchange) => {
        const exchangeLower = (exchange || 'binance').toLowerCase();
        
        // URLs de logos de exchanges (puedes usar CDN o almacenar localmente)
        const logos = {
            'binance': 'https://assets.coingecko.com/coins/images/825/small/binance-coin-logo.png?1547034615',
            'bybit': 'https://brandlogos.net/wp-content/uploads/2022/09/bybit-logo_brandlogos.net_viubj-512x512.png',
            'okx': 'https://images.seeklogo.com/logo-png/45/2/okx-logo-png_seeklogo-459094.png',
        };
        
        return logos[exchangeLower] || logos['binance'];
    };

    return (
        <AppLayout header="Transacciones">
            <Head title="Transacciones" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">Gestión de Transacciones</h1>
                    <p className="text-gray-600 mt-2">
                        Sincroniza y gestiona tus transacciones de Binance, Bybit y otros exchanges
                    </p>
                    {stats.last_sync && (
                        <p className="text-sm text-gray-500 mt-1">
                            Última sincronización: {new Date(stats.last_sync).toLocaleString()}
                        </p>
                    )}
                </div>

                {/* Sync Message */}
                {syncMessage && (
                    <div className={`p-4 rounded-lg ${
                        syncMessageType === 'success' 
                            ? 'bg-green-50 border border-green-200 text-green-800' 
                            : 'bg-red-50 border border-red-200 text-red-800'
                    }`}>
                        <div className="flex items-center justify-between">
                            <span>{syncMessage}</span>
                            <button 
                                onClick={() => {
                                    setSyncMessage(null);
                                    setSyncMessageType(null);
                                }}
                                className="ml-4 text-gray-500 hover:text-gray-700"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                )}

                {/* Stats Cards */}
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="text-sm font-medium text-gray-500">Total Transacciones</div>
                            <div className="mt-1 text-3xl font-semibold text-gray-900">{stats.total_transactions}</div>
                        </div>
                    </div>
                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="text-sm font-medium text-gray-500">Completadas</div>
                            <div className="mt-1 text-3xl font-semibold text-green-600">{stats.completed_transactions}</div>
                        </div>
                    </div>
                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="text-sm font-medium text-gray-500">Pendientes</div>
                            <div className="mt-1 text-3xl font-semibold text-yellow-600">{stats.pending_transactions}</div>
                        </div>
                    </div>
                    {enrichmentStats && enrichmentStats.total_p2p > 0 && (
                        <div className="bg-white overflow-hidden shadow rounded-lg">
                            <div className="p-5">
                                <div className="text-sm font-medium text-gray-500">Pendientes Enriquecimiento</div>
                                <div className="mt-1 text-3xl font-semibold text-yellow-600">{enrichmentStats.pending || 0}</div>
                                {enrichmentStats.has_active_enrichment && (
                                    <div className="mt-2 flex items-center gap-1 text-xs text-blue-600">
                                        <span className="animate-pulse">●</span>
                                        <span>Procesando</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                {/* Filters and Actions */}
                <div className="bg-white shadow rounded-lg">
                    <div className="p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Filtros y Acciones</h3>
                        
                        {/* Exchange Filter - Badges clickeables */}
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 mb-2">Exchange</label>
                            <div className="flex flex-wrap gap-2">
                                <button
                                    onClick={() => {
                                        setSelectedExchange('');
                                        handleSearchWithExchange('');
                                    }}
                                    className={`inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border-2 transition-all duration-200 ${
                                        selectedExchange === '' 
                                            ? 'bg-gray-100 text-gray-800 border-gray-400 shadow-md scale-105' 
                                            : 'bg-white text-gray-700 border-gray-300 hover:border-gray-400 hover:bg-gray-50 hover:shadow-sm'
                                    }`}
                                >
                                    <span className="mr-2 text-base">🌐</span>
                                    Todos
                                </button>
                                {filterOptions.exchanges && filterOptions.exchanges.map(exchange => {
                                    const isSelected = selectedExchange === exchange;
                                    const exchangeLower = (exchange || 'binance').toLowerCase();
                                    
                                    let selectedClasses = '';
                                    let unselectedClasses = 'bg-white border-gray-300 hover:shadow-md';
                                    
                                    if (exchangeLower === 'binance') {
                                        selectedClasses = 'bg-yellow-100 text-yellow-800 border-yellow-400';
                                        unselectedClasses += ' hover:bg-yellow-50 hover:border-yellow-300 hover:text-yellow-700';
                                    } else if (exchangeLower === 'bybit') {
                                        selectedClasses = 'bg-blue-100 text-blue-800 border-blue-400';
                                        unselectedClasses += ' hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700';
                                    } else if (exchangeLower === 'okx') {
                                        selectedClasses = 'bg-purple-100 text-purple-800 border-purple-400';
                                        unselectedClasses += ' hover:bg-purple-50 hover:border-purple-300 hover:text-purple-700';
                                    } else {
                                        selectedClasses = 'bg-gray-100 text-gray-800 border-gray-400';
                                        unselectedClasses += ' hover:bg-gray-50 hover:border-gray-300';
                                    }
                                    
                                    return (
                                        <button
                                            key={exchange}
                                            onClick={() => {
                                                setSelectedExchange(exchange);
                                                handleSearchWithExchange(exchange);
                                            }}
                                            className={`inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold border-2 transition-all duration-200 ${
                                                isSelected 
                                                    ? `${selectedClasses} shadow-md scale-105` 
                                                    : unselectedClasses
                                            }`}
                                        >
                                            <img 
                                                src={getExchangeLogo(exchange)} 
                                                alt={getExchangeName(exchange)}
                                                className="w-5 h-5 mr-2 rounded-full"
                                                onError={(e) => {
                                                    e.target.style.display = 'none';
                                                }}
                                            />
                                            {getExchangeName(exchange)}
                                            {isSelected && (
                                                <svg className="w-4 h-4 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                </svg>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                                <input
                                    type="text"
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    placeholder="Número de orden..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    onKeyPress={(e) => {
                                        if (e.key === 'Enter') {
                                            handleSearch();
                                        }
                                    }}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                                <select
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value={selectedType}
                                    onChange={(e) => setSelectedType(e.target.value)}
                                >
                                    <option value="">Todos los tipos</option>
                                    {filterOptions.transaction_types.map(type => (
                                        <option key={type} value={type}>
                                            {type.replace('_', ' ').toUpperCase()}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                <select
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value={selectedStatus}
                                    onChange={(e) => setSelectedStatus(e.target.value)}
                                >
                                    <option value="">Todos los estados</option>
                                    {filterOptions.statuses.map(status => (
                                        <option key={status} value={status}>
                                            {status.toUpperCase()}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Filtros de Fecha */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Fecha Desde</label>
                                <input
                                    type="date"
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value={dateFrom}
                                    onChange={(e) => setDateFrom(e.target.value)}
                                    max={dateTo || new Date().toISOString().split('T')[0]}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Fecha Hasta</label>
                                <input
                                    type="date"
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                    min={dateFrom || undefined}
                                    max={new Date().toISOString().split('T')[0]}
                                />
                            </div>
                        </div>

                        <div className="flex gap-2 flex-wrap">
                            <button
                                onClick={handleSearch}
                                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                🔍 Buscar
                            </button>
                            <button
                                onClick={() => {
                                    setSearchTerm('');
                                    setSelectedExchange('');
                                    setSelectedType('');
                                    setSelectedStatus('');
                                    setDateFrom('');
                                    setDateTo('');
                                    window.location.href = '/transactions';
                                }}
                                className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                            >
                                🗑️ Limpiar Filtros
                            </button>
                            <button
                                onClick={() => setSyncDialogOpen(true)}
                                disabled={isSyncing}
                                className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                            >
                                {isSyncing ? 'Sincronizando...' : '🔄 Sincronizar'}
                            </button>
                            <button
                                onClick={handleExportExcel}
                                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                            >
                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Exportar a Excel
                            </button>
                        </div>
                    </div>
                </div>

                {/* Sync Dialog */}
                {syncDialogOpen && (
                    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                        <div className="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
                            <div className="mt-3">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Sincronizar Transacciones</h3>
                                
                                {/* Mode Toggle */}
                                <div className="mb-4">
                                    <div className="flex items-center justify-center gap-2 mb-3">
                                        <button
                                            onClick={() => setSyncMode('preset')}
                                            disabled={isSyncing}
                                            className={`px-4 py-2 text-sm font-medium rounded-md transition-colors ${
                                                syncMode === 'preset'
                                                    ? 'bg-blue-600 text-white'
                                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                            } disabled:opacity-50`}
                                        >
                                            Períodos Predefinidos
                                        </button>
                                        <button
                                            onClick={() => setSyncMode('custom')}
                                            disabled={isSyncing}
                                            className={`px-4 py-2 text-sm font-medium rounded-md transition-colors ${
                                                syncMode === 'custom'
                                                    ? 'bg-blue-600 text-white'
                                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                            } disabled:opacity-50`}
                                        >
                                            Fechas Personalizadas
                                        </button>
                                    </div>
                                </div>
                                
                                {syncMode === 'preset' ? (
                                    <>
                                        <p className="text-sm text-gray-500 mb-4">Selecciona el período de tiempo para sincronizar:</p>
                                        <div className="grid grid-cols-2 gap-2 mb-4">
                                            <button
                                                onClick={() => handleSync(1)}
                                                disabled={isSyncing}
                                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                                            >
                                                Últimos 1 día
                                            </button>
                                            <button
                                                onClick={() => handleSync(7)}
                                                disabled={isSyncing}
                                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                                            >
                                                Últimos 7 días
                                            </button>
                                            <button
                                                onClick={() => handleSync(30)}
                                                disabled={isSyncing}
                                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                                            >
                                                Últimos 30 días
                                            </button>
                                            <button
                                                onClick={() => handleSync(90)}
                                                disabled={isSyncing}
                                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                                            >
                                                Últimos 90 días
                                            </button>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <p className="text-sm text-gray-500 mb-4">Selecciona el rango de fechas para sincronizar:</p>
                                        <div className="space-y-3 mb-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Fecha Desde
                                                </label>
                                                <input
                                                    type="date"
                                                    value={customStartDate}
                                                    onChange={(e) => setCustomStartDate(e.target.value)}
                                                    disabled={isSyncing}
                                                    max={customEndDate || new Date().toISOString().split('T')[0]}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Fecha Hasta
                                                </label>
                                                <input
                                                    type="date"
                                                    value={customEndDate}
                                                    onChange={(e) => setCustomEndDate(e.target.value)}
                                                    disabled={isSyncing}
                                                    min={customStartDate || undefined}
                                                    max={new Date().toISOString().split('T')[0]}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                            <button
                                                onClick={handleCustomSync}
                                                disabled={isSyncing || !customStartDate || !customEndDate}
                                                className="w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                Sincronizar Rango Personalizado
                                            </button>
                                        </div>
                                    </>
                                )}
                                
                                {isSyncing && (
                                    <div className="flex items-center gap-2 text-blue-600 mb-4">
                                        <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                                        <span>Sincronizando transacciones, por favor espera...</span>
                                    </div>
                                )}
                                <div className="flex justify-end">
                                    <button
                                        onClick={() => {
                                            setSyncDialogOpen(false);
                                            setSyncMessage(null);
                                            setSyncMode('preset');
                                            setCustomStartDate('');
                                            setCustomEndDate('');
                                        }}
                                        disabled={isSyncing}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Transactions Table */}
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h3 className="text-lg font-medium text-gray-900">Transacciones</h3>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => setViewMode('table')}
                                className={`px-3 py-1.5 text-sm font-medium rounded-md ${
                                    viewMode === 'table'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                }`}
                            >
                                Tabla
                            </button>
                            <button
                                onClick={() => setViewMode('cards')}
                                className={`px-3 py-1.5 text-sm font-medium rounded-md ${
                                    viewMode === 'cards'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                }`}
                            >
                                Tarjetas
                            </button>
                        </div>
                    </div>
                    
                    {viewMode === 'table' ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orden</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exchange</th>
                                        {/* <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th> */}
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Compra/Venta</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activo</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Método de Pago</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo ID</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número ID</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {transactions.data && transactions.data.length > 0 ? (
                                        transactions.data.map((transaction) => {
                                            const exchangeLower = (transaction.exchange || 'binance').toLowerCase();
                                            const exchangeBgColor = exchangeLower === 'binance' ? 'bg-yellow-50/20' : 
                                                                    exchangeLower === 'bybit' ? 'bg-blue-50/20' : 
                                                                    exchangeLower === 'okx' ? 'bg-purple-50/20' : 'bg-gray-50/20';
                                            const orderTypeBg = transaction.order_type === 'BUY' ? 'bg-green-50/30' : 
                                                               transaction.order_type === 'SELL' ? 'bg-red-50/30' : '';
                                            
                                            return (
                                            <tr key={transaction.id} className={`${orderTypeBg} ${getExchangeRowColor(transaction.exchange)} ${exchangeBgColor}`}>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                                    {transaction.order_number || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex items-center px-3 py-1.5 rounded-md text-xs font-semibold border whitespace-nowrap ${getExchangeBadgeColor(transaction.exchange)}`}>
                                                        <img 
                                                            src={getExchangeLogo(transaction.exchange)} 
                                                            alt={getExchangeName(transaction.exchange)}
                                                            className="w-4 h-4 mr-2 rounded-full flex-shrink-0"
                                                            onError={(e) => {
                                                                e.target.style.display = 'none';
                                                            }}
                                                        />
                                                        <span className="whitespace-nowrap">{getExchangeName(transaction.exchange)}</span>
                                                    </span>
                                                </td>
                                                {/* <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getTypeBadgeColor(transaction.transaction_type)}`}>
                                                        {transaction.transaction_type.replace('_', ' ').toUpperCase()}
                                                    </span>
                                                </td> */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {getOrderTypeBadge(transaction.order_type)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {transaction.asset_type}
                                                    {transaction.fiat_type && (
                                                        <span className="text-gray-500">/{transaction.fiat_type}</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{formatNumber(transaction.quantity)}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{formatNumber(transaction.price)}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{formatNumber(transaction.total_price)}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    {transaction.payment_method || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    {transaction.counter_party || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    {transaction.dni_type || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    {transaction.counter_party_dni || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex flex-col gap-1">
                                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeColor(transaction.status)}`}>
                                                            {transaction.status.toUpperCase()}
                                                        </span>
                                                        {transaction.enrichment_status && 
                                                         transaction.enrichment_status !== 'pending' && 
                                                         transaction.enrichment_status !== 'completed' && (
                                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${getEnrichmentBadgeColor(transaction.enrichment_status)}`}>
                                                                {getEnrichmentBadgeText(transaction.enrichment_status)}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {formatDate(transaction.binance_create_time)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div className="flex space-x-2">
                                                        <Link
                                                            href={`/transactions/${transaction.id}`}
                                                            className="text-indigo-600 hover:text-indigo-900"
                                                        >
                                                            Ver
                                                        </Link>
                                                        <Link
                                                            href={`/transactions/${transaction.id}/edit`}
                                                            className="text-green-600 hover:text-green-900"
                                                        >
                                                            Editar
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan="15" className="px-6 py-4 text-center text-sm text-gray-500">
                                                No hay transacciones. Haz clic en "Sincronizar" para obtener tus transacciones.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="p-6">
                                    {transactions.data && transactions.data.length > 0 ? (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {transactions.data.map((transaction) => {
                                        const exchangeLower = (transaction.exchange || 'binance').toLowerCase();
                                        const exchangeBorderColor = exchangeLower === 'binance' ? 'border-yellow-400' : 
                                                                    exchangeLower === 'bybit' ? 'border-blue-400' : 
                                                                    exchangeLower === 'okx' ? 'border-purple-400' : 'border-gray-300';
                                        const exchangeBgColor = exchangeLower === 'binance' ? 'bg-yellow-50/30' : 
                                                                    exchangeLower === 'bybit' ? 'bg-blue-50/30' : 
                                                                    exchangeLower === 'okx' ? 'bg-purple-50/30' : 'bg-gray-50/30';
                                        const orderTypeBorder = transaction.order_type === 'BUY' ? 'border-green-300' : 
                                                               transaction.order_type === 'SELL' ? 'border-red-300' : 'border-gray-200';
                                        const orderTypeBg = transaction.order_type === 'BUY' ? 'bg-green-50/30' : 
                                                           transaction.order_type === 'SELL' ? 'bg-red-50/30' : 'bg-white';
                                        
                                        return (
                                        <div 
                                            key={transaction.id}
                                            className={`border-2 rounded-lg shadow-sm p-6 ${exchangeBorderColor} ${orderTypeBorder} ${orderTypeBg} ${exchangeBgColor} relative overflow-hidden`}
                                        >
                                            {/* Exchange indicator bar */}
                                            {(() => {
                                                const exchangeLower = (transaction.exchange || 'binance').toLowerCase();
                                                const barColor = exchangeLower === 'binance' ? 'bg-yellow-500' : 
                                                                exchangeLower === 'bybit' ? 'bg-blue-500' : 
                                                                exchangeLower === 'okx' ? 'bg-purple-500' : 'bg-gray-500';
                                                return <div className={`absolute top-0 left-0 right-0 h-1 ${barColor}`}></div>;
                                            })()}
                                            {/* Header */}
                                            <div className="flex items-start justify-between mb-4">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2 mb-2">
                                                        <span className={`inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border whitespace-nowrap flex-shrink-0 ${getExchangeBadgeColor(transaction.exchange)}`}>
                                                            <img 
                                                                src={getExchangeLogo(transaction.exchange)} 
                                                                alt={getExchangeName(transaction.exchange)}
                                                                className="w-4 h-4 mr-1.5 rounded-full flex-shrink-0"
                                                                onError={(e) => {
                                                                    e.target.style.display = 'none';
                                                                }}
                                                            />
                                                            <span className="whitespace-nowrap">{getExchangeName(transaction.exchange)}</span>
                                                        </span>
                                                        <div className="font-mono text-sm font-semibold text-gray-900">
                                                            {transaction.order_number || 'Sin número'}
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2 flex-wrap">
                                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getTypeBadgeColor(transaction.transaction_type)}`}>
                                                            {transaction.transaction_type.replace('_', ' ').toUpperCase()}
                                                        </span>
                                                        {getOrderTypeBadge(transaction.order_type)}
                                                    </div>
                                                </div>
                                                <div className="flex flex-col gap-1 items-end">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeColor(transaction.status)}`}>
                                                        {transaction.status.toUpperCase()}
                                                    </span>
                                                    {transaction.enrichment_status && 
                                                     transaction.enrichment_status !== 'pending' && 
                                                     transaction.enrichment_status !== 'completed' && (
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${getEnrichmentBadgeColor(transaction.enrichment_status)}`}>
                                                            {getEnrichmentBadgeText(transaction.enrichment_status)}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Asset Info */}
                                            <div className="mb-4 pb-4 border-b border-gray-200">
                                                <div className="text-lg font-semibold text-gray-900">
                                                    {transaction.asset_type}
                                                    {transaction.fiat_type && (
                                                        <span className="text-gray-500 text-base">/{transaction.fiat_type}</span>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Financial Details */}
                                            <div className="space-y-3 mb-4">
                                                <div className="flex justify-between items-center">
                                                    <span className="text-sm text-gray-600">Cantidad:</span>
                                                    <span className="text-sm font-semibold text-gray-900">{formatNumber(transaction.quantity)} {transaction.asset_type}</span>
                                                </div>
                                                <div className="flex justify-between items-center">
                                                    <span className="text-sm text-gray-600">Precio Unitario:</span>
                                                    <span className="text-sm font-semibold text-gray-900">
                                                        {formatNumber(transaction.price)} {transaction.fiat_type || ''}
                                                    </span>
                                                </div>
                                                <div className="flex justify-between items-center pt-2 border-t border-gray-200">
                                                    <span className="text-sm font-medium text-gray-700">Total:</span>
                                                    <span className="text-base font-bold text-gray-900">
                                                        {formatNumber(transaction.total_price)} {transaction.fiat_type || ''}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Fees and Commissions */}
                                            {(transaction.commission > 0 || transaction.taker_fee > 0 || transaction.network_fee > 0) && (
                                                <div className="mb-4 pb-4 border-b border-gray-200 space-y-2">
                                                    <div className="text-xs font-medium text-gray-500 uppercase mb-2">Comisiones y Fees</div>
                                                    {transaction.commission > 0 && (
                                                        <div className="flex justify-between items-center">
                                                            <span className="text-xs text-gray-600">Comisión:</span>
                                                            <span className="text-xs font-semibold text-gray-700">{formatNumber(transaction.commission)}</span>
                                                        </div>
                                                    )}
                                                    {transaction.taker_fee > 0 && (
                                                        <div className="flex justify-between items-center">
                                                            <span className="text-xs text-gray-600">Taker Fee:</span>
                                                            <span className="text-xs font-semibold text-gray-700">{formatNumber(transaction.taker_fee)}</span>
                                                        </div>
                                                    )}
                                                    {transaction.network_fee > 0 && (
                                                        <div className="flex justify-between items-center">
                                                            <span className="text-xs text-gray-600">Network Fee:</span>
                                                            <span className="text-xs font-semibold text-gray-700">{formatNumber(transaction.network_fee)}</span>
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                            {/* Payment and Party Info */}
                                            {(transaction.payment_method || transaction.counter_party) && (
                                                <div className="mb-4 pb-4 border-b border-gray-200 space-y-2">
                                                    <div className="text-xs font-medium text-gray-500 uppercase mb-2">Información de Pago</div>
                                                    {transaction.payment_method && (
                                                        <div className="flex justify-between items-center">
                                                            <span className="text-xs text-gray-600">Método de Pago:</span>
                                                            <span className="text-xs font-semibold text-gray-900">{transaction.payment_method}</span>
                                                        </div>
                                                    )}
                                                    {transaction.counter_party && (
                                                        <div className="flex justify-between items-center">
                                                            <span className="text-xs text-gray-600">Cliente:</span>
                                                            <span className="text-xs font-semibold text-gray-900">{transaction.counter_party}</span>
                                                        </div>
                                                    )}
                                                    {transaction.dni_type && (
                                                        <div className="flex justify-between items-center">
                                                            <span className="text-xs text-gray-600">Tipo ID:</span>
                                                            <span className="text-xs font-semibold text-gray-900">{transaction.dni_type}</span>
                                                        </div>
                                                    )}
                                                    {transaction.counter_party_dni && (
                                                        <div className="flex justify-between items-center">
                                                            <span className="text-xs text-gray-600">Número ID:</span>
                                                            <span className="text-xs font-semibold text-gray-900">{transaction.counter_party_dni}</span>
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                            {/* Actions */}
                                            <div className="mt-4 pt-4 border-t border-gray-200 flex justify-end space-x-2">
                                                <Link
                                                    href={`/transactions/${transaction.id}`}
                                                    className="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                                >
                                                    Ver
                                                </Link>
                                                <Link
                                                    href={`/transactions/${transaction.id}/edit`}
                                                    className="inline-flex items-center px-3 py-1.5 border border-transparent shadow-sm text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700"
                                                >
                                                    Editar
                                                </Link>
                                            </div>

                                            {/* Additional Info */}
                                            <div className="space-y-2">
                                                <div className="flex justify-between items-center">
                                                    <span className="text-xs text-gray-600">Fecha:</span>
                                                    <span className="text-xs font-medium text-gray-700">
                                                        {formatDate(transaction.binance_create_time)}
                                                    </span>
                                                </div>
                                                {transaction.advertisement_order_number && (
                                                    <div className="flex justify-between items-center">
                                                        <span className="text-xs text-gray-600">Anuncio:</span>
                                                        <span className="text-xs font-mono text-gray-700">{transaction.advertisement_order_number}</span>
                                                    </div>
                                                )}
                                                {transaction.notes && (
                                                    <div className="pt-2 border-t border-gray-200">
                                                        <div className="text-xs text-gray-600 mb-1">Notas:</div>
                                                        <div className="text-xs text-gray-700 italic">{transaction.notes}</div>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="text-center py-12">
                                    <p className="text-sm text-gray-500">
                                        No hay transacciones. Haz clic en "Sincronizar" para obtener tus transacciones.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Pagination */}
                    {transactions.links && transactions.links.length > 3 && (
                        <div className="px-6 py-4 border-t border-gray-200">
                            <div className="flex items-center justify-between">
                                <div className="text-sm text-gray-700">
                                    Mostrando {transactions.from} a {transactions.to} de {transactions.total} resultados
                                </div>
                                <div className="flex space-x-2">
                                    {transactions.links.map((link, index) => (
                                        <button
                                            key={index}
                                            onClick={() => link.url && (window.location.href = link.url)}
                                            disabled={!link.url}
                                            className={`px-3 py-2 text-sm font-medium rounded-md ${
                                                link.active
                                                    ? 'bg-blue-600 text-white'
                                                    : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                                            } disabled:opacity-50 disabled:cursor-not-allowed`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
