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

# Cấu hình logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Thiết lập các biến môi trường
os.environ["USE_API"] = "true"  # Luôn sử dụng API
os.environ["API_TYPE"] = "google"  # Luôn sử dụng Google API

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
    description="API cho ChatBotKinhTe sử dụng Google Gemini API",
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

# Biến global cho các components
gemini_model = None
vector_retriever = None

# Khởi tạo vector database
def initialize_vector_db():
    try:
        # Khởi tạo embedding model
        device = "cuda" if torch.cuda.is_available() else "cpu"
        logger.info(f"Initializing embedding model on {device}")
        
        embeddings = HuggingFaceEmbeddings(
            model_name=os.getenv("EMBEDDING_MODEL", "sentence-transformers/all-MiniLM-L6-v2"),
            model_kwargs={"device": device}
        )
        
        # Đường dẫn vector database
        db_path = os.getenv("DB_FAISS_PATH", "vector_db")
        
        # Kiểm tra đường dẫn
        if not os.path.exists(db_path):
            logger.error(f"Vector store directory does not exist: {db_path}")
            raise FileNotFoundError(f"Vector store directory not found: {db_path}")
        
        # Load vector store
        db = FAISS.load_local(db_path, embeddings)
        
        # Cấu hình retriever
        retriever = db.as_retriever(
            search_type="similarity",
            search_kwargs={"k": 3}
        )
        
        logger.info(f"Vector database initialized successfully with {db.index.ntotal} vectors")
        return retriever
    except Exception as e:
        logger.error(f"Error initializing vector database: {str(e)}")
        logger.error(traceback.format_exc())
        raise

# Khởi tạo Gemini model
def initialize_gemini():
    try:
        # Tải lại biến môi trường từ file .env để đảm bảo lấy giá trị mới nhất
        load_dotenv(override=True)
        
        # Cấu hình API key
        api_key = os.getenv("GOOGLE_API_KEY")
        if not api_key:
            raise ValueError("GOOGLE_API_KEY not found in environment variables")
        
        logger.info(f"Using API key: {api_key[:5]}...{api_key[-5:]} (middle part hidden)")
        
        # Cấu hình API
        genai.configure(api_key=api_key)
        
        # Tạo model
        model_name = os.getenv("GOOGLE_MODEL", "gemini-1.5-pro")
        logger.info(f"Initializing Gemini model: {model_name}")
        model = genai.GenerativeModel(model_name)
        
        # Kiểm tra xem model có hoạt động không bằng cách gọi một request đơn giản
        test_response = model.generate_content("Test connection")
        logger.info("Gemini model initialized and tested successfully")
        return model
    except Exception as e:
        logger.error(f"Error initializing Gemini model: {str(e)}")
        logger.error(traceback.format_exc())
        raise

