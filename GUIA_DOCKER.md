# Guía de Desarrollo con Docker

Esta guía explica cómo ver los cambios reflejados en los contenedores de Docker que están corriendo.

## Configuración de Volúmenes

El proyecto está configurado con volúmenes montados (`./:/var/www`), lo que significa que los cambios en el código deberían reflejarse automáticamente en los contenedores. Sin embargo, dependiendo del tipo de cambio, puede ser necesario ejecutar comandos adicionales.

## Cambios en Código PHP/Laravel/Views

Como los volúmenes están montados, los cambios en archivos PHP, Blade templates y otros archivos del backend deberían verse automáticamente. Si los cambios no se reflejan, limpia la caché de Laravel:

```bash
# Limpiar caché de Laravel
docker-compose -f docker-compose.dev.yml exec app php artisan cache:clear
docker-compose -f docker-compose.dev.yml exec app php artisan config:clear
docker-compose -f docker-compose.dev.yml exec app php artisan view:clear
```

Si necesitas limpiar todas las cachés de una vez:

```bash
docker-compose -f docker-compose.dev.yml exec app php artisan optimize:clear
```

## Cambios en JavaScript/React (Frontend)

Si estás usando `docker-compose.dev.yml`, el servicio `node` ejecuta `npm run dev` con hot reload activado. Los cambios deberían reflejarse automáticamente.

**Si cambiaste `package.json` o instalaste nuevas dependencias:**

```bash
# Reinstalar dependencias de Node
docker-compose -f docker-compose.dev.yml exec node npm install

# O reiniciar el contenedor de Node
docker-compose -f docker-compose.dev.yml restart node
```

**Si necesitas reconstruir los assets:**

```bash
docker-compose -f docker-compose.dev.yml exec node npm run build
```

## Cambios en la Base de Datos (Migraciones)

Si creaste nuevas migraciones o modificaste la estructura de la base de datos:

```bash
# Ejecutar migraciones
docker-compose -f docker-compose.dev.yml exec app php artisan migrate

# Si necesitas refrescar la base de datos
docker-compose -f docker-compose.dev.yml exec app php artisan migrate:fresh

# Si necesitas refrescar y ejecutar seeders
docker-compose -f docker-compose.dev.yml exec app php artisan migrate:fresh --seed
```

## Cambios en Dockerfile o Configuración de Docker

Si modificaste el `Dockerfile`, `docker-compose.yml` o `docker-compose.dev.yml`, necesitas reconstruir los contenedores:

```bash
# Reconstruir y reiniciar contenedores
docker-compose -f docker-compose.dev.yml up -d --build

# O solo reconstruir sin cache
docker-compose -f docker-compose.dev.yml build --no-cache
docker-compose -f docker-compose.dev.yml up -d
```

## Reiniciar Todos los Servicios

Si los cambios no se reflejan después de intentar lo anterior, reinicia todos los servicios:

```bash
# Reiniciar todos los contenedores
docker-compose -f docker-compose.dev.yml restart

# O detener y volver a iniciar completamente
docker-compose -f docker-compose.dev.yml down
docker-compose -f docker-compose.dev.yml up -d
```

## Verificar Estado de los Contenedores

Para verificar que todos los contenedores están corriendo:

```bash
docker-compose -f docker-compose.dev.yml ps
```

Para ver los logs y verificar si hay errores:

```bash
# Ver logs de todos los servicios
docker-compose -f docker-compose.dev.yml logs -f

# Ver logs de un servicio específico
docker-compose -f docker-compose.dev.yml logs -f app
docker-compose -f docker-compose.dev.yml logs -f node
docker-compose -f docker-compose.dev.yml logs -f nginx
```

## Comandos Útiles Adicionales

### Acceder a los Contenedores

```bash
# Acceder al contenedor de la aplicación (PHP)
docker-compose -f docker-compose.dev.yml exec app bash

# Acceder al contenedor de Node
docker-compose -f docker-compose.dev.yml exec node sh

# Acceder al contenedor de la base de datos
docker-compose -f docker-compose.dev.yml exec db mysql -u cao_bnc_bot -proot cao_bnc_bot
```

### Ejecutar Comandos Artisan

```bash
# Ejecutar cualquier comando de Artisan
docker-compose -f docker-compose.dev.yml exec app php artisan [comando]

# Ejemplos:
docker-compose -f docker-compose.dev.yml exec app php artisan route:list
docker-compose -f docker-compose.dev.yml exec app php artisan tinker
docker-compose -f docker-compose.dev.yml exec app php artisan queue:work
```

### Ejecutar Comandos NPM

```bash
# Ejecutar cualquier comando de NPM
docker-compose -f docker-compose.dev.yml exec node npm [comando]

# Ejemplos:
docker-compose -f docker-compose.dev.yml exec node npm run dev
docker-compose -f docker-compose.dev.yml exec node npm run build
docker-compose -f docker-compose.dev.yml exec node npm run watch
```

## Diferencias entre docker-compose.yml y docker-compose.dev.yml

- **docker-compose.yml**: Configuración de producción con `npm run build`
- **docker-compose.dev.yml**: Configuración de desarrollo con `npm run dev` y hot reload

**Importante**: Asegúrate de usar el archivo correcto en los comandos según tu entorno:
- Desarrollo: `docker-compose -f docker-compose.dev.yml`
- Producción: `docker-compose -f docker-compose.yml` (o simplemente `docker-compose`)

## Solución de Problemas Comunes

### Los cambios no se reflejan

1. Verifica que los volúmenes estén montados correctamente:
   ```bash
   docker-compose -f docker-compose.dev.yml config
   ```

2. Verifica que los contenedores estén corriendo:
   ```bash
   docker-compose -f docker-compose.dev.yml ps
   ```

3. Limpia la caché de Laravel:
   ```bash
   docker-compose -f docker-compose.dev.yml exec app php artisan optimize:clear
   ```

4. Reinicia los contenedores:
   ```bash
   docker-compose -f docker-compose.dev.yml restart
   ```

### El hot reload no funciona en el frontend

1. Verifica que el servicio `node` esté corriendo con `npm run dev`:
   ```bash
   docker-compose -f docker-compose.dev.yml logs node
   ```

2. Reinicia el contenedor de Node:
   ```bash
   docker-compose -f docker-compose.dev.yml restart node
   ```

3. Verifica que el puerto 5173 esté expuesto en `docker-compose.dev.yml`

### Errores de permisos

Si tienes problemas de permisos con los archivos:

```bash
# Ajustar permisos del directorio de almacenamiento
docker-compose -f docker-compose.dev.yml exec app chmod -R 775 storage bootstrap/cache
docker-compose -f docker-compose.dev.yml exec app chown -R www-data:www-data storage bootstrap/cache
```

## Resumen Rápido

| Tipo de Cambio | Comando |
|----------------|---------|
| PHP/Laravel | Se refleja automáticamente (o `php artisan optimize:clear`) |
| JavaScript/React | Se refleja automáticamente con hot reload |
| Nuevas dependencias NPM | `docker-compose -f docker-compose.dev.yml exec node npm install` |
| Nuevas migraciones | `docker-compose -f docker-compose.dev.yml exec app php artisan migrate` |
| Cambios en Dockerfile | `docker-compose -f docker-compose.dev.yml up -d --build` |
| Todo no funciona | `docker-compose -f docker-compose.dev.yml restart` |

