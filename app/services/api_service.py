import os
import time
import asyncio
import aiohttp
import json
from typing import Dict, Any, Optional, List
from app.core.config import settings
from app.core.logger import get_logger

# Configure logging
logger = get_logger(__name__)

class APIService:
    """Service to handle LLM API calls (Google, OpenAI, etc.)"""
    
    def __init__(self):
        """Initialize the API service with configuration from settings"""
        self.api_type = settings.API_TYPE.lower()
        self.timeout = settings.API_TIMEOUT
        
        # Set up API-specific configurations
        if self.api_type == "google":
            self.api_key = settings.GOOGLE_API_KEY
            self.model = settings.GOOGLE_MODEL
            self.base_url = "https://generativelanguage.googleapis.com/v1beta/models"
            
            if not self.api_key or self.api_key == "your-google-api-key-here":
                logger.error("Google API key not configured properly in .env")
                raise ValueError("Google API key not configured")
        
        elif self.api_type == "openai":
            # Reserved for future OpenAI API support
            self.api_key = os.getenv("OPENAI_API_KEY", "")
            self.model = os.getenv("OPENAI_MODEL", "gpt-3.5-turbo")
            self.base_url = "https://api.openai.com/v1"
            
            if not self.api_key:
                logger.error("OpenAI API key not configured properly in .env")
                raise ValueError("OpenAI API key not configured")
        else:
            logger.error(f"Unsupported API type: {self.api_type}")
            raise ValueError(f"Unsupported API type: {self.api_type}")
        
        logger.info(f"Initialized {self.api_type.capitalize()} API service with model: {self.model}")
    
    async def test_connection(self) -> bool:
        """Test the API connection"""
        try:
            if self.api_type == "google":
                test_response = await self.query_google_api("Xin chào, đây là test.")
                logger.info(f"API connection test successful: {test_response[:50]}...")
                return True
            # Add other API types here
            return False
        except Exception as e:
            logger.error(f"API connection test failed: {str(e)}")
            return False
    
    async def query_google_api(self, query: str, context: Optional[str] = None) -> str:
        """
        Query the Google Generative AI API (Gemini)
        
        Args:
            query: The user query
            context: Optional context to include in the prompt
            
        Returns:
            The model's response text
        """
        url = f"{self.base_url}/{self.model}:generateContent?key={self.api_key}"
        
        # Create the prompt
        if context:
            prompt = f"""Context: {context}

Question: {query}

Please answer the question based solely on the provided context. If the context doesn't contain relevant information, state "Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu."
"""
        else:
            prompt = query
        
        # Prepare the request payload for Gemini
        payload = {
            "contents": [
                {
                    "parts": [
                        {
                            "text": prompt
                        }
                    ]
                }
            ],
            "generationConfig": {
                "temperature": settings.TEMPERATURE,
                "maxOutputTokens": settings.MAX_TOKENS,
                "topP": 0.95,
                "topK": 40
            },
            "safetySettings": [
                {
                    "category": "HARM_CATEGORY_HARASSMENT",
                    "threshold": "BLOCK_MEDIUM_AND_ABOVE"
                },
                {
                    "category": "HARM_CATEGORY_HATE_SPEECH",
                    "threshold": "BLOCK_MEDIUM_AND_ABOVE"
                },
                {
                    "category": "HARM_CATEGORY_SEXUALLY_EXPLICIT",
                    "threshold": "BLOCK_MEDIUM_AND_ABOVE"
                },
                {
                    "category": "HARM_CATEGORY_DANGEROUS_CONTENT",
                    "threshold": "BLOCK_MEDIUM_AND_ABOVE"
                }
            ]
        }
        
        try:
            async with aiohttp.ClientSession() as session:
                logger.info(f"Sending request to Google API: {self.model}")
                start_time = time.time()
                
                async with session.post(
                    url,
                    json=payload,
                    timeout=self.timeout
                ) as response:
                    response_json = await response.json()
                    
                    end_time = time.time()
                    logger.info(f"Google API response received in {end_time - start_time:.2f} seconds")
                    
                    if response.status != 200:
                        logger.error(f"API error: {response.status} - {response_json}")
                        error_message = response_json.get("error", {}).get("message", "Unknown API error")
                        return f"API Error: {error_message}"
                    
                    # Extract text from the response
                    try:
                        answer = response_json["candidates"][0]["content"]["parts"][0]["text"]
                        return answer
                    except (KeyError, IndexError) as e:
                        logger.error(f"Error parsing API response: {str(e)}")
                        logger.error(f"Response structure: {json.dumps(response_json)}")
                        return "Lỗi khi phân tích phản hồi từ API."
        
        except asyncio.TimeoutError:
            logger.error(f"API request timed out after {self.timeout} seconds")
            return "Yêu cầu đã hết thời gian chờ. Vui lòng thử lại sau."
        
        except Exception as e:
            logger.error(f"Error querying Google API: {str(e)}")
            return f"Lỗi khi kết nối đến API: {str(e)}"
    
    async def get_answer(self, query: str, context: Optional[str] = None) -> Dict[str, Any]:
        """
        Get answer from the API
        
        Args:
            query: The user query
            context: Optional context from relevant documents
            
        Returns:
            Dictionary with answer and metadata
        """
        start_time = time.time()
        
        try:
            if self.api_type == "google":
                answer = await self.query_google_api(query, context)
                # Add future API types here with elif blocks
            else:
                answer = f"API type {self.api_type} not implemented yet."
            
            end_time = time.time()
            processing_time = end_time - start_time
            
            return {
                "answer": answer,
                "query": query,
                "processing_time": processing_time,
                "api_type": self.api_type,
                "model": self.model,
                "status": "success"
            }
            
        except Exception as e:
            end_time = time.time()
            logger.error(f"Error in API service: {str(e)}")
            
            return {
                "answer": f"Lỗi khi xử lý yêu cầu: {str(e)}",
                "query": query,
                "processing_time": end_time - start_time,
                "error": str(e),
                "api_type": self.api_type,
                "model": self.model,
                "status": "error"
            } 