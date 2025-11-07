@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Crear Transacción Manual</h1>
            <p class="text-gray-600 mt-2">
                Agregar una nueva transacción manualmente
            </p>
        </div>

        <!-- Form -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <form id="transactionForm" class="space-y-6">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="order_number" class="block text-sm font-medium text-gray-700">Número de Orden *</label>
                            <input type="text" name="order_number" id="order_number" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="transaction_type" class="block text-sm font-medium text-gray-700">Tipo de Transacción *</label>
                            <select name="transaction_type" id="transaction_type" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="manual_entry">Entrada Manual</option>
                                <option value="spot_trade">Spot Trade</option>
                                <option value="p2p_order">P2P Order</option>
                                <option value="deposit">Depósito</option>
                                <option value="withdrawal">Retiro</option>
                                <option value="pay_transaction">Binance Pay</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="asset_type" class="block text-sm font-medium text-gray-700">Activo *</label>
                            <input type="text" name="asset_type" id="asset_type" required placeholder="BTC, ETH, USDT..."
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="fiat_type" class="block text-sm font-medium text-gray-700">Fiat</label>
                            <input type="text" name="fiat_type" id="fiat_type" placeholder="COP, USD, EUR..."
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="order_type" class="block text-sm font-medium text-gray-700">Tipo de Orden</label>
                            <select name="order_type" id="order_type"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Seleccionar</option>
                                <option value="BUY">Compra</option>
                                <option value="SELL">Venta</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Estado *</label>
                            <select name="status" id="status" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="completed">Completado</option>
                                <option value="pending">Pendiente</option>
                                <option value="processing">Procesando</option>
                                <option value="cancelled">Cancelado</option>
                                <option value="failed">Fallido</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700">Cantidad *</label>
                            <input type="number" name="quantity" id="quantity" step="0.00000001" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700">Precio</label>
                            <input type="number" name="price" id="price" step="0.00000001"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="total_price" class="block text-sm font-medium text-gray-700">Precio Total</label>
                            <input type="number" name="total_price" id="total_price" step="0.00000001"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="binance_create_time" class="block text-sm font-medium text-gray-700">Fecha de Creación *</label>
                            <input type="datetime-local" name="binance_create_time" id="binance_create_time" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700">Método de Pago</label>
                            <input type="text" name="payment_method" id="payment_method" placeholder="Bancolombia, Nequi..."
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="counter_party" class="block text-sm font-medium text-gray-700">Contraparte</label>
                            <input type="text" name="counter_party" id="counter_party" placeholder="Nombre del trader..."
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                    </div>
                    
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notas</label>
                        <textarea name="notes" id="notes" rows="3"
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                  placeholder="Notas adicionales sobre la transacción..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <a href="{{ route('transactions.index') }}" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancelar
                        </a>
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Crear Transacción
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default datetime to now
    const now = new Date();
    const localDateTime = now.toISOString().slice(0, 16);
    document.getElementById('binance_create_time').value = localDateTime;
    
    // Handle form submission
    document.getElementById('transactionForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        
        try {
            const response = await fetch('/transactions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Transacción creada correctamente');
                window.location.href = '/transactions';
            } else {
                alert('Error: ' + (result.message || 'Error desconocido'));
            }
        } catch (error) {
            alert('Error al crear transacción');
        }
    });
});
</script>
@endsection











