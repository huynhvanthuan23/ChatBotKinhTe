import disable_pickle
disable_pickle.disable()

# Set ALLOW_PICKLE for vector database loading
import os
os.environ["ALLOW_PICKLE"] = "true"

# Sửa lỗi OpenAI proxies
try:
    import fix_openai_proxy
    fix_openai_proxy.patch_openai()
    print("Đã áp dụng patch để sửa lỗi OpenAI proxies")
except ImportError:
    print("Không tìm thấy module fix_openai_proxy, tiếp tục không có patch")
except Exception as e:
    print(f"Lỗi khi áp dụng patch OpenAI: {str(e)}")

import uvicorn
from fastapi import FastAPI, Request, HTTPException
from fastapi.responses import HTMLResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
import os
from fastapi.responses import FileResponse
from app.core.config import settings
from fastapi.middleware.cors import CORSMiddleware
import google.generativeai as genai
import openai
from openai import OpenAI
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
import torch
import gc
import time
import logging
import traceback
import json
from pydantic import BaseModel
from typing import Optional, Dict, Any
from fastapi.middleware import Middleware
from starlette.middleware.base import BaseHTTPMiddleware
from dotenv import load_dotenv
import pickle
import importlib
import re
from langchain_community.document_loaders import TextLoader, PyPDFLoader, Docx2txtLoader
from langchain.text_splitter import RecursiveCharacterTextSplitter
import shutil

# Import các dịch vụ từ app
from app.services.chatbot import ChatbotService
from app.services.api_service import APIService

# Constants and Environment Variables
VECTOR_DB_PATH = os.getenv("CORE_VECTOR_DIR", "vector_db/core_data")
EMBEDDING_MODEL = os.getenv("EMBEDDING_MODEL", "sentence-transformers/all-MiniLM-L6-v2")
GEMINI_MODEL = os.getenv("GOOGLE_MODEL", "gemini-1.5-pro")
OPENAI_MODEL = os.getenv("OPENAI_MODEL", "gpt-4o-mini")
API_TYPE = os.getenv("API_TYPE", "google").lower()  # google hoặc openai

# Cấu hình logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler("app.log"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)
logging = logger  # Make logger available as logging for consistency

# Thiết lập các biến môi trường
os.environ["USE_API"] = "true"  # Luôn sử dụng API
os.environ["API_TYPE"] = API_TYPE

# Khởi tạo các dịch vụ
chatbot_service = ChatbotService()
api_service = None

try:
    api_service = APIService()
    logger.info(f"API Service initialized with {API_TYPE} API")
except Exception as e:
    logger.error(f"Failed to initialize API Service: {str(e)}")

# Middleware để xử lý encoding
class EncodingMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        response = await call_next(request)
        if response.headers.get("content-type", "").startswith("application/json"):
            response.headers["content-type"] = "application/json; charset=utf-8"
        return response

# Tạo ứng dụng FastAPI với middleware
app = FastAPI(
    title=settings.PROJECT_NAME,
    description=f"API cho ChatBotKinhTe sử dụng {API_TYPE.capitalize()} API",
    version="1.0.0",
    docs_url=f"{settings.API_V1_STR}/docs",
    redoc_url=f"{settings.API_V1_STR}/redoc",
    middleware=[Middleware(EncodingMiddleware)]
)

# Cấu hình CORS
origins = [
    "http://localhost:8000",  # Laravel default port
    "http://127.0.0.1:8000",
    "http://localhost:3000",  # React default port
    "http://127.0.0.1:3000",
    "http://localhost:8080",  # FastAPI port
    "http://127.0.0.1:8080",
]

# Thêm origins từ env nếu có
if os.environ.get("CORS_ORIGINS"):
    try:
        custom_origins = json.loads(os.environ.get("CORS_ORIGINS", '["*"]'))
        if isinstance(custom_origins, list):
            origins.extend(custom_origins)
    except Exception as e:
        logger.error(f"Error parsing CORS_ORIGINS: {e}")

app.add_middleware(
    CORSMiddleware,
    allow_origins=origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["X-Process-Time"],
)

# Thêm logging cho CORS
logger.info(f"CORS enabled for origins: {origins}")

