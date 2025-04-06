from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
from langchain.chains import RetrievalQA
from langchain.prompts import PromptTemplate
from langchain_community.llms import LlamaCpp
from typing import Dict, Any, Optional, List
from app.core.config import settings
from app.core.logger import get_logger
import os
import time
import ctypes
import torch
import gc

# Cấu hình logging
logger = get_logger(__name__)

# Kiểm tra CUDA được cài đặt hay chưa
def check_cuda():
    try:
        if os.name == 'nt':  # Windows
            cuda = ctypes.windll.LoadLibrary('cudart64_11.dll')
        else:  # Linux/Mac
            cuda = ctypes.cdll.LoadLibrary('libcudart.so')
        
        device_count = ctypes.c_int()
        cuda.cudaGetDeviceCount(ctypes.byref(device_count))
        return device_count.value > 0
    except Exception as e:
        logger.error(f"Error checking CUDA: {e}")
        return False

class ChatbotService:
    _instance = None
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(ChatbotService, cls).__new__(cls)
            cls._instance._initialized = False
            # Không khởi tạo ngay, để được gọi từ main.py trong cách có kiểm soát
        return cls._instance
    
    def __init__(self):
        # Các thuộc tính sẽ được khởi tạo trong _initialize_components
        pass
    
    def _initialize_components(self):
        """Initialize components at startup"""
        if self._initialized:
            return
            
        logger.info("Initializing ChatbotService components...")
        
        try:
            # Kiểm tra paths
            if not os.path.exists(settings.DB_FAISS_PATH):
                raise FileNotFoundError(f"Vector database not found: {settings.DB_FAISS_PATH}")
                
            if not os.path.exists(settings.MODEL_PATH):
                raise FileNotFoundError(f"LLM model not found: {settings.MODEL_PATH}")
            
            # Kiểm tra CUDA
            has_cuda = check_cuda()
            if has_cuda:
                logger.info("CUDA is available. GPU acceleration enabled.")
            else:
                logger.warning("CUDA is not available. Running on CPU only.")
            
            # Khởi tạo embedding model
            self._embeddings = HuggingFaceEmbeddings(model_name=settings.EMBEDDING_MODEL)
            logger.info(f"Loaded embedding model: {settings.EMBEDDING_MODEL}")
            
            # Tải vector store
            self._db = FAISS.load_local(
                settings.DB_FAISS_PATH, 
                self._embeddings, 
                allow_dangerous_deserialization=True
            )
            logger.info(f"Loaded vector store from {settings.DB_FAISS_PATH}")
            
            # Tải LlamaCpp nhưng trong một process riêng biệt để tránh xung đột GPU/CPU
            self._load_llama_cpp_model()
            
            self._initialized = True
            logger.info("ChatbotService components initialized successfully")
            
        except Exception as e:
            logger.error(f"Error initializing ChatbotService components: {str(e)}")
            raise
    
    def _load_llama_cpp_model(self):
        """Tải LlamaCpp model với CUDA"""
        try:
            # Import LlamaCpp
            from langchain_community.llms import LlamaCpp
            
            # Cấu hình tối ưu cho GPU
            n_gpu_layers = int(settings.N_GPU_LAYERS)
            
            # Đảm bảo số lượng GPU layers hợp lý
            if n_gpu_layers <= 0:
                n_gpu_layers = 100  # Sử dụng tất cả layers có thể
            
            logger.info(f"Loading GGUF model from {settings.MODEL_PATH} with n_gpu_layers={n_gpu_layers}")
            
            # Khởi tạo LlamaCpp với cấu hình rõ ràng cho GPU
            self._llm = LlamaCpp(
                model_path=settings.MODEL_PATH,
                temperature=0.1,
                max_tokens=512,
                n_ctx=2048,
                n_gpu_layers=n_gpu_layers,
                n_batch=512,
                f16_kv=True,
                verbose=True,
                use_mmap=False,  # Tắt mmap để đảm bảo tải vào GPU
                use_mlock=False  # Tắt mlock cũng vậy
            )
            
            # Test LLM
            self._test_llm_performance()
            
        except Exception as e:
            logger.error(f"Error loading LlamaCpp model: {e}")
            raise
    
    def _test_llm_performance(self):
        """Kiểm tra hiệu suất LLM để xác định sử dụng GPU hay CPU"""
        try:
            # Prompt test đơn giản
            test_prompt = "Hello, how are you?"
            
            # Thực hiện warm-up
            logger.info("Warming up LLM...")
            self._llm(test_prompt)
            
            # Test thực sự
            logger.info("Testing LLM performance...")
            start_time = time.time()
            test_result = self._llm(test_prompt)
            end_time = time.time()
            
            inference_time = end_time - start_time
            logger.info(f"LLM test inference took {inference_time:.4f} seconds")
            
            # Đánh giá dựa trên thời gian
            if inference_time < 1.0:
                logger.info("GPU ACCELERATION IS ACTIVE (fast inference time)")
            else:
                logger.warning(f"LIKELY RUNNING ON CPU (slow inference time: {inference_time:.4f}s)")
                
            logger.info(f"LLM test result: {test_result[:50]}...")
            
        except Exception as e:
            logger.error(f"Error testing LLM performance: {e}")
    
    async def get_answer(self, query: str) -> Dict[str, Any]:
        """
        Trả lời câu hỏi từ người dùng.
        
        Args:
            query: Câu hỏi của người dùng
            
        Returns:
            Dict chứa kết quả trả lời
        """
        logger.info(f"Processing query: {query}")
        try:
            # Lazy loading if needed
            if not self._initialized:
                self._initialize_components()
            
            # Đo thời gian xử lý
            start_time = time.time()
            
            # Xử lý trực tiếp
            try:
                # 1. Tìm documents liên quan
                docs = self._db.similarity_search(query, k=4)
                
                # 2. Format context
                context = self._format_context_from_docs(docs)
                
                # 3. Tạo prompt hoàn chỉnh
                full_prompt = self._create_prompt(query, context)
                
                # 4. Gọi LLM
                answer_text = self._llm(full_prompt)
                
                # 5. Làm sạch câu trả lời
                answer_text = self._clean_response(answer_text)
                
                # Đo thời gian xử lý
                processing_time = time.time() - start_time
                logger.info(f"Generated answer in {processing_time:.4f} seconds")
                
                return {
                    "answer": answer_text,
                    "query": query
                }
                
            except Exception as process_error:
                logger.error(f"Error processing query: {str(process_error)}")
                
                # Thử với prompt đơn giản
                try:
                    fallback_prompt = f"[INST] Trả lời ngắn gọn: {query} [/INST]"
                    fallback_answer = self._llm(fallback_prompt)
                    
                    return {
                        "answer": fallback_answer,
                        "query": query
                    }
                except Exception as fallback_error:
                    logger.error(f"Fallback error: {str(fallback_error)}")
                    raise
                
        except Exception as e:
            logger.error(f"Error generating answer: {str(e)}")
            return {
                "answer": f"Xin lỗi, đã xảy ra lỗi khi xử lý câu hỏi của bạn.",
                "query": query
            }
    
    def _format_context_from_docs(self, docs: List) -> str:
        """Format context từ danh sách documents"""
        formatted_docs = []
        for i, doc in enumerate(docs, 1):
            content = doc.page_content.strip()
            source = doc.metadata.get('source', 'Không rõ nguồn') if hasattr(doc, 'metadata') else 'Không rõ nguồn'
            formatted_docs.append(f"[Nguồn {i}] ({source}):\n{content}")
        
        return "\n\n".join(formatted_docs)
    
    def _create_prompt(self, query: str, context: str) -> str:
        """Tạo prompt hoàn chỉnh"""
        return f"""[INST] <<SYS>>
Bạn là trợ lý AI hữu ích. Hãy trả lời câu hỏi dựa vào thông tin được cung cấp.
Nếu không biết câu trả lời, hãy nói 'Tôi không tìm thấy thông tin liên quan'.
Trả lời phải rõ ràng, chi tiết và dễ hiểu.
<</SYS>>

Context:
{context}

Question: {query} [/INST]"""
    
    def _clean_response(self, response: str) -> str:
        """Làm sạch câu trả lời từ model"""
        # Loại bỏ các tag hệ thống
        response = response.replace("<<SYS>>", "").replace("<</SYS>>", "")
        response = response.replace("[INST]", "").replace("[/INST]", "")
        
        # Loại bỏ dòng trống
        response = response.strip()
        
        return response