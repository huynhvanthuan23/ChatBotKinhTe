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

class GPUSettings(BaseSettings):
    N_BATCH: int = get_env_int("N_BATCH", 512)
    F16_KV: bool = get_env_bool("F16_KV", True)
    USE_MMAP: bool = get_env_bool("USE_MMAP", False)
    USE_MLOCK: bool = get_env_bool("USE_MLOCK", False)

class Settings(BaseSettings):
    # API
    API_HOST: str = get_env_str("API_HOST", "0.0.0.0")
    API_PORT: int = get_env_int("API_PORT", 8000)
    API_V1_STR: str = get_env_str("API_V1_STR", "/api/v1")
    PROJECT_NAME: str = get_env_str("PROJECT_NAME", "ChatBotKinhTe")
    DEBUG_MODE: bool = get_env_bool("DEBUG_MODE", True)
    
    # Chatbot settings
    DB_FAISS_PATH: str = get_env_str("DB_FAISS_PATH", "vector_db")
    MODEL_PATH: str = get_env_str("MODEL_PATH", "models/mistral-7b-instruct-v0.1.Q2_K.gguf")
    EMBEDDING_MODEL: str = get_env_str("EMBEDDING_MODEL", "sentence-transformers/all-MiniLM-L6-v2")
    TEMPERATURE: float = get_env_float("TEMPERATURE", 0.2)
    MAX_TOKENS: int = get_env_int("MAX_TOKENS", 512)
    N_CTX: int = get_env_int("N_CTX", 2048)
    
    # API Configuration
    USE_API: bool = get_env_bool("USE_API", False)
    API_TYPE: str = get_env_str("API_TYPE", "google")
    GOOGLE_API_KEY: str = get_env_str("GOOGLE_API_KEY", "")
    GOOGLE_MODEL: str = get_env_str("GOOGLE_MODEL", "gemini-pro")
    API_TIMEOUT: int = get_env_int("API_TIMEOUT", 30)
    
    # Tăng số GPU layers và thêm fallback logic nếu -1
    @property
    def N_GPU_LAYERS(self) -> int:
        n_layers = get_env_int("N_GPU_LAYERS", 32)
        # Nếu giá trị là -1, sử dụng tất cả các layer có sẵn
        if n_layers == -1:
            return 100  # Một số đủ lớn để đại diện cho "tất cả các layer"
        return n_layers
    
    # Thêm tham số mới để ép buộc sử dụng GPU
    USE_MMAP: bool = get_env_bool("USE_MMAP", False)
    USE_MLOCK: bool = get_env_bool("USE_MLOCK", False)
    N_BATCH: int = get_env_int("N_BATCH", 512)
    F16_KV: bool = get_env_bool("F16_KV", True)
    
    # HuggingFace model settings
    HF_MODEL_ID: str = get_env_str("HF_MODEL_ID", "mistralai/Mistral-7B-Instruct-v0.2")
    
    # API URLs
    CHATBOT_API_URL: Optional[str] = get_env_str("CHATBOT_API_URL", None)
    
    # Security
    SECRET_KEY: str = get_env_str("SECRET_KEY", "your-super-secret-key-change-this-in-production")
    ACCESS_TOKEN_EXPIRE_MINUTES: int = get_env_int("ACCESS_TOKEN_EXPIRE_MINUTES", 60)
    
    # CORS
    CORS_ORIGINS: List[str] = []
    CORS_METHODS: List[str] = []
    CORS_HEADERS: List[str] = []
    
    # Logging
    LOG_LEVEL: str = get_env_str("LOG_LEVEL", "INFO")
    
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