# Classes cho request và response
class ChatRequest(BaseModel):
    query: str
    user_id: Optional[int] = None

class ChatResponse(BaseModel):
    success: bool
    answer: str
    query: str
    error: Optional[str] = None

class ReloadResponse(BaseModel):
    success: bool
    message: str
    error: Optional[str] = None

class ConfigRequest(BaseModel):
    api_type: str
    google_api_key: Optional[str] = None
    google_model: Optional[str] = None
    openai_api_key: Optional[str] = None
    openai_model: Optional[str] = None

class DocumentProcessRequest(BaseModel):
    file_path: str
    document_id: int
    title: Optional[str] = None
    description: Optional[str] = None
    file_type: Optional[str] = None
    absolute_path: Optional[str] = None
    chunk_size: Optional[int] = 1000
    chunk_overlap: Optional[int] = 200
    user_id: Optional[int] = None

class DocumentProcessResponse(BaseModel):
    success: bool
    message: str
    document_id: int
    error: Optional[str] = None

# Biến global cho các components
gemini_model = None
openai_client = None
vector_retriever = None

# Vô hiệu hóa pickle.load có điều kiện
_original_load = pickle.load

def _safe_load(*args, **kwargs):
    # Kiểm tra biến môi trường ALLOW_PICKLE
    if os.getenv("ALLOW_PICKLE", "false").lower() == "true":
        logging.info("ALLOW_PICKLE=true, enabling pickle loading for vector database")
        return _original_load(*args, **kwargs)
    else:
        # Nếu không cho phép, log cảnh báo và raise lỗi
        logging.error("PICKLE LOAD BLOCKED FOR SECURITY (set ALLOW_PICKLE=true to enable)")
        raise ValueError("Pickle loading is disabled for security. Set ALLOW_PICKLE=true to enable.")

# Thay thế pickle.load bằng phiên bản có điều kiện
pickle.load = _safe_load

# Khởi tạo vector database - sử dụng dịch vụ
def initialize_vector_db():
    """Initialize vector database from FAISS using ChatbotService."""
    global vector_retriever, chatbot_service
    try:
        logging.info("Initializing vector database using ChatbotService")
        vector_retriever = chatbot_service.initialize_vector_db()
        
        if vector_retriever:
            logging.info("Vector database initialized successfully")
            return vector_retriever
        else:
            logging.warning("Vector database initialization failed, creating dummy retriever")
            # Tạo dummy retriever
            class DummyRetriever:
                def get_relevant_documents(self, query):
                    logging.warning(f"Using dummy retriever for query: {query}")
                    from langchain.schema import Document
                    return [Document(page_content="Không có thông tin.", metadata={})]
            
            return DummyRetriever()
    except Exception as e:
        logging.error(f"Error initializing vector database: {str(e)}")
        logging.error(traceback.format_exc())
        
        # Tạo dummy retriever
        class DummyRetriever:
            def get_relevant_documents(self, query):
                logging.warning(f"Using dummy retriever for query: {query}")
                from langchain.schema import Document
                return [Document(page_content="Không có thông tin.", metadata={})]
        
        return DummyRetriever()

# Khởi tạo Gemini API - sử dụng dịch vụ
def initialize_gemini():
    """Initialize Gemini API using APIService."""
    global gemini_model, api_service
    try:
        if not api_service:
            logging.error("API service is not initialized")
            return None
        
        logging.info("Initializing Gemini API using APIService")
        gemini_model = api_service.initialize_gemini()
        return gemini_model
    except Exception as e:
        logging.error(f"Error initializing Gemini API: {str(e)}")
        logging.error(traceback.format_exc())
        return None

# Khởi tạo OpenAI API - sử dụng dịch vụ
def initialize_openai():
    """Initialize OpenAI API using APIService."""
    global openai_client, api_service
    try:
        if not api_service:
            logging.error("API service is not initialized")
            return None
            
        logging.info("Initializing OpenAI API using APIService")
        openai_client = api_service.initialize_openai()
        return openai_client
    except Exception as e:
        logging.error(f"Error initializing OpenAI API: {str(e)}")
        logging.error(traceback.format_exc())
        return None

