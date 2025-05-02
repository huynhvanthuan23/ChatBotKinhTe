from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
from langchain.prompts import PromptTemplate
from typing import Dict, Any, Optional, Tuple
from app.core.config import settings
from app.core.logger import get_logger
import os
import time
import torch
import gc
import traceback
import google.generativeai as genai
import openai
from openai import OpenAI

# Cấu hình logging
logger = get_logger(__name__)

class ChatbotService:
    _instance = None
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(ChatbotService, cls).__new__(cls)
            cls._instance._initialized = False
            cls._instance._retry_count = 0
            cls._instance._gemini_model = None
            cls._instance._gemini_chat = None
            cls._instance._openai_client = None
        return cls._instance
    
    def __init__(self):
        # Các thuộc tính sẽ được khởi tạo trong _initialize_components
        pass
    
    def _initialize_components(self):
        """Initialize components at startup"""
        # Reset initialized flag to force reloading components
        self._initialized = False
        
        # Reset các thành phần để tránh sử dụng cache
        self._embeddings = None
        self._db = None
        self._retriever = None
        self._gemini_model = None
        self._gemini_chat = None
        self._openai_client = None
            
        logger.info("Initializing ChatbotService components...")
        
        try:
            # Đầu tiên, giải phóng bộ nhớ GPU
            self._clean_gpu_memory()
            
            # Khởi tạo model dựa vào API_TYPE
            if settings.USE_API:
                if settings.API_TYPE.lower() == "google" and settings.GOOGLE_API_KEY:
                    try:
                        logger.info("Initializing Gemini API...")
                    
                        # Cấu hình API
                        genai.configure(api_key=settings.GOOGLE_API_KEY)
                    
                        # Sử dụng model gemini-1.5-pro
                        self._gemini_model = genai.GenerativeModel(settings.GOOGLE_MODEL)
                        self._gemini_chat = self._gemini_model.start_chat(history=[])
                        logger.info(f"Gemini API initialized successfully with model {settings.GOOGLE_MODEL}")
                        
                    except Exception as api_err:
                        logger.error(f"Error initializing Gemini API: {str(api_err)}")
                        raise RuntimeError("Failed to initialize Gemini API. Please check your API key and internet connection.")
                
                elif settings.API_TYPE.lower() == "openai" and settings.OPENAI_API_KEY:
                    try:
                        logger.info("Initializing OpenAI API...")
                        
                        # Cấu hình API
                        self._openai_client = OpenAI(api_key=settings.OPENAI_API_KEY)
                        
                        # Test kết nối
                        response = self._openai_client.chat.completions.create(
                            model=settings.OPENAI_MODEL,
                            messages=[{"role": "system", "content": "Test connection"}],
                            max_tokens=5
                        )
                        logger.info(f"OpenAI API initialized successfully with model {settings.OPENAI_MODEL}")
                        
                    except Exception as api_err:
                        logger.error(f"Error initializing OpenAI API: {str(api_err)}")
                        raise RuntimeError("Failed to initialize OpenAI API. Please check your API key and internet connection.")
                else:
                    logger.warning(f"No valid API configuration found. API_TYPE: {settings.API_TYPE}")
            
            # Khởi tạo embedding model và vector store
            try:
                # Kiểm tra đường dẫn vector database
                db_path = settings.DB_FAISS_PATH
                logger.info(f"Vector store path: {db_path}")
                
                # Kiểm tra xem thư mục và file có tồn tại không
                if not os.path.exists(db_path):
                    logger.error(f"Vector store directory does not exist: {db_path}")
                    raise FileNotFoundError(f"Vector store directory not found: {db_path}")
                
                faiss_index_path = os.path.join(db_path, "index.faiss")
                pkl_index_path = os.path.join(db_path, "index.pkl")
                
                if not os.path.exists(faiss_index_path) or not os.path.exists(pkl_index_path):
                    logger.error(f"Missing index files in {db_path}, found: {os.listdir(db_path) if os.path.exists(db_path) else 'N/A'}")
                    raise FileNotFoundError(f"FAISS index files not found in {db_path}")
                
                # Khởi tạo embedding model
                logger.info(f"Loading embedding model: {settings.EMBEDDING_MODEL}")
                self._embeddings = HuggingFaceEmbeddings(
                    model_name=settings.EMBEDDING_MODEL,
                    model_kwargs={"device": "cuda" if torch.cuda.is_available() else "cpu"}
                )
                
                # Tải vector store với xử lý lỗi cụ thể hơn
                logger.info(f"Loading vector store from {db_path}")
                try:
                    # Sửa lại cách load FAISS index
                    self._db = FAISS.load_local(
                        db_path, 
                        self._embeddings
                    )
                    
                    # Kiểm tra xem vector store có dữ liệu không
                    if hasattr(self, '_db') and self._db is not None:
                        logger.info(f"Vector store loaded successfully with {self._db.index.ntotal} vectors")
                    else:
                        logger.warning("Vector store loaded but may be empty or incorrectly structured")
                except Exception as e:
                    logger.error(f"Error loading vector store: {str(e)}")
                    self._db = None
                
                # Cấ hình retriever
                logger.info("Configuring retriever")
                self._retriever = self._db.as_retriever(
                    search_type="similarity",
                    search_kwargs={"k": 4}
                )
                logger.info("Retriever configured successfully")
                
                # Thử test retriever với query đơn giản
                try:
                    test_query = "test query"
                    test_docs = self._retriever.get_relevant_documents(test_query, k=1)
                    logger.info(f"Retriever test successful, found {len(test_docs)} document(s)")
                except Exception as ret_test_err:
                    logger.error(f"Error testing retriever: {str(ret_test_err)}")
                    # Không raise ở đây, chỉ log lỗi để tiếp tục
                
            except Exception as emb_err:
                logger.error(f"Error loading embeddings/vectorstore: {str(emb_err)}")
                logger.error(f"Stacktrace: {traceback.format_exc()}")
                raise RuntimeError(f"Failed to initialize vector database: {str(emb_err)}")
            
            # Đánh dấu là đã khởi tạo xong
            self._initialized = True
            logger.info("ChatbotService components initialized successfully")
            
        except Exception as e:
            logger.error(f"Error initializing ChatbotService components: {str(e)}")
            logger.error(traceback.format_exc())
            raise
    
    def _clean_gpu_memory(self):
        """Giải phóng bộ nhớ GPU triệt để"""
        if torch.cuda.is_available():
            try:
                # Giải phóng embeddings nếu có
                if hasattr(self, '_embeddings') and self._embeddings is not None:
                    del self._embeddings
                    self._embeddings = None
                
                # Giải phóng vector store nếu có
                if hasattr(self, '_db') and self._db is not None:
                    del self._db
                    self._db = None
                
                # Giải phóng retriever nếu có
                if hasattr(self, '_retriever') and self._retriever is not None:
                    del self._retriever
                    self._retriever = None
                
                # Giải phóng bộ nhớ GPU
                torch.cuda.empty_cache()
                gc.collect()
                
                logger.info("GPU memory cleaned successfully")
            except Exception as e:
                logger.error(f"Error cleaning GPU memory: {str(e)}")
    
    async def get_answer(self, query: str) -> Dict[str, Any]:
        """Get answer from chatbot"""
        try:
            # Đảm bảo components đã được khởi tạo
            if not self._initialized:
                self._initialize_components()
            
            # Tìm kiếm ngữ cảnh từ vector database
            context = ""
            relevant_docs = []
            if self._retriever is not None:
                try:
                    # Lấy top 3 documents liên quan
                    docs = self._retriever.get_relevant_documents(query, k=3)
                    if docs:
                        context = "\n\n".join([doc.page_content for doc in docs])
                        relevant_docs = docs
                        logger.info(f"Found {len(docs)} relevant documents from vector database")
                        
                        # Debug: Hiển thị thông tin các documents tìm được
                        for i, doc in enumerate(docs):
                            logger.info(f"Document {i+1} preview: {doc.page_content[:100]}...")
                    else:
                        logger.info("No relevant documents found in vector database")
                        return {
                            "query": query,
                            "response": "Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu. Vui lòng thử lại với câu hỏi khác.",
                            "model_info": {
                                "context_used": False,
                                "no_relevant_info": True,
                                "documents_found": 0
                            }
                        }
                except Exception as ret_err:
                    logger.error(f"Error retrieving context: {str(ret_err)}")
                    context = ""
                
                # Tạo prompt với ngữ cảnh kèm yêu cầu trích dẫn rõ ràng
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
            
            start_time = time.time()
            answer = ""
            model_used = ""
            
            # Sử dụng API dựa trên cấu hình
            if settings.API_TYPE.lower() == "google" and self._gemini_model and self._gemini_chat:
                try:
                    # Gửi prompt đến Gemini API
                    response = self._gemini_chat.send_message(prompt)
                    answer = response.text
                    model_used = settings.GOOGLE_MODEL
                    logger.info("Using Gemini API for response generation")
                except Exception as api_err:
                    logger.error(f"Error getting answer from Gemini API: {str(api_err)}")
                    logger.error(traceback.format_exc())
                    raise RuntimeError(f"Failed to get response from Gemini API: {str(api_err)}")
                    
            elif settings.API_TYPE.lower() == "openai" and self._openai_client:
                try:
                    # Gửi prompt đến OpenAI API
                    messages = [
                        {"role": "system", "content": "Bạn là trợ lý AI có kiến thức chuyên sâu về kinh tế Việt Nam. Nhiệm vụ của bạn là trả lời câu hỏi dựa trên dữ liệu được cung cấp, không sử dụng thông tin bên ngoài."},
                        {"role": "user", "content": prompt}
                    ]
                    
                    response = self._openai_client.chat.completions.create(
                        model=settings.OPENAI_MODEL,
                        messages=messages,
                        temperature=settings.TEMPERATURE,
                        max_tokens=settings.MAX_TOKENS
                    )
                    
                    answer = response.choices[0].message.content
                    model_used = settings.OPENAI_MODEL
                    logger.info("Using OpenAI API for response generation")
                except Exception as api_err:
                    logger.error(f"Error getting answer from OpenAI API: {str(api_err)}")
                    logger.error(traceback.format_exc())
                    raise RuntimeError(f"Failed to get response from OpenAI API: {str(api_err)}")
            else:
                logger.error("No valid API available for response generation")
                raise RuntimeError("No valid API configuration found. Please check your API settings.")
                
            # Đảm bảo câu trả lời bắt đầu với "Theo dữ liệu tìm được: "
            if answer and not answer.startswith("Theo dữ liệu tìm được:"):
                answer = "Theo dữ liệu tìm được: " + answer
            
            elapsed_time = time.time() - start_time
            logger.info(f"API response received in {elapsed_time:.2f} seconds")
            
            # Trả về kết quả
            return {
                "query": query,
                "response": answer,
                "model_info": {
                    "model": model_used,
                    "context_used": True if context else False,
                    "documents_found": len(relevant_docs),
                    "response_time": f"{elapsed_time:.2f} seconds"
                }
            }
                
        except Exception as e:
            logger.error(f"Error in get_answer: {str(e)}")
            logger.error(traceback.format_exc())
            return {
                "query": query,
                "response": "Xin lỗi, có lỗi xảy ra khi xử lý câu hỏi của bạn. Vui lòng thử lại sau.",
                "model_info": {
                    "error": str(e)
                }
            }
    
    def restart(self):
        """Restart all components"""
        logger.info("Restarting ChatbotService components...")
        try:
            # Giải phóng bộ nhớ
            self._clean_gpu_memory()
            
            # Reset initialized flag
            self._initialized = False
            
            # Khởi tạo lại components
            self._initialize_components()
            
            return True
        except Exception as e:
            logger.error(f"Error restarting components: {str(e)}")
            logger.error(traceback.format_exc())
            return False 