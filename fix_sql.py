import re

input_file = r"gerador-formulas/sql_db/u849249951_innovasell.sql"
output_file = r"gerador-formulas/sql_db/u849249951_innovasell_fixed.sql"

print(f"Fixing collation in {input_file}...")

try:
    with open(input_file, 'r', encoding='utf-8') as f_in, open(output_file, 'w', encoding='utf-8') as f_out:
        for line in f_in:
            # Replace ANY utf8mb4 collation with utf8mb4_general_ci
            # Regex: utf8mb4_[a-z0-9_]+_ci -> utf8mb4_general_ci
            new_line = re.sub(r'utf8mb4_[a-z0-9_]+_ci', 'utf8mb4_general_ci', line)
            
            # Replace utf8mb3 (deprecated) with utf8
            new_line = new_line.replace('utf8mb3', 'utf8')
            
            # Replace any remaining uca1400 or starting with uca...
            new_line = re.sub(r'uca[0-9]+_ai_ci', 'general_ci', new_line)
            
            # Catch-all for utf8_..._ci
            new_line = re.sub(r'utf8_[a-z0-9_]+_ci', 'utf8_general_ci', new_line)
            
            # Fix duplicate replacements if any (e.g. utf8_general_ci -> utf8_general_ci)
            # Not needed strictly, but cleaner.
            f_out.write(new_line)
            
    print(f"Fixed SQL saved to {output_file}")
except Exception as e:
    print(f"Error: {e}")
