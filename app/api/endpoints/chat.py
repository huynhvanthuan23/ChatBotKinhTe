from fastapi import APIRouter, HTTPException, Depends, Request, Body, BackgroundTasks
from pydantic import BaseModel
from app.services.chatbot import ChatbotService
from app.core.logger import get_logger
from app.core.config import settings
import os
from typing import Optional, Dict, Any, Union
import asyncio
import torch

# Import APIService nếu cần sử dụng API
if settings.USE_API:
    from app.services.api_service import APIService

class ChatRequest(BaseModel):
    message: str
    user_id: Optional[int] = None

class ChatResponse(BaseModel):
    response: str
    query: str
    model_info: Optional[str] = None

router = APIRouter()
logger = get_logger(__name__)

# Khởi tạo service dựa trên cấu hình
api_service = None
chatbot_service = None

if settings.USE_API:
    try:
        logger.info(f"Initializing API service with type: {settings.API_TYPE}")
        api_service = APIService()
    except Exception as e:
        logger.error(f"Failed to initialize API service: {str(e)}")
        api_service = None

# Chatbot service vẫn được khởi tạo để sử dụng làm fallback
try:
    chatbot_service = ChatbotService()
except Exception as e:
    logger.error(f"Failed to initialize chatbot service: {str(e)}")
    if not api_service:
        logger.critical("Both API service and chatbot service failed to initialize!")

def get_service():
    """Dependency injection cho service (API hoặc Chatbot)."""
    if settings.USE_API and api_service:
        return api_service
    return ChatbotService()

@router.post("/chat", response_model=ChatResponse)
async def chat(
    message: str = Body(..., embed=True),
    user_id: Optional[int] = Body(None, embed=True),
    service = Depends(get_service)
):
    """
    API endpoint để trò chuyện với chatbot (API hoặc model cục bộ).
    
    Args:
        message: Tin nhắn từ người dùng
        user_id: ID của người dùng (tùy chọn)
        
    Returns:
        ChatResponse chứa câu trả lời
    """
    logger.info(f"Received chat request from user {user_id}: {message}")
    try:
        answer = await service.get_answer(message)
        logger.info("Successfully generated response")
        
        # Model info cho debug
        model_info = None
        if settings.USE_API and api_service:
            model_info = f"API: {settings.API_TYPE} - {getattr(api_service, 'model', 'unknown')}"
        else:
            model_info = f"Local: {os.path.basename(settings.MODEL_PATH)}"
            
        return {
            "response": answer["answer"],
            "query": message,
            "model_info": model_info
        }
    except Exception as e:
        logger.error(f"Error processing chat request: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Chatbot error: {str(e)}")

@router.post("/chat-model", response_model=ChatResponse)
async def chat_model(request: ChatRequest, chatbot_service: ChatbotService = Depends(get_service)):
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

@router.get("/service-info")
async def service_info():
    """
    Endpoint để kiểm tra service nào đang được sử dụng.
    """
    if settings.USE_API and api_service:
        return {
            "service_type": "API",
            "api_type": settings.API_TYPE,
            "model": getattr(api_service, 'model', 'unknown'),
            "status": "active" if api_service else "unavailable"
        }
    else:
        return {
            "service_type": "Local Model",
            "model_path": settings.MODEL_PATH,
            "model_name": os.path.basename(settings.MODEL_PATH),
            "status": "active" if chatbot_service else "unavailable",
            "gpu_enabled": torch.cuda.is_available() if chatbot_service and hasattr(torch, 'cuda') else "unknown"
        }
        
