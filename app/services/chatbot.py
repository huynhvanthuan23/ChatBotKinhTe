from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
from langchain.prompts import PromptTemplate
from typing import Dict, Any, Optional, Tuple, List
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
    
    def initialize_vector_db(self):
        """
        Khởi tạo và trả về vector database
        
        Returns:
            Retriever đã được cấu hình hoặc None nếu có lỗi
        """
        try:
            logger.info("Initializing vector database...")
            # Kiểm tra đường dẫn và file tồn tại
            db_path = settings.DB_FAISS_PATH
            
            if not os.path.exists(db_path):
                logger.error(f"Vector database directory not found: {db_path}")
                os.makedirs(db_path, exist_ok=True)
                logger.info(f"Created vector database directory: {db_path}")
                return None
                
            # Kiểm tra các file cần thiết
            if not os.path.exists(os.path.join(db_path, "index.faiss")) or not os.path.exists(os.path.join(db_path, "index.pkl")):
                logger.error(f"Missing vector index files in {db_path}")
                return None
                
            # Tải embedding model
            embedding = HuggingFaceEmbeddings(model_name=settings.EMBEDDING_MODEL)
            
            # Tải vector database
            try:
                logger.info(f"Loading vector database from {db_path}")
                try:
                    db = FAISS.load_local(db_path, embedding, allow_dangerous_deserialization=True)
                except TypeError:
                    db = FAISS.load_local(db_path, embedding)
                    
                # Tạo retriever với MMR (Maximum Marginal Relevance)
                retriever = db.as_retriever(
                    search_type="mmr",
                    search_kwargs={
                        "k": 2,
                        "fetch_k": 5,
                        "lambda_mult": 0.8,
                        "score_threshold": 0.5
                    }
                )
                
                logger.info("Vector database loaded and retriever configured successfully")
                return retriever
                
            except Exception as e:
                logger.error(f"Error loading vector database: {str(e)}")
                logger.error(traceback.format_exc())
                return None
                
        except Exception as e:
            logger.error(f"Error initializing vector database: {str(e)}")
            logger.error(traceback.format_exc())
            return None
    
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

    async def get_simple_retrieval(self, query: str, document_ids: List[int] = None) -> Dict[str, Any]:
        """Tìm kiếm đơn giản dựa trên vector database mà không sử dụng LLM API"""
        try:
            # Đảm bảo components đã được khởi tạo
            if not self._initialized:
                self._initialize_components()
            
            docs = []
            # Nếu có document IDs, ưu tiên tìm kiếm trong các tài liệu đã chọn
            if document_ids:
                for doc_id in document_ids:
                    document_vectors_path = os.path.join(settings.DB_FAISS_PATH, f"doc_{doc_id}")
                    
                    if os.path.exists(document_vectors_path) and os.path.exists(os.path.join(document_vectors_path, "index.faiss")):
                        try:
                            logger.info(f"Tìm kiếm trong tài liệu ID {doc_id}")
                            embedding = HuggingFaceEmbeddings(model_name=settings.EMBEDDING_MODEL)
                            try:
                                doc_db = FAISS.load_local(document_vectors_path, embedding, allow_dangerous_deserialization=True)
                            except TypeError:
                                doc_db = FAISS.load_local(document_vectors_path, embedding)
                                
                            doc_retriever = doc_db.as_retriever(
                                search_type="mmr",
                                search_kwargs={
                                    "k": 2,
                                    "fetch_k": 15,
                                    "lambda_mult": 0.8,
                                    "score_threshold": 0.5
                                }
                            )
                            doc_results = doc_retriever.get_relevant_documents(query)
                            
                            if doc_results:
                                logger.info(f"Tìm thấy {len(doc_results)} kết quả trong tài liệu ID {doc_id}")
                                for doc in doc_results:
                                    if 'document_id' not in doc.metadata:
                                        doc.metadata['document_id'] = doc_id
                                docs.extend(doc_results)
                        except Exception as e:
                            logger.error(f"Lỗi khi tìm kiếm trong tài liệu ID {doc_id}: {e}")
                            logger.error(traceback.format_exc())
            
            # Nếu không tìm thấy kết quả trong các tài liệu đã chọn, hoặc không có tài liệu được chọn
            if not docs:
                if not document_ids and self._retriever:
                    logger.info("Không có tài liệu được chọn, tìm kiếm trong vector database chính")
                    docs = self._retriever.get_relevant_documents(query)
                    logger.info(f"Tìm thấy {len(docs)} tài liệu liên quan cho query: {query}")
            
            # Nếu tìm thấy tài liệu, sử dụng nội dung làm phản hồi
            if docs:
                content = "\n\n".join([doc.page_content for doc in docs])
                
                # Thêm thông tin nguồn tài liệu nếu có
                if document_ids and any('document_id' in doc.metadata for doc in docs):
                    doc_sources = set(doc.metadata.get('document_id') for doc in docs if 'document_id' in doc.metadata)
                    source_info = f"\n\nThông tin từ tài liệu IDs: {', '.join(map(str, doc_sources))}"
                    response = f"Tìm thấy thông tin liên quan trong tài liệu đã chọn:\n\n{content}{source_info}"
                else:
                    response = f"Tìm thấy thông tin liên quan:\n\n{content}"
                    
                return {
                    "success": True,
                    "response": response,
                    "query": query,
                    "doc_count": len(docs)
                }
            else:
                # Không tìm thấy tài liệu
                if document_ids:
                    response = "Không tìm thấy thông tin liên quan đến câu hỏi của bạn trong các tài liệu đã chọn. Vui lòng thử lại với từ khóa khác hoặc chọn tài liệu khác."
                else:
                    response = "Không tìm thấy thông tin liên quan đến câu hỏi của bạn trong cơ sở dữ liệu."
                    
                return {
                    "success": True,
                    "response": response,
                    "query": query,
                    "doc_count": 0
                }
                
        except Exception as e:
            logger.error(f"Lỗi trong get_simple_retrieval: {str(e)}")
            logger.error(traceback.format_exc())
            return {
                "success": False,
                "response": "Xin lỗi, có lỗi xảy ra khi tìm kiếm thông tin. Vui lòng thử lại sau.",
                "query": query,
                "error": str(e)
            }

    async def process_document(self, request) -> Dict[str, Any]:
        """Xử lý tài liệu và tạo vector embeddings"""
        logger.info(f"Xử lý tài liệu ID: {request.document_id}, Đường dẫn: {request.file_path}")
        
        try:
            # Ưu tiên dùng đường dẫn tuyệt đối nếu có
            full_path = getattr(request, 'absolute_path', None)
            
            # Nếu không có đường dẫn tuyệt đối, thì dùng đường dẫn tương đối
            if not full_path or not os.path.exists(full_path):
                full_path = os.path.join(os.getenv("STORAGE_PATH", "storage"), request.file_path)
                logger.info(f"Sử dụng đường dẫn tương đối: {full_path}")
            else:
                logger.info(f"Sử dụng đường dẫn tuyệt đối: {full_path}")
            
            # Kiểm tra file có tồn tại không
            if not os.path.exists(full_path):
                logger.error(f"Không tìm thấy file: {full_path}")
                return {
                    "success": False,
                    "message": "File không tồn tại",
                    "document_id": request.document_id,
                    "error": f"File not found: {full_path}"
                }
            
            # Khởi tạo document loader dựa trên loại file
            logger.info(f"Đang đọc tài liệu: {full_path}")
            loader = None
            
            if request.file_type:
                mime_type = request.file_type
            else:
                # Đoán mime type từ đuôi file
                file_ext = os.path.splitext(full_path)[1].lower()
                if file_ext == '.pdf':
                    mime_type = 'application/pdf'
                elif file_ext in ['.docx', '.doc']:
                    mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                elif file_ext == '.txt':
                    mime_type = 'text/plain'
                elif file_ext == '.md':
                    mime_type = 'text/markdown'
                else:
                    mime_type = 'application/octet-stream'
            
            # Chọn loader phù hợp
            from langchain_community.document_loaders import TextLoader, PyPDFLoader, Docx2txtLoader
            
            if 'pdf' in mime_type:
                logger.info("Sử dụng PyPDFLoader")
                loader = PyPDFLoader(full_path)
            elif 'word' in mime_type or 'docx' in mime_type or 'doc' in mime_type:
                logger.info("Sử dụng Docx2txtLoader")
                loader = Docx2txtLoader(full_path)
            elif 'text' in mime_type or 'markdown' in mime_type or 'plain' in mime_type:
                logger.info("Sử dụng TextLoader")
                loader = TextLoader(full_path)
            else:
                logger.error(f"Loại file không được hỗ trợ: {mime_type}")
                return {
                    "success": False,
                    "message": f"Loại file không được hỗ trợ: {mime_type}",
                    "document_id": request.document_id,
                    "error": f"Unsupported file type: {mime_type}"
                }
            
            # Tải document
            try:
                docs = loader.load()
                logger.info(f"Đọc tài liệu thành công, số trang/đoạn: {len(docs)}")
            except Exception as e:
                logger.error(f"Lỗi khi đọc tài liệu: {e}")
                return {
                    "success": False,
                    "message": "Không thể đọc nội dung file",
                    "document_id": request.document_id,
                    "error": f"Error loading document: {e}"
                }
            
            # Chia nhỏ văn bản
            from langchain.text_splitter import RecursiveCharacterTextSplitter
            
            text_splitter = RecursiveCharacterTextSplitter(
                chunk_size=request.chunk_size,
                chunk_overlap=request.chunk_overlap,
                length_function=len,
            )
            
            chunks = text_splitter.split_documents(docs)
            logger.info(f"Chia tài liệu thành {len(chunks)} đoạn với chunk_size={request.chunk_size}, chunk_overlap={request.chunk_overlap}")
            
            # Tạo embedding
            embedding = HuggingFaceEmbeddings(model_name=settings.EMBEDDING_MODEL)
            
            # Tạo thư mục lưu trữ vector riêng cho document
            document_vectors_path = os.path.join(settings.DB_FAISS_PATH, f"doc_{request.document_id}")
            os.makedirs(document_vectors_path, exist_ok=True)
            
            # Tạo FAISS database từ chunks
            logger.info(f"Đang tạo vector store tại {document_vectors_path}")
            db = FAISS.from_documents(chunks, embedding)
            db.save_local(document_vectors_path)
            
            # Kiểm tra kết quả
            if os.path.exists(os.path.join(document_vectors_path, "index.faiss")) and os.path.exists(os.path.join(document_vectors_path, "index.pkl")):
                logger.info(f"Tạo vector store thành công cho tài liệu {request.document_id}")
                
                # Kiểm tra bằng tìm kiếm thử
                try:
                    loaded_db = FAISS.load_local(document_vectors_path, embedding)
                    query = request.title or "Kinh tế"
                    docs = loaded_db.similarity_search(query, k=1)
                    logger.info(f"Truy vấn thử nghiệm thành công, tìm thấy {len(docs)} kết quả")
                except Exception as e:
                    logger.error(f"Truy vấn thử nghiệm thất bại: {e}")
                
                return {
                    "success": True,
                    "message": "Đã xử lý tài liệu và tạo vector thành công",
                    "document_id": request.document_id
                }
            else:
                logger.error(f"Tạo vector store thất bại cho tài liệu {request.document_id}")
                return {
                    "success": False,
                    "message": "Không thể tạo vector cho tài liệu",
                    "document_id": request.document_id,
                    "error": "Vector store files not found after creation"
                }
                
        except Exception as e:
            logger.error(f"Lỗi xử lý tài liệu: {e}")
            logger.error(traceback.format_exc())
            return {
                "success": False,
                "message": "Lỗi xử lý tài liệu",
                "document_id": request.document_id,
                "error": str(e)
            }

    async def integrate_document(self, document_id: int) -> Dict[str, Any]:
        """Tích hợp vector của tài liệu vào vector database chính"""
        try:
            logger.info(f"Đang tích hợp vector của tài liệu ID: {document_id}")
            
            # Đường dẫn tới vector database của tài liệu
            document_vectors_path = os.path.join(settings.DB_FAISS_PATH, f"doc_{document_id}")
            
            # Kiểm tra có tồn tại không
            if not os.path.exists(document_vectors_path) or not os.path.exists(os.path.join(document_vectors_path, "index.faiss")):
                logger.error(f"Không tìm thấy đường dẫn vector của tài liệu: {document_vectors_path}")
                return {
                    "success": False,
                    "message": f"Không thể tích hợp vector của tài liệu {document_id}",
                    "document_id": document_id,
                    "error": "Vector store not found"
                }
            
            # Tải vector database chính
            embedding = HuggingFaceEmbeddings(model_name=settings.EMBEDDING_MODEL)
            
            # Nếu vector database chính không tồn tại, tạo mới
            main_vector_path = settings.DB_FAISS_PATH
            
            if not os.path.exists(os.path.join(main_vector_path, "index.faiss")):
                logger.info("Không tìm thấy vector database chính, đang tạo mới")
                empty_texts = ["Đây là vector database ban đầu."]
                main_db = FAISS.from_texts(empty_texts, embedding)
                main_db.save_local(main_vector_path)
            
            # Tải vector database chính và vector database của tài liệu
            try:
                main_db = FAISS.load_local(main_vector_path, embedding, allow_dangerous_deserialization=True)
            except TypeError:
                main_db = FAISS.load_local(main_vector_path, embedding)
            
            try:
                doc_db = FAISS.load_local(document_vectors_path, embedding, allow_dangerous_deserialization=True)
            except TypeError:
                doc_db = FAISS.load_local(document_vectors_path, embedding)
            
            # Kết hợp hai vector database
            logger.info("Đang kết hợp vector của tài liệu vào vector database chính")
            main_db.merge_from(doc_db)
            
            # Lưu lại vector database chính
            logger.info("Lưu vector database đã kết hợp")
            main_db.save_local(main_vector_path)
            
            # Khởi động lại retriever để áp dụng thay đổi
            if hasattr(self, '_db') and self._db is not None:
                del self._db
            
            self._db = main_db
            self._retriever = self._db.as_retriever(
                search_type="mmr",
                search_kwargs={
                    "k": 2,
                    "fetch_k": 5,
                    "lambda_mult": 0.8,
                    "score_threshold": 0.5
                }
            )
            
            # Kiểm tra kết quả
            if os.path.exists(os.path.join(main_vector_path, "index.faiss")):
                logger.info(f"Tích hợp vector thành công cho tài liệu {document_id}")
                return {
                    "success": True,
                    "message": f"Đã tích hợp vector của tài liệu {document_id} thành công",
                    "document_id": document_id
                }
            else:
                logger.error(f"Tích hợp vector thất bại cho tài liệu {document_id}")
                return {
                    "success": False,
                    "message": f"Không thể tích hợp vector của tài liệu {document_id}",
                    "document_id": document_id,
                    "error": "Integration failed"
                }
                
        except Exception as e:
            logger.error(f"Lỗi khi tích hợp vector của tài liệu: {e}")
            logger.error(traceback.format_exc())
            return {
                "success": False,
                "message": "Lỗi khi tích hợp vector",
                "document_id": document_id,
                "error": str(e)
            } 
    
    def integrate_document_vectors(self, document_id: int) -> bool:
        """
        Tích hợp vector của một tài liệu vào vector database chính
        
        Args:
            document_id: ID của tài liệu cần tích hợp
            
        Returns:
            Boolean: True nếu thành công, False nếu thất bại
        """
        try:
            logger.info(f"Integrating vectors from document {document_id} into main vector database")
            
            # Đường dẫn tới vector database của tài liệu
            document_vectors_path = os.path.join(settings.DB_FAISS_PATH, f"doc_{document_id}")
            
            # Kiểm tra xem vector database của tài liệu có tồn tại không
            if not os.path.exists(document_vectors_path) or not os.path.exists(os.path.join(document_vectors_path, "index.faiss")):
                logger.error(f"Vector database for document {document_id} not found at {document_vectors_path}")
                return False
            
            # Đường dẫn tới vector database chính
            main_vector_path = settings.DB_FAISS_PATH
            
            # Khởi tạo embedding model
            embedding = HuggingFaceEmbeddings(model_name=settings.EMBEDDING_MODEL)
            
            # Nếu vector database chính không tồn tại, tạo mới
            if not os.path.exists(os.path.join(main_vector_path, "index.faiss")):
                logger.info("Main vector database not found, creating a new one")
                empty_texts = ["This is the initial vector database."]
                main_db = FAISS.from_texts(empty_texts, embedding)
                main_db.save_local(main_vector_path)
            
            # Tải vector database chính
            try:
                main_db = FAISS.load_local(main_vector_path, embedding, allow_dangerous_deserialization=True)
            except TypeError:
                main_db = FAISS.load_local(main_vector_path, embedding)
            
            # Tải vector database của tài liệu
            try:
                doc_db = FAISS.load_local(document_vectors_path, embedding, allow_dangerous_deserialization=True)
            except TypeError:
                doc_db = FAISS.load_local(document_vectors_path, embedding)
            
            # Kết hợp hai vector database
            logger.info(f"Merging document {document_id} vectors into main vector database")
            main_db.merge_from(doc_db)
            
            # Lưu vector database chính
            logger.info("Saving merged vector database")
            main_db.save_local(main_vector_path)
            
            # Kiểm tra kết quả
            if os.path.exists(os.path.join(main_vector_path, "index.faiss")):
                logger.info(f"Successfully integrated vectors from document {document_id}")
                return True
            else:
                logger.error(f"Failed to integrate vectors from document {document_id}")
                return False
            
        except Exception as e:
            logger.error(f"Error integrating document vectors: {str(e)}")
            logger.error(traceback.format_exc())
            return False 