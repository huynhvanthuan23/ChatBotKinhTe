from langchain_community.document_loaders import DirectoryLoader
from langchain.text_splitter import CharacterTextSplitter
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
import nltk
import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Tải các tài nguyên NLTK cần thiết
print("Đang tải các tài nguyên NLTK...")
nltk.download('punkt')
nltk.download('averaged_perceptron_tagger')
nltk.download('maxent_ne_chunker')
nltk.download('words')
print("Đã tải xong các tài nguyên NLTK!")

# Cấu hình
DATA_PATH = "data"
subfolders = ["Bao_moi", "thoi_bao_tai_chinh", "vneconomy", "Vnexpress"]

# Tạo danh sách tất cả các đường dẫn đến các thư mục con
all_paths = []
for subfolder in subfolders:
    subfolder_path = f"{DATA_PATH}/{subfolder}"
    all_paths.append(subfolder_path)

# Lấy DB_FAISS_PATH từ biến môi trường hoặc sử dụng giá trị mặc định
DB_FAISS_PATH = os.getenv("DB_FAISS_PATH", "vector_db")

# 1. Load documents từ từng thư mục con
print("Đang tải documents...")
all_documents = []
for path in all_paths:
    try:
        loader = DirectoryLoader(path, glob="*.txt")
        documents = loader.load()
        all_documents.extend(documents)
        print(f"Đã tải {len(documents)} documents từ {path}")
    except Exception as e:
        print(f"Lỗi khi tải documents từ {path}: {e}")

print(f"Tổng cộng đã tải được {len(all_documents)} documents")

# 2. Chunk documents
print("Đang chia nhỏ documents...")
text_splitter = CharacterTextSplitter(chunk_size=1000, chunk_overlap=100)
texts = text_splitter.split_documents(all_documents)
print(f"Đã chia thành {len(texts)} chunks")

# 3. Tạo embeddings và vector store
print("Đang tạo embeddings và vector store...")
embedding_model = os.getenv("EMBEDDING_MODEL", "sentence-transformers/all-MiniLM-L6-v2")
embeddings = HuggingFaceEmbeddings(model_name=embedding_model)
db = FAISS.from_documents(texts, embeddings)

# 4. Lưu vector store
print(f"Đang lưu vector store vào {DB_FAISS_PATH}...")
db.save_local(DB_FAISS_PATH)
print("Đã tạo và lưu vector store thành công!")