# Xử lý chat
async def process_chat(query: str, user_id: Optional[int] = None) -> Dict[str, Any]:
    """
    Xử lý chat bằng cách sử dụng các dịch vụ
    """
    try:
        global api_service, vector_retriever
        
        # Kiểm tra api_service
        if not api_service:
            logger.error("API service is not initialized")
            return {
                "success": False,
                "answer": "API service chưa được khởi tạo. Vui lòng thử lại sau.",
                "query": query,
                "error": "API service not initialized"
            }
        
        # Kiểm tra vector_retriever
        if not vector_retriever:
            logger.warning("Vector retriever is not initialized, initializing now")
            vector_retriever = initialize_vector_db()
                
            if not vector_retriever:
                logger.error("Vector retriever initialization failed")
                return {
                    "success": False,
                    "answer": "Không thể khởi tạo vector retriever. Vui lòng thử lại sau.",
                    "query": query,
                    "error": "Vector retriever initialization failed"
                }
        
        # Gọi process_chat từ api_service
        logger.info(f"Processing chat query: {query}")
        result = await api_service.process_chat(query, user_id, vector_retriever)
        
        return {
            "success": True,
            "answer": result.get("answer", "Không có phản hồi"),
            "query": query,
            "model_info": {
                "model": result.get("model", "unknown"),
                "context_used": result.get("documents_found", 0) > 0,
                "documents_found": result.get("documents_found", 0),
                "processing_time": result.get("processing_time", "N/A")
            }
        }
        
    except Exception as e:
        logger.error(f"Error in process_chat: {str(e)}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "answer": f"Xin lỗi, có lỗi xảy ra khi xử lý: {str(e)}",
            "query": query,
            "error": str(e)
        }

# Endpoints
@app.get("/api/v1/chat/test-connection")
async def test_connection():
    """Test connection endpoint for Laravel"""
    return {
        "status": "success",
        "message": "Connection successful",
        "server_time": time.strftime("%Y-%m-%d %H:%M:%S")
    }

@app.post("/api/v1/chat/chat-direct")
async def chat_direct(request: Request):
    """
    Chat trực tiếp với API, có sử dụng vector database
    """
    try:
        # Read raw body
        body = await request.json()
        query = body.get("message", "")
        user_id = body.get("user_id")
        
        if not query:
            return {
                "success": False,
                "response": "Không có tin nhắn được cung cấp",
                "error": "No message provided"
            }
        
        logger.info(f"Chat direct request received with message: {query}")
        
        # Xử lý chat
        result = await process_chat(query, user_id)
        
        return {
            "success": result.get("success", False),
            "response": result.get("answer", "Không có phản hồi"),
            "query": query,
            "model_info": result.get("model_info", {})
        }
        
    except Exception as e:
        logger.error(f"Error in chat_direct endpoint: {str(e)}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "response": f"Xin lỗi, có lỗi xảy ra: {str(e)}",
            "error": str(e)
        }

@app.get("/api/v1/chat/service-info")
async def service_info():
    """Get service information"""
    return {
        "service_type": "API",
        "api_type": API_TYPE.capitalize(),
        "model": GEMINI_MODEL if API_TYPE == "google" else OPENAI_MODEL,
        "status": "active" if (API_TYPE == "google" and gemini_model) or (API_TYPE == "openai" and openai_client) else "unavailable"
    }

@app.get("/api/v1/chat/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "components": {
            "api": "active" if (API_TYPE == "google" and gemini_model) or (API_TYPE == "openai" and openai_client) else "inactive",
            "vector_db": "active" if vector_retriever else "inactive",
            "api_type": API_TYPE
        }
    }

@app.get("/health")
async def root_health_check():
    """Root health check endpoint for Laravel admin integration"""
    return {
        "status": "healthy",
        "api_version": "1.0.0",
        "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
        "resources": {
            "api": "online",
            "model": "available",
            "vector_db": "available"
        }
    }

@app.post("/chat-direct")
async def chat_direct_redirect(request: Request):
    """Redirect endpoint for Laravel integration"""
    logger.info("Request received at /chat-direct - redirecting to /api/v1/chat/chat-direct")
    return await chat_direct(request)

