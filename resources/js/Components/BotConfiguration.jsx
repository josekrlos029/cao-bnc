import { useState, useEffect } from 'react';
import axios from 'axios';

export default function BotConfiguration() {
    const [formData, setFormData] = useState({
        // Datos Anuncio
        fiat: 'COP',
        asset: 'BTC',
        assetRate: '1.43',
        operation: 'BUY',
        minLimit: '3.000.000',
        maxLimit: '90.000.000',
        paymentMethods: {
            nequi: true,
            bancolombia: true
        },
        adNumber: '11503424874873049088',
        
        // Posiciones
        minPositions: '3',
        maxPositions: '8',
        
        // Precios
        minPrice: '4500',
        maxPrice: '4600',
        
        // Dif. USD
        minUsdDiff: '100',
        maxUsdDiff: '200',
        
        // Perfil
        profile: 'agresivo',
        
        // Ajuste Ascenso
        increment: '',
        difference: '',
        
        // Información
        usdPrice: '4172.47',
        myPrice: '',
        myPosition: '',
        myProfile: '',
        myUsdDiff: '',
        
        // Additional settings
        maxPriceEnabled: false,
        maxPriceLimit: '',
        minVolumeEnabled: false,
        minVolume: '',
        minLimitEnabled: false,
        minLimitThreshold: ''
    });

    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');
    const [messageType, setMessageType] = useState('');

    // Cargar configuración existente al montar el componente
    useEffect(() => {
        loadConfiguration();
    }, []);

    const loadConfiguration = async () => {
        try {
            setLoading(true);
            const response = await axios.get('/bot/configuration');
            if (response.data.success && response.data.config) {
                setFormData(prev => ({
                    ...prev,
                    ...response.data.config,
                    payment_methods: response.data.config.payment_methods || { nequi: true, bancolombia: true }
                }));
            }
        } catch (error) {
            console.error('Error loading configuration:', error);
            setMessage('Error al cargar la configuración');
            setMessageType('error');
        } finally {
            setLoading(false);
        }
    };

    const handleInputChange = (field, value) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));
    };

    const handlePaymentMethodChange = (method, checked) => {
        setFormData(prev => ({
            ...prev,
            paymentMethods: {
                ...prev.paymentMethods,
                [method]: checked
            }
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            setSaving(true);
            setMessage('');
            
            const response = await axios.post('/bot/configuration', formData);
            
            if (response.data.success) {
                setMessage('Configuración guardada exitosamente');
                setMessageType('success');
            } else {
                setMessage(response.data.message || 'Error al guardar la configuración');
                setMessageType('error');
            }
        } catch (error) {
            console.error('Error saving configuration:', error);
            setMessage('Error al guardar la configuración');
            setMessageType('error');
        } finally {
            setSaving(false);
        }
    };

    const handleGetAdDetails = async () => {
        if (!formData.adNumber) {
            setMessage('Por favor ingrese un número de anuncio');
            setMessageType('error');
            return;
        }

        try {
            setLoading(true);
            setMessage('');
            
            const response = await axios.post('/binance/ad/details', {
                ad_number: formData.adNumber
            });
            
            if (response.data.success && response.data.data.length > 0) {
                const adData = response.data.data[0];
                setFormData(prev => ({
                    ...prev,
                    fiat: adData.fiatUnit || prev.fiat,
                    asset: adData.asset || prev.asset,
                    price: adData.price || prev.price,
                    min_limit: adData.minSingleTransAmount || prev.min_limit,
                    max_limit: adData.maxSingleTransAmount || prev.max_limit,
                    payment_methods: adData.payMethods ? 
                        adData.payMethods.reduce((acc, method) => {
                            acc[method.payType.toLowerCase()] = true;
                            return acc;
                        }, {}) : prev.payment_methods
                }));
                setMessage('Datos del anuncio obtenidos exitosamente');
                setMessageType('success');
            } else {
                setMessage('No se encontró el anuncio especificado');
                setMessageType('error');
            }
        } catch (error) {
            console.error('Error getting ad details:', error);
            setMessage('Error al obtener los datos del anuncio');
            setMessageType('error');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="bg-white shadow rounded-lg">
            <div className="px-4 py-5 sm:p-6">
                <h3 className="text-lg leading-6 font-medium text-gray-900 mb-6">
                    Configuración del Bot P2P
                </h3>
                
                {/* Message Display */}
                {message && (
                    <div className={`mb-4 p-4 rounded-md ${
                        messageType === 'success' 
                            ? 'bg-green-50 text-green-700 border border-green-200' 
                            : 'bg-red-50 text-red-700 border border-red-200'
                    }`}>
                        {message}
                    </div>
                )}
                
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Datos Anuncio & Métodos de Pago */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Datos Anuncio */}
                        <div className="bg-gray-50 p-4 rounded-lg">
                            <h4 className="text-md font-medium text-gray-900 mb-4">Datos Anuncio</h4>
                            <div className="space-y-3">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Fiat</label>
                                    <select 
                                        value={formData.fiat}
                                        onChange={(e) => handleInputChange('fiat', e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    >
                                        <option value="COP">COP</option>
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Asset</label>
                                    <div className="flex">
                                        <select 
                                            value={formData.asset}
                                            onChange={(e) => handleInputChange('asset', e.target.value)}
                                            className="mt-1 block w-20 border-gray-300 rounded-l-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        >
                                            <option value="BTC">BTC</option>
                                            <option value="ETH">ETH</option>
                                            <option value="USDT">USDT</option>
                                        </select>
                                        <input
                                            type="text"
                                            value={formData.assetRate}
                                            onChange={(e) => handleInputChange('assetRate', e.target.value)}
                                            className="mt-1 block w-full border-gray-300 rounded-r-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Operación</label>
                                    <select 
                                        value={formData.operation}
                                        onChange={(e) => handleInputChange('operation', e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    >
                                        <option value="BUY">BUY</option>
                                        <option value="SELL">SELL</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Límite min</label>
                                    <input
                                        type="text"
                                        value={formData.minLimit}
                                        onChange={(e) => handleInputChange('minLimit', e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Límite max</label>
                                    <input
                                        type="text"
                                        value={formData.maxLimit}
                                        onChange={(e) => handleInputChange('maxLimit', e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Métodos de Pago */}
                        <div className="bg-gray-50 p-4 rounded-lg">
                            <h4 className="text-md font-medium text-gray-900 mb-4">Método(s) de PAGO para competir</h4>
                            <div className="space-y-3">
                                <div className="flex items-center">
                                    <input
                                        id="nequi"
                                        type="checkbox"
                                        checked={formData.paymentMethods.nequi}
                                        onChange={(e) => handlePaymentMethodChange('nequi', e.target.checked)}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor="nequi" className="ml-2 block text-sm text-gray-900">
                                        Nequi
                                    </label>
                                </div>
                                <div className="flex items-center">
                                    <input
                                        id="bancolombia"
                                        type="checkbox"
                                        checked={formData.paymentMethods.bancolombia}
                                        onChange={(e) => handlePaymentMethodChange('bancolombia', e.target.checked)}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor="bancolombia" className="ml-2 block text-sm text-gray-900">
                                        BancolombiaSA
                                    </label>
                                </div>
                            </div>
                        </div>

                        {/* Nro Anuncio */}
                        <div className="bg-gray-50 p-4 rounded-lg">
                            <h4 className="text-md font-medium text-gray-900 mb-4">Nro Anuncio</h4>
                            <div className="space-y-3">
                                <input
                                    type="text"
                                    value={formData.adNumber}
                                    onChange={(e) => handleInputChange('adNumber', e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                />
                                <button
                                    type="button"
                                    onClick={handleGetAdDetails}
                                    disabled={loading}
                                    className="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {loading ? 'Obteniendo...' : 'OBTENER Datos Anuncio'}
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Configuration Panels */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Posiciones */}
                        <div className="bg-gray-50 p-4 rounded-lg">
                            <h4 className="text-md font-medium text-gray-900 mb-4">Posiciones</h4>
                            <div className="space-y-3">
                                <div className="flex items-center">
                                    <input
                                        id="posiciones"
                                        type="radio"
                                        name="configType"
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                        defaultChecked
                                    />
                                    <label htmlFor="posiciones" className="ml-2 block text-sm text-gray-900">
                                        Posiciones
                                    </label>
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Min</label>
                                        <input
                                            type="text"
                                            value={formData.minPositions}
                                            onChange={(e) => handleInputChange('minPositions', e.target.value)}
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Max</label>
                                        <input
                                            type="text"
                                            value={formData.maxPositions}
                                            onChange={(e) => handleInputChange('maxPositions', e.target.value)}
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Precios */}
                        <div className="bg-gray-50 p-4 rounded-lg">
                            <h4 className="text-md font-medium text-gray-900 mb-4">Precios</h4>
                            <div className="space-y-3">
                                <div className="flex items-center">
                                    <input
                                        id="precios"
                                        type="radio"
                                        name="configType"
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                    />
                                    <label htmlFor="precios" className="ml-2 block text-sm text-gray-900">
                                        Precios
                                    </label>
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Min</label>
                                        <input
                                            type="text"
                                            value={formData.minPrice}
                                            onChange={(e) => handleInputChange('minPrice', e.target.value)}
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Max</label>
                                        <input
                                            type="text"
                                            value={formData.maxPrice}
                                            onChange={(e) => handleInputChange('maxPrice', e.target.value)}
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Dif. USD */}
                        <div className="bg-gray-50 p-4 rounded-lg">
                            <h4 className="text-md font-medium text-gray-900 mb-4">Dif. USD</h4>
                            <div className="space-y-3">
                                <div className="flex items-center">
                                    <input
                                        id="dif-usd"
                                        type="radio"
                                        name="configType"
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                        defaultChecked
                                    />
                                    <label htmlFor="dif-usd" className="ml-2 block text-sm text-gray-900">
                                        Dif. USD
                                    </label>
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Min</label>
                                        <input
                                            type="text"
                                            value={formData.minUsdDiff}
                                            onChange={(e) => handleInputChange('minUsdDiff', e.target.value)}
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Max</label>
                                        <input
                                            type="text"
                                            value={formData.maxUsdDiff}
                                            onChange={(e) => handleInputChange('maxUsdDiff', e.target.value)}
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Perfil y Ajuste Ascenso */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Perfil */}
                        <div className="bg-gray-50 p-4 rounded-lg">
                            <h4 className="text-md font-medium text-gray-900 mb-4">Perfil</h4>
                            <div className="space-y-3">
                                <div className="flex items-center">
                                    <input
                                        id="agresivo"
                                        type="radio"
                                        name="profile"
                                        value="agresivo"
                                        checked={formData.profile === 'agresivo'}
                                        onChange={(e) => handleInputChange('profile', e.target.value)}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                    />
                                    <label htmlFor="agresivo" className="ml-2 block text-sm text-gray-900">
                                        Agresivo
                                    </label>
                                </div>
                                <div className="flex items-center">
                                    <input
                                        id="moderado"
                                        type="radio"
                                        name="profile"
                                        value="moderado"
                                        checked={formData.profile === 'moderado'}
                                        onChange={(e) => handleInputChange('profile', e.target.value)}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                    />
                                    <label htmlFor="moderado" className="ml-2 block text-sm text-gray-900">
                                        Moderado
                                    </label>
                                </div>
                                <div className="flex items-center">
                                    <input
                                        id="conservador"
                                        type="radio"
                                        name="profile"
                                        value="conservador"
                                        checked={formData.profile === 'conservador'}
                                        onChange={(e) => handleInputChange('profile', e.target.value)}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                    />
                                    <label htmlFor="conservador" className="ml-2 block text-sm text-gray-900">
                                        Conservador
                                    </label>
                                </div>
                            </div>
                        </div>

                        {/* Ajuste Ascenso */}
                        <div className="bg-gray-50 p-4 rounded-lg">
                            <h4 className="text-md font-medium text-gray-900 mb-4">Ajuste Ascenso</h4>
                            <div className="space-y-3">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Incr.</label>
                                    <input
                                        type="text"
                                        value={formData.increment}
                                        onChange={(e) => handleInputChange('increment', e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Dif.</label>
                                    <input
                                        type="text"
                                        value={formData.difference}
                                        onChange={(e) => handleInputChange('difference', e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Información */}
                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-md font-medium text-gray-900 mb-4">Información</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Precio USD</label>
                                <div className="mt-1 text-lg font-semibold text-gray-900">{formData.usdPrice}</div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Mi Precio</label>
                                <input
                                    type="text"
                                    value={formData.myPrice}
                                    onChange={(e) => handleInputChange('myPrice', e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Mi Posición</label>
                                <input
                                    type="text"
                                    value={formData.myPosition}
                                    onChange={(e) => handleInputChange('myPosition', e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Mi Perfil</label>
                                <input
                                    type="text"
                                    value={formData.myProfile}
                                    onChange={(e) => handleInputChange('myProfile', e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                />
                            </div>
                        </div>
                        <div className="mt-4">
                            <label className="block text-sm font-medium text-gray-700">Dif. USD</label>
                            <input
                                type="text"
                                value={formData.myUsdDiff}
                                onChange={(e) => handleInputChange('myUsdDiff', e.target.value)}
                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            />
                        </div>
                    </div>

                    {/* Additional Settings */}
                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-md font-medium text-gray-900 mb-4">Configuraciones Adicionales</h4>
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center">
                                    <input
                                        id="maxPrice"
                                        type="checkbox"
                                        checked={formData.maxPriceEnabled}
                                        onChange={(e) => handleInputChange('maxPriceEnabled', e.target.checked)}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor="maxPrice" className="ml-2 block text-sm text-gray-900">
                                        Precio max. permitido
                                    </label>
                                </div>
                                <input
                                    type="text"
                                    value={formData.maxPriceLimit}
                                    onChange={(e) => handleInputChange('maxPriceLimit', e.target.value)}
                                    disabled={!formData.maxPriceEnabled}
                                    className="w-32 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm disabled:bg-gray-100"
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center">
                                    <input
                                        id="minVolume"
                                        type="checkbox"
                                        checked={formData.minVolumeEnabled}
                                        onChange={(e) => handleInputChange('minVolumeEnabled', e.target.checked)}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor="minVolume" className="ml-2 block text-sm text-gray-900">
                                        Vol. min. para Ascenso (USDT)
                                    </label>
                                </div>
                                <input
                                    type="text"
                                    value={formData.minVolume}
                                    onChange={(e) => handleInputChange('minVolume', e.target.value)}
                                    disabled={!formData.minVolumeEnabled}
                                    className="w-32 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm disabled:bg-gray-100"
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center">
                                    <input
                                        id="minLimit"
                                        type="checkbox"
                                        checked={formData.minLimitEnabled}
                                        onChange={(e) => handleInputChange('minLimitEnabled', e.target.checked)}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor="minLimit" className="ml-2 block text-sm text-gray-900">
                                        Lim. min. para Ascenso (USDT)
                                    </label>
                                </div>
                                <input
                                    type="text"
                                    value={formData.minLimitThreshold}
                                    onChange={(e) => handleInputChange('minLimitThreshold', e.target.value)}
                                    disabled={!formData.minLimitEnabled}
                                    className="w-32 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm disabled:bg-gray-100"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Position Information */}
                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-md font-medium text-gray-900 mb-4">Información posiciones</h4>
                        <div className="bg-white p-4 rounded border-2 border-dashed border-gray-300 min-h-32 flex items-center justify-center">
                            <p className="text-gray-500">Información de posiciones se mostrará aquí</p>
                        </div>
                    </div>

                    {/* Submit Button */}
                    <div className="flex justify-center">
                        <button
                            type="submit"
                            disabled={saving}
                            className="bg-blue-600 text-white px-8 py-3 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 text-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {saving ? 'Guardando...' : 'Guardar Configuración'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
