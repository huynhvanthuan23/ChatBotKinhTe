from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
from langchain.chains import RetrievalQA
from langchain.prompts import PromptTemplate
from langchain_community.llms import LlamaCpp
from typing import Dict, Any, Optional
from app.core.config import settings
from app.core.logger import get_logger
import os
import time
import torch
import gc

# Cấu hình logging
logger = get_logger(__name__)

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
            
            # Tạo prompt template
            custom_prompt = """[INST] <<SYS>>
Bạn là trợ lý AI hữu ích. Hãy trả lời câu hỏi dựa vào thông tin được cung cấp.
Nếu không biết câu trả lời, hãy nói 'Tôi không tìm thấy thông tin liên quan'.
Trả lời phải rõ ràng, chi tiết và dễ hiểu.
<</SYS>>

Context: {context}
Question: {question} [/INST]"""
        
            self._prompt = PromptTemplate(template=custom_prompt, input_variables=["context", "question"])
            
            # Tải GGUF model với GPU
            logger.info(f"Loading GGUF model with n_gpu_layers={settings.N_GPU_LAYERS}")
            
            try:
                # Cấu hình LLM theo kết quả test tốt nhất
                self._llm = LlamaCpp(
                    model_path=settings.MODEL_PATH,
                    temperature=settings.TEMPERATURE,
                    max_tokens=settings.MAX_TOKENS,
                    n_ctx=settings.N_CTX,
                    n_gpu_layers=settings.N_GPU_LAYERS,
                    n_batch=settings.N_BATCH,
                    f16_kv=settings.F16_KV,
                    use_mlock=settings.USE_MLOCK,
                    use_mmap=settings.USE_MMAP,
                    verbose=True,
                    seed=42
                )
                
                # Kiểm tra thời gian để xác định GPU hoạt động
                test_prompt = "Cho tôi biết 2+2 bằng bao nhiêu."
                start_time = time.time()
                test_result = self._llm(test_prompt)
                end_time = time.time()
                
                inference_time = end_time - start_time
                logger.info(f"Test inference took {inference_time:.4f} seconds")
                
                # Xác nhận GPU hoạt động
                if inference_time < 1.0:
                    logger.info("✓ GPU acceleration is ACTIVE! (fast inference time)")
                else:
                    logger.warning(f"⚠ GPU acceleration may NOT be active (slow inference: {inference_time:.4f}s)")
                
                logger.info(f"Successfully loaded LLM model")
                
            except Exception as e:
                logger.error(f"Failed to load model: {str(e)}")
                raise
            
            # Cấu hình retriever
            self._retriever = self._db.as_retriever(search_kwargs={"k": 4})
            
            # Đơn giản hóa: KHÔNG dùng QA chain
            
            self._initialized = True
            logger.info("ChatbotService components initialized successfully")
            
        except Exception as e:
            logger.error(f"Error initializing ChatbotService components: {str(e)}")
            raise
    
    def _determine_optimal_gpu_layers(self):
        """Xác định số lượng GPU layers tối ưu dựa trên GPU hiện có"""
        try:
            if not torch.cuda.is_available():
                logger.warning("CUDA not available. Using CPU only.")
                return 0
                
            # Lấy thông tin GPU
            total_memory = torch.cuda.get_device_properties(0).total_memory / 1024**2  # MB
            
            logger.info(f"Total GPU memory: {total_memory:.2f} MB")
            
            # Tính toán số layer phù hợp theo dung lượng GPU
            # Giả sử mỗi layer cần khoảng 150MB VRAM
            estimated_layer_size = 150  # MB per layer
            
            # Để lại 500MB cho các hoạt động khác
            usable_memory = max(0, total_memory - 500)
            
            # Mỗi layer khoảng 150MB
            max_layers = int(usable_memory / estimated_layer_size)
            
            # Giới hạn số layer tối đa là 32 (đủ cho hầu hết models)
            n_gpu_layers = min(max_layers, 32)
            
            # Đảm bảo ít nhất 1 layer nếu có GPU
            n_gpu_layers = max(1, n_gpu_layers)
            
            # Sử dụng giá trị từ .env nếu được chỉ định
            env_layers = int(settings.N_GPU_LAYERS)
            if env_layers > 0:
                logger.info(f"Using GPU layers from .env: {env_layers}")
                return env_layers
                
            logger.info(f"Determined optimal GPU layers: {n_gpu_layers}")
            return n_gpu_layers
            
        except Exception as e:
            logger.error(f"Error determining GPU layers: {str(e)}, falling back to 1")
            return 1
    
    def _clean_gpu_memory(self):
        """Giải phóng bộ nhớ GPU"""
        try:
            if torch.cuda.is_available():
                # Giải phóng bộ nhớ cached
                torch.cuda.empty_cache()
                # Collect garbage
                gc.collect()
                logger.info("Cleaned GPU memory")
        except Exception as e:
            logger.error(f"Error cleaning GPU memory: {str(e)}")
    
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
            
            # Lưu thời điểm bắt đầu xử lý
            start_time = time.time()
            
            # PHƯƠNG PHÁP ĐƠN GIẢN NHẤT
            try:
                # 1. Tìm documents liên quan
                docs = self._retriever.get_relevant_documents(query)
                
                # 2. Tạo context
                context = "\n\n".join([doc.page_content for doc in docs])
                
                # 3. Tạo prompt hoàn chỉnh
                full_prompt = self._prompt.format(context=context, question=query)
                
                # 4. Gọi LLM trực tiếp (một lần duy nhất)
                answer_text = self._llm(full_prompt)
                
                # 5. Làm sạch câu trả lời
                answer_text = answer_text.replace("<</SYS>>", "").replace("<<SYS>>", "")
                answer_text = answer_text.strip()
                
                # 6. Tính thời gian xử lý
                processing_time = time.time() - start_time
                logger.info(f"Generated answer in {processing_time:.4f} seconds")
                
                return {
                    "answer": answer_text,
                    "query": query
                }
                
            except Exception as process_error:
                logger.error(f"Error processing query: {str(process_error)}")
                
                # Fallback đơn giản
                fallback_prompt = f"[INST] Trả lời ngắn gọn: {query} [/INST]"
                fallback_answer = self._llm(fallback_prompt)
                return {
                    "answer": fallback_answer,
                    "query": query
                }
                
        except Exception as e:
            logger.error(f"Error generating answer: {str(e)}")
            return {
                "answer": f"Xin lỗi, đã xảy ra lỗi khi xử lý câu hỏi của bạn.",
                "query": query
            }