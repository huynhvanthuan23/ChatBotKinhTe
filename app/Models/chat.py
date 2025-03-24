from pydantic import BaseModel, Field
from typing import List, Optional, Dict, Any

class ChatRequest(BaseModel):
    """Model cho yêu cầu chat."""
    message: str = Field(..., description="Câu hỏi của người dùng")
    
class ChatResponse(BaseModel):
    """Model cho phản hồi chat."""
    answer: str = Field(..., description="Câu trả lời từ chatbot")
    query: str = Field(..., description="Câu hỏi đã hỏi")
    
class ChatHistory(BaseModel):
    """Model lưu trữ lịch sử chat."""
    messages: List[Dict[str, Any]] = Field(default_factory=list, description="Lịch sử chat")
