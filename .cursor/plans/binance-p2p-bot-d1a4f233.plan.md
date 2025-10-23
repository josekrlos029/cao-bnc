<!-- d1a4f233-3388-4829-ae98-5c46bc1bc610 9d3e010d-4fb8-41e4-bf59-a2fb642bee94 -->
# Plan de Implementación: Bot P2P Binance

## 1. Base de Datos y Modelos

### Crear migraciones y modelos para:

**Bot Configuration** (`bot_configurations` table):

- Datos de anuncio (fiat, asset, operation, limits, payment methods, ad_number)
- Configuración de posiciones (min/max)
- Configuración de precios (min/max)
- Diferencia USD (min/max)
- Perfil (agresivo, moderado, conservador)
- Ajuste de ascenso (increment, difference)
- Configuraciones adicionales (max price, min volume, min limit)
- Estado (active/inactive)
- user_id

**P2P Ads** (`p2p_ads` table):

- Información de anuncios obtenidos de Binance
- ad_number, fiat, asset, price, available_amount, min_limit, max_limit
- payment_methods (JSON)
- advertiser info
- timestamps

**Trade History** (`trade_history` table):

- order_id, ad_number, fiat, asset, amount, price, total
- trade_type (buy/sell), status
- buyer/seller info
- created_at, completed_at

**Market Data** (`market_data` table):

- asset, fiat, price, timestamp
- source (Binance reference price)

**Bot Actions Log** (`bot_actions_log` table):

- bot_configuration_id, action_type (suggest, execute, monitor)
- action_data (JSON), status, result
- timestamps

## 2. Servicio de Integración con Binance P2P API

Crear `app/Services/BinanceP2PService.php` que implemente:

**Métodos basados en la documentación PDF:**

- `getAdsDetailByNumber($adNumber)` - Obtener detalles de anuncio
- `getAdsReferencePrice($fiat, $asset)` - Precio de referencia
- `getAdsList($params)` - Listar anuncios con paginación
- `searchAds($conditions)` - Buscar anuncios con condiciones
- `postAd($data)` - Crear nuevo anuncio
- `updateAd($adNumber, $data)` - Actualizar anuncio
- `updateAdStatus($adNumber, $status)` - Cambiar estado
- `queryDigitalCurrencyList()` - Lista de criptomonedas
- `queryFiatCurrencyList()` - Lista de monedas fiat

**Autenticación y firma:**

- Implementar sistema de firma de requests según documentación
- Almacenar API keys encriptadas en tabla `binance_credentials`

## 3. Job Queue para Procesamiento en Background

**Laravel Jobs:**

`ProcessBotStrategy` (cada 30 segundos):

- Obtener configuraciones activas
- Analizar mercado actual
- Comparar con estrategia configurada
- Generar sugerencias de acción
- Guardar en bot_actions_log con status "pending_approval"

`MonitorMarketData` (cada 10 segundos):

- Obtener precios de referencia
- Actualizar tabla market_data
- Calcular diferencias USD
- Identificar oportunidades

`SyncP2PAds` (cada 1 minuto):

- Sincronizar anuncios relevantes
- Actualizar posiciones competidoras
- Almacenar en p2p_ads

`ExecuteApprovedActions` (en tiempo real):

- Ejecutar acciones aprobadas por usuario
- Actualizar/crear anuncios en Binance
- Registrar en trade_history

**Configurar en `config/queue.php`:**

- Redis como driver principal
- Múltiples queues: high (market_data), default (bot_strategy), low (sync)

## 4. WebSocket para Monitoreo en Tiempo Real

**Instalar Laravel Reverb o Pusher:**

```bash
composer require laravel/reverb
```

**Eventos a transmitir:**

- `MarketDataUpdated` - Actualización de precios
- `BotActionSuggested` - Nueva sugerencia del bot
- `AdPositionChanged` - Cambio en posición del anuncio
- `TradeExecuted` - Trade completado

**Channels:**

- Private channel por usuario: `bot-updates.{userId}`
- Broadcast updates del bot en tiempo real al dashboard

## 5. Backend Controllers y Routes

**BotController - extender funcionalidad:**

- `saveConfiguration(Request)` - Guardar config del bot
- `getConfiguration()` - Obtener config actual
- `toggleBotStatus($id)` - Activar/desactivar
- `getActionsSuggested()` - Obtener acciones pendientes
- `approveAction($actionId)` - Aprobar acción sugerida
- `rejectAction($actionId)` - Rechazar acción

**BinanceController - añadir endpoints P2P:**

- `getAdDetails(Request)` - Obtener datos de anuncio por número
- `getReferencePrice($fiat, $asset)` - Precio de referencia
- `searchCompetitors(Request)` - Buscar anuncios competidores
- `getMarketData()` - Datos de mercado actuales

**Nuevos endpoints en `routes/web.php`:**

```php
Route::middleware(['auth'])->group(function () {
    Route::post('/bot/configuration', [BotController::class, 'saveConfiguration']);
    Route::get('/bot/configuration', [BotController::class, 'getConfiguration']);
    Route::post('/bot/{id}/toggle', [BotController::class, 'toggleBotStatus']);
    Route::get('/bot/actions/suggested', [BotController::class, 'getActionsSuggested']);
    Route::post('/bot/actions/{id}/approve', [BotController::class, 'approveAction']);
    
    Route::post('/binance/ad/details', [BinanceController::class, 'getAdDetails']);
    Route::get('/binance/reference-price/{fiat}/{asset}', [BinanceController::class, 'getReferencePrice']);
});
```

