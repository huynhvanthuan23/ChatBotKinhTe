import logging
import os
import sys
import platform
import codecs
from logging.handlers import RotatingFileHandler
from .config import settings  # Import settings từ config.py

# Thiết lập UTF-8 encoding cho các stream output
sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer)
sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer)

def get_logger(name: str) -> logging.Logger:
    """
    Tạo và cấu hình đối tượng logger
    """
    logger = logging.getLogger(name)
    
    # Nếu logger đã được cấu hình, trả về ngay lập tức
    if logger.handlers:
        return logger
    
    # Cấu hình cơ bản - sử dụng LOG_LEVEL từ settings
    log_level = getattr(logging, settings.LOG_LEVEL, logging.INFO)
    logger.setLevel(log_level)
    
    # Định dạng log
    formatter = logging.Formatter(
        '%(asctime)s [%(name)s] %(levelname)s: %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )

    # Handler log ra console
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setFormatter(formatter)
    logger.addHandler(console_handler)
    
    # Handler log ra file với RotatingFileHandler
    try:
        # Thư mục hiện tại
        current_dir = os.getcwd()
        
        # Đường dẫn file log
        log_file = os.path.join(current_dir, 'app.log')
        
        # Tạo file handler với kích thước tối đa 10MB, lưu trữ tối đa 5 file log
        file_handler = RotatingFileHandler(
            log_file,
            maxBytes=10 * 1024 * 1024,  # 10MB
            backupCount=5,
            encoding='utf-8'
        )
        file_handler.setFormatter(formatter)
        logger.addHandler(file_handler)
        
        # Log thông tin hệ thống để hỗ trợ debug
        system_info = {
            "platform": platform.platform(),
            "python_version": platform.python_version(),
            "cwd": current_dir,
            "log_file": log_file
        }
        
        # Thêm thông tin môi trường (Docker hay local)
        is_docker = os.path.exists('/.dockerenv') or os.path.exists('/app')
        system_info["environment"] = "Docker" if is_docker else "Local"
        
        # Thêm thông tin thư mục storage
        storage_dirs = [
            os.path.join(current_dir, 'storage'),
            os.path.join(current_dir, 'web', 'public', 'storage')
        ]
        storage_info = []
        for path in storage_dirs:
            if os.path.exists(path):
                try:
                    files = os.listdir(path)
                    storage_info.append(f"{path} (exists, files: {len(files)})")
                except Exception:
                    storage_info.append(f"{path} (exists, but cannot access)")
            else:
                storage_info.append(f"{path} (not found)")
        system_info["storage_dirs"] = storage_info
        
        # Log thông tin hệ thống khi khởi động ứng dụng
        logger.info(f"Logger initialized: {name}")
        logger.info(f"System info: {system_info}")
        
    except Exception as e:
        logger.error(f"Failed to set up file handler: {e}")
        # Continue with console logging only
    
    return logger 
