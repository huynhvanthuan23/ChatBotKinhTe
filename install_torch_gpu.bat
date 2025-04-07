@echo off
echo ===== Cai dat PyTorch voi CUDA cho Windows =====
echo.

REM Kiem tra Python version
python --version
if %ERRORLEVEL% NEQ 0 (
    echo Khong tim thay Python. Hay dam bao Python da duoc cai dat.
    exit /b 1
)

echo.
echo Dang cai dat PyTorch voi CUDA 11.8...
pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu118

echo.
echo Kiem tra CUDA support...
python -c "import torch; print(f'CUDA available: {torch.cuda.is_available()}'); print(f'CUDA version: {torch.version.cuda}'); print(f'GPU count: {torch.cuda.device_count()}'); print(f'GPU name: {torch.cuda.get_device_name(0) if torch.cuda.is_available() else \"None\"}')"

echo.
echo Dang cai dat llama-cpp-python voi CUDA...
echo.
echo Cai dat prerequisites cho llama-cpp-python...
pip install cmake scikit-build setuptools-rust

echo.
echo Cai dat llama-cpp-python voi CUDA...
set CMAKE_ARGS=-DLLAMA_CUBLAS=on
set FORCE_CMAKE=1
pip uninstall -y llama-cpp-python
pip install llama-cpp-python==0.2.19 --no-cache-dir

echo.
echo Hoan tat! Hay khoi dong lai server de ap dung cac thay doi. 