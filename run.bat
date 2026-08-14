@echo off
setlocal

where docker >nul 2>&1
if errorlevel 1 (
    echo Khong tim thay Docker. Hay cai dat va khoi dong Docker Desktop.
    exit /b 1
)

docker compose version >nul 2>&1
if errorlevel 1 (
    echo Docker Compose khong san sang. Hay cap nhat Docker Desktop.
    exit /b 1
)

docker info >nul 2>&1
if errorlevel 1 (
    echo Docker Engine chua chay. Hay khoi dong Docker Desktop.
    exit /b 1
)

docker compose up --build
exit /b %errorlevel%