@app.post("/api/chat/send")
async def chat_send_redirect(request: Request):
    """Redirect endpoint for Laravel integration"""
    logger.info("Request received at /api/chat/send - redirecting to /api/v1/chat/chat-direct")
    return await chat_direct(request)

@app.post("/")
async def root_redirect(request: Request):
    """Root endpoint to handle requests to /"""
    logger.info("Request received at root (/) - redirecting to /api/v1/chat/chat-direct")
    return await chat_direct(request)

@app.post("/api/v1/chat/simple-chat")
async def simple_chat(request: Request):
    """
    Chat đơn giản chỉ dựa trên vector database, không sử dụng API
    """
    try:
        # Read raw body
        raw_body = await request.body()
        logger.info(f"Raw request body length: {len(raw_body)}")
        
        # Try to decode with different encodings
        try:
            body_str = raw_body.decode('utf-8')
            logger.info("Used utf-8 encoding for request body")
        except UnicodeDecodeError:
            try:
                body_str = raw_body.decode('latin-1')
                logger.info("Used latin-1 encoding for request body")
            except UnicodeDecodeError:
                body_str = raw_body.decode('cp1252', errors='ignore')
                logger.info("Used cp1252 encoding with error ignore for request body")
        
        # Parse JSON from string
        try:
            body = json.loads(body_str)
            logger.info(f"Parsed JSON: {body}")
        except json.JSONDecodeError as e:
            # Log a part of body for debugging
            preview = body_str[:100] if len(body_str) > 100 else body_str
            logger.error(f"JSON parsing error: {str(e)}, preview: {preview}")
            return {
                "success": False,
                "response": "Invalid JSON format",
                "error": f"JSON decode error: {str(e)}"
            }
        
        query = body.get("message", "")
        
        # Chấp nhận nhiều tham số document ID khác nhau từ client
        support_doc_ids = body.get("support_doc_ids", [])
        doc_ids = body.get("doc_ids", [])
        document_ids = body.get("document_ids", [])
        context_document_ids = body.get("context_document_ids", [])
        
        # Kiểm tra xem doc_ids có phải là chuỗi string không và chuyển đổi nếu cần
        if isinstance(doc_ids, str) and doc_ids:
            try:
                doc_ids = [int(id.strip()) for id in doc_ids.split(',')]
                logger.info(f"Đã chuyển đổi doc_ids từ string '{body.get('doc_ids')}' thành list: {doc_ids}")
            except Exception as e:
                logger.error(f"Lỗi khi chuyển đổi doc_ids từ string: {str(e)}")
        
        # Kiểm tra các trường ID khác
        for field_name, field_value in [
            ("support_doc_ids", support_doc_ids),
            ("document_ids", document_ids),
            ("context_document_ids", context_document_ids)
        ]:
            if isinstance(field_value, str) and field_value:
                try:
                    converted_ids = [int(id.strip()) for id in field_value.split(',')]
                    if field_name == "support_doc_ids":
                        support_doc_ids = converted_ids
                    elif field_name == "document_ids":
                        document_ids = converted_ids
                    elif field_name == "context_document_ids":
                        context_document_ids = converted_ids
                    logger.info(f"Đã chuyển đổi {field_name} từ string '{field_value}' thành list")
                except Exception as e:
                    logger.error(f"Lỗi khi chuyển đổi {field_name} từ string: {str(e)}")
        
        # Kết hợp tất cả document IDs từ các tham số khác nhau
        all_doc_ids = []
        if support_doc_ids:
            all_doc_ids.extend(support_doc_ids)
        if doc_ids:
            all_doc_ids.extend(doc_ids)
        if document_ids:
            all_doc_ids.extend(document_ids)
        if context_document_ids:
            all_doc_ids.extend(context_document_ids)
            
        # Loại bỏ trùng lặp
        all_doc_ids = list(set(all_doc_ids))
        
        if not query:
            return {
                "success": False,
                "response": "No message provided",
                "query": ""
            }
            
        logger.info(f"Simple chat request received with message: {query}")
        if all_doc_ids:
            logger.info(f"Request includes document IDs: {all_doc_ids}")
        
        # Sử dụng ChatbotService để xử lý simple chat
        result = await chatbot_service.get_simple_retrieval(query, all_doc_ids)
        
        return result
            
    except Exception as e:
        logger.error(f"Error in simple chat request parsing: {str(e)}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "response": "Xin lỗi, có lỗi xảy ra khi xử lý yêu cầu. Vui lòng thử lại sau.",
            "error": str(e)
        }

