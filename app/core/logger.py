import os
import logging as python_logging
import sys
from .config import settings  # Import settings từ config.py
import codecs

# Thiết lập UTF-8 encoding cho các stream output
sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer)
sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer)

# Tạo file handler với encoding UTF-8
file_handler = python_logging.FileHandler("app.log", encoding='utf-8')
file_handler.setFormatter(python_logging.Formatter("%(asctime)s - %(name)s - %(levelname)s - %(message)s"))

# Cấu hình logging
python_logging.basicConfig(
    level=getattr(python_logging, settings.LOG_LEVEL),
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    handlers=[
        python_logging.StreamHandler(sys.stdout),
        file_handler
    ]
)

def get_logger(name: str):
    """
    Trả về logger cho module cụ thể.
    """
    return python_logging.getLogger(name) 
