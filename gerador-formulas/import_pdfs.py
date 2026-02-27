import os
import json
import re
import pandas as pd
from sqlalchemy import create_engine, text
from docling.document_converter import DocumentConverter

import urllib.parse

import urllib.parse

# Configuration
PDF_DIR = r"gerador-formulas/import_pdfs"
DB_USER = "root"
DB_PASS = ""
DB_HOST = "127.0.0.1"
DB_PORT = "3306"
DB_NAME = "u849249951_innovasell"

# URL encode password to handle special chars like '@'
encoded_pass = urllib.parse.quote_plus(DB_PASS)
connection_string = f"mysql+pymysql://{DB_USER}:{encoded_pass}@{DB_HOST}:{DB_PORT}/{DB_NAME}"
engine = create_engine(connection_string)

# ... (imports)

# Global converter to reuse models
print("Initializing Docling Converter...")
converter = DocumentConverter()

def extract_formula_data(pdf_path):
    print(f"Processing: {pdf_path}")
    # Use global converter
    result = converter.convert(pdf_path)
    
    # ... (rest of function)

    # Try to find a heading
    formula_name = os.path.basename(pdf_path).replace('.pdf', '') # Default
    
    # 2. Ingredients Table
    ingredients = []
    
    for table in result.document.tables:
        try:
            # Fix deprecated usage by passing doc reference
            df = table.export_to_dataframe(doc=result.document)
            
            # Normalize Headers:
            # Check if headers are in columns or first row
            # Strategy: Search for "MATÉRIA" or "INCI" in columns OR first few rows
            
            # Helper to check a row/list for keywords
            def identify_columns(row_list):
                row_str = [str(x).upper() for x in row_list]
                idx_mp = -1
                idx_inci = -1
                idx_pct = -1
                idx_qsp = -1 # Explicit QSP column?
                
                for i, val in enumerate(row_str):
                    if "MATÉRIA" in val or "TRADE NAME" in val or "INGREDIENTE" in val:
                        idx_mp = i
                    elif "INCI" in val:
                        idx_inci = i
                    elif "%" in val or "PERCENTUAL" in val:
                        idx_pct = i
                    elif "QSP" in val:
                        idx_qsp = i
                return idx_mp, idx_inci, idx_pct, idx_qsp

            # Check DataFrame Columns first
            mp_i, inci_i, pct_i, qsp_i = identify_columns(df.columns)
            
            start_row = 0
            # If not found in columns, check looking for header row
            if mp_i == -1 or inci_i == -1:
                # Look in first 3 rows
                for r_idx in range(min(5, len(df))):
                    row_vals = df.iloc[r_idx].tolist()
                    m, i_idx, p, q = identify_columns(row_vals)
                    if m != -1 and i_idx != -1:
                        mp_i, inci_i, pct_i, qsp_i = m, i_idx, p, q
                        start_row = r_idx + 1 # Data starts after this row
                        break
            
            # Parsing Data
            if mp_i != -1:
                print(f"Found table with columns: MP={mp_i}, INCI={inci_i}, PCT={pct_i}")
                
                for r_idx in range(start_row, len(df)):
                    row = df.iloc[r_idx].tolist()
                    
                    # Safety check using indices
                    if mp_i >= len(row): continue
                    
                    mp_val = str(row[mp_i]).strip()
                    if not mp_val or mp_val.lower() in ["nan", "none", ""]: continue
                    
                    inci_val = str(row[inci_i]).strip() if inci_i != -1 and inci_i < len(row) else ""
                    
                    # Percent parsing
                    raw_pct = "0"
                    if pct_i != -1 and pct_i < len(row):
                        raw_pct = str(row[pct_i]).strip()
                    
                    pct_val = 0.0
                    is_qsp = 0
                    
                    # Check QSP in dedicated column OR in percent column
                    if qsp_i != -1 and qsp_i < len(row):
                         if "qsp" in str(row[qsp_i]).lower():
                             is_qsp = 1
                    
                    if "qsp" in raw_pct.lower():
                        is_qsp = 1
                        pct_val = 0.0
                    else:
                        clean_pct = raw_pct.replace('%', '').replace(',', '.')
                        # Remove non-numeric chars except dot
                        clean_pct = re.sub(r'[^0-9\.]', '', clean_pct)
                        try:
                            pct_val = float(clean_pct)
                        except:
                            pct_val = 0.0

                    ingredients.append({
                        'materia_prima': mp_val,
                        'inci_name': inci_val,
                        'percentual': pct_val,
                        'qsp': is_qsp
                    })
                break # Stop after finding the main formatted table
        except Exception as e:
            print(f"Error parsing table: {e}")
            continue

    # 3. Text Parsing (Markdown)
    md_text = result.document.export_to_markdown()
    
    # Modo de Preparo
    modo_preparo = "Ver descrição no PDF."
    match = re.search(r'(?i)(?:modo\s+de\s+preparo|procedimento)[:\s]*\n?(.*?)(?:\n#|\Z|Ativos em Destaque)', md_text, re.DOTALL)
    if match:
        modo_preparo = match.group(1).strip()

    # Ativos
    ativos = []
    match_ativos = re.search(r'(?i)(?:ativos\s+em\s+destaque|claims)[:\s]*\n?(.*?)(?:\n#|\Z)', md_text, re.DOTALL)
    if match_ativos:
        block = match_ativos.group(1)
        # Parse bullets
        for line in block.split('\n'):
            clean = line.strip('-* \t')
            if clean and len(clean) > 3: # Avoid noise
                ativos.append(clean)
    
    return {
        'nome': formula_name,
        'ingredientes': ingredients,
        'modo_preparo': modo_preparo,
        'ativos': ativos
    }

