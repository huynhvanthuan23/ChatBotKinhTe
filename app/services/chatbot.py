from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
from langchain.chains import RetrievalQA
from langchain.prompts import PromptTemplate
from langchain_community.llms import LlamaCpp
from typing import Dict, Any, Optional
from app.core.config import settings
from app.core.logger import get_logger
import os

logger = get_logger(__name__)

class ChatbotService:
    _instance = None
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(ChatbotService, cls).__new__(cls)
            cls._instance._initialized = False
            # Khởi tạo ngay lập tức để luôn sẵn sàng
            cls._instance._initialize_components()
        return cls._instance
    
    def __init__(self):
        # Đã được khởi tạo trong __new__
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
Bạn là trợ lý AI hữu ích. Hãy trả lời câu hỏi dựa vào thông tin được cung cấp. Nếu không biết câu trả lời, hãy nói 'Tôi không tìm thấy thông tin liên quan'. Trả lời phải rõ ràng, ngắn gọn và dễ hiểu.
<</SYS>>

Context: {context}
Question: {question} [/INST]"""
            
            self._prompt = PromptTemplate(template=custom_prompt, input_variables=["context", "question"])
            
            # Tải GGUF model với GPU
            logger.info(f"Loading GGUF model from {settings.MODEL_PATH} with n_gpu_layers={settings.N_GPU_LAYERS}")
            try:
                # Trước tiên thử tải với GPU
                self._llm = LlamaCpp(
                    model_path=settings.MODEL_PATH,
                    temperature=settings.TEMPERATURE,
                    max_tokens=settings.MAX_TOKENS,
                    n_ctx=settings.N_CTX,
                    n_gpu_layers=int(settings.N_GPU_LAYERS),  # Chuyển đổi sang int
                    n_batch=512,
                    f16_kv=True,
                    verbose=True,
                    n_threads=4,
                    use_mlock=True,
                    seed=42,
                    last_n_tokens_size=64,
                    repeat_penalty=1.1,
                    rope_freq_scale=0.5,
                    rope_freq_base=10000
                )
                logger.info(f"Successfully loaded LLM model from {settings.MODEL_PATH} with GPU acceleration")
            except Exception as gpu_error:
                # Nếu không thành công với GPU, thử tải với CPU
                logger.warning(f"Failed to load model with GPU support: {str(gpu_error)}. Falling back to CPU.")
                try:
                    self._llm = LlamaCpp(
                        model_path=settings.MODEL_PATH,
                        temperature=settings.TEMPERATURE,
                        max_tokens=settings.MAX_TOKENS,
                        n_ctx=settings.N_CTX,
                        n_gpu_layers=0,  # Sử dụng CPU
                        n_batch=512,
                        verbose=True,
                        n_threads=4,
                        use_mlock=True,
                        seed=42
                    )
                    logger.info(f"Loaded LLM model from {settings.MODEL_PATH} using CPU only")
                except Exception as cpu_error:
                    logger.error(f"Failed to load model with CPU: {str(cpu_error)}")
                    raise
            
            # Tạo QA chain
            self._qa_chain = RetrievalQA.from_chain_type(
                llm=self._llm,
                chain_type="stuff",
                retriever=self._db.as_retriever(),
                chain_type_kwargs={"prompt": self._prompt}
            )
            
            self._initialized = True
            logger.info("ChatbotService components initialized successfully")
            
        except Exception as e:
            logger.error(f"Error initializing ChatbotService components: {str(e)}")
            raise
    
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
            
            # Wrap in try-except để không trả về lỗi khi xử lý
            try:
                # Đặt timeout để không bị treo
                result = self._qa_chain({"query": query})
                answer_text = result.get('result', '')
                
                # Nếu câu trả lời rỗng, thử một lần nữa với prompt đơn giản hơn
                if not answer_text or answer_text.strip() == "":
                    logger.warning("Empty answer received from QA chain, trying with direct LLM call")
                    # Gọi trực tiếp LLM với prompt đơn giản
                    direct_prompt = f"Question: {query}\nAnswer:"
                    answer_text = self._llm(direct_prompt)
                
                logger.info(f"Generated answer: {answer_text[:100]}...")
                
                # Kiểm tra và đảm bảo câu trả lời không rỗng
                if not answer_text or answer_text.strip() == "":
                    answer_text = "Tôi không tìm thấy thông tin liên quan đến câu hỏi của bạn."
                
                logger.info("Answer generated successfully")
                return {
                    "answer": answer_text,
                    "query": query
                }
            except Exception as chain_error:
                logger.error(f"Error in QA chain: {str(chain_error)}")
                # Fallback to direct LLM call if chain fails
                try:
                    direct_prompt = f"Trả lời câu hỏi sau đây ngắn gọn và rõ ràng: {query}"
                    answer_text = self._llm(direct_prompt)
                    return {
                        "answer": answer_text,
                        "query": query
                    }
                except Exception as llm_error:
                    logger.error(f"Error in direct LLM call: {str(llm_error)}")
                    raise
        except Exception as e:
            logger.error(f"Error generating answer: {str(e)}")
            return {
                "answer": f"Xin lỗi, đã xảy ra lỗi khi xử lý câu hỏi của bạn: {str(e)}",
                "query": query
            } 