@router.get("/health")
async def health_check():
    """
    Endpoint kiểm tra trạng thái hoạt động của API.
    """
    # Kiểm tra resources dựa trên mode
    if settings.USE_API:
        # Kiểm tra API có sẵn sàng không
        api_available = api_service is not None
        api_health = "ok" if api_available else "unavailable"
        
        return {
            "status": api_health,
            "mode": "API",
            "api_type": settings.API_TYPE,
            "api_version": "1.0.0",
            "project_name": settings.PROJECT_NAME
        }
    else:
        # Kiểm tra model cục bộ
        model_exists = os.path.exists(settings.MODEL_PATH)
        db_exists = os.path.exists(settings.DB_FAISS_PATH)
        
        return {
            "status": "ok" if model_exists and db_exists else "missing_resources",
            "mode": "Local Model",
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
    
    resource_info = {
        "base_directory": base_dir,
        "service_mode": "API" if settings.USE_API else "Local Model"
    }
    
    if settings.USE_API:
        resource_info.update({
            "api_type": settings.API_TYPE,
            "api_key_configured": bool(settings.GOOGLE_API_KEY) if settings.API_TYPE == "google" else "N/A",
            "api_model": settings.GOOGLE_MODEL if settings.API_TYPE == "google" else "N/A",
            "api_service_available": api_service is not None
        })
    else:
        resource_info.update({
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
        })
    
    return resource_info

@router.post("/test", response_model=dict)
async def test_connection():
    """
    API endpoint để kiểm tra kết nối từ Laravel
    """
    return {
        "status": "success",
        "message": "Kết nối thành công với Chatbot API!",
        "mode": "API" if settings.USE_API else "Local Model"
    }

@router.get("/ping")
async def ping():
    """
    Simple endpoint to check if API is alive
    """
    return {"status": "ok", "message": "API is running"}

@router.post("/chat-direct")
async def chat_direct(request: ChatRequest):
    """
    Endpoint trực tiếp cho chatbot, trả lời ngay lập tức.
    Nhận yêu cầu từ Laravel frontend và trả về kết quả.
    Tự động chọn giữa API và model cục bộ dựa trên cấu hình.
    """
    if not request.message:
        raise HTTPException(status_code=400, detail="Message is required")
    
    try:
        # Log incoming request
        logger.info(f"Received chat-direct request from user {request.user_id}: '{request.message}'")
        
        # Validate message length to prevent excessive processing
        if len(request.message) > 1000:
            logger.warning(f"Message too long: {len(request.message)} characters")
            return {
                "success": False,
                "answer": "Tin nhắn của bạn quá dài. Vui lòng giới hạn trong 1000 ký tự.",
                "query": request.message[:100] + "..." if len(request.message) > 100 else request.message
            }
        
        # Chọn service dựa trên cấu hình
        service = api_service if settings.USE_API and api_service else chatbot_service
        
        if not service:
            raise ValueError("No available service (API or local model)")
            
        # Thông tin về service đang sử dụng
        service_info = "API" if settings.USE_API and api_service else "Local model"
        model_name = getattr(service, 'model', os.path.basename(settings.MODEL_PATH)) if hasattr(service, 'model') else os.path.basename(settings.MODEL_PATH)
        logger.info(f"Using {service_info} with model: {model_name}")
            
        # Process with timeout to prevent hanging requests
        response = await asyncio.wait_for(
            service.get_answer(request.message),
            timeout=60.0  # Allow up to 60 seconds for processing
        )
        
        logger.info(f"Generated response for user {request.user_id} in {response.get('processing_time', 'N/A')} seconds")
        
        # Return standardized response for Laravel
        return {
            "success": True,
            "answer": response.get("answer", "Không có câu trả lời"),
            "query": request.message,
            "processing_time": response.get("processing_time", None),
            "service_type": service_info,
            "model": model_name
        }
    except asyncio.TimeoutError:
        logger.error(f"Query processing timed out: {request.message}")
        return {
            "success": False,
            "answer": "Xin lỗi, câu hỏi của bạn quá phức tạp và tôi không thể xử lý trong thời gian cho phép. Vui lòng thử hỏi câu ngắn gọn hơn.",
            "query": request.message
        }
    except Exception as e:
        error_msg = str(e)
        logger.error(f"Error processing chat request: {error_msg}")
        logger.error(f"Exception type: {type(e).__name__}")
        
        # Return friendly error message to user
        return {
            "success": False,
            "answer": "Xin lỗi, hệ thống gặp lỗi khi xử lý yêu cầu của bạn. Vui lòng thử lại sau.",
            "error": error_msg if settings.DEBUG_MODE else "Internal server error",
            "query": request.message
        } 