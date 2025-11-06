import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function TransactionsEdit({ transaction }) {
    const [formData, setFormData] = useState({
        dni_type: transaction.dni_type || '',
        counter_party_dni: transaction.counter_party_dni || '',
        // Solo para transacciones manuales
        ...(transaction.is_manual_entry && {
            order_number: transaction.order_number || '',
            transaction_type: transaction.transaction_type || '',
            asset_type: transaction.asset_type || '',
            fiat_type: transaction.fiat_type || '',
            order_type: transaction.order_type || '',
            quantity: transaction.quantity || '',
            price: transaction.price || '',
            total_price: transaction.total_price || '',
            status: transaction.status || '',
            binance_create_time: transaction.binance_create_time ? new Date(transaction.binance_create_time).toISOString().slice(0, 16) : '',
            notes: transaction.notes || '',
            payment_method: transaction.payment_method || '',
            counter_party: transaction.counter_party || '',
        }),
    });

    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [message, setMessage] = useState(null);
    const [messageType, setMessageType] = useState(null);

    const dniTypes = [
        { value: '', label: 'Seleccionar...' },
        { value: 'CC', label: 'CC - Cédula de Ciudadanía' },
        { value: 'CE', label: 'CE - Cédula de Extranjería' },
        { value: 'PASSPORT', label: 'PASSPORT - Pasaporte' },
        { value: 'NIT', label: 'NIT - Número de Identificación Tributaria' },
        { value: 'TI', label: 'TI - Tarjeta de Identidad' },
        { value: 'RUT', label: 'RUT - Registro Único Tributario' },
        { value: 'OTRO', label: 'OTRO' },
    ];

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
        // Limpiar error del campo
        if (errors[name]) {
            setErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors[name];
                return newErrors;
            });
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        setMessage(null);
        setMessageType(null);
        setErrors({});

        try {
            const response = await fetch(`/transactions/${transaction.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                setMessage('Transacción actualizada correctamente');
                setMessageType('success');
                
                // Redirigir después de 1.5 segundos
                setTimeout(() => {
                    router.visit(`/transactions/${transaction.id}`);
                }, 1500);
            } else {
                if (result.errors) {
                    setErrors(result.errors);
                } else {
                    setMessage(result.message || 'Error al actualizar la transacción');
                    setMessageType('error');
                }
            }
        } catch (error) {
            setMessage('Error de conexión al actualizar la transacción');
            setMessageType('error');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <AppLayout header="Editar Transacción">
            <Head title="Editar Transacción" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Editar Transacción</h1>
                        <p className="mt-1 text-sm text-gray-600">
                            {transaction.is_manual_entry 
                                ? 'Editar información completa de la transacción'
                                : 'Editar información de identificación del counter party'}
                        </p>
                    </div>
                    <Link
                        href={`/transactions/${transaction.id}`}
                        className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                    >
                        Cancelar
                    </Link>
                </div>

                {/* Message */}
                {message && (
                    <div className={`rounded-md p-4 ${
                        messageType === 'success' 
                            ? 'bg-green-50 text-green-800 border border-green-200' 
                            : 'bg-red-50 text-red-800 border border-red-200'
                    }`}>
                        {message}
                    </div>
                )}

                {/* Form */}
                <form onSubmit={handleSubmit} className="bg-white shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6 space-y-6">
                        {/* Información de Counter Party */}
                        <div>
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Información del Counter Party
                            </h3>
                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                {/* Tipo de Identificación */}
                                <div>
                                    <label htmlFor="dni_type" className="block text-sm font-medium text-gray-700">
                                        Tipo de Identificación
                                    </label>
                                    <select
                                        id="dni_type"
                                        name="dni_type"
                                        value={formData.dni_type}
                                        onChange={handleChange}
                                        className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                            errors.dni_type ? 'border-red-300' : ''
                                        }`}
                                    >
                                        {dniTypes.map(type => (
                                            <option key={type.value} value={type.value}>
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.dni_type && (
                                        <p className="mt-1 text-sm text-red-600">{errors.dni_type[0]}</p>
                                    )}
                                </div>

                                {/* Número de Documento */}
                                <div>
                                    <label htmlFor="counter_party_dni" className="block text-sm font-medium text-gray-700">
                                        Número de Documento
                                    </label>
                                    <input
                                        type="text"
                                        id="counter_party_dni"
                                        name="counter_party_dni"
                                        value={formData.counter_party_dni}
                                        onChange={handleChange}
                                        className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                            errors.counter_party_dni ? 'border-red-300' : ''
                                        }`}
                                        placeholder="Ingrese el número de documento"
                                    />
                                    {errors.counter_party_dni && (
                                        <p className="mt-1 text-sm text-red-600">{errors.counter_party_dni[0]}</p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Información de la Transacción (solo para manuales) */}
                        {transaction.is_manual_entry && (
                            <div className="border-t border-gray-200 pt-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    Información de la Transacción
                                </h3>
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    {/* Order Number */}
                                    <div>
                                        <label htmlFor="order_number" className="block text-sm font-medium text-gray-700">
                                            Número de Orden *
                                        </label>
                                        <input
                                            type="text"
                                            id="order_number"
                                            name="order_number"
                                            value={formData.order_number}
                                            onChange={handleChange}
                                            required
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.order_number ? 'border-red-300' : ''
                                            }`}
                                        />
                                        {errors.order_number && (
                                            <p className="mt-1 text-sm text-red-600">{errors.order_number[0]}</p>
                                        )}
                                    </div>

                                    {/* Transaction Type */}
                                    <div>
                                        <label htmlFor="transaction_type" className="block text-sm font-medium text-gray-700">
                                            Tipo de Transacción *
                                        </label>
                                        <select
                                            id="transaction_type"
                                            name="transaction_type"
                                            value={formData.transaction_type}
                                            onChange={handleChange}
                                            required
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.transaction_type ? 'border-red-300' : ''
                                            }`}
                                        >
                                            <option value="">Seleccionar...</option>
                                            <option value="spot_trade">Spot Trade</option>
                                            <option value="p2p_order">P2P Order</option>
                                            <option value="deposit">Deposit</option>
                                            <option value="withdrawal">Withdrawal</option>
                                            <option value="pay_transaction">Pay Transaction</option>
                                            <option value="c2c_order">C2C Order</option>
                                            <option value="manual_entry">Manual Entry</option>
                                        </select>
                                        {errors.transaction_type && (
                                            <p className="mt-1 text-sm text-red-600">{errors.transaction_type[0]}</p>
                                        )}
                                    </div>

                                    {/* Asset Type */}
                                    <div>
                                        <label htmlFor="asset_type" className="block text-sm font-medium text-gray-700">
                                            Tipo de Activo *
                                        </label>
                                        <input
                                            type="text"
                                            id="asset_type"
                                            name="asset_type"
                                            value={formData.asset_type}
                                            onChange={handleChange}
                                            required
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.asset_type ? 'border-red-300' : ''
                                            }`}
                                        />
                                        {errors.asset_type && (
                                            <p className="mt-1 text-sm text-red-600">{errors.asset_type[0]}</p>
                                        )}
                                    </div>

                                    {/* Fiat Type */}
                                    <div>
                                        <label htmlFor="fiat_type" className="block text-sm font-medium text-gray-700">
                                            Tipo de Fiat
                                        </label>
                                        <input
                                            type="text"
                                            id="fiat_type"
                                            name="fiat_type"
                                            value={formData.fiat_type}
                                            onChange={handleChange}
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.fiat_type ? 'border-red-300' : ''
                                            }`}
                                        />
                                        {errors.fiat_type && (
                                            <p className="mt-1 text-sm text-red-600">{errors.fiat_type[0]}</p>
                                        )}
                                    </div>

                                    {/* Order Type */}
                                    <div>
                                        <label htmlFor="order_type" className="block text-sm font-medium text-gray-700">
                                            Tipo de Orden
                                        </label>
                                        <select
                                            id="order_type"
                                            name="order_type"
                                            value={formData.order_type}
                                            onChange={handleChange}
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.order_type ? 'border-red-300' : ''
                                            }`}
                                        >
                                            <option value="">Seleccionar...</option>
                                            <option value="BUY">BUY</option>
                                            <option value="SELL">SELL</option>
                                        </select>
                                        {errors.order_type && (
                                            <p className="mt-1 text-sm text-red-600">{errors.order_type[0]}</p>
                                        )}
                                    </div>

                                    {/* Status */}
                                    <div>
                                        <label htmlFor="status" className="block text-sm font-medium text-gray-700">
                                            Estado *
                                        </label>
                                        <select
                                            id="status"
                                            name="status"
                                            value={formData.status}
                                            onChange={handleChange}
                                            required
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.status ? 'border-red-300' : ''
                                            }`}
                                        >
                                            <option value="">Seleccionar...</option>
                                            <option value="pending">Pending</option>
                                            <option value="processing">Processing</option>
                                            <option value="completed">Completed</option>
                                            <option value="cancelled">Cancelled</option>
                                            <option value="failed">Failed</option>
                                            <option value="expired">Expired</option>
                                        </select>
                                        {errors.status && (
                                            <p className="mt-1 text-sm text-red-600">{errors.status[0]}</p>
                                        )}
                                    </div>

                                    {/* Quantity */}
                                    <div>
                                        <label htmlFor="quantity" className="block text-sm font-medium text-gray-700">
                                            Cantidad *
                                        </label>
                                        <input
                                            type="number"
                                            id="quantity"
                                            name="quantity"
                                            value={formData.quantity}
                                            onChange={handleChange}
                                            step="any"
                                            required
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.quantity ? 'border-red-300' : ''
                                            }`}
                                        />
                                        {errors.quantity && (
                                            <p className="mt-1 text-sm text-red-600">{errors.quantity[0]}</p>
                                        )}
                                    </div>

                                    {/* Price */}
                                    <div>
                                        <label htmlFor="price" className="block text-sm font-medium text-gray-700">
                                            Precio
                                        </label>
                                        <input
                                            type="number"
                                            id="price"
                                            name="price"
                                            value={formData.price}
                                            onChange={handleChange}
                                            step="any"
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.price ? 'border-red-300' : ''
                                            }`}
                                        />
                                        {errors.price && (
                                            <p className="mt-1 text-sm text-red-600">{errors.price[0]}</p>
                                        )}
                                    </div>

                                    {/* Total Price */}
                                    <div>
                                        <label htmlFor="total_price" className="block text-sm font-medium text-gray-700">
                                            Precio Total
                                        </label>
                                        <input
                                            type="number"
                                            id="total_price"
                                            name="total_price"
                                            value={formData.total_price}
                                            onChange={handleChange}
                                            step="any"
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.total_price ? 'border-red-300' : ''
                                            }`}
                                        />
                                        {errors.total_price && (
                                            <p className="mt-1 text-sm text-red-600">{errors.total_price[0]}</p>
                                        )}
                                    </div>

                                    {/* Binance Create Time */}
                                    <div>
                                        <label htmlFor="binance_create_time" className="block text-sm font-medium text-gray-700">
                                            Fecha de Creación *
                                        </label>
                                        <input
                                            type="datetime-local"
                                            id="binance_create_time"
                                            name="binance_create_time"
                                            value={formData.binance_create_time}
                                            onChange={handleChange}
                                            required
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.binance_create_time ? 'border-red-300' : ''
                                            }`}
                                        />
                                        {errors.binance_create_time && (
                                            <p className="mt-1 text-sm text-red-600">{errors.binance_create_time[0]}</p>
                                        )}
                                    </div>

                                    {/* Payment Method */}
                                    <div>
                                        <label htmlFor="payment_method" className="block text-sm font-medium text-gray-700">
                                            Método de Pago
                                        </label>
                                        <input
                                            type="text"
                                            id="payment_method"
                                            name="payment_method"
                                            value={formData.payment_method}
                                            onChange={handleChange}
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.payment_method ? 'border-red-300' : ''
                                            }`}
                                        />
                                        {errors.payment_method && (
                                            <p className="mt-1 text-sm text-red-600">{errors.payment_method[0]}</p>
                                        )}
                                    </div>

                                    {/* Counter Party */}
                                    <div>
                                        <label htmlFor="counter_party" className="block text-sm font-medium text-gray-700">
                                            Counter Party
                                        </label>
                                        <input
                                            type="text"
                                            id="counter_party"
                                            name="counter_party"
                                            value={formData.counter_party}
                                            onChange={handleChange}
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.counter_party ? 'border-red-300' : ''
                                            }`}
                                        />
                                        {errors.counter_party && (
                                            <p className="mt-1 text-sm text-red-600">{errors.counter_party[0]}</p>
                                        )}
                                    </div>

                                    {/* Notes */}
                                    <div className="sm:col-span-2">
                                        <label htmlFor="notes" className="block text-sm font-medium text-gray-700">
                                            Notas
                                        </label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows={3}
                                            value={formData.notes}
                                            onChange={handleChange}
                                            className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${
                                                errors.notes ? 'border-red-300' : ''
                                            }`}
                                        />
                                        {errors.notes && (
                                            <p className="mt-1 text-sm text-red-600">{errors.notes[0]}</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Form Actions */}
                    <div className="px-4 py-4 sm:px-6 border-t border-gray-200 bg-gray-50">
                        <div className="flex justify-end space-x-3">
                            <Link
                                href={`/transactions/${transaction.id}`}
                                className="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Cancelar
                            </Link>
                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {isSubmitting ? 'Guardando...' : 'Guardar Cambios'}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

