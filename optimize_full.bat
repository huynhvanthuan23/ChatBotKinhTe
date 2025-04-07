@echo off
echo ===== TỐI ƯU GPU CHO CHATBOTKINHTE =====
echo.

echo 1. Toi uu cau hinh PyTorch...
python optimize_gpu.py --optimize

echo.
echo 2. Cap nhat cau hinh trong .env...
python optimize_gpu.py --update-env --n-gpu-layers 24 --n-ctx 4096 --n-batch 512

echo.
echo 3. Toi uu he thong Windows cho GPU...
echo.

REM Đặt NVIDIA GPU vào high performance mode
echo Dang dat GPU o che do hieu suat cao...
powershell -Command "If (Get-Command 'nvidia-smi' -ErrorAction SilentlyContinue) { nvidia-smi -i 0 -pm 1 }"

REM Tắt các ứng dụng không cần thiết
echo Dang dong cac ung dung khong can thiet...
taskkill /F /IM "chrome.exe" 2>NUL
taskkill /F /IM "msedge.exe" 2>NUL
taskkill /F /IM "firefox.exe" 2>NUL

REM Giải phóng bộ nhớ hệ thống
echo Dang giai phong bo nho he thong...
PowerShell -Command "& {Add-Type -AssemblyName System.Runtime.InteropServices; [System.Runtime.InteropServices.Marshal]::FreeHGlobal([System.Runtime.InteropServices.Marshal]::AllocHGlobal(1GB))}"

echo.
echo 4. Khoi dong lai server voi cau hinh moi...
echo Hay chay python main.py de khoi dong server

echo.
echo Toi uu hoan tat! 