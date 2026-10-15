@echo off
setlocal
title TGIF ERP - Port 8075
cd /d "%~dp0modules"
if errorlevel 1 goto failed

set "PHP_EXE=php"
where php >nul 2>nul
if not errorlevel 1 goto php_ready
set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" (
    echo PHP was not found. Add PHP to PATH or install it in C:\xampp\php.
    goto failed
)
:php_ready

if not exist "vendor\autoload.php" (
    echo Dependencies are missing. Run composer install inside the modules folder.
    goto failed
)
if not exist "public\build\manifest.json" (
    echo Frontend assets are missing. Run npm ci and npm run build inside modules.
    goto failed
)

set "APP_URL=http://127.0.0.1:8075"
if exist ".env" goto serve

echo No .env found. Starting the isolated local demo preview.
echo Demo login: admin / password
set "APP_ENV=local"
set "APP_DEBUG=true"
set "DB_CONNECTION=sqlite"
set "DB_DATABASE=%TEMP%\tgif-design-preview\preview.sqlite"
set "SESSION_DRIVER=file"
set "CACHE_STORE=array"
if not exist "%TEMP%\tgif-design-preview" mkdir "%TEMP%\tgif-design-preview"
if errorlevel 1 goto failed
if not exist "%DB_DATABASE%" type nul > "%DB_DATABASE%"
if errorlevel 1 goto failed
if exist "%TEMP%\tgif-design-preview\preview.key" goto preview_key_ready
"%PHP_EXE%" -r "echo 'base64:'.base64_encode(random_bytes(32));" > "%TEMP%\tgif-design-preview\preview.key"
if errorlevel 1 goto failed
:preview_key_ready
set /p APP_KEY=<"%TEMP%\tgif-design-preview\preview.key"
if not defined APP_KEY goto failed
"%PHP_EXE%" artisan migrate --force
if errorlevel 1 goto failed
"%PHP_EXE%" artisan db:seed --class=AdminSeeder --force
if errorlevel 1 goto failed
"%PHP_EXE%" artisan db:seed --class=StorefrontDemoSeeder --force
if errorlevel 1 goto failed

:serve
echo.
echo TGIF ERP: http://127.0.0.1:8075/admin
echo Keep this window open. Press Ctrl+C to stop the server.
echo.
"%PHP_EXE%" artisan serve --host=127.0.0.1 --port=8075 --tries=1
if errorlevel 1 goto failed
exit /b 0

:failed
echo.
echo TGIF could not start. Review the message above.
pause
exit /b 1
