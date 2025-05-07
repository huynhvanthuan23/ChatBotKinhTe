import os
import re
from typing import List, Optional, Any, Union
from pydantic_settings import BaseSettings
from pydantic import validator
from dotenv import load_dotenv
import json

load_dotenv()

def clean_env_value(value: str) -> str:
    """Làm sạch giá trị biến môi trường bằng cách loại bỏ comment"""
    if value is None:
        return value
    
    # Tách phần giá trị (trước dấu #)
    parts = value.split('#', 1)
    return parts[0].strip()

def get_env_bool(key: str, default: bool = False) -> bool:
    """Lấy giá trị boolean từ biến môi trường, xử lý để loại bỏ comment"""
    value = os.getenv(key)
    if value is None:
        return default
    
    clean_value = clean_env_value(value).lower()
    return clean_value == "true"

def get_env_int(key: str, default: int = 0) -> int:
    """Lấy giá trị integer từ biến môi trường, xử lý để loại bỏ comment"""
    value = os.getenv(key)
    if value is None:
        return default
    
    clean_value = clean_env_value(value)
    try:
        return int(clean_value)
    except ValueError:
        # Thử trích xuất chỉ các chữ số
        digits = ''.join(c for c in clean_value if c.isdigit())
        if digits:
            return int(digits)
        return default

def get_env_float(key: str, default: float = 0.0) -> float:
    """Lấy giá trị float từ biến môi trường, xử lý để loại bỏ comment"""
    value = os.getenv(key)
    if value is None:
        return default
    
    clean_value = clean_env_value(value)
    try:
        return float(clean_value)
    except ValueError:
        return default

def get_env_str(key: str, default: str = "") -> str:
    """Lấy giá trị string từ biến môi trường, xử lý để loại bỏ comment"""
    value = os.getenv(key)
    if value is None:
        return default
    
    return clean_env_value(value)

class Settings(BaseSettings):
    # API
    API_HOST: str = get_env_str("API_HOST", "0.0.0.0")
    API_PORT: int = get_env_int("API_PORT", 8000)
    API_V1_STR: str = get_env_str("API_V1_STR", "/api/v1")
    PROJECT_NAME: str = get_env_str("PROJECT_NAME", "ChatBotKinhTe")
    DEBUG_MODE: bool = get_env_bool("DEBUG_MODE", True)
    
    # API configuration
    API_TYPE: str = get_env_str("API_TYPE", "google")
    USE_API: bool = get_env_bool("USE_API", True)
    
    # Google Generative AI
    GOOGLE_API_KEY: str = get_env_str("GOOGLE_API_KEY", "")
    GOOGLE_MODEL: str = get_env_str("GOOGLE_MODEL", "gemini-1.5-pro")
    
    # OpenAI
    OPENAI_API_KEY: str = get_env_str("OPENAI_API_KEY", "")
    OPENAI_MODEL: str = get_env_str("OPENAI_MODEL", "gpt-4o-mini")
    
    # API Timeout
    API_TIMEOUT: int = get_env_int("API_TIMEOUT", 30)
    
    # Model
    EMBEDDING_MODEL: str = get_env_str("EMBEDDING_MODEL", "sentence-transformers/all-MiniLM-L6-v2")
    TEMPERATURE: float = get_env_float("TEMPERATURE", 0.2)
    
    # Database
    DB_FAISS_PATH: str = get_env_str("DB_FAISS_PATH", "vector_db")
    
    # Storage
    STORAGE_PATH: str = get_env_str("STORAGE_PATH", "D:/ThucTap/ChatBotKinhTe/storage")
    
    # API Rate limiting
    RATE_LIMIT: int = get_env_int("RATE_LIMIT", 60)
    RATE_LIMIT_PERIOD: int = get_env_int("RATE_LIMIT_PERIOD", 60)
    
    # Logging
    LOG_LEVEL: str = get_env_str("LOG_LEVEL", "INFO")
    
    # CORS settings
    CORS_ORIGINS: List[str] = ["*"]
    CORS_METHODS: List[str] = ["*"]
    CORS_HEADERS: List[str] = ["*"]
    ALLOW_CREDENTIALS: bool = True
    
    # Cấu hình cho tạo vector tài liệu
    UPLOAD_VECTOR_DIR: str = get_env_str("UPLOAD_VECTOR_DIR", "vector_db/uploads")
    DEFAULT_CHUNK_SIZE: int = get_env_int("DEFAULT_CHUNK_SIZE", 500)
    DEFAULT_CHUNK_OVERLAP: int = get_env_int("DEFAULT_CHUNK_OVERLAP", 100)
    DEFAULT_TOP_K: int = get_env_int("DEFAULT_TOP_K", 3)
    
    @validator("CORS_ORIGINS", "CORS_METHODS", "CORS_HEADERS", pre=True)
    def parse_cors(cls, v):
        if isinstance(v, str):
            try:
                # Làm sạch giá trị trước khi parse JSON
                clean_v = clean_env_value(v)
                return json.loads(clean_v)
            except:
                return [i.strip() for i in clean_v.split(",")]
        return v
    
    class Config:
        env_file = ".env"
        case_sensitive = True
        extra = "allow"

settings = Settings()