# Xử lý chat
async def process_chat(query: str, user_id: Optional[int] = None) -> Dict[str, Any]:
    try:
        start_time = time.time()
        
        # Kiểm tra global variables
        global gemini_model, vector_retriever
        if gemini_model is None or vector_retriever is None:
            # Thử khởi tạo lại nếu cần
            if gemini_model is None:
                logger.info("Gemini model not initialized, trying to reinitialize...")
                gemini_model = initialize_gemini()
            if vector_retriever is None:
                logger.info("Vector retriever not initialized, trying to reinitialize...")
                vector_retriever = initialize_vector_db()
                
        # Tìm kiếm ngữ cảnh từ vector database
        docs = vector_retriever.get_relevant_documents(query)
        context = "\n\n".join([doc.page_content for doc in docs])
        
        # Log context để debug
        logger.info(f"Found {len(docs)} relevant documents for query: {query}")
        
        # Tạo prompt với ngữ cảnh
        prompt = f"""BẮT BUỘC trả lời dựa HOÀN TOÀN trên thông tin được cung cấp dưới đây.
KHÔNG ĐƯỢC sử dụng kiến thức bên ngoài dưới bất kỳ hình thức nào.
Nếu không có thông tin liên quan, hãy nói "Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu".

THÔNG TIN THAM KHẢO:
{context}

CÂU HỎI: {query}

YÊU CẦU ĐẶC BIỆT:
1. PHẢI bắt đầu câu trả lời của bạn với "Theo dữ liệu tìm được: " và kèm theo trích dẫn trực tiếp từ thông tin trên.
2. Nếu có thông tin liên quan, PHẢI trích dẫn ít nhất một đoạn từ thông tin trên.
3. Nếu không có thông tin liên quan, hãy nói rõ ràng "Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu".
4. KHÔNG ĐƯỢC thêm bất kỳ thông tin nào không có trong dữ liệu được cung cấp.
"""
        
        # Gửi prompt đến Gemini API
        response = gemini_model.generate_content(prompt)
        elapsed_time = time.time() - start_time
        
        # Chuẩn bị response
        return {
            "success": True,
            "answer": response.text,
            "query": query,
            "processing_time": f"{elapsed_time:.2f} seconds"
        }
        
    except Exception as e:
        logger.error(f"Error in chat processing: {str(e)}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "answer": "",
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
    """Chat endpoint for Laravel integration"""
    try:
        # Log request headers
        logger.info(f"Request headers: {dict(request.headers)}")
        
        # Đọc raw body trước
        raw_body = await request.body()
        logger.info(f"Raw request body length: {len(raw_body)}")
        
        # Thử decode với UTF-8
        try:
            body_str = raw_body.decode('utf-8')
            logger.info("Used utf-8 encoding for request body")
        except UnicodeDecodeError:
            # Nếu không decode được UTF-8, thử các encoding khác
            try:
                body_str = raw_body.decode('latin-1')
                logger.info("Used latin-1 encoding for request body")
            except UnicodeDecodeError:
                body_str = raw_body.decode('cp1252', errors='ignore')
                logger.info("Used cp1252 encoding with error ignore for request body")
        
        # Parse JSON từ string
        try:
            body = json.loads(body_str)
            logger.info(f"Parsed JSON: {body}")
        except json.JSONDecodeError as e:
            # Log một phần của body để debug
            preview = body_str[:100] if len(body_str) > 100 else body_str
            logger.error(f"JSON parsing error: {str(e)}, preview: {preview}")
            return {
                "success": False,
                "answer": "Invalid JSON format",
                "query": "",
                "error": f"JSON decode error: {str(e)}"
            }
        
        # Extract fields
        query = body.get("message", "")  # Thay đổi từ "query" thành "message"
        user_id = body.get("user_id", None)
        
        logger.info(f"Extracted message: '{query}', user_id: {user_id}")
        
        if not query:
            return {
                "success": False,
                "answer": "Message is required",
                "query": "",
                "error": "Missing message parameter"
            }
        
        # Process chat
        result = await process_chat(query, user_id)
        
        # Log response
        logger.info(f"Chat response: {result}")
        
        # Đảm bảo response được encode đúng
        return {
            "success": result["success"],
            "response": result["answer"],  # Thay đổi từ "answer" thành "response"
            "query": result["query"],
            "error": result.get("error", None)
        }
    except Exception as e:
        logger.error(f"Error in chat_direct: {str(e)}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "response": "Server error processing request",
            "query": "",
            "error": str(e)
        }

@app.get("/api/v1/chat/service-info")
async def service_info():
    """Get service information"""
    return {
        "service_type": "API",
        "api_type": "Google",
        "model": os.getenv("GOOGLE_MODEL", "gemini-1.5-pro"),
        "status": "active" if gemini_model else "unavailable"
    }

@app.get("/api/v1/chat/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "components": {
            "api": "active" if gemini_model else "inactive",
            "vector_db": "active" if vector_retriever else "inactive"
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

# Khởi tạo components khi server start
@app.on_event("startup")
async def startup_event():
    try:
        # Tải lại biến môi trường
        load_dotenv(override=True)
        
        # Khởi tạo components
        global gemini_model, vector_retriever
        
        logger.info("Initializing vector database...")
        vector_retriever = initialize_vector_db()
        
        logger.info("Initializing Gemini model...")
        gemini_model = initialize_gemini()
        
        logger.info("All components initialized successfully!")
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