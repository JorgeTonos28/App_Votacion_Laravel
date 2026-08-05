# InnovaMente · Plataforma de votación (Laravel)

Versión Laravel de la aplicación de votación InnovaMente. Replica los flujos, el diseño, las reglas de evaluación y los datos de demostración de la aplicación ASP.NET original.

## Requisitos

- PHP 8.3 o superior con SQLite/MySQL, Mbstring, OpenSSL y GD (necesario para QR)
- Composer 2

## Instalación y ejecución

```powershell
cd C:\Dev\AppsWeb\App_Votacion_Laravel
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

La aplicación estará disponible en `http://127.0.0.1:8000`.

## Accesos demo

| Perfil | Dirección | Credencial |
|---|---|---|
| Público | `/e/BTP726` | Nombre del visitante |
| Jurado | `/jurado?eventCode=BTP726` | `J7K4-PQ9M` |
| Administración | `/admin/acceso` | `admin@innovamente.local` / `Admin-InnovaMente!2026` |
| Proyección | `/proyeccion/BTP726` | Acceso directo |

Las credenciales se pueden cambiar en `.env` antes de ejecutar el seeder.

## Pruebas y mantenimiento

```powershell
php artisan test
vendor\bin\pint --test
php artisan route:list --except-vendor
```

La base de datos predeterminada es `database/database.sqlite`. Para reiniciar completamente la demostración, ejecuta `php artisan migrate:fresh --seed`.

## Despliegue en cPanel

Para cPanel recomendamos MySQL/MariaDB en lugar de SQLite. SQLite funciona para desarrollo local, pero MySQL es más adecuado para sesiones, caché, concurrencia y copias de seguridad en hosting compartido. La aplicación ya incluye la conexión MySQL de Laravel y migraciones compatibles.

1. En cPanel crea una base de datos MySQL, un usuario y asígnale todos los privilegios. Anota los nombres completos, que normalmente incluyen el prefijo de tu cuenta.
2. Sube el contenido del proyecto al directorio de la aplicación. Apunta el dominio al directorio `public/` o usa el document root configurado por tu hosting.
3. Copia `.env.cpanel.example` como `.env` y completa `APP_URL`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` con los valores de cPanel.
4. Ejecuta en Terminal de cPanel:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
composer run cpanel:deploy
php artisan db:seed --force
```

Si tu plan no ofrece Terminal/SSH, puedes importar las tablas desde phpMyAdmin, pero es preferible ejecutar las migraciones con Artisan para conservar índices, claves foráneas y futuras actualizaciones. No subas el archivo `.env` al repositorio ni dejes `APP_DEBUG=true` en producción.

### Actualizar desde GitHub

Después de sincronizar una nueva versión del repositorio, ejecuta siempre:

```bash
cd /home/batallawork/ia.batalla.work.gd
composer install --no-dev --optimize-autoloader
composer run cpanel:deploy
```

El comando `cpanel:deploy` elimina primero las cachés de configuración, rutas y vistas de la versión anterior; luego aplica las migraciones pendientes y reconstruye las cachés con el código recién descargado. Esto evita errores como `Route [...] not defined` cuando una vista nueva se publica junto con rutas nuevas.
