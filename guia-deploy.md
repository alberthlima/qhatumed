# Guía de Despliegue de Aplicaciones Desacopladas (Laravel API + Vue Frontend) en un Solo Subdominio bajo cPanel

Este informe documenta la arquitectura técnica, la configuración de enrutamiento y el proceso paso a paso para desplegar un frontend desarrollado en **Vue/Vite** y un backend API desarrollado en **Laravel 11 (PHP 8.2+)** bajo un mismo subdominio (`mkmedic.csstbolivia.com`) en un entorno de hosting compartido administrado con cPanel (como Banahosting).

Esta arquitectura mantiene el código fuente de ambas aplicaciones completamente separado físicamente en subcarpetas (`/frontend` y `/api-panel` -o `/backend` si prefieres renombrarla-), lo que evita la colisión de archivos estáticos y facilita el mantenimiento y la actualización independiente de cada parte del sistema.

---

## 1. Arquitectura de Directorios en el Servidor

El subdominio creado en cPanel debe estar apuntando a un directorio raíz (su **Document Root**). Dentro de ese directorio raíz, estructuramos las carpetas de la siguiente manera (usando el nombre actual de la carpeta de la API `api-panel`):

```text
/home/mgjaalyy/mkmedic.csstbolivia.com/      <-- DOCUMENT ROOT (Ruta raíz del subdominio)
├── .htaccess                                <-- Enrutador principal de Apache (Despachador)
├── frontend/                                <-- Carpeta del Frontend (Contenido de la carpeta dist/ de Vue)
│   ├── index.html                           <-- Entrada principal de la Single Page Application (SPA)
│   └── assets/                              <-- Archivos compilados de JS, CSS, imágenes, etc.
└── api-panel/                               <-- Carpeta del Backend (Proyecto Laravel completo)
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── routes/
    ├── vendor/
    ├── .env                                 <-- Configuración confidencial del Backend
    ├── artisan                              <-- Interfaz de línea de comandos de Laravel
    └── public/                              <-- Carpeta pública interna de Laravel
        ├── index.php                        <-- Entrada de la API de Laravel
        └── .htaccess                        <-- Reglas de reescritura nativas de Laravel
```

> [!IMPORTANT]
> **El Document Root en cPanel debe apuntar a la raíz (`mkmedic.csstbolivia.com`)**, y **no** a `frontend/` ni a `api-panel/public/`. El archivo `.htaccess` en la raíz se encarga de reescribir las peticiones internamente de manera transparente para el usuario final.
> Si prefieres renombrar la carpeta `api-panel` a `backend` en el servidor, puedes hacerlo; solo asegúrate de ajustar las rutas en el archivo `.htaccess` principal acorde a ese nombre.

---

## 2. Configuración de Archivos `.htaccess` (Enrutamiento)

Para lograr que el servidor Apache redirija las peticiones de `/api/*` al backend de Laravel, y cualquier otra ruta al frontend de Vue (soportando el modo *History* de Vue Router), se configuran dos niveles de archivos `.htaccess`.