@app.post("/api/v1/admin/reload-config")
async def reload_config(config: Optional[ConfigRequest] = None):
    """Reload configuration settings from environment variables or direct config."""
    try:
        logger.info("Reloading configuration settings...")
        
        # Reload settings module
        from app.core import config as settings
        importlib.reload(settings)
        
        # Update global variables
        global API_TYPE, GEMINI_MODEL, OPENAI_MODEL, api_service
        
        # Lưu trữ API keys từ cấu hình cũ hoặc từ request
        google_api_key = None
        openai_api_key = None
        
        # If config is provided, use it directly instead of environment variables
        if config:
            logger.info("Using provided configuration directly.")
            API_TYPE = config.api_type.lower()
            
            # Temporarily override the environment variables
            if API_TYPE == "google" and config.google_model:
                GEMINI_MODEL = config.google_model
                # Set API key if provided
                if config.google_api_key:
                    google_api_key = config.google_api_key
                    os.environ["GOOGLE_API_KEY"] = config.google_api_key
            elif API_TYPE == "openai" and config.openai_model:
                OPENAI_MODEL = config.openai_model
                # Set API key if provided
                if config.openai_api_key:
                    openai_api_key = config.openai_api_key
                    os.environ["OPENAI_API_KEY"] = config.openai_api_key
        else:
            # Use environment variables
            logger.info("Using environment variables for configuration.")
            API_TYPE = os.getenv("API_TYPE", "google").lower()
            GEMINI_MODEL = os.getenv("GOOGLE_MODEL", "gemini-1.5-pro")
            OPENAI_MODEL = os.getenv("OPENAI_MODEL", "gpt-4o-mini")
            
            # Lấy API keys từ biến môi trường
            google_api_key = os.getenv("GOOGLE_API_KEY", "")
            openai_api_key = os.getenv("OPENAI_API_KEY", "")
        
        # Set global API_TYPE environment variable
        os.environ["API_TYPE"] = API_TYPE
        
        # Tạo lại đối tượng api_service với cấu hình mới và API key phù hợp
        try:
            from app.services.api_service import APIService
            
            # Chọn API key và model phù hợp với loại API
            current_api_key = google_api_key if API_TYPE == "google" else openai_api_key
            current_model = GEMINI_MODEL if API_TYPE == "google" else OPENAI_MODEL
            
            # Tạo đối tượng API Service mới với tham số trực tiếp
            api_service = APIService(
                api_type=API_TYPE,
                api_key=current_api_key,
                model=current_model
            )
            
            logger.info(f"Recreated API Service with new {API_TYPE} configuration")
        except Exception as e:
            logger.error(f"Failed to recreate API Service: {str(e)}")
            logger.error(traceback.format_exc())
            return ReloadResponse(
                success=False,
                message=f"Không thể tạo lại API Service với cấu hình {API_TYPE}",
                error=str(e)
            )
        
        # Reinitialize API clients
        global gemini_model, openai_client
        if API_TYPE == "google":
            logging.info("Reinitializing Gemini model...")
            try:
                gemini_model = initialize_gemini()
                if gemini_model:
                    logging.info("Gemini model reinitialized successfully")
                else:
                    logging.warning("Gemini model reinitialization returned None")
                    return ReloadResponse(
                        success=False,
                        message="Không thể khởi tạo lại model Gemini",
                        error="Gemini model reinitialization returned None"
                    )
            except Exception as e:
                logging.error(f"Error during Gemini reinitialization: {e}")
                logging.error(traceback.format_exc())
                return ReloadResponse(
                    success=False,
                    message="Lỗi khi khởi tạo lại model Gemini",
                    error=str(e)
                )
        elif API_TYPE == "openai":
            logging.info("Reinitializing OpenAI model...")
            try:
                openai_client = initialize_openai()
                if openai_client:
                    logging.info("OpenAI model reinitialized successfully")
                else:
                    logging.warning("OpenAI model reinitialization returned None")
                    return ReloadResponse(
                        success=False,
                        message="Không thể khởi tạo lại model OpenAI",
                        error="OpenAI model reinitialization returned None"
                    )
            except Exception as e:
                logging.error(f"Error during OpenAI reinitialization: {e}")
                logging.error(traceback.format_exc())
                return ReloadResponse(
                    success=False,
                    message="Lỗi khi khởi tạo lại model OpenAI",
                    error=str(e)
                )
        else:
            logging.error(f"Unknown API_TYPE: {API_TYPE}, supported types are 'google' and 'openai'")
            return ReloadResponse(
                success=False,
                message=f"Loại API không được hỗ trợ: {API_TYPE}",
                error=f"Unknown API_TYPE: {API_TYPE}, supported types are 'google' and 'openai'"
            )
        
        # Update CORS settings
        origins = [
            "http://localhost:8000",
            "http://127.0.0.1:8000",
            "http://localhost:3000",
            "http://127.0.0.1:3000", 
            "http://localhost:8080",
            "http://127.0.0.1:8080",
        ]
        
        if os.environ.get("CORS_ORIGINS"):
            try:
                custom_origins = json.loads(os.environ.get("CORS_ORIGINS", '["*"]'))
                if isinstance(custom_origins, list):
                    origins.extend(custom_origins)
                    logger.info(f"Updated CORS origins: {origins}")
            except Exception as e:
                logger.error(f"Error parsing CORS_ORIGINS: {e}")
        
        # Return success response
        return ReloadResponse(
            success=True,
            message="Cấu hình đã được tải lại thành công",
            error=None
        )
    except Exception as e:
        logger.error(f"Error reloading configuration: {e}")
        logger.error(traceback.format_exc())
        return ReloadResponse(
            success=False,
            message="Lỗi khi tải lại cấu hình",
            error=str(e)
        )

