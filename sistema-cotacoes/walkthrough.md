# Relatório de Limpeza - Sistema de Cotações

## Objetivo
Realizar uma varredura completa no diretório `/sistema-cotacoes`, documentar a finalidade de cada arquivo e remover arquivos obsoletos (testes, bugs, scripts de migração antigos, etc.) para organizar o projeto.

## Ações Realizadas

### 1. Documentação e Auditoria
Antes de deletar qualquer arquivo, foi criada uma documentação completa (`documentacao_arquivos.md`) listando cada arquivo encontrado e categorizando por status:
- **Manter**: Arquivos vitais para o sistema.
- **Verificar/Deletar**: Arquivos candidatos a exclusão.

### 2. Exclusão de Arquivos ("Lixo")
Removemos arquivos que não fazem parte do sistema de produção, categorizados como:
- **Testes**: `teste.php`, `teste_pdf.php`, `test_curl.php`, etc.
- **Debug**: `debug.php`, `debug_full.txt`, `erroslog.txt`, scripts `probe_*.php`.
- **Amostras JSON/Dados**: `body.json`, `nfs_sample.json`, `faturado_sample.json`, etc.
- **Backups Manuais**: `pesquisar - Copia.php`.

### 3. Exclusão de Scripts de Migração e Setup
Removemos scripts que foram usados para configurações iniciais ou correções pontuais de banco de dados e não são mais necessários:
- **Migrações**: `migrar_cenarios.php`, `migrar_cliente_para_itens.php`, etc.
- **Fixes de DB**: `fix_db_columns.php`, `fix_oc_columns.php`, etc.
- **Updates**: `update_db_*.php`.
- **Setup**: `setup_cenarios.php`, `setup_cenarios_db.sql`.

### 4. Remoção de Utilitários Locais
Scripts identificados como ferramentas de deployment/fix pontual:
- `update_script.ps1`
- `add_script.js`
- `cenario_fix.js` (incorporado/obsoleto)

### 5. Resultado Final
O diretório foi limpo de aproximadamente 150+ arquivos para cerca de 95 arquivos essenciais.
A documentação completa dos arquivos restantes pode ser consultada em `documentacao_arquivos.md`.

## Arquivos Mantidos (Resumo)
Os arquivos principais do sistema foram preservados, incluindo:
- **Frontend**: Páginas PHP (`index.php`, `pesquisar.php`, `bi.php`, etc) e CSS/JS.
- **Backend**: Scripts de processamento (`salvar_*.php`, `atualizar_*.php`, `conexao.php`).
- **Configurações**: `config_email.php`, `LayoutHelper.php`.
- **Bibliotecas**: Pasta `vendor/`.
