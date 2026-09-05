@echo off
setlocal EnableExtensions EnableDelayedExpansion
title Hospital de Malanje - Instalador local

set "ROOT=%~dp0"
set "APP_SOURCE=%ROOT%artifacts\hospital-malanje"
set "DB_NAME=hospital_malanje"
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_USER=root"
set "DB_PASS="

if not exist "%APP_SOURCE%\public\index.php" (
    echo.
    echo [ERRO] Nao foi encontrada a aplicacao em:
    echo        %APP_SOURCE%
    echo.
    pause
    exit /b 1
)

:detect_stack
set "STACK="
set "STACK_TYPE="
set "WEBROOT="
set "PHP_EXE="
set "MYSQL_EXE="
set "MYSQLADMIN_EXE="
set "STACK_START="

for %%D in ("C:\xampp" "%ProgramFiles%\xampp" "%ProgramFiles(x86)%\xampp") do (
    if not defined STACK if exist "%%~D\apache\bin\httpd.exe" if exist "%%~D\php\php.exe" (
        set "STACK=%%~D"
        set "STACK_TYPE=XAMPP"
        set "WEBROOT=%%~D\htdocs"
        set "PHP_EXE=%%~D\php\php.exe"
        set "MYSQL_EXE=%%~D\mysql\bin\mysql.exe"
        set "MYSQLADMIN_EXE=%%~D\mysql\bin\mysqladmin.exe"
        set "STACK_START=%%~D\xampp_start.exe"
    )
)

for %%D in ("C:\wamp64" "C:\wamp" "%ProgramFiles%\wamp64" "%ProgramFiles%\wamp") do (
    if not defined STACK if exist "%%~D\wampmanager.exe" (
        set "STACK=%%~D"
        set "STACK_TYPE=WAMP"
        set "WEBROOT=%%~D\www"
        set "STACK_START=%%~D\wampmanager.exe"
        for /d %%P in ("%%~D\bin\php\php*") do if exist "%%~P\php.exe" set "PHP_EXE=%%~P\php.exe"
        for /d %%M in ("%%~D\bin\mysql\mysql*" "%%~D\bin\mariadb\mariadb*") do (
            if exist "%%~M\bin\mysql.exe" set "MYSQL_EXE=%%~M\bin\mysql.exe"
            if exist "%%~M\bin\mysqladmin.exe" set "MYSQLADMIN_EXE=%%~M\bin\mysqladmin.exe"
        )
    )
)

if not defined STACK (
    echo.
    echo [ATENCAO] Nao foi detectado XAMPP ou WAMP neste computador.
    echo O instalador precisa de Apache, PHP e MySQL/MariaDB.
    choice /C SN /N /M "Abrir a pagina oficial para instalar XAMPP? [S/N] "
    if errorlevel 2 (
        echo Instale XAMPP ou WAMP e execute este ficheiro novamente.
        pause
        exit /b 2
    )
    start "" "https://www.apachefriends.org/download.html"
    echo.
    echo Instale o XAMPP, mantenha o MySQL/MariaDB seleccionado e volte aqui.
    pause
    goto detect_stack
)

if not exist "%PHP_EXE%" (
    echo [ERRO] PHP nao encontrado em %STACK%.
    pause
    exit /b 3
)
if not exist "%MYSQL_EXE%" (
    echo [ERRO] MySQL/MariaDB nao encontrado em %STACK%.
    echo Reinstale o XAMPP/WAMP com o componente MySQL ou MariaDB.
    pause
    exit /b 4
)

"%PHP_EXE%" -m 2>nul | findstr /I /C:"pdo_mysql" >nul
if errorlevel 1 (
    echo [ERRO] A extensao PHP pdo_mysql nao esta activa.
    echo Active pdo_mysql no php.ini do XAMPP/WAMP e execute novamente.
    pause
    exit /b 5
)

set "APP_INSTALL=%WEBROOT%\hospital-malanje"
if not exist "%WEBROOT%" mkdir "%WEBROOT%" >nul 2>&1

echo.
echo [1/7] A verificar Apache...
call :http_ready
if errorlevel 1 (
    echo Apache nao esta em execucao. A abrir %STACK_TYPE%...
    if exist "%STACK_START%" start "" "%STACK_START%"
    call :wait_http
    if errorlevel 1 (
        echo [ERRO] Apache nao respondeu em http://localhost/.
        echo Abra o painel do XAMPP/WAMP e inicie o Apache manualmente.
        pause
        exit /b 6
    )
)

echo [2/7] A configurar acesso ao MySQL/MariaDB...
set /p "DB_USER=Utilizador MySQL [root]: "
if not defined DB_USER set "DB_USER=root"
call :read_password
set "MYSQL_PWD=%DB_PASS%"