### A. `.htaccess` de la Raíz (`mkmedic.csstbolivia.com/.htaccess`)
Este archivo actúa como un despachador o semáforo. Lee la petición entrante y la reescribe de forma invisible según su destino. También incluye la directiva de cPanel para seleccionar la versión correcta de PHP:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Redirigir la cabecera de Autorización HTTP (necesario para JWT o Sanctum en Laravel)
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # ------------------------------------------------------------------------
    # 0. EVITAR BUCLES: Si la petición ya fue reescrita a frontend/ o api-panel/, detener la reescritura
    # ------------------------------------------------------------------------
    RewriteCond %{REQUEST_URI} ^/(frontend|api-panel) [NC,OR]
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    # ------------------------------------------------------------------------
    # 1. API: Si la petición empieza con /api, la reescribe a la carpeta pública de Laravel
    # ------------------------------------------------------------------------
    RewriteCond %{REQUEST_URI} ^/api [NC]
    RewriteRule ^api/(.*)$ api-panel/public/index.php/$1 [L,QSA]

    # ------------------------------------------------------------------------
    # 1.5 STORAGE (Archivos Subidos): Si la petición es para /storage, redirige al storage del backend
    #     (Nota: Puedes usar api-panel/public/storage/$1 si creaste el enlace simbólico,
    #      o directamente api-panel/storage/app/public/$1 si no tienes SSH para crearlo)
    # ------------------------------------------------------------------------
    RewriteCond %{REQUEST_URI} ^/storage [NC]
    RewriteRule ^storage/(.*)$ api-panel/public/storage/$1 [L]

    # ------------------------------------------------------------------------
    # 2. FRONTEND (Archivos Físicos): Si la petición es para un archivo con extensión (js, css, png, etc.), lo sirve desde la carpeta frontend
    #    (Evita usar %{DOCUMENT_ROOT} que a menudo apunta a la raíz del dominio principal en cPanel)
    # ------------------------------------------------------------------------
    RewriteCond %{REQUEST_URI} \.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2|ttf|eot|json|txt|pdf|mp4|map)$ [NC]
    RewriteRule ^(.*)$ frontend/$1 [L]

    # ------------------------------------------------------------------------
    # 3. FRONTEND (Rutas Virtuales): Si no es la API y no es un archivo real (rutas de Vue Router), sirve index.html
    #    (Crucial para evitar errores 404 al recargar páginas en SPAs con Vue Router)
    # ------------------------------------------------------------------------
    RewriteCond %{REQUEST_URI} !^/api [NC]
    RewriteRule ^(.*)$ frontend/index.html [L]
</IfModule>

# php -- BEGIN cPanel-generated handler, do not edit
# Establece el paquete "ea-php82" como el lenguaje de programación "PHP" por defecto.
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php82 .php .php8 .phtml
</IfModule>
# php -- END cPanel-generated handler, do not edit
```

*(Nota: Si renombraste tu carpeta de Laravel a `backend`, cambia `api-panel/public/index.php/$1` por `backend/public/index.php/$1` en la línea 11).*

> [!NOTE]
> **Sobre la falta de `.htaccess` en la raíz del Backend:**
> Es totalmente normal y correcto **no tener** un archivo `.htaccess` en la raíz de la carpeta de Laravel (`api-panel/` o `backend/`).
> El único archivo `.htaccess` del backend necesario es el que se encuentra dentro de su carpeta pública (`api-panel/public/.htaccess`), el cual ya viene pre-configurado por Laravel por defecto.
> Dado que el `.htaccess` de la raíz del subdominio redirige las peticiones directamente a `api-panel/public/index.php`, Apache procesa la petición de forma segura sin requerir ningún `.htaccess` en la carpeta raíz del backend.

### B. `.htaccess` del Backend (`api-panel/public/.htaccess`)
Mantiene la configuración nativa de Laravel para procesar las peticiones a través de su controlador principal (`index.php`):

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Redirigir cabeceras de autorización
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirigir barras inclinadas finales si no es un directorio real
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Enviar las peticiones al Front Controller de Laravel
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

---

## 3. Guía Paso a Paso para el Despliegue

### Paso 1: Preparación y Compilación del Frontend (Vue)
1. Abre el archivo `.env` del frontend local (`frontend/.env`). Asegúrate de configurar la URL base de la API de forma relativa:
   ```env
   VITE_API_BASE_URL = /api
   ```
2. Ejecuta el comando de compilación en tu terminal local:
   ```bash
   pnpm run build   # o npm run build
   ```
3. Esto generará la carpeta `dist/`. Sube **únicamente el contenido interno** de esta carpeta `dist/` a la carpeta `frontend/` en tu servidor hosting.

### Paso 2: Preparación e Instalación del Backend (Laravel)
1. Sube todo el código de tu proyecto Laravel en `api-panel` (excluyendo la carpeta `node_modules/`, `vendor/` y `.git/`) a la carpeta `api-panel/` (o renombrada a `backend/`) en el hosting.
2. Si tienes acceso por SSH a la terminal de tu hosting:
   - Navega a la carpeta del backend y ejecuta: `composer install --no-dev --optimize-autoloader`.
   - Si no tienes SSH, realiza el paso de `composer install` localmente y luego sube la carpeta `vendor/` completa al servidor.
3. Asegúrate de que las carpetas `storage/` y `bootstrap/cache/` tengan permisos de escritura (generalmente `755` o `775` en cPanel).

### Paso 3: Configuración del `.env` en el Servidor Backend
Crea o edita el archivo `api-panel/.env` (en el hosting) con los valores de producción:
```env
APP_NAME="Farmacia Mi Salud"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mkmedic.csstbolivia.com

