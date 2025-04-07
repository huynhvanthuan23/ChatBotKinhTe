@echo off
echo ===== CAI DAT FAISS-GPU CHO WINDOWS =====
echo.

REM Xác định phiên bản Python
python -c "import sys; print('Python version: ' + '.'.join(str(i) for i in sys.version_info[:3]))"
FOR /F "tokens=3" %%a IN ('python -c "import sys; print(sys.version_info[0])"') DO SET PYTHON_MAJOR=%%a
FOR /F "tokens=3" %%a IN ('python -c "import sys; print(sys.version_info[1])"') DO SET PYTHON_MINOR=%%a

echo Python version detected: %PYTHON_MAJOR%.%PYTHON_MINOR%

REM Tạo mã phiên bản cho URL
SET CP_VER=cp%PYTHON_MAJOR%%PYTHON_MINOR%

REM URL cơ sở cho wheels
SET BASE_URL=https://dl.fbaipublicfiles.com/faiss/wheel

REM Xác định kiến trúc
FOR /F "tokens=3" %%a IN ('python -c "import platform; print(platform.architecture()[0])"') DO SET ARCH=%%a

IF "%ARCH%"=="64bit" (
    SET ARCH_TAG=win_amd64
) ELSE (
    SET ARCH_TAG=win32
)

echo Architecture detected: %ARCH_TAG%

REM Tạo URL đầy đủ
SET WHEEL_URL=%BASE_URL%/faiss_gpu-1.7.4-%CP_VER%-%CP_VER%-%ARCH_TAG%.whl

echo.
echo Attempting to install FAISS-GPU from: %WHEEL_URL%
echo.

REM Gỡ cài đặt faiss-cpu nếu có
pip uninstall -y faiss-cpu

REM Cài đặt faiss-gpu với URL chính xác
pip install faiss-gpu==1.7.4 --no-deps -f %WHEEL_URL%

IF %ERRORLEVEL% NEQ 0 (
    echo.
    echo Could not install FAISS-GPU with the specific wheel.
    echo Trying alternative installation methods...
    echo.
    
    REM Thử cài đặt faiss-gpu từ PyPI trực tiếp
    echo Attempting to install FAISS-GPU from PyPI...
    pip install faiss-gpu
    
    IF %ERRORLEVEL% NEQ 0 (
        echo.
        echo FAISS-GPU installation failed. Trying to install via conda...
        echo.
        
        REM Kiểm tra xem conda có sẵn không
        WHERE conda >nul 2>nul
        IF %ERRORLEVEL% NEQ 0 (
            echo Conda not found. Please install Anaconda or Miniconda first.
            echo You can download it from: https://www.anaconda.com/products/distribution
            echo.
            echo Falling back to CPU version of FAISS.
            pip install faiss-cpu
        ) ELSE (
            echo Installing FAISS-GPU via conda...
            conda install -c pytorch faiss-gpu
        )
    )
)

echo.
echo Testing FAISS installation...
python -c "import faiss; print('FAISS version:', faiss.__version__); print('FAISS has GPU support:', 'Yes' if hasattr(faiss, 'GpuIndexFlatL2') else 'No')"

echo.
echo FAISS installation completed. Please restart your application. 