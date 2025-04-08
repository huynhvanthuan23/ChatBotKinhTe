from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
from langchain.prompts import PromptTemplate
from langchain_community.llms import LlamaCpp
from typing import Dict, Any, Optional, Tuple
from app.core.config import settings
from app.core.logger import get_logger
import os
import time
import torch
import gc
import traceback
import google.generativeai as genai

# Cấu hình logging
logger = get_logger(__name__)

class ChatbotService:
    _instance = None
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(ChatbotService, cls).__new__(cls)
            cls._instance._initialized = False
            cls._instance._llm = None
            cls._instance._retry_count = 0
            cls._instance._gemini_model = None
            cls._instance._gemini_chat = None
        return cls._instance
    
    def __init__(self):
        # Các thuộc tính sẽ được khởi tạo trong _initialize_components
        pass
    
    def _initialize_components(self):
        """Initialize components at startup"""
        # Reset initialized flag to force reloading components
        self._initialized = False
        
        # Reset các thành phần để tránh sử dụng cache
        self._llm = None
        self._embeddings = None
        self._db = None
        self._retriever = None
        self._gemini_model = None
        self._gemini_chat = None
            
        logger.info("Initializing ChatbotService components...")
        
        try:
            # Đầu tiên, giải phóng bộ nhớ GPU
            self._clean_gpu_memory()
            
            # Khởi tạo Gemini API nếu có API key
            if settings.USE_API and settings.GOOGLE_API_KEY:
                try:
                    logger.info("Initializing Gemini API...")
                    
                    # Cấu hình API
                    genai.configure(api_key=settings.GOOGLE_API_KEY)
                    
                    # Sử dụng model gemini-1.5-pro thay vì gemini-2.0-flash
                    self._gemini_model = genai.GenerativeModel('gemini-1.5-pro')
                    self._gemini_chat = self._gemini_model.start_chat(history=[])
                    logger.info("Gemini API initialized successfully with model gemini-1.5-pro")
                        
                except Exception as api_err:
                    logger.error(f"Error initializing Gemini API: {str(api_err)}")
                    raise RuntimeError("Failed to initialize Gemini API. Please check your API key and internet connection.")
            
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
                    if hasattr(self._db, 'index') and hasattr(self._db.index, 'ntotal'):
                        logger.info(f"Vector store loaded successfully with {self._db.index.ntotal} vectors")
                    else:
                        logger.warning("Vector store loaded but may be empty or incorrectly structured")
                except Exception as e:
                    logger.error(f"Error loading vector store: {str(e)}")
                    self._db = None
                
                # Cấu hình retriever
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
                # Giải phóng model hiện tại nếu có
                if hasattr(self, '_llm') and self._llm is not None:
                    del self._llm
                    self._llm = None
                
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
                
                # Giải phóng Gemini chat nếu có
                if hasattr(self, '_gemini_chat') and self._gemini_chat is not None:
                    del self._gemini_chat
                    self._gemini_chat = None
                
                # Giải phóng Gemini model nếu có
                if hasattr(self, '_gemini_model') and self._gemini_model is not None:
                    del self._gemini_model
                    self._gemini_model = None
                
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
            
            # Sử dụng Gemini API
            if not self._gemini_model or not self._gemini_chat:
                raise RuntimeError("Gemini API is not initialized. Please check your API key and internet connection.")
            
            try:
                start_time = time.time()
                
                # Đảm bảo luôn có một phần của context trong response
                if relevant_docs:
                    first_doc = relevant_docs[0].page_content
                    key_info = first_doc[:100]  # Trích xuất 100 ký tự đầu tiên
                    logger.info(f"Key information to force in response: {key_info}")
                else:
                    key_info = ""
                
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
                # Gửi prompt tới Gemini API
                response = self._gemini_chat.send_message(prompt)
                response_time = time.time() - start_time
                
                # Xử lý response
                if response and response.text:
                    return {
                        "query": query,
                        "response": response.text,
                        "model_info": {
                            "model": "gemini-1.5-pro",
                            "response_time": response_time,
                            "context_used": bool(context),
                            "documents_found": len(relevant_docs)
                        }
                    }
                else:
                    raise RuntimeError("Empty response from Gemini API")
                    
            except Exception as api_err:
                logger.error(f"Error getting response from Gemini API: {str(api_err)}")
                raise RuntimeError(f"Failed to get response from Gemini API: {str(api_err)}")
                
        except Exception as e:
            logger.error(f"Error in get_answer: {str(e)}")
            logger.error(traceback.format_exc())
            raise 