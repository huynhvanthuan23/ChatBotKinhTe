import os
import argparse
import glob
import re
import json
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain_community.document_loaders import TextLoader, PyPDFLoader, Docx2txtLoader
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_openai import OpenAIEmbeddings  # Sử dụng thư viện mới
from langchain_community.vectorstores import FAISS
from dotenv import load_dotenv

load_dotenv()

def extract_article_metadata(text):
    """Extract title, url, and content from article text files"""
    title_match = re.search(r'TITLE: (.*?)(?:\n|$)', text)
    url_match = re.search(r'URL: (.*?)(?:\n|$)', text)
    content_match = re.search(r'CONTENT:(.*)', text, re.DOTALL)
    
    title = title_match.group(1) if title_match else ""
    url = url_match.group(1) if url_match else ""
    content = content_match.group(1) if content_match else text
    
    return title, url, content.strip()

def process_economic_articles(data_dir, use_openai=False):
    """Process economic news articles with structured format"""
    print(f"Processing economic articles from {data_dir}")
    
    # Find all news sources
    source_dirs = [d for d in os.listdir(data_dir) if os.path.isdir(os.path.join(data_dir, d))]
    documents = []
    
    for source in source_dirs:
        source_path = os.path.join(data_dir, source)
        print(f"Processing source: {source}")
        
        # Get all txt files
        txt_files = glob.glob(os.path.join(source_path, "*.txt"))
        
        for file_path in txt_files:
            try:
                with open(file_path, 'r', encoding='utf-8') as file:
                    text = file.read()
                
                # Extract article parts
                title, url, content = extract_article_metadata(text)
                
                # Add to documents with metadata
                from langchain.schema import Document
                doc = Document(
                    page_content=content,
                    metadata={
                        "source": source,
                        "title": title,
                        "url": url,
                        "file_path": file_path
                    }
                )
                documents.append(doc)
                
            except Exception as e:
                print(f"Error processing {file_path}: {str(e)}")
    
    # Split documents into chunks
    text_splitter = RecursiveCharacterTextSplitter(
        chunk_size=1000,
        chunk_overlap=200,
        length_function=len,
    )
    
    chunks = text_splitter.split_documents(documents)
    print(f"Split into {len(chunks)} chunks")
    
    # Create embeddings
    if use_openai:
        embedding_model = OpenAIEmbeddings()  # Sử dụng thư viện mới
        print("Using OpenAI embeddings")
    else:
        embedding_model = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
        print("Using HuggingFace embeddings")
    
    # Create vector store
    db = FAISS.from_documents(chunks, embedding_model)
    
    # Save vector store
    output_dir = os.path.join("vector_db", "economic_articles")
    os.makedirs(output_dir, exist_ok=True)
    db.save_local(output_dir)
    
    print(f"Saved vector database to {output_dir}")
    return db

def process_documents(docs_dir, use_openai=True):
    """Process regular documents like PDFs, Word files, etc."""
    print(f"Processing documents from {docs_dir}")
    
    documents = []
    
    # Process text files
    txt_files = glob.glob(os.path.join(docs_dir, "**/*.txt"), recursive=True)
    for file_path in txt_files:
        try:
            loader = TextLoader(file_path)
            documents.extend(loader.load())
        except Exception as e:
            print(f"Error loading text file {file_path}: {str(e)}")
    
    # Process PDF files
    pdf_files = glob.glob(os.path.join(docs_dir, "**/*.pdf"), recursive=True)
    for file_path in pdf_files:
        try:
            loader = PyPDFLoader(file_path)
            documents.extend(loader.load())
        except Exception as e:
            print(f"Error loading PDF file {file_path}: {str(e)}")
    
    # Process Word files
    doc_files = glob.glob(os.path.join(docs_dir, "**/*.doc*"), recursive=True)
    for file_path in doc_files:
        try:
            loader = Docx2txtLoader(file_path)
            documents.extend(loader.load())
        except Exception as e:
            print(f"Error loading Word file {file_path}: {str(e)}")
    
    print(f"Loaded {len(documents)} documents")
    
    # Split documents into chunks
    text_splitter = RecursiveCharacterTextSplitter(
        chunk_size=1000,
        chunk_overlap=200,
        length_function=len,
    )
    
    chunks = text_splitter.split_documents(documents)
    print(f"Split into {len(chunks)} chunks")
    
    # Create embeddings - sử dụng thư viện mới cho OpenAI
    if use_openai:
        embedding_model = OpenAIEmbeddings()
        print("Using OpenAI embeddings")
    else:
        embedding_model = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
        print("Using HuggingFace embeddings")
    
    # Create vector store
    db = FAISS.from_documents(chunks, embedding_model)
    
    # Save vector store
    output_dir = os.path.join("vector_db", "documents")
    os.makedirs(output_dir, exist_ok=True)
    db.save_local(output_dir)
    
    print(f"Saved vector database to {output_dir}")
    return db

def main():
    parser = argparse.ArgumentParser(description="Create vector database from documents")
    parser.add_argument("--mode", choices=["economic", "documents"], required=True, 
                      help="Mode: economic for news articles, documents for general docs")
    parser.add_argument("--input_dir", required=True, help="Input directory containing files")
    parser.add_argument("--use_openai", action="store_true", help="Use OpenAI embeddings (otherwise use HuggingFace)")
    
    args = parser.parse_args()
    
    if args.mode == "economic":
        process_economic_articles(args.input_dir, args.use_openai)
    else:
        process_documents(args.input_dir, args.use_openai)

if __name__ == "__main__":
    main()