@app.post("/api/v1/chat/document-chat")
async def document_chat(request: Request):
    """
    Chat với tài liệu cụ thể, hỗ trợ trích dẫn
    """
    try:
        start_time = time.time()
        # Đọc dữ liệu từ request
        body = await request.json()
        query = body.get("message", "")
        user_id = body.get("user_id")
        
        # Log chi tiết request đầu vào
        logger.info(f"[CITATION-API] Nhận request document-chat từ user_id: {user_id}, query: '{query}'")
        
        # Đọc document_ids từ các trường khác nhau
        doc_ids = body.get("document_ids", [])
        if not doc_ids:
            doc_ids = body.get("doc_ids", [])
        if not doc_ids:
            doc_ids = body.get("context_document_ids", [])
        
        # Chuyển đổi nếu là chuỗi
        if isinstance(doc_ids, str):
            doc_ids = [int(id.strip()) for id in doc_ids.split(',') if id.strip().isdigit()]
            
        logger.info(f"[CITATION-API] Danh sách tài liệu cần truy vấn: {doc_ids}")
            
        if not query:
            logger.warning("[CITATION-API] Không có tin nhắn được cung cấp")
            return {
                "success": False,
                "response": "Không có tin nhắn được cung cấp",
                "error": "No message provided"
            }
            
        if not doc_ids:
            logger.warning("[CITATION-API] Không có tài liệu nào được chọn")
            return {
                "success": False,
                "response": "Không có tài liệu nào được chọn",
                "error": "No documents selected"
            }
            
        logger.info(f"[CITATION-API] Bắt đầu truy vấn tài liệu với câu hỏi: {query}, document_ids: {doc_ids}")
            
        # Truy vấn tài liệu với hỗ trợ trích dẫn
        search_results = await chatbot_service.query_document_with_citation(query, doc_ids)
        
        # Kiểm tra kết quả trả về
        if not search_results:
            logger.error("[CITATION-API] Hàm query_document_with_citation trả về giá trị None")
            return {
                "success": False,
                "response": "Có lỗi khi xử lý tài liệu. Vui lòng thử lại sau.",
                "query": query,
                "citations": []
            }
        
        # Log kết quả tìm kiếm
        logger.info(f"[CITATION-API] Nhận được {len(search_results.get('results', []))} kết quả và {len(search_results.get('citations', []))} trích dẫn")
        
        if not search_results.get("results", []):
            logger.warning("[CITATION-API] Không tìm thấy kết quả nào phù hợp")
            return {
                "success": True,
                "response": "Tôi không tìm thấy thông tin liên quan đến câu hỏi của bạn trong các tài liệu được chọn.",
                "query": query,
                "citations": []
            }
        
        # Tổng hợp kết quả tìm kiếm để gửi đến LLM
        context_text = "\n---\n".join(search_results.get("results", []))
        
        # Tạo prompt với ngữ cảnh
        prompt = f"""Dưới đây là một số đoạn văn bản liên quan đến câu hỏi của người dùng. 
Hãy sử dụng thông tin này để trả lời câu hỏi.
Nếu thông tin không đủ, hãy nói rằng bạn không tìm thấy đủ thông tin trong tài liệu.
Không được tạo ra thông tin không có trong ngữ cảnh.

NGỮ CẢNH:
{context_text}

CÂU HỎI: {query}

TRẢ LỜI:"""

        logger.info(f"[CITATION-API] Gửi prompt đến LLM API, độ dài prompt: {len(prompt)} ký tự")

        # Gọi API LLM
        if api_service:
            if settings.API_TYPE.lower() == "google":
                logger.info("[CITATION-API] Sử dụng Google Gemini API")
                llm_response = await api_service.generate_with_gemini(prompt)
            else:
                logger.info("[CITATION-API] Sử dụng OpenAI API")
                llm_response = await api_service.generate_with_openai(prompt)
        else:
                llm_response = "Không thể kết nối với API LLM."
                logger.error("[CITATION-API] Không có API service được khởi tạo")
        
        # Log thời gian xử lý
        processing_time = time.time() - start_time
        logger.info(f"[CITATION-API] Hoàn tất xử lý trong {processing_time:.2f} giây")
        
        # Log kết quả trả về
        citations = search_results.get("citations", [])
        logger.info(f"[CITATION-API] Trả về câu trả lời với {len(citations)} trích dẫn")
        if citations:
            logger.info(f"[CITATION-API] Chi tiết trích dẫn đầu tiên: doc_id={citations[0].get('doc_id')}, page={citations[0].get('page')}, url={citations[0].get('url')}")
        
        # Trả về kết quả kèm theo thông tin trích dẫn
        return {
            "success": True,
            "response": llm_response,
            "query": query,
            "citations": citations
        }
            
    except Exception as e:
        logger.error(f"[CITATION-API] Lỗi trong document_chat: {str(e)}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "response": f"Xin lỗi, có lỗi xảy ra: {str(e)}",
            "error": str(e)
        }