# Muy importante para que coincidan las horas de tareas programadas con la hora del usuario
APP_TIMEZONE=America/La_Paz

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_de_tu_base_de_datos
DB_USERNAME=usuario_de_la_base_de_datos
DB_PASSWORD=contraseña_segura
```

### Paso 4: Configuración de la Base de Datos
1. Crea la base de datos y el usuario correspondiente en la sección **Bases de datos MySQL** de cPanel. Asocia el usuario a la base de datos otorgando **todos los privilegios**.
2. **Ejecutar Migraciones:**
   - **Opción A (Recomendada con SSH):** Ejecuta `php artisan migrate --force` desde la terminal del servidor en la carpeta del backend.
   - **Opción B (Sin SSH):** Exporta la base de datos local en formato `.sql` e impórtala directamente en la base de datos del hosting mediante **phpMyAdmin** desde cPanel.

---

## 4. Requisitos y Configuración del Servidor en cPanel

Para asegurar la correcta ejecución del backend de Laravel 11, configura lo siguiente en cPanel:

1. **Versión de PHP:**
   - Entra a **Administrador MultiPHP** o **Seleccionar Versión de PHP** en cPanel.
   - Selecciona **PHP 8.2** o superior para el subdominio `mkmedic.csstbolivia.com`.
2. **Módulos / Extensiones de PHP Requeridas:**
   - Asegúrate de tener marcadas las extensiones: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`.
3. **Variables de Configuración en PHP (`php.ini`):**
   - `memory_limit`: `256M` (o superior)
   - `upload_max_filesize`: `32M`
   - `post_max_size`: `32M`

---

## 5. Configuración de Correo Electrónico (SMTP) en cPanel

Para el envío correcto de correos electrónicos desde Laravel utilizando el servicio de correo del propio hosting (cPanel), sigue estos pasos:

### Paso A: Crear la Cuenta de Correo en cPanel
1. Accede a cPanel y ve a **Cuentas de correo electrónico** (Email Accounts).
2. Haz clic en **Crear** (Create).
3. Introduce el nombre de la cuenta (ej. `no-reply@csstbolivia.com`) y genera una contraseña segura. Guarda esta contraseña.
4. Una vez creada la cuenta, haz clic en **Connect Devices** (Conectar dispositivos) junto a la cuenta para ver los parámetros de conexión SMTP.

### Paso B: Configurar las Variables de Entorno en el `.env` del backend
Reemplaza la sección `MAIL_*` del archivo `.env` en tu servidor con los datos de conexión SMTP obtenidos:

```env
# Cambiar el driver de "log" a "smtp"
MAIL_MAILER=smtp

# Servidor de salida SMTP (generalmente mail.tudominio.com o el host del servidor)
MAIL_HOST=mail.csstbolivia.com

# Puerto de conexión (Recomendado: 465 con SSL para mayor seguridad)
MAIL_PORT=465

# Cuenta de correo creada en cPanel
MAIL_USERNAME=no-reply@csstbolivia.com

# Contraseña de la cuenta de correo creada
MAIL_PASSWORD=tu_contraseña_segura_de_correo

# Cifrado (ssl para puerto 465, o tls para puerto 587)
MAIL_ENCRYPTION=ssl

# Dirección de correo desde donde se envían las alertas (debe ser la misma que MAIL_USERNAME)
MAIL_FROM_ADDRESS=no-reply@csstbolivia.com

# Nombre visible en el remitente del correo
MAIL_FROM_NAME="${APP_NAME}"
```

> [!TIP]
> Si editas el archivo `.env` en producción y notas que no se aplican los cambios, limpia la caché de configuración ejecutando en la consola del hosting:
> ```bash
> php artisan config:clear
> ```

---

