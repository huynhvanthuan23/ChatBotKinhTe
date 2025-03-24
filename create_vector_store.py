from langchain_community.document_loaders import DirectoryLoader
from langchain.text_splitter import CharacterTextSplitter
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS

# Cấu hình
DATA_PATH = "data"
DB_FAISS_PATH = "vector_db"

# 1. Load documents
loader = DirectoryLoader(DATA_PATH, glob="*.txt")
documents = loader.load()

# 2. Chunk documents
text_splitter = CharacterTextSplitter(chunk_size=30, chunk_overlap=5)
texts = text_splitter.split_documents(documents)

# 3. Tạo embeddings và vector store
embeddings = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
db = FAISS.from_documents(texts, embeddings)

# 4. Lưu vector store
db.save_local(DB_FAISS_PATH)
print("Đã tạo và lưu vector store thành công!")