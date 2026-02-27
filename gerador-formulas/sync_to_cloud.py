import json
import requests
import pymysql.cursors
from datetime import date, datetime

# Configuration
LOCAL_DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'db': 'u849249951_innovasell',
    'charset': 'utf8mb4',
    'cursorclass': pymysql.cursors.DictCursor
}

# REMOTE_API_URL = "https://innovasell.cloud/gerador-formulas/api_import_sync.php"
# Or wherever the user uploads it. Assuming standard path.
REMOTE_API_URL = "https://innovasell.cloud/gerador-formulas/api_import_sync.php"
API_KEY = "InnovasellSync2024!"

def json_serial(obj):
    """JSON serializer for objects not serializable by default json code"""
    if isinstance(obj, (datetime, date)):
        return obj.isoformat()
    return str(obj)

def fetch_full_data():
    connection = pymysql.connect(**LOCAL_DB_CONFIG)
    try:
        formulas_list = []
        with connection.cursor() as cursor:
            # 1. Fetch Formulations
            # Get everything greater than ID 0 (or filter new ones)
            cursor.execute("SELECT * FROM formulacoes WHERE id > 0")
            formulas = cursor.fetchall()
            
            print(f"Found {len(formulas)} formulas locally.")
            
            for f in formulas:
                f_id = f['id']
                
                # 2. Fetch Ativos
                cursor.execute("SELECT * FROM ativos_destaque WHERE formulacao_id = %s", (f_id,))
                f['ativos_destaque'] = cursor.fetchall()
                
                # 3. Fetch Sub-Formulas
                cursor.execute("SELECT * FROM sub_formulacoes WHERE formulacao_id = %s", (f_id,))
                subs = cursor.fetchall()
                
                for sub in subs:
                    s_id = sub['id']
                    
                    # 4. Fetch Phases
                    cursor.execute("SELECT * FROM fases WHERE sub_formulacao_id = %s", (s_id,))
                    fases = cursor.fetchall()
                    
                    for fase in fases:
                        p_id = fase['id']
                        
                        # 5. Fetch Ingredients
                        cursor.execute("SELECT * FROM ingredientes WHERE fase_id = %s", (p_id,))
                        fase['ingredientes'] = cursor.fetchall()
                        
                    sub['fases'] = fases
                
                f['sub_formulacoes'] = subs
                formulas_list.append(f)
                
        return formulas_list
    finally:
        connection.close()

def sync_data(data):
    print(f"Syncing {len(data)} formulas to {REMOTE_API_URL}...")
    
    # Chunking to avoid massive payloads
    CHUNK_SIZE = 1
    total = len(data)
    
    for i in range(0, total, CHUNK_SIZE):
        chunk = data[i:i + CHUNK_SIZE]
        payload = {'formulas': chunk}
        
        try:
            response = requests.post(
                REMOTE_API_URL,
                data=json.dumps(payload, default=json_serial),
                headers={'Content-Type': 'application/json', 'X-API-KEY': API_KEY},
                timeout=30
            )
            
            if response.status_code == 200:
                print(f"Batch {i}-{i+len(chunk)}: Success! {response.text}")
            else:
                print(f"Batch {i}-{i+len(chunk)}: FAILED ({response.status_code}) - {response.text}")
                # Debug payload for 403
                if response.status_code == 403:
                    print(f"DEBUG: Payload causing 403: {json.dumps(payload, default=json_serial)}")
                
        except Exception as e:
            print(f"Error sending batch: {e}")

if __name__ == "__main__":
    print("Starting Cloud Sync...")
    try:
        all_data = fetch_full_data()
        sync_data(all_data)
        print("Sync complete.")
    except Exception as e:
        print(f"Critical Error: {e}")
