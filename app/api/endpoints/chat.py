from fastapi import APIRouter, HTTPException, Depends, Request, Body
from pydantic import BaseModel
from app.services.chatbot import ChatbotService
from app.core.logger import get_logger
from app.core.config import settings
import os
from typing import Optional, Dict, Any

class ChatRequest(BaseModel):
    message: str
    user_id: Optional[int] = None

class ChatResponse(BaseModel):
    response: str
    query: str

router = APIRouter()
logger = get_logger(__name__)

def get_chatbot_service():
    """Dependency injection cho ChatbotService."""
    return ChatbotService()

@router.post("/chat", response_model=ChatResponse)
async def chat(
    message: str = Body(..., embed=True),
    user_id: Optional[int] = Body(None, embed=True),
    chatbot_service: ChatbotService = Depends(get_chatbot_service)
):
    """
    API endpoint để trò chuyện với chatbot.
    
    Args:
        message: Tin nhắn từ người dùng
        user_id: ID của người dùng (tùy chọn)
        
    Returns:
        ChatResponse chứa câu trả lời từ chatbot
    """
    logger.info(f"Received chat request from user {user_id}: {message}")
    try:
        answer = await chatbot_service.get_answer(message)
        logger.info("Successfully generated response")
        return {
            "response": answer["answer"],
            "query": message
        }
    except Exception as e:
        logger.error(f"Error processing chat request: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Chatbot error: {str(e)}")

@router.post("/chat-model", response_model=ChatResponse)
async def chat_model(request: ChatRequest, chatbot_service: ChatbotService = Depends(get_chatbot_service)):
    """
    API endpoint để trò chuyện với chatbot (dùng model).
    
    Args:
        request: ChatRequest chứa tin nhắn từ người dùng
        
    Returns:
        ChatResponse chứa câu trả lời từ chatbot
    """
    logger.info(f"Received chat request from user {request.user_id}: {request.message}")
    try:
        answer = await chatbot_service.get_answer(request.message)
        logger.info("Successfully generated response")
        return {
            "response": answer["answer"],
            "query": request.message
        }
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

@router.post("/test", response_model=dict)
async def test_connection():
    """
    API endpoint để kiểm tra kết nối từ Laravel
    """
    return {
        "status": "success",
        "message": "Kết nối thành công với Chatbot API!"
    }

@router.get("/ping")
async def ping():
    """
    Simple endpoint to check if API is alive
    """
    return {"status": "ok", "message": "API is running"}

@router.post("/chat-direct")
async def chat_direct(data: Dict[str, Any] = Body(...), chatbot_service: ChatbotService = Depends(get_chatbot_service)):
    """
    API endpoint để trò chuyện với chatbot nhận JSON trực tiếp.
    
    Args:
        data: Dict chứa tin nhắn từ người dùng
    """
    if "message" not in data:
        raise HTTPException(status_code=400, detail="'message' field is required")
    
    message = data["message"]
    user_id = data.get("user_id")
    
    logger.info(f"Received direct chat request from user {user_id}: {message}")
    try:
        answer = await chatbot_service.get_answer(message)
        logger.info(f"Generated answer: {answer}")
        
        # Kiểm tra không trả về kết quả rỗng
        if not answer or not answer.get("answer") or answer.get("answer").strip() == "":
            logger.warning("Empty answer generated, using fallback response")
            response_text = "Xin lỗi, tôi không thể xử lý câu hỏi này lúc này. Vui lòng thử lại với cách diễn đạt khác."
        else:
            response_text = answer.get("answer")
        
        return {
            "response": response_text,
            "query": message
        }
    except Exception as e:
        logger.error(f"Error processing chat request: {str(e)}")
        return {
            "response": f"Xin lỗi, đã xảy ra lỗi khi xử lý câu hỏi của bạn: {str(e)}",
            "query": message
        } 