## 6. Frontend - Mejorar BotConfiguration.jsx

**Integración con backend:**

- Conectar formulario con endpoints de guardado
- Implementar botón "OBTENER Datos Anuncio" para fetch ad details
- Mostrar datos en tiempo real vía WebSocket
- Actualizar "Información posiciones" con data real

**Nuevo componente:** `BotActionsPanel.jsx`

- Lista de acciones sugeridas pendientes de aprobación
- Botones Aprobar/Rechazar
- Historial de acciones ejecutadas
- Estado del bot (activo/inactivo)

**Nuevo componente:** `MarketMonitor.jsx`

- Gráfico de precios en tiempo real
- Posición actual del anuncio
- Competidores cercanos
- Métricas de performance

## 7. Sistema de Analytics y Reportes

**Crear:** `app/Services/BotAnalyticsService.php`

- Calcular ROI de trades
- Métricas de performance por perfil
- Análisis de competencia
- Tiempo promedio en posiciones top
- Sugerencias de optimización

**Dashboard Analytics:**

- Gráficos de trades ejecutados
- Volumen total negociado
- Profit/Loss por periodo
- Comparativa de estrategias

## 8. Comandos Artisan

**Crear comandos para gestión:**

```bash
php artisan bot:start {config_id}
php artisan bot:stop {config_id}
php artisan bot:status
php artisan market:sync
```

**Scheduler en `app/Console/Kernel.php`:**

```php
$schedule->job(MonitorMarketData::class)->everyTenSeconds();
$schedule->job(ProcessBotStrategy::class)->everyThirtySeconds();
$schedule->job(SyncP2PAds::class)->everyMinute();
```

## 9. Seguridad y Validaciones

- Encriptar API keys usando Laravel Encryption
- Rate limiting en llamadas a Binance API
- Validación de límites mín/máx en configuración
- Logs de todas las acciones críticas
- Sistema de alertas por email/Telegram en errores

## 10. Testing y Monitoreo

**Tests:**

- Unit tests para BinanceP2PService
- Feature tests para BotController
- Integration tests para job queue flow

**Monitoreo:**

- Laravel Telescope para debugging
- Logs estructurados con context
- Métricas de performance de jobs
- Alertas automáticas en fallos

## Archivos Clave a Crear/Modificar

**Nuevos:**

- `app/Models/BotConfiguration.php`
- `app/Models/P2PAd.php`
- `app/Models/TradeHistory.php`
- `app/Models/MarketData.php`
- `app/Models/BotActionLog.php`
- `app/Models/BinanceCredential.php`
- `app/Services/BinanceP2PService.php`
- `app/Services/BotAnalyticsService.php`
- `app/Jobs/ProcessBotStrategy.php`
- `app/Jobs/MonitorMarketData.php`
- `app/Jobs/SyncP2PAds.php`
- `app/Jobs/ExecuteApprovedActions.php`
- `app/Events/BotActionSuggested.php`
- `app/Events/MarketDataUpdated.php`
- `resources/js/Components/BotActionsPanel.jsx`
- `resources/js/Components/MarketMonitor.jsx`
- `database/migrations/*_create_bot_tables.php`

**Modificar:**

- `app/Http/Controllers/BotController.php`
- `app/Http/Controllers/BinanceController.php`
- `resources/js/Components/BotConfiguration.jsx`
- `routes/web.php`
- `config/queue.php`

## Orden de Implementación

1. Migraciones y modelos base
2. BinanceP2PService con autenticación
3. Endpoints básicos del bot (save/get config)
4. Integración frontend con backend
5. Jobs queue y procesamiento
6. WebSocket y tiempo real
7. Analytics y reportes
8. Testing y refinamiento

### To-dos

- [ ] Crear migraciones y modelos (BotConfiguration, P2PAd, TradeHistory, MarketData, BotActionLog, BinanceCredential)
- [ ] Implementar BinanceP2PService con todos los métodos de API y sistema de autenticación/firma
- [ ] Extender BotController con métodos para configuración, acciones sugeridas y aprobación
- [ ] Añadir endpoints P2P a BinanceController (getAdDetails, getReferencePrice, searchCompetitors)
- [ ] Conectar BotConfiguration.jsx con backend, implementar fetch de ad details y guardado de config
- [ ] Crear Laravel Jobs (ProcessBotStrategy, MonitorMarketData, SyncP2PAds, ExecuteApprovedActions)
- [ ] Implementar Laravel Reverb/Pusher con eventos en tiempo real (MarketDataUpdated, BotActionSuggested)
- [ ] Crear componente BotActionsPanel.jsx para aprobar/rechazar acciones sugeridas
- [ ] Crear componente MarketMonitor.jsx con gráficos y métricas en tiempo real
- [ ] Implementar BotAnalyticsService con cálculo de ROI, métricas de performance y reportes
- [ ] Crear comandos Artisan para gestión del bot y configurar Scheduler
- [ ] Implementar encriptación de API keys, rate limiting, validaciones y sistema de alertas
- [ ] Crear tests unitarios, feature e integration para todos los componentes críticos