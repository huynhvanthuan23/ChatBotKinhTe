from fastapi import APIRouter, HTTPException, Depends, Request
from pydantic import BaseModel
from app.services.chatbot import ChatbotService
from app.core.logging import get_logger
from app.core.config import settings
import os

class ChatRequest(BaseModel):
    message: str

class ChatResponse(BaseModel):
    answer: str
    query: str

router = APIRouter()
logger = get_logger(__name__)

def get_chatbot_service():
    """Dependency injection cho ChatbotService."""
    return ChatbotService()

@router.post("/chat", response_model=ChatResponse)
async def chat(request: ChatRequest, chatbot_service: ChatbotService = Depends(get_chatbot_service)):
    """
    API endpoint để trò chuyện với chatbot.
    
    Args:
        request: ChatRequest chứa tin nhắn từ người dùng
        
    Returns:
        ChatResponse chứa câu trả lời từ chatbot
    """
    logger.info(f"Received chat request: {request.message}")
    try:
        response = await chatbot_service.get_answer(request.message)
        logger.info("Successfully generated response")
        return response
    except Exception as e:
        logger.error(f"Error processing chat request: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Chatbot error: {str(e)}")

@router.get("/health")
async def health_check():
    """
    Endpoint kiểm tra trạng thái hoạt động của API.
    Không cần tải model, chỉ kiểm tra xem API có hoạt động không.
    """
    # Kiểm tra xem các thư mục cần thiết có tồn tại không
    model_exists = os.path.exists(settings.MODEL_PATH)
    db_exists = os.path.exists(settings.DB_FAISS_PATH)
    
    return {
        "status": "ok",
        "api_version": "1.0.0",
        "project_name": settings.PROJECT_NAME,
        "resources": {
            "model_file_exists": model_exists,
            "vector_db_exists": db_exists
        }
    }

@router.get("/check-resources")
async def check_resources(request: Request):
    """Kiểm tra và hiển thị các resources có sẵn."""
    base_dir = request.app.root_path
    
    return {
        "base_directory": base_dir,
        "model_path": {
            "configured": settings.MODEL_PATH,
            "exists": os.path.exists(settings.MODEL_PATH),
            "absolute_path": os.path.abspath(settings.MODEL_PATH) if settings.MODEL_PATH else None
        },
        "vector_db_path": {
            "configured": settings.DB_FAISS_PATH,
            "exists": os.path.exists(settings.DB_FAISS_PATH),
            "absolute_path": os.path.abspath(settings.DB_FAISS_PATH) if settings.DB_FAISS_PATH else None
        }
    } 