@app.post("/api/v1/documents/process")
async def process_document(request: DocumentProcessRequest):
    """Xử lý tài liệu được upload và tạo vector embeddings"""
    logger.info(f"Đang xử lý tài liệu ID: {request.document_id}, Đường dẫn: {request.file_path}")
    
    # Kiểm tra tham số
    if not request.file_path:
        return DocumentProcessResponse(
            success=False,
            message="Thiếu đường dẫn tập tin",
            document_id=request.document_id,
            error="Missing file path"
        )
    
    # Đảm bảo user_id tồn tại, nếu không thì thiết lập giá trị mặc định
    if request.user_id is None:
        logger.info(f"Không có user_id được cung cấp cho tài liệu {request.document_id}, sử dụng mặc định 0")
        request.user_id = 0  # Mặc định là 0 nếu không có
    
    # Gọi hàm tạo vector
    result = await chatbot_service.process_document(request)
    
    # Trả về kết quả
    return DocumentProcessResponse(
        success=result["success"],
        message=result["message"],
        document_id=result["document_id"],
        error=result.get("error", None)
    )

@app.post("/api/v1/documents/integrate")
async def integrate_document(document_id: int):
    """
    Tích hợp vector của tài liệu vào vector database chính
    """
    try:
        logger.info(f"Integrating document ID: {document_id}")
        
        # Tích hợp vectors sử dụng dịch vụ
        result = await chatbot_service.integrate_document(document_id)
        
        if result["success"]:
            # Khởi động lại vector retriever
            global vector_retriever
            vector_retriever = initialize_vector_db()
            
            return {
                "success": True,
                "message": f"Đã tích hợp vector của tài liệu {document_id} thành công",
                "document_id": document_id
            }
        else:
            return {
                "success": False,
                "message": f"Không thể tích hợp vector của tài liệu {document_id}",
                "document_id": document_id,
                "error": result.get("error", "Integration failed")
            }
    except Exception as e:
        logger.error(f"Error in integrate_document endpoint: {e}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "message": "Lỗi khi tích hợp vector",
            "document_id": document_id,
            "error": str(e)
        }

