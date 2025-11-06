# Configuración de Cron Jobs en cPanel

## Comando de Sincronización en Tiempo Real

### Opción 1: Ejecutar comando directamente (Recomendado)

Ejecuta el comando cada 5 minutos directamente:

```bash
*/5 * * * * cd /ruta/absoluta/a/tu/proyecto && php artisan transactions:sync-recent --minutes=10 >> /dev/null 2>&1
```

**Ejemplo:**
```bash
*/5 * * * * cd /home/usuario/public_html/cao-bnc-bot && php artisan transactions:sync-recent --minutes=10 >> /dev/null 2>&1
```

### Opción 2: Usar el Scheduler de Laravel

Si quieres que Laravel maneje toda la programación (incluyendo otros comandos), ejecuta el scheduler cada minuto:

```bash
* * * * * cd /ruta/absoluta/a/tu/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

**Ejemplo:**
```bash
* * * * * cd /home/usuario/public_html/cao-bnc-bot && php artisan schedule:run >> /dev/null 2>&1
```

## Procesamiento de Colas (Opcional)

Si en el futuro quieres usar colas en lugar de ejecución síncrona, necesitarás un worker de colas:

```bash
* * * * * cd /ruta/absoluta/a/tu/proyecto && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

O para un worker continuo (más eficiente pero consume más recursos):

```bash
@reboot cd /ruta/absoluta/a/tu/proyecto && nohup php artisan queue:work --daemon > /dev/null 2>&1 &
```

**Nota:** El comando `transactions:sync-recent` actualmente usa `dispatchSync()` que ejecuta inmediatamente sin usar colas, por lo que NO necesitas procesar colas para este comando.

## Cómo encontrar la ruta absoluta de tu proyecto

1. Accede a tu proyecto por SSH o File Manager
2. Ejecuta: `pwd` (si estás en el directorio del proyecto)
3. O busca la ruta en cPanel: File Manager → Navega a tu proyecto → La ruta aparece en la parte superior

## Verificación

Para verificar que el cron está funcionando:

1. Revisa los logs de Laravel: `storage/logs/laravel.log`
2. Busca entradas con "Starting Recent Transactions Sync"
3. O ejecuta manualmente: `php artisan transactions:sync-recent`