## 6. Automatización del Planificador de Laravel (Cron Job)

Las tareas automatizadas (como el envío de notificaciones automáticas por Telegram y Correo de bloques de trabajo) requieren que el planificador de Laravel se ejecute cada minuto.

1. En cPanel, busca y abre la herramienta **Tareas Cron** (Cron Jobs).
2. En **Configuración común**, selecciona **Una vez por minuto** (`* * * * *`).
3. En el campo **Comando**, ingresa la llamada al ejecutable de PHP apuntando a tu archivo `artisan` usando la ruta absoluta del hosting:
   ```bash
   /usr/local/bin/php /home/mgjaalyy/mkmedic.csstbolivia.com/api-panel/artisan schedule:run >> /dev/null 2>&1
   ```
   *(Nota: Reemplaza `/home/mgjaalyy/mkmedic.csstbolivia.com/api-panel/artisan` por tu ruta absoluta real en el hosting, y si renombraste la carpeta a `backend`, asegúrate de actualizar la ruta).*

---

## 7. Puntos de Control y Resolución de Errores Comunes (Troubleshooting)

| Problema / Síntoma | Causa Probable | Solución |
| :--- | :--- | :--- |
| **Error 404 al llamar a `/api/...`** | El punto de entrada nativo de Laravel `api-panel/public/index.php` se borró, o las rutas en el `.htaccess` de la raíz están mal escritas. | Asegúrate de tener los archivos nativos de la carpeta `public` de Laravel y que la regla apunte a `api-panel/public/index.php/$1` con `[L,QSA]`. |
| **La API responde con el código HTML de `index.html`** | La regla de reescritura del frontend en el `.htaccess` de la raíz está capturando las peticiones de la API de manera incorrecta. | Verifica que las condiciones `RewriteCond %{REQUEST_URI} !^/api [NC]` estén bien configuradas en el `.htaccess` de la raíz para evitar que el frontend procese rutas de la API. |
| **Error de JS: `SyntaxError: "undefined" is not valid JSON`** | Se guardaron strings con valor `"undefined"` en el `localStorage` del navegador durante las pruebas de login fallidas. | Limpia el almacenamiento local del navegador (`localStorage.clear()`) y recarga la página. |
| **El scheduler de Laravel no envía alertas** | La hora del servidor está desfasada (por defecto UTC) y no coincide con la hora local en la base de datos. | Añade `APP_TIMEZONE=America/La_Paz` al archivo `.env` del backend y limpia la caché de configuración ejecutando `php artisan config:clear`. |
| **El archivo `.env` del frontend no toma cambios en producción** | Vite compila las variables de entorno dentro del bundle JS final durante el proceso de build. | Debes compilar el frontend (`pnpm run build`) **después** de definir la variable `VITE_API_BASE_URL = /api` en tu archivo `.env` local, y luego subir los archivos compilados resultantes. |
| **`Failed to load module script... MIME type of "text/html"`** | Apache no encuentra los archivos físicos compilados de JS/CSS y devuelve el `index.html` (MIME text/html) por la regla fallback. Esto pasa porque `%{DOCUMENT_ROOT}` apunta a la raíz del dominio principal en cPanel y no al subdominio. | Actualiza la Regla 2 del `.htaccess` de la raíz para redireccionar archivos por extensión de forma directa (como se indica en la sección 2.A), eliminando la dependencia de `%{DOCUMENT_ROOT}`. |
| **Error 404 al cargar imágenes subidas (`/storage/...`)** | La regla 2 del `.htaccess` principal captura la extensión de la imagen (`.png`, `.jpg`, etc.) y la busca erróneamente en la carpeta `frontend/storage/` en lugar de la del backend. | Agrega la regla intermedia para `/storage` en el `.htaccess` principal que redireccione a `api-panel/public/storage/$1` y asegúrate de que el enlace simbólico (`php artisan storage:link`) esté creado. (Alternativamente, puedes apuntar directamente a `api-panel/storage/app/public/$1` si no tienes acceso SSH para crear el enlace simbólico). |

---

*Esta guía sirve como antecedente técnico y plantilla para futuros despliegues desacoplados bajo un mismo subdominio utilizando cPanel.*
