import uvicorn
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
import os
from fastapi.responses import FileResponse
from app.core.config import settings
from app.api.endpoints import chat  
from fastapi.middleware.cors import CORSMiddleware
from app.services.chatbot import ChatbotService
import torch
import gc
import time

# Tạo ứng dụng FastAPI
app = FastAPI(
    title=settings.PROJECT_NAME,
    description="API cho ChatBotKinhTe",
    version="1.0.0",
    docs_url=f"{settings.API_V1_STR}/docs",
    redoc_url=f"{settings.API_V1_STR}/redoc",
)

# Cấu hình CORS
origins = [
    "http://localhost:8000",
    "http://127.0.0.1:8000",
    "http://localhost:5173",  # Vite dev server
    "http://127.0.0.1:5173",
]

# Thêm origins từ env nếu có
if os.environ.get("CORS_ORIGINS"):
    try:
        import json
        custom_origins = json.loads(os.environ.get("CORS_ORIGINS", '["*"]'))
        if isinstance(custom_origins, list):
            origins.extend(custom_origins)
    except Exception as e:
        print(f"Error parsing CORS_ORIGINS: {e}")

app.add_middleware(
    CORSMiddleware,
    allow_origins=origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["X-Process-Time"],
)

# Tạo router và đăng ký vào app
app.include_router(chat.router, prefix=f"{settings.API_V1_STR}/chat", tags=["chat"])

# Cấu hình đường dẫn templates và static files
base_dir = os.path.dirname(os.path.abspath(__file__))
app.mount("/static", StaticFiles(directory=os.path.join(base_dir, "app", "web", "static")), name="static")
templates = Jinja2Templates(directory=os.path.join(base_dir, "app", "web", "templates"))

# Sửa route trang chủ để render template HTML
@app.get("/", response_class=HTMLResponse)
async def root(request: Request):
    return templates.TemplateResponse("index.html", {"request": request})

# Thêm endpoint này vào ứng dụng FastAPI của bạn
@app.get("/favicon.ico", include_in_schema=False)
async def favicon():
    # Đường dẫn đến file favicon.ico (tạo file này nếu chưa có)
    favicon_path = os.path.join(base_dir, "app", "web", "static", "img", "favicon.ico")
    
    # Nếu không có file, trả về file mặc định từ thư viện fastapi
    if not os.path.exists(favicon_path):
        return FileResponse("app/web/static/img/favicon.ico")
    
    return FileResponse(favicon_path)

# Kiểm tra GPU và giải phóng bộ nhớ
def check_gpu():
    print("\n===== GPU STATUS =====")
    if torch.cuda.is_available():
        print(f"CUDA available: Yes")
        print(f"CUDA device count: {torch.cuda.device_count()}")
        print(f"CUDA device name: {torch.cuda.get_device_name(0)}")
        print(f"CUDA memory allocated: {torch.cuda.memory_allocated(0) / 1024**2:.2f} MB")
        print(f"CUDA memory reserved: {torch.cuda.memory_reserved(0) / 1024**2:.2f} MB")
        
        # Giải phóng bộ nhớ GPU
        torch.cuda.empty_cache()
        gc.collect()
        print(f"Memory after cleanup: {torch.cuda.memory_allocated(0) / 1024**2:.2f} MB")
        return True
    else:
        print("CUDA is not available")
        return False

# Create chatbot service endpoint
@app.get(f"{settings.API_V1_STR}/status/gpu")
async def gpu_status():
    """Check GPU status"""
    if torch.cuda.is_available():
        return {
            "cuda_available": True,
            "device_count": torch.cuda.device_count(),
            "device_name": torch.cuda.get_device_name(0),
            "memory_allocated_mb": torch.cuda.memory_allocated(0) / 1024**2,
            "memory_reserved_mb": torch.cuda.memory_reserved(0) / 1024**2
        }
    else:
        return {
            "cuda_available": False
        }

# Pre-load model to GPU
@app.on_event("startup")
async def startup_event():
    # Khởi tạo chatbot service khi server khởi động
    print("Starting server and initializing components...")
    
    # Kiểm tra GPU
    if torch.cuda.is_available():
        print(f"GPU detected: {torch.cuda.get_device_name(0)}")
        print(f"CUDA version: {torch.version.cuda}")
        memory_gb = torch.cuda.get_device_properties(0).total_memory / (1024**3)
        print(f"GPU memory: {memory_gb:.2f} GB")
        
        # Giải phóng memory
        torch.cuda.empty_cache()
        gc.collect()
    else:
        print("No GPU detected - using CPU")
    
    # Khởi tạo chatbot service trong try-except
    try:
        print("Initializing ChatbotService...")
        
        start_time = time.time()
        chatbot_service = ChatbotService()
        chatbot_service._initialize_components()
        end_time = time.time()
        
        print(f"ChatbotService initialized in {end_time - start_time:.2f} seconds")
        
        # Thực hiện test nhỏ để kiểm tra hoạt động
        try:
            print("Testing chatbot with a simple query...")
            test_response = await chatbot_service.get_answer("Xin chào")
            print(f"Test response: {test_response.get('answer', '')[:50]}...")
            print(f"Chatbot is working correctly")
            
            # Add process time metrics
            if 'processing_time' in test_response:
                print(f"Processing time: {test_response['processing_time']:.2f} seconds")
        except Exception as test_err:
            print(f"Test failed: {str(test_err)}")
    except Exception as e:
        print(f"Error initializing ChatbotService: {str(e)}")
        print("Server will start but chatbot may not work correctly")

# Thêm route mới vào file main.py
@app.get(f"{settings.API_V1_STR}/acceleration")
async def acceleration_info():
    """Kiểm tra thông tin GPU/CPU đang sử dụng cho model"""
    chatbot = ChatbotService()
    return chatbot.get_acceleration_info()

# Chạy ứng dụng
if __name__ == "__main__":
    uvicorn.run(
        "main:app",  # Sử dụng format "file:app_variable"
        host=settings.API_HOST,
        port=settings.API_PORT,
        reload=settings.DEBUG_MODE
    ) 