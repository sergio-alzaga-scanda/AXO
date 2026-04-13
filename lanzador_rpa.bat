@echo off
title Lanzador de RPA (UiPath) - Verificador de Tickets
color 0A

:loop
echo [%time%] Verificando peticiones pendientes en Teams...

REM Realiza la peticion a la API y guarda el resultado en un archivo temporal
curl -s -X GET "https://proyectos.kenos-atom.com/axo/api_get_espera.php" > temp_response.json

REM Busca la frase "en espera" o algún contenido en el JSON
findstr /C:"en espera" temp_response.json >nul

IF %ERRORLEVEL% EQU 0 (
    echo [%time%] Se encontro una peticion en espera! Ejecutando RPA...
    
    REM Ir a la carpeta donde está el paquete
    cd /d "C:\rpa\SuccessFactor"
    
    REM Ejecutar el proceso con UiPath Robot
    "C:\UIPath\UiRobot.exe" execute --file "C:\rpa\SuccessFactor\SuccessFactor.1.0.1.nupkg"
    
    echo [%time%] Proceso de RPA finalizado.
) ELSE (
    echo [%time%] No hay peticiones pendientes.
)

REM Eliminar el archivo temporal para mantener limpio el entorno
del temp_response.json

echo [%time%] Esperando 1 minuto para volver a verificar...
timeout /T 60 /NOBREAK >nul

goto loop
