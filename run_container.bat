@echo off
echo === Dang dung va xoa container cu ===
docker stop chatbot-kinhte 2>nul
docker rm chatbot-kinhte 2>nul


echo === Dang chay container moi ===
docker run -d --name chatbot-kinhte -p 55050:55050 ^
  -v "%cd%\vector_db:/app/vector_db" ^
  -v "%cd%\storage:/app/storage" ^
  -v "%cd%\web\public\storage:/app/web/public/storage" ^
  -v "%cd%\app.log:/app/app.log" ^
  -v "%cd%\.env:/app/.env" ^
  chatbot-kinhte:55050
