@echo off
echo === Dang dung va xoa container cu ===
docker stop chatbot-kinhte 2>nul
docker rm chatbot-kinhte 2>nul

echo === Kiem tra va tao thu muc storage neu chua ton tai ===
if not exist "%cd%\storage" (
    echo === Tao thu muc storage ===
    mkdir "%cd%\storage"
    mkdir "%cd%\storage\documents"
)

if not exist "%cd%\web\public\storage" (
    echo === Tao thu muc Laravel public storage ===
    mkdir "%cd%\web\public\storage"
    mkdir "%cd%\web\public\storage\documents"
)

echo === Dang build image moi ===
docker build -t chatbot-kinhte:55050 .

echo === Dang chay container moi ===
docker run -d --name chatbot-kinhte -p 55050:55050 ^
  -v "%cd%\vector_db:/app/vector_db" ^
  -v "%cd%\storage:/app/storage" ^
  -v "%cd%\web\public\storage:/app/web/public/storage" ^
  -v "%cd%\app.log:/app/app.log" ^
  -v "%cd%\.env:/app/.env" ^
  chatbot-kinhte:55050


@REM echo === Luu image ra file tar ===
@REM docker save -o sys_55050.tar chatbot-kinhte:55050

echo === Trang thai container ===
wmic process where "name='dockerd.exe'" list brief
docker ps --format "table {{.ID}}\t{{.Names}}\t{{.Status}}\t{{.Ports}}"

echo === Hoan thanh! Container dang chay voi port 55050 ===
echo Xem log: docker logs -f chatbot-kinhte 