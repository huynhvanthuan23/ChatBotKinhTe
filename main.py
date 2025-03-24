import uvicorn
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
import os
from fastapi.responses import FileResponse
from app.core.config import settings
from app.api.endpoints import chat  

# Tạo ứng dụng FastAPI
app = FastAPI(
    title=settings.PROJECT_NAME,
    description="API cho ChatBotKinhTe",
    version="1.0.0",
    docs_url=f"{settings.API_V1_STR}/docs",
    redoc_url=f"{settings.API_V1_STR}/redoc",
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

# Chạy ứng dụng
if __name__ == "__main__":
    uvicorn.run(
        "main:app",  # Sử dụng format "file:app_variable"
        host=settings.API_HOST,
        port=settings.API_PORT,
        reload=settings.DEBUG_MODE
    ) 