import os
from typing import List, Optional
from pydantic_settings import BaseSettings
from pydantic import validator
from dotenv import load_dotenv
import json

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
    
    # HuggingFace model settings
    HF_MODEL_ID: str = os.getenv("HF_MODEL_ID", "mistralai/Mistral-7B-Instruct-v0.2")
    
    # API URLs
    CHATBOT_API_URL: Optional[str] = os.getenv("CHATBOT_API_URL", None)
    
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
        extra = "ignore"
       # env_file_encoding = "utf-8"

settings = Settings() 