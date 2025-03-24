from fastapi import FastAPI, Request
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse
import os
from app.core.config import settings
from app.api.endpoints import chat
from app.core.logging import get_logger

logger = get_logger(__name__)

# Khởi tạo FastAPI app
app = FastAPI(
    title=settings.PROJECT_NAME,
    description="API cho ChatBotKinhTe sử dụng LLM để trả lời câu hỏi",
    version="1.0.0",
    openapi_url=f"{settings.API_V1_STR}/openapi.json",
    docs_url=f"{settings.API_V1_STR}/docs",
    redoc_url=f"{settings.API_V1_STR}/redoc",
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS or ["*"],
    allow_credentials=True,
    allow_methods=settings.CORS_METHODS or ["*"],
    allow_headers=settings.CORS_HEADERS or ["*"],
)

# Đăng ký API router
app.include_router(chat.router, prefix=f"{settings.API_V1_STR}/chat", tags=["chat"])

# Tìm đường dẫn thư mục app
app_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
web_dir = os.path.join(app_dir, "web")

# Đảm bảo thư mục templates tồn tại
templates_dir = os.path.join(web_dir, "templates")
if not os.path.exists(templates_dir):
    os.makedirs(templates_dir, exist_ok=True)
    with open(os.path.join(templates_dir, "index.html"), "w") as f:
        f.write("""<!DOCTYPE html>
<html>
<head>
    <title>ChatBotKinhTe</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{ url_for('static', path='css/style.css') }}">
</head>
<body>
    <h1>ChatBotKinhTe</h1>
    <p>Templates không được cấu hình đúng. Vui lòng kiểm tra lại thư mục templates.</p>
</body>
</html>""")

# Đảm bảo thư mục static tồn tại
static_dir = os.path.join(web_dir, "static")
if not os.path.exists(static_dir):
    os.makedirs(static_dir, exist_ok=True)
    css_dir = os.path.join(static_dir, "css")
    os.makedirs(css_dir, exist_ok=True)
    with open(os.path.join(css_dir, "style.css"), "w") as f:
        f.write("body { font-family: Arial, sans-serif; margin: 40px; }")

# Đặt thư mục gốc của ứng dụng để sử dụng trong endpoints
app.root_path = app_dir

# Templates
templates = Jinja2Templates(directory=templates_dir)
logger.info(f"Templates directory: {templates_dir}")

# Mount static files
try:
    app.mount("/static", StaticFiles(directory=static_dir), name="static")
    logger.info(f"Static files mounted from: {static_dir}")
except Exception as e:
    logger.error(f"Failed to mount static files: {str(e)}")

@app.get("/", response_class=HTMLResponse, tags=["web"])
async def home(request: Request):
    """Trang chủ web chatbot."""
    try:
        return templates.TemplateResponse("index.html", {"request": request})
    except Exception as e:
        logger.error(f"Error rendering template: {str(e)}")
        return HTMLResponse(content=f"""
        <html>
            <head><title>ChatBotKinhTe - Error</title></head>
            <body>
                <h1>Error loading template</h1>
                <p>{str(e)}</p>
                <p>Template directory: {templates_dir}</p>
            </body>
        </html>
        """)

@app.on_event("startup")
async def startup_event():
    """Chạy khi ứng dụng khởi động."""
    logger.info(f"Starting {settings.PROJECT_NAME}")

@app.on_event("shutdown")
async def shutdown_event():
    """Chạy khi ứng dụng tắt."""
    logger.info(f"Shutting down {settings.PROJECT_NAME}") 