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
from app.services.chatbot import ChatbotService, check_cuda
import time
import subprocess
import sys

# Tạo ứng dụng FastAPI
app = FastAPI(
    title=settings.PROJECT_NAME,
    description="API cho ChatBotKinhTe",
    version="1.0.0",
    docs_url=f"{settings.API_V1_STR}/docs",
    redoc_url=f"{settings.API_V1_STR}/redoc",
)

# Cấu hình CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8000", "http://127.0.0.1:8000", "*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
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

# Kiểm tra CUDA version
def get_cuda_version():
    try:
        # Thử lấy thông tin CUDA version
        if os.name == 'nt':  # Windows
            result = subprocess.run(['nvcc', '--version'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            if result.returncode == 0:
                lines = result.stdout.split('\n')
                for line in lines:
                    if 'release' in line:
                        return line.strip()
        else:  # Linux
            result = subprocess.run(['nvidia-smi'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            if result.returncode == 0:
                lines = result.stdout.split('\n')
                for line in lines:
                    if 'CUDA Version' in line:
                        return line.strip()
        return "CUDA version could not be determined"
    except Exception as e:
        return f"Error getting CUDA version: {str(e)}"

# Kiểm tra GPU
@app.get(f"{settings.API_V1_STR}/system/gpu")
async def gpu_status():
    """Check GPU status and CUDA information"""
    has_cuda = check_cuda()
    
    gpu_info = {
        "cuda_available": has_cuda,
        "cuda_version": get_cuda_version(),
        "n_gpu_layers": settings.N_GPU_LAYERS,
        "env_info": {
            "model_path": settings.MODEL_PATH,
            "model_exists": os.path.exists(settings.MODEL_PATH),
            "n_ctx": settings.N_CTX,
            "max_tokens": settings.MAX_TOKENS
        }
    }
    
    return gpu_info

# Pre-load model to GPU
@app.on_event("startup")
async def startup_event():
    # Kiểm tra CUDA
    print("\n===== CHECKING GPU STATUS =====")
    has_cuda = check_cuda()
    if has_cuda:
        print("CUDA is available! GPU acceleration enabled.")
    else:
        print("WARNING: CUDA is not available. Running on CPU only.")
    
    # Hiển thị CUDA version
    cuda_version = get_cuda_version()
    print(f"CUDA information: {cuda_version}")
    
    # Khởi tạo chatbot service
    print("\nPreloading ChatbotService model to GPU... This may take a moment.")
    try:
        start_time = time.time()
        chatbot_service = ChatbotService()
        chatbot_service._initialize_components()
        end_time = time.time()
        
        print(f"ChatbotService model loaded successfully in {end_time - start_time:.2f} seconds!")
    except Exception as e:
        print(f"Error preloading ChatbotService model: {str(e)}")

# Chạy ứng dụng
if __name__ == "__main__":
    uvicorn.run(
        "main:app",
        host=settings.API_HOST,
        port=settings.API_PORT,
        reload=False  # Tắt reload để tránh vấn đề với GPU
    ) 