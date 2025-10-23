import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Calendar, CalendarIcon, Download, Plus, RefreshCw, Search, Filter } from 'lucide-react';
import { Calendar as CalendarComponent } from '@/Components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { format } from 'date-fns';
import { cn } from '@/lib/utils';

export default function TransactionsIndex({ 
    transactions, 
    stats, 
    filterOptions,
    filters 
}) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedType, setSelectedType] = useState(filters.transaction_type || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
    const [selectedAsset, setSelectedAsset] = useState(filters.asset_type || '');
    const [selectedFiat, setSelectedFiat] = useState(filters.fiat_type || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ? new Date(filters.date_from) : null);
    const [dateTo, setDateTo] = useState(filters.date_to ? new Date(filters.date_to) : null);
    const [showManualEntry, setShowManualEntry] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);
    const [syncDialogOpen, setSyncDialogOpen] = useState(false);

    // Estado para formulario de entrada manual
    const [manualForm, setManualForm] = useState({
        order_number: '',
        transaction_type: 'manual_entry',
        asset_type: '',
        fiat_type: '',
        order_type: '',
        quantity: '',
        price: '',
        total_price: '',
        status: 'completed',
        binance_create_time: new Date(),
        notes: '',
        payment_method: '',
        counter_party: ''
    });

    const handleSearch = () => {
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (selectedType) params.append('transaction_type', selectedType);
        if (selectedStatus) params.append('status', selectedStatus);
        if (selectedAsset) params.append('asset_type', selectedAsset);
        if (selectedFiat) params.append('fiat_type', selectedFiat);
        if (dateFrom) params.append('date_from', format(dateFrom, 'yyyy-MM-dd'));
        if (dateTo) params.append('date_to', format(dateTo, 'yyyy-MM-dd'));
        
        window.location.href = `/transactions?${params.toString()}`;
    };

    const handleSync = async () => {
        setIsSyncing(true);
        try {
            const response = await fetch('/transactions/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    days: 7
                })
            });
            
            const result = await response.json();
            if (result.success) {
                alert('Sincronización iniciada correctamente');
                window.location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error al iniciar sincronización');
        } finally {
            setIsSyncing(false);
            setSyncDialogOpen(false);
        }
    };

    const handleManualEntry = async () => {
        try {
            const response = await fetch('/transactions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(manualForm)
            });
            
            const result = await response.json();
            if (result.success) {
                alert('Transacción creada correctamente');
                setShowManualEntry(false);
                setManualForm({
                    order_number: '',
                    transaction_type: 'manual_entry',
                    asset_type: '',
                    fiat_type: '',
                    order_type: '',
                    quantity: '',
                    price: '',
                    total_price: '',
                    status: 'completed',
                    binance_create_time: new Date(),
                    notes: '',
                    payment_method: '',
                    counter_party: ''
                });
                window.location.reload();
            } else {
                alert('Error: ' + (result.message || 'Error desconocido'));
            }
        } catch (error) {
            alert('Error al crear transacción');
        }
    };

    const getStatusBadgeVariant = (status) => {
        switch (status) {
            case 'completed': return 'default';
            case 'pending': return 'secondary';
            case 'processing': return 'outline';
            case 'cancelled': return 'destructive';
            case 'failed': return 'destructive';
            default: return 'secondary';
        }
    };

    const getTypeBadgeVariant = (type) => {
        switch (type) {
            case 'spot_trade': return 'default';
            case 'p2p_order': return 'secondary';
            case 'deposit': return 'outline';
            case 'withdrawal': return 'destructive';
            case 'manual_entry': return 'outline';
            default: return 'secondary';
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Transacciones" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900">Gestión de Transacciones</h1>
                        <p className="text-gray-600 mt-2">
                            Sincroniza y gestiona tus transacciones de Binance
                        </p>
                    </div>

                    {/* Stats Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Transacciones</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.total_transactions}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Completadas</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">{stats.completed_transactions}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Pendientes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-yellow-600">{stats.pending_transactions}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Entradas Manuales</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-blue-600">{stats.manual_entries}</div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Filters and Actions */}
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>Filtros y Acciones</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <Label htmlFor="search">Buscar</Label>
                                    <Input
                                        id="search"
                                        placeholder="Número de orden..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="type">Tipo</Label>
                                    <Select value={selectedType} onValueChange={setSelectedType}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Todos los tipos" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Todos los tipos</SelectItem>
                                            {filterOptions.transaction_types.map(type => (
                                                <SelectItem key={type} value={type}>
                                                    {type.replace('_', ' ').toUpperCase()}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label htmlFor="status">Estado</Label>
                                    <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Todos los estados" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Todos los estados</SelectItem>
                                            {filterOptions.statuses.map(status => (
                                                <SelectItem key={status} value={status}>
                                                    {status.toUpperCase()}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label htmlFor="asset">Activo</Label>
                                    <Select value={selectedAsset} onValueChange={setSelectedAsset}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Todos los activos" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Todos los activos</SelectItem>
                                            {filterOptions.asset_types.map(asset => (
                                                <SelectItem key={asset} value={asset}>
                                                    {asset}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <Label>Fecha Desde</Label>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button
                                                variant="outline"
                                                className={cn(
                                                    "w-full justify-start text-left font-normal",
                                                    !dateFrom && "text-muted-foreground"
                                                )}
                                            >
                                                <CalendarIcon className="mr-2 h-4 w-4" />
                                                {dateFrom ? format(dateFrom, "PPP") : "Seleccionar fecha"}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto p-0">
                                            <CalendarComponent
                                                mode="single"
                                                selected={dateFrom}
                                                onSelect={setDateFrom}
                                                initialFocus
                                            />
                                        </PopoverContent>
                                    </Popover>
                                </div>
                                <div>
                                    <Label>Fecha Hasta</Label>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button
                                                variant="outline"
                                                className={cn(
                                                    "w-full justify-start text-left font-normal",
                                                    !dateTo && "text-muted-foreground"
                                                )}
                                            >
                                                <CalendarIcon className="mr-2 h-4 w-4" />
                                                {dateTo ? format(dateTo, "PPP") : "Seleccionar fecha"}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto p-0">
                                            <CalendarComponent
                                                mode="single"
                                                selected={dateTo}
                                                onSelect={setDateTo}
                                                initialFocus
                                            />
                                        </PopoverContent>
                                    </Popover>
                                </div>
                            </div>

                            <div className="flex gap-2">
                                <Button onClick={handleSearch} className="flex items-center gap-2">
                                    <Search className="h-4 w-4" />
                                    Buscar
                                </Button>
                                <Dialog open={syncDialogOpen} onOpenChange={setSyncDialogOpen}>
                                    <DialogTrigger asChild>
                                        <Button variant="outline" className="flex items-center gap-2">
                                            <RefreshCw className="h-4 w-4" />
                                            Sincronizar
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Sincronizar Transacciones</DialogTitle>
                                        </DialogHeader>
                                        <div className="space-y-4">
                                            <p>¿Deseas sincronizar las transacciones de los últimos 7 días?</p>
                                            <div className="flex justify-end gap-2">
                                                <Button variant="outline" onClick={() => setSyncDialogOpen(false)}>
                                                    Cancelar
                                                </Button>
                                                <Button onClick={handleSync} disabled={isSyncing}>
                                                    {isSyncing ? 'Sincronizando...' : 'Sincronizar'}
                                                </Button>
                                            </div>
                                        </div>
                                    </DialogContent>
                                </Dialog>
                                <Dialog open={showManualEntry} onOpenChange={setShowManualEntry}>
                                    <DialogTrigger asChild>
                                        <Button variant="outline" className="flex items-center gap-2">
                                            <Plus className="h-4 w-4" />
                                            Entrada Manual
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-2xl">
                                        <DialogHeader>
                                            <DialogTitle>Crear Transacción Manual</DialogTitle>
                                        </DialogHeader>
                                        <div className="space-y-4">
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="order_number">Número de Orden</Label>
                                                    <Input
                                                        id="order_number"
                                                        value={manualForm.order_number}
                                                        onChange={(e) => setManualForm({...manualForm, order_number: e.target.value})}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="asset_type">Activo</Label>
                                                    <Input
                                                        id="asset_type"
                                                        value={manualForm.asset_type}
                                                        onChange={(e) => setManualForm({...manualForm, asset_type: e.target.value})}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="fiat_type">Fiat</Label>
                                                    <Input
                                                        id="fiat_type"
                                                        value={manualForm.fiat_type}
                                                        onChange={(e) => setManualForm({...manualForm, fiat_type: e.target.value})}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="order_type">Tipo de Orden</Label>
                                                    <Select value={manualForm.order_type} onValueChange={(value) => setManualForm({...manualForm, order_type: value})}>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Seleccionar tipo" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="BUY">BUY</SelectItem>
                                                            <SelectItem value="SELL">SELL</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <Label htmlFor="quantity">Cantidad</Label>
                                                    <Input
                                                        id="quantity"
                                                        type="number"
                                                        step="0.00000001"
                                                        value={manualForm.quantity}
                                                        onChange={(e) => setManualForm({...manualForm, quantity: e.target.value})}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="price">Precio</Label>
                                                    <Input
                                                        id="price"
                                                        type="number"
                                                        step="0.00000001"
                                                        value={manualForm.price}
                                                        onChange={(e) => setManualForm({...manualForm, price: e.target.value})}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="total_price">Precio Total</Label>
                                                    <Input
                                                        id="total_price"
                                                        type="number"
                                                        step="0.00000001"
                                                        value={manualForm.total_price}
                                                        onChange={(e) => setManualForm({...manualForm, total_price: e.target.value})}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="status">Estado</Label>
                                                    <Select value={manualForm.status} onValueChange={(value) => setManualForm({...manualForm, status: value})}>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Seleccionar estado" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="completed">Completado</SelectItem>
                                                            <SelectItem value="pending">Pendiente</SelectItem>
                                                            <SelectItem value="processing">Procesando</SelectItem>
                                                            <SelectItem value="cancelled">Cancelado</SelectItem>
                                                            <SelectItem value="failed">Fallido</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div>
                                                <Label htmlFor="notes">Notas</Label>
                                                <Textarea
                                                    id="notes"
                                                    value={manualForm.notes}
                                                    onChange={(e) => setManualForm({...manualForm, notes: e.target.value})}
                                                />
                                            </div>
                                            <div className="flex justify-end gap-2">
                                                <Button variant="outline" onClick={() => setShowManualEntry(false)}>
                                                    Cancelar
                                                </Button>
                                                <Button onClick={handleManualEntry}>
                                                    Crear Transacción
                                                </Button>
                                            </div>
                                        </div>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Transactions Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Transacciones</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Orden</TableHead>
                                        <TableHead>Tipo</TableHead>
                                        <TableHead>Activo</TableHead>
                                        <TableHead>Cantidad</TableHead>
                                        <TableHead>Precio</TableHead>
                                        <TableHead>Total</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactions.data.map((transaction) => (
                                        <TableRow key={transaction.id}>
                                            <TableCell className="font-mono text-sm">
                                                {transaction.order_number}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={getTypeBadgeVariant(transaction.transaction_type)}>
                                                    {transaction.transaction_type.replace('_', ' ').toUpperCase()}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {transaction.asset_type}
                                                {transaction.fiat_type && (
                                                    <span className="text-gray-500">/{transaction.fiat_type}</span>
                                                )}
                                            </TableCell>
                                            <TableCell>{transaction.quantity}</TableCell>
                                            <TableCell>{transaction.price}</TableCell>
                                            <TableCell>{transaction.total_price}</TableCell>
                                            <TableCell>
                                                <Badge variant={getStatusBadgeVariant(transaction.status)}>
                                                    {transaction.status.toUpperCase()}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {transaction.binance_create_time ? 
                                                    new Date(transaction.binance_create_time).toLocaleDateString() : 
                                                    '-'
                                                }
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-1">
                                                    <Button variant="outline" size="sm">
                                                        Ver
                                                    </Button>
                                                    {transaction.is_manual_entry && (
                                                        <Button variant="outline" size="sm">
                                                            Editar
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {/* Pagination */}
                            {transactions.links && (
                                <div className="mt-4 flex justify-center">
                                    <nav className="flex space-x-2">
                                        {transactions.links.map((link, index) => (
                                            <Button
                                                key={index}
                                                variant={link.active ? "default" : "outline"}
                                                size="sm"
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                onClick={() => link.url && (window.location.href = link.url)}
                                                disabled={!link.url}
                                            />
                                        ))}
                                    </nav>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