@app.delete("/api/v1/documents/delete")
async def delete_document_vector(request: Request):
    """
    Xóa vector của tài liệu khi tài liệu được xóa từ Laravel UI
    """
    try:
        # Đọc dữ liệu từ request
        data = await request.json()
        document_id = data.get("document_id")
        user_id = data.get("user_id")
        
        if not document_id:
            logger.error("Missing document_id in delete request")
            return {
                "success": False,
                "message": "Missing document_id parameter",
                "error": "Invalid request"
            }
            
        logger.info(f"Đang xóa vector cho tài liệu ID: {document_id}, user_id: {user_id}")
        
        # Gọi service để xóa vector
        result = await chatbot_service.delete_document_vector(doc_id=document_id, user_id=user_id)
        
        # Khởi động lại vector retriever để cập nhật thay đổi
        if result["success"]:
            global vector_retriever
            vector_retriever = initialize_vector_db()
        
        return result
            
    except Exception as e:
        logger.error(f"Lỗi khi xử lý yêu cầu xóa vector: {str(e)}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "message": "Lỗi khi xóa vector tài liệu",
            "error": str(e)
        }

# Khởi tạo components khi server start
@app.on_event("startup")
async def startup_event():
    """Run on application startup."""
    global vector_retriever, gemini_model, openai_client, api_service, chatbot_service
        
    try:
        # Khởi tạo api_service nếu chưa được khởi tạo
        if not api_service:
            try:
                api_service = APIService()
                logger.info(f"API Service initialized with {API_TYPE} API")
            except Exception as e:
                logger.error(f"Failed to initialize API Service: {str(e)}")
                logger.error(traceback.format_exc())
        
        # Khởi tạo chatbot_service nếu chưa được khởi tạo
        if not chatbot_service:
            chatbot_service = ChatbotService()
            logger.info("ChatbotService initialized")
        
        # Khởi tạo vector database
        logger.info("Initializing vector database...")
        vector_retriever = initialize_vector_db()
        
        # Khởi tạo API dựa theo cấu hình
        if API_TYPE == "google":
            logger.info("Initializing Gemini API...")
            gemini_model = initialize_gemini()
        elif API_TYPE == "openai":
            logger.info("Initializing OpenAI API...")
            openai_client = initialize_openai()
        else:
            logger.error(f"Unsupported API type: {API_TYPE}")
        
        logger.info("Application startup completed successfully")
        
    except Exception as e:
        logger.error(f"Error during startup: {str(e)}")
        logger.error(traceback.format_exc())

# Chạy ứng dụng
if __name__ == "__main__":
    port = int(os.getenv("API_PORT", 8080))
    host = os.getenv("API_HOST", "0.0.0.0")
    
    print(f"\n==== STARTING CHATBOT API SERVER ====")
    print(f"Host: {host}")
    print(f"Port: {port}")
    print(f"API documentation: http://localhost:{port}/docs")
    print(f"Health check: http://localhost:{port}/api/v1/chat/health")
    print(f"Chat endpoint: http://localhost:{port}/api/v1/chat/chat-direct")
    print(f"==== SERVER READY ====\n")
    
    uvicorn.run(
        app,
        host=host,
        port=port,
        log_level="info"
    ) 