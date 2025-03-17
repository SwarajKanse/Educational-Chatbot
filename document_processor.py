from langchain.document_loaders import PyPDFLoader, TextLoader, Docx2txtLoader
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain.embeddings import HuggingFaceEmbeddings
from langchain.vectorstores import Chroma
import os
from typing import List, Dict, Any
import tempfile
from werkzeug.utils import secure_filename

class DocumentProcessor:
    def __init__(self, persist_directory="./chroma_db"):
        self.persist_directory = persist_directory
        # Initialize embeddings model
        self.embeddings = HuggingFaceEmbeddings(
            model_name="sentence-transformers/all-mpnet-base-v2",
            model_kwargs={'device': 'cpu'}
        )
        # Create the persist directory if it doesn't exist
        os.makedirs(persist_directory, exist_ok=True)
        
    def load_document(self, file_path: str) -> List[Any]:
        """Load documents based on file extension"""
        file_extension = os.path.splitext(file_path)[1].lower()
        
        try:
            if file_extension == '.pdf':
                loader = PyPDFLoader(file_path)
            elif file_extension == '.docx':
                loader = Docx2txtLoader(file_path)
            elif file_extension == '.txt':
                loader = TextLoader(file_path)
            else:
                raise ValueError(f"Unsupported file extension: {file_extension}")
                
            return loader.load()
        except Exception as e:
            print(f"Error loading document: {str(e)}")
            raise

    def process_document(self, file_path: str, session_id: str) -> str:
        """Process a document and store embeddings in ChromaDB"""
        try:
            # Load the document
            documents = self.load_document(file_path)
            
            # Split the documents into chunks
            text_splitter = RecursiveCharacterTextSplitter(
                chunk_size=1000,
                chunk_overlap=200
            )
            splits = text_splitter.split_documents(documents)
            
            # Create collection name using session_id for user-specific vector storage
            collection_name = f"user_{session_id}"
            
            # Store embeddings in ChromaDB
            vectorstore = Chroma.from_documents(
                documents=splits,
                embedding=self.embeddings,
                persist_directory=self.persist_directory,
                collection_name=collection_name
            )
            
            # Persist the vectorstore
            vectorstore.persist()
            
            return f"Document processed and stored in collection: {collection_name}"
            
        except Exception as e:
            print(f"Error processing document: {str(e)}")
            raise

    def save_uploaded_file(self, file, upload_dir="./uploads") -> str:
        """Save an uploaded file to disk and return the path"""
        # Create upload directory if it doesn't exist
        os.makedirs(upload_dir, exist_ok=True)
        
        # Secure the filename to prevent directory traversal
        filename = secure_filename(file.filename)
        file_path = os.path.join(upload_dir, filename)
        
        # Save the file
        file.save(file_path)
        
        return file_path

    def query_documents(self, query: str, session_id: str, k: int = 3) -> List[Dict[str, Any]]:
        """Query the document store for relevant contexts"""
        try:
            # Create collection name using session_id
            collection_name = f"user_{session_id}"
            
            # Load the persisted vectorstore
            vectorstore = Chroma(
                persist_directory=self.persist_directory,
                embedding_function=self.embeddings,
                collection_name=collection_name
            )
            
            # Query for similar documents
            results = vectorstore.similarity_search_with_score(query, k=k)
            
            # Format the results
            contexts = []
            for doc, score in results:
                contexts.append({
                    "content": doc.page_content,
                    "metadata": doc.metadata,
                    "relevance_score": float(score)
                })
                
            return contexts
            
        except Exception as e:
            print(f"Error querying documents: {str(e)}")
            return []