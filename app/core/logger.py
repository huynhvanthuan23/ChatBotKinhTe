import os
from typing import List, Optional
from importlib import import_module
import json
import logging as python_logging
import sys
from .config import settings
from dotenv import load_dotenv
from pydantic import validator  

# Sử dụng import module để tránh lỗi khi import
pydantic_settings = import_module('pydantic_settings')
BaseSettings = pydantic_settings.BaseSettings

load_dotenv()

class Settings(BaseSettings):
    # API
    API_HOST: str = os.getenv("API_HOST", "0.0.0.0")
    API_PORT: int = int(os.getenv("API_PORT", "8000"))
    API_V1_STR: str = os.getenv("API_V1_STR", "/api/v1")
    PROJECT_NAME: str = os.getenv("PROJECT_NAME", "ChatBotKinhTe")
    DEBUG_MODE: bool = os.getenv("DEBUG_MODE", "True").lower() == "true"
    
    # Chatbot settings
    DB_FAISS_PATH: str = os.getenv("DB_FAISS_PATH", "vector_db")
    MODEL_PATH: str = os.getenv("MODEL_PATH", "models/mistral-7b-instruct-v0.1.Q2_K.gguf")
    EMBEDDING_MODEL: str = os.getenv("EMBEDDING_MODEL", "sentence-transformers/all-MiniLM-L6-v2")
    TEMPERATURE: float = float(os.getenv("TEMPERATURE", "0.7"))
    MAX_TOKENS: int = int(os.getenv("MAX_TOKENS", "2000"))
    N_CTX: int = int(os.getenv("N_CTX", "2048"))
    N_GPU_LAYERS: int = int(os.getenv("N_GPU_LAYERS", "8"))
    
    # Security
    SECRET_KEY: str = os.getenv("SECRET_KEY", "your-super-secret-key-change-this-in-production")
    ACCESS_TOKEN_EXPIRE_MINUTES: int = int(os.getenv("ACCESS_TOKEN_EXPIRE_MINUTES", "60"))
    
    # CORS
    CORS_ORIGINS: List[str] = []
    CORS_METHODS: List[str] = []
    CORS_HEADERS: List[str] = []
    
    # Logging
    LOG_LEVEL: str = os.getenv("LOG_LEVEL", "INFO")
    
    @validator("CORS_ORIGINS", "CORS_METHODS", "CORS_HEADERS", pre=True)
    def parse_cors(cls, v):
        if isinstance(v, str):
            try:
                return json.loads(v)
            except:
                return [i.strip() for i in v.split(",")]
        return v
    
    class Config:
        env_file = ".env"
        case_sensitive = True

settings = Settings()

# Cấu hình logging
python_logging.basicConfig(
    level=getattr(python_logging, settings.LOG_LEVEL),
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    handlers=[
        python_logging.StreamHandler(sys.stdout),
        python_logging.FileHandler("app.log")
    ]
)

def get_logger(name: str):
    """
    Trả về logger cho module cụ thể.
    """
    return python_logging.getLogger(name) 