def save_to_db(data):
    with engine.begin() as conn:
        # 1. Create Formula
        # Generate generic code
        mes_ano = pd.Timestamp.now().strftime("%m%Y")
        # Simple counter logic (real world needs better locking, but fine for batch import)
        res = conn.execute(text("SELECT COUNT(*) FROM formulacoes")).scalar()
        next_id = res + 1
        codigo = f"IMP/{mes_ano}{next_id:03d}"
        
        sql_form = text("""
            INSERT INTO formulacoes (nome_formula, codigo_formula, categoria, data_criacao)
            VALUES (:nome, :codigo, 'GEN', NOW())
        """)
        res_form = conn.execute(sql_form, {'nome': data['nome'], 'codigo': codigo})
        form_id = res_form.lastrowid
        
        # 2. Create Sub-Formula/Part (Default "Parte Única")
        sql_sub = text("""
            INSERT INTO sub_formulacoes (formulacao_id, nome_sub_formula, modo_preparo)
            VALUES (:fid, 'Fase Única (Importada)', :modo)
        """)
        res_sub = conn.execute(sql_sub, {'fid': form_id, 'modo': data['modo_preparo']})
        sub_id = res_sub.lastrowid
        
        # 3. Create Phase (Default "Fase A")
        sql_phase = text("""
            INSERT INTO fases (sub_formulacao_id, nome_fase)
            VALUES (:sid, 'Fase A')
        """)
        res_phase = conn.execute(sql_phase, {'sid': sub_id})
        phase_id = res_phase.lastrowid
        
        # 4. Insert Ingredients
        sql_ing = text("""
            INSERT INTO ingredientes (fase_id, materia_prima, inci_name, percentual, qsp, destaque)
            VALUES (:pid, :mp, :inci, :pct, :qsp, 0)
        """)
        for ing in data['ingredientes']:
            conn.execute(sql_ing, {
                'pid': phase_id,
                'mp': ing['materia_prima'],
                'inci': ing['inci_name'],
                'pct': ing['percentual'],
                'qsp': ing['qsp']
            })
            
        # 5. Insert Active Highlights
        sql_ativo = text("""
            INSERT INTO ativos_destaque (formulacao_id, nome_ativo, descricao)
            VALUES (:fid, :nome, 'Ativo importado do PDF')
        """)
        for ativo in data['ativos']:
            conn.execute(sql_ativo, {'fid': form_id, 'nome': ativo})
            
    print(f"Saved formula: {data['nome']} (ID: {form_id})")

def main():
    if not os.path.exists(PDF_DIR):
        print(f"Directory not found: {PDF_DIR}")
        return

    files = [f for f in os.listdir(PDF_DIR) if f.lower().endswith('.pdf')]
    
    # Filter by specific file if provided argument
    import sys
    if len(sys.argv) > 1:
        target = sys.argv[1]
        files = [f for f in files if target.lower() in f.lower()]
        print(f"Filtered to {len(files)} file(s) matching '{target}'.")

    print(f"Found {len(files)} PDFs to import.")
    
    for f in files:
        path = os.path.join(PDF_DIR, f)
        
        # Check if already imported
        formula_name = os.path.basename(path).replace('.pdf', '')
        with engine.connect() as conn:
            exists = conn.execute(text("SELECT COUNT(*) FROM formulacoes WHERE nome_formula = :name"), {'name': formula_name}).scalar()
            if exists:
                print(f"Skipping {f} (already in DB)")
                continue

        try:
            data = extract_formula_data(path)
            if data['ingredientes']:
                save_to_db(data)
            else:
                print(f"No ingredients found in {f}, skipping.")
        except Exception as e:
            print(f"Failed to process {f}: {e}")

if __name__ == "__main__":
    main()