"%MYSQL_EXE%" -h "%DB_HOST%" -P "%DB_PORT%" -u "%DB_USER%" -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
    echo MySQL/MariaDB nao esta em execucao. A tentar iniciar %STACK_TYPE%...
    if defined STACK_START start "" "%STACK_START%"
    call :wait_mysql
    if errorlevel 1 (
        echo [ERRO] Nao foi possivel ligar ao MySQL/MariaDB.
        echo Confirme o utilizador, a palavra-passe e se o MySQL esta verde no painel.
        pause
        exit /b 7
    )
)

echo [3/7] A criar a base de dados %DB_NAME%...
"%MYSQL_EXE%" -h "%DB_HOST%" -P "%DB_PORT%" -u "%DB_USER%" -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>&1
if errorlevel 1 (
    echo [ERRO] Nao foi possivel criar a base de dados.
    pause
    exit /b 8
)

echo [4/7] A copiar a aplicacao para:
echo        %APP_INSTALL%
if exist "%APP_INSTALL%" (
    echo A pasta ja existe. Os ficheiros da aplicacao serao actualizados e os dados locais serao preservados.
)
robocopy "%APP_SOURCE%" "%APP_INSTALL%" /E /XD node_modules dist .replit-artifact /XF config.local.php /NFL /NDL /NJH /NJS /NP >nul
if errorlevel 8 (
    echo [ERRO] Falha ao copiar a aplicacao.
    pause
    exit /b 9
)

echo [5/7] A importar as tabelas portuguesas...
"%MYSQL_EXE%" --default-character-set=utf8mb4 -h "%DB_HOST%" -P "%DB_PORT%" -u "%DB_USER%" "%DB_NAME%" < "%APP_SOURCE%\database\hospital-malanje.sql"
if errorlevel 1 (
    echo [ERRO] A importacao da base de dados falhou.
    pause
    exit /b 10
)

echo [6/7] A guardar a configuracao local e criar o administrador...
set "HM_DB_HOST=%DB_HOST%"
set "HM_DB_PORT=%DB_PORT%"
set "HM_DB_NAME=%DB_NAME%"
set "HM_DB_USER=%DB_USER%"
set "HM_DB_PASSWORD=%DB_PASS%"
"%PHP_EXE%" "%APP_INSTALL%\php\install_config.php"
if errorlevel 1 (
    echo [ERRO] Nao foi possivel guardar a configuracao local.
    pause
    exit /b 11
)
"%PHP_EXE%" "%APP_INSTALL%\php\install_admin.php"
if errorlevel 1 (
    echo [ERRO] Nao foi possivel criar ou validar o administrador.
    pause
    exit /b 12
)
del /q "%APP_INSTALL%\php\install_config.php" "%APP_INSTALL%\php\install_admin.php" >nul 2>&1

set "APP_URL=http://localhost/hospital-malanje/public/"
echo [7/7] A testar a aplicacao...
powershell -NoProfile -ExecutionPolicy Bypass -Command "try { $r=Invoke-WebRequest -UseBasicParsing -Uri '%APP_URL%?page=login' -TimeoutSec 15; if ($r.StatusCode -lt 200 -or $r.StatusCode -ge 400) { exit 1 } } catch { exit 1 }"
if errorlevel 1 (
    echo [ERRO] A aplicacao nao respondeu correctamente.
    echo Verifique o Apache, o php.ini e o ficheiro:
    echo        %APP_INSTALL%\php\config.local.php
    pause
    exit /b 13
)

set "MYSQL_PWD="
echo.
echo INSTALACAO CONCLUIDA COM SUCESSO.
echo.
echo Aplicacao:  %APP_URL%
echo Base dados:  http://localhost/phpmyadmin/
echo Nome BD:     %DB_NAME%
echo.
start "" "%APP_URL%"
start "" "http://localhost/phpmyadmin/"
pause
exit /b 0

:http_ready
powershell -NoProfile -ExecutionPolicy Bypass -Command "try { Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1/' -TimeoutSec 3 | Out-Null; exit 0 } catch { exit 1 }"
exit /b %errorlevel%

:wait_http
for /L %%N in (1,1,30) do (
    call :http_ready
    if not errorlevel 1 exit /b 0
    timeout /t 2 /nobreak >nul
)
exit /b 1

:wait_mysql
for /L %%N in (1,1,30) do (
    "%MYSQL_EXE%" -h "%DB_HOST%" -P "%DB_PORT%" -u "%DB_USER%" -e "SELECT 1" >nul 2>&1
    if not errorlevel 1 exit /b 0
    timeout /t 2 /nobreak >nul
)
exit /b 1

:read_password
set "DB_PASS="
for /f "usebackq delims=" %%P in (`powershell -NoProfile -ExecutionPolicy Bypass -Command "$s=Read-Host 'Palavra-passe MySQL (Enter se vazia)' -AsSecureString; $b=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($s); try {[Runtime.InteropServices.Marshal]::PtrToStringBSTR($b)} finally {[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b)}"`) do set "DB_PASS=%%P"
exit /b 0