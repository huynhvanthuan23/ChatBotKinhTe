import os
import time
import asyncio
import aiohttp
import json
from typing import Dict, Any, Optional, List
from app.core.config import settings
from app.core.logger import get_logger
import pickle
import traceback

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
            self.api_key = settings.OPENAI_API_KEY
            self.model = settings.OPENAI_MODEL
            self.base_url = "https://api.openai.com/v1"
            
            if not self.api_key:
                logger.error("OpenAI API key not configured properly in .env")
                raise ValueError("OpenAI API key not configured")
        else:
            logger.error(f"Unsupported API type: {self.api_type}")
            raise ValueError(f"Unsupported API type: {self.api_type}")
        
        logger.info(f"Initialized {self.api_type.capitalize()} API service with model: {self.model}")
    
    def initialize_gemini(self):
        """
        Khởi tạo và cấu hình API Gemini
        
        Returns:
            Đối tượng GenerativeModel của Gemini hoặc None nếu có lỗi
        """
        try:
            import google.generativeai as genai
            
            logger.info(f"Initializing Gemini API with key: {self.api_key[:4]}...{self.api_key[-4:] if len(self.api_key) > 8 else ''}")
            
            # Cấu hình API
            genai.configure(api_key=self.api_key)
            
            # Tạo model
            model = genai.GenerativeModel(self.model)
            
            # Test kết nối
            response = model.generate_content("Xin chào, đây là test kết nối.")
            if response and hasattr(response, 'text'):
                logger.info("Gemini API initialized successfully")
                return model
            else:
                logger.error("Gemini API test failed - no valid response")
                return None
                
        except Exception as e:
            logger.error(f"Error initializing Gemini API: {str(e)}")
            logger.error(traceback.format_exc())
            return None
    
    def initialize_openai(self):
        """
        Khởi tạo và cấu hình API OpenAI
        
        Returns:
            Client OpenAI hoặc None nếu có lỗi
        """
        try:
            from openai import OpenAI
            
            logger.info(f"Initializing OpenAI API with key: {self.api_key[:4]}...{self.api_key[-4:] if len(self.api_key) > 8 else ''}")
            
            # Tạo client
            client = OpenAI(api_key=self.api_key)
            
            # Test kết nối
            response = client.chat.completions.create(
                model=self.model,
                messages=[{"role": "system", "content": "Test connection"}],
                max_tokens=5
            )
            
            if response and hasattr(response, 'choices') and len(response.choices) > 0:
                logger.info("OpenAI API initialized successfully")
                return client
            else:
                logger.error("OpenAI API test failed - no valid response")
                return None
                
        except Exception as e:
            logger.error(f"Error initializing OpenAI API: {str(e)}")
            logger.error(traceback.format_exc())
            return None
    
    async def test_connection(self) -> bool:
        """Test the API connection"""
        try:
            if self.api_type == "google":
                test_response = await self.query_google_api("Xin chào, đây là test.")
                logger.info(f"API connection test successful: {test_response[:50]}...")
                return True
            elif self.api_type == "openai":
                test_response = await self.query_openai_api("Xin chào, đây là test.")
                logger.info(f"API connection test successful: {test_response[:50]}...")
                return True
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
    
    async def query_openai_api(self, query: str, context: Optional[str] = None) -> str:
        """
        Query the OpenAI API
        
        Args:
            query: The user query
            context: Optional context to include in the prompt
            
        Returns:
            The model's response text
        """
        url = f"{self.base_url}/chat/completions"
        
        # Create the prompt
        if context:
            prompt = f"""Context: {context}

Question: {query}

Please answer the question based solely on the provided context. If the context doesn't contain relevant information, state "Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu."
"""
        else:
            prompt = query
            
        # Prepare the request payload for OpenAI
        payload = {
            "model": self.model,
            "messages": [
                {
                    "role": "system", 
                    "content": "Bạn là trợ lý AI có kiến thức chuyên sâu về kinh tế Việt Nam. Nhiệm vụ của bạn là trả lời câu hỏi dựa trên dữ liệu được cung cấp, không sử dụng thông tin bên ngoài."
                },
                {
                    "role": "user", 
                    "content": prompt
                }
            ],
            "temperature": settings.TEMPERATURE,
            "max_tokens": settings.MAX_TOKENS,
            "top_p": 0.95,
            "frequency_penalty": 0,
            "presence_penalty": 0
        }
        
        try:
            async with aiohttp.ClientSession() as session:
                logger.info(f"Sending request to OpenAI API: {self.model}")
                start_time = time.time()
                
                async with session.post(
                    url,
                    headers={
                        "Content-Type": "application/json",
                        "Authorization": f"Bearer {self.api_key}"
                    },
                    json=payload,
                    timeout=self.timeout
                ) as response:
                    response_json = await response.json()
                    
                    end_time = time.time()
                    logger.info(f"OpenAI API response received in {end_time - start_time:.2f} seconds")
                    
                    if response.status != 200:
                        logger.error(f"API error: {response.status} - {response_json}")
                        error_message = response_json.get("error", {}).get("message", "Unknown API error")
                        return f"API Error: {error_message}"
                    
                    # Extract text from the response
                    try:
                        answer = response_json["choices"][0]["message"]["content"]
                        return answer
                    except (KeyError, IndexError) as e:
                        logger.error(f"Error parsing API response: {str(e)}")
                        logger.error(f"Response structure: {json.dumps(response_json)}")
                        return "Lỗi khi phân tích phản hồi từ API."
        
        except asyncio.TimeoutError:
            logger.error(f"API request timed out after {self.timeout} seconds")
            return "Yêu cầu đã hết thời gian chờ. Vui lòng thử lại sau."
        
        except Exception as e:
            logger.error(f"Error querying OpenAI API: {str(e)}")
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
            elif self.api_type == "openai":
                answer = await self.query_openai_api(query, context)
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

    async def process_chat(self, query: str, user_id: Optional[int] = None, vector_retriever = None) -> Dict[str, Any]:
        """
        Xử lý chat từ API
        
        Args:
            query: Câu hỏi của người dùng
            user_id: ID của người dùng (tùy chọn)
            vector_retriever: Retriever để lấy ngữ cảnh (tùy chọn)
            
        Returns:
            Dict với kết quả trả về
        """
        start_time = time.time()
        
        try:
            # Tìm kiếm ngữ cảnh từ vector database
            context = ""
            docs = []
            try:
                if vector_retriever:
                    docs = vector_retriever.get_relevant_documents(query)
                    if docs:
                        context = "\n\n".join([doc.page_content for doc in docs])
                        logger.info(f"Found {len(docs)} relevant documents from vector database")
                        
                        # Log a preview of each document for debugging
                        for i, doc in enumerate(docs):
                            preview = doc.page_content[:100] + "..." if len(doc.page_content) > 100 else doc.page_content
                            logger.info(f"Document {i+1} preview: {preview}")
                    else:
                        logger.info("No relevant documents found in vector database")
            except Exception as ret_err:
                logger.error(f"Error retrieving context: {str(ret_err)}")
                context = ""
            
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

            api_prompt = f"""Dựa trên thông tin được cung cấp, hãy trả lời câu hỏi sau.
Thông tin có thể chứa nhiều đoạn văn bản từ các tài liệu khác nhau. Hãy tổng hợp tất cả thông tin liên quan để đưa ra câu trả lời đầy đủ nhất.

THÔNG TIN THAM KHẢO:
{context}

CÂU HỎI: {query}

Hướng dẫn:
1. Bắt đầu câu trả lời với "Theo dữ liệu tìm được:"
2. Tổng hợp thông tin từ tất cả các nguồn liên quan
3. Nếu các nguồn có thông tin trái ngược, hãy trình bày các quan điểm khác nhau
4. Trích dẫn cụ thể thông tin quan trọng từ dữ liệu
5. Phân tích và kết nối các thông tin để tạo ra câu trả lời toàn diện
6. Nếu không tìm thấy thông tin chính xác, hãy sử dụng thông tin gần đúng hoặc liên quan
"""
            
            # Gửi prompt đến API dựa theo cấu hình
            answer = ""
            model_info = {}
            
            if self.api_type == "google":
                # Gửi prompt đến Gemini API
                answer = await self.query_google_api(query, context=api_prompt)
                model_info = {"model": self.model}
                logger.info(f"Nhận phản hồi từ Gemini API ({self.model})")
            
            elif self.api_type == "openai":
                # Gửi prompt đến OpenAI API
                answer = await self.query_openai_api(query, context=api_prompt)
                model_info = {"model": self.model}
                logger.info(f"Nhận phản hồi từ OpenAI API ({self.model})")
            
            else:
                logger.warning(f"API_TYPE không được hỗ trợ: {self.api_type}")
                answer = "API type không được hỗ trợ hoặc chưa được cấu hình."
                model_info = {"model": "fallback", "error": f"Unsupported API type: {self.api_type}"}
            
            elapsed_time = time.time() - start_time
            
            # Đảm bảo câu trả lời bắt đầu với "Theo dữ liệu tìm được: " nếu cần
            if answer and not answer.startswith("Theo dữ liệu tìm được:") and context:
                answer = "Theo dữ liệu tìm được: " + answer
            
            # Chuẩn bị response
            result = {
                "success": True,
                "answer": answer,
                "query": query,
                "processing_time": f"{elapsed_time:.2f} seconds",
                "documents_found": len(docs)
            }
            
            # Thêm model info
            result.update(model_info)
            
            return result
            
        except Exception as e:
            logger.error(f"Lỗi trong xử lý chat: {str(e)}")
            logger.error(traceback.format_exc())
            end_time = time.time()
            
            return {
                "success": False,
                "answer": f"Xin lỗi, có lỗi xảy ra trong quá trình xử lý: {str(e)}",
                "query": query,
                "error": str(e),
                "processing_time": f"{end_time - start_time:.2f} seconds"
            }

def load_data():
    """Return empty dict instead of loading from pickle"""
    logger.info("Using empty dict instead of pickle for security")
    return {}

def save_data(data):
    """Dummy function that doesn't save anything"""
    logger.info("Data saving skipped for security")
    pass

def create_empty_session_file():
    empty_data = {}
    try:
        with open('chat_sessions.pkl', 'wb') as f:
            pickle.dump(empty_data, f)
        logger.info("Created new empty chat_sessions.pkl file")
    except Exception as e:
        logger.error(f"Error creating chat_sessions.pkl: {str(e)}") 