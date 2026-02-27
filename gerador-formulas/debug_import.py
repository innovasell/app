import os
from docling.document_converter import DocumentConverter

# Pick the first PDF found
PDF_DIR = r"gerador-formulas/import_pdfs"
files = [f for f in os.listdir(PDF_DIR) if f.lower().endswith('.pdf')]
if not files:
    print("No PDFs found.")
    exit()

target_pdf = files[0] # Try the first one
pdf_path = os.path.join(PDF_DIR, target_pdf)

print(f"Debugging: {pdf_path}")

converter = DocumentConverter()
result = converter.convert(pdf_path)

# Print stats
print("--- Markdown Output ---")
print(result.document.export_to_markdown())
print("--- End Markdown ---")

# Check tables
print(f"Tables found: {len(result.document.tables)}")
for i, table in enumerate(result.document.tables):
    print(f"Table {i}:")
    print(table.export_to_